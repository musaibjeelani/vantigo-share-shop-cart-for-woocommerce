<?php
/**
 * Cart Manager: Serializes, saves, loads and restores shared carts.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Cart_Manager {

    /**
     * Session keys stored in shared cart.
     *
     * @var array
     */
    private static $session_cart_keys = array(
        'cart',
        'cart_totals',
        'applied_coupons',
        'coupon_discount_totals',
        'coupon_discount_tax_totals',
    );

    /**
     * Initialize cart manager hooks.
     */
    public static function init() {
        // Restore only after the visitor confirms and WooCommerce loads their cart.
        add_action( 'wp_loaded', array( __CLASS__, 'apply_shared_cart_session' ), 30 );
        add_action( 'template_redirect', array( __CLASS__, 'render_restore_confirmation' ) );
        add_filter( 'nonce_user_logged_out', array( __CLASS__, 'guest_nonce_user' ), 10, 2 );
        
        // Cron cleanup
        add_action( 'vantshsh_daily_cleanup_carts_cron', array( __CLASS__, 'clean_expired_carts' ) );
    }

    /**
     * Serialize shareable part of current WooCommerce cart.
     *
     * @return string Serialized cart session or empty string.
     */
    public static function get_session_cart() {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return '';
        }

        $cart_session = array();
        foreach ( self::$session_cart_keys as $key ) {
            $cart_session[ $key ] = WC()->session->get( $key );
        }

        return serialize( $cart_session );
    }

    /**
     * Generate and persist a shareable URL for the current cart.
     *
     * @return string Share URL or empty string.
     */
    public static function generate_share_url( $persist = true ) {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return '';
        }

        $session_cart = self::get_session_cart();
        if ( '' === $session_cart ) {
            return '';
        }

        if ( ! $persist ) {
            return add_query_arg( 'share_cart', wp_hash( $session_cart ), wc_get_cart_url() );
        }

        global $wpdb;
        $hash       = wp_hash( $session_cart );
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';

        $expiry_days = (int) get_option( 'vantshsh_cart_expiry_days', '30' );
        $expires_at  = null;
        if ( $expiry_days > 0 ) {
            $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
        }

        $cart_total = 0.00;
        if ( WC()->cart ) {
            $cart_total = (float) WC()->cart->get_total( 'edit' );
        }

        $user_id = get_current_user_id();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

        if ( $table_exists ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, share_count FROM `" . esc_sql( $table_name ) . "` WHERE cart_hash = %s", $hash ) );

            if ( $existing ) {
                $wpdb->update(
                    $table_name,
                    array(
                        'share_count' => (int) $existing->share_count + 1,
                        'cart_total'  => $cart_total,
                        'expires_at'  => $expires_at,
                    ),
                    array( 'id' => $existing->id ),
                    array( '%d', '%f', '%s' ),
                    array( '%d' )
                );
            } else {
                $wpdb->insert(
                    $table_name,
                    array(
                        'cart_hash'      => $hash,
                        'cart_data'      => $session_cart,
                        'created_at'     => gmdate( 'Y-m-d H:i:s' ),
                        'expires_at'     => $expires_at,
                        'share_count'    => 1,
                        'views_count'    => 0,
                        'restored_count' => 0,
                        'cart_total'     => $cart_total,
                        'user_id'        => $user_id,
                    ),
                    array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%d' )
                );
            }
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( ! $table_exists || ! empty( $wpdb->last_error ) ) {
            return '';
        }

        // Support standard share query argument
        $share_url = add_query_arg( 'share_cart', $hash, wc_get_cart_url() );

        /**
         * Filter generated share URL.
         *
         * @param string $share_url The share URL.
         * @param string $hash      The cart hash.
         */
        return apply_filters( 'vantshsh_share_url', $share_url, $hash );
    }

    /**
     * Restore shared cart when visiting a shared cart URL.
     */
    public static function apply_shared_cart_session() {
        if ( 'yes' !== get_option( 'vantshsh_enable', 'yes' ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
            return;
        }
        if ( ! isset( $_POST['vantshsh_restore_hash'], $_POST['vantshsh_restore_nonce'] ) ||
            ! is_string( $_POST['vantshsh_restore_hash'] ) || ! is_string( $_POST['vantshsh_restore_nonce'] ) ) {
            return;
        }
        $hash_param = sanitize_text_field( wp_unslash( $_POST['vantshsh_restore_hash'] ) );
        if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $hash_param ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vantshsh_restore_nonce'] ) ), 'vantshsh_restore_cart_' . $hash_param ) ) {
            wc_add_notice( __( 'Security check failed. Open the shared link again and confirm.', 'vantigo-share-shop-cart-for-woocommerce' ), 'error' );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';
        $cart_raw   = '';
        $is_expired = false;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

        if ( $table_exists ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `" . esc_sql( $table_name ) . "` WHERE cart_hash = %s", $hash_param ) );

            if ( $row ) {
                if ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) < time() ) {
                    $is_expired = true;
                } else {
                    $cart_raw = $row->cart_data;

                }
            }
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( $is_expired ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'This shared cart link has expired.', 'vantigo-share-shop-cart-for-woocommerce' ), 'notice' );
            }
            return;
        }

        if ( empty( $cart_raw ) ) {
            return;
        }

        // Unserialize safely without arbitrary class instantiation
        $cart = unserialize( $cart_raw, array( 'allowed_classes' => false ) );
        if ( ! is_array( $cart ) || empty( $cart['cart'] ) || ! is_array( $cart['cart'] ) ) {
            return;
        }

        $mode            = get_option( 'vantshsh_restore_mode', 'replace' );
        $restore_coupons = 'yes' === get_option( 'vantshsh_restore_coupons', 'yes' );

        if ( 'append' !== $mode ) {
            WC()->cart->empty_cart();
        }
        // Re-add products through WooCommerce so prices and stock are revalidated.
        foreach ( $cart['cart'] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
            $quantity     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
            $variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
            $variation    = isset( $item['variation'] ) ? (array) $item['variation'] : array();
            $cart_item_data = array();

            // Preserve custom line item data
            foreach ( $item as $item_k => $item_v ) {
                if ( ! in_array( $item_k, array( 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'line_total', 'line_tax', 'line_subtotal', 'line_subtotal_tax' ), true ) ) {
                    $cart_item_data[ $item_k ] = $item_v;
                }
            }

            if ( $product_id > 0 && $quantity > 0 ) {
                WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
            }
        }

        if ( $restore_coupons && ! empty( $cart['applied_coupons'] ) ) {
            foreach ( $cart['applied_coupons'] as $coupon_code ) {
                if ( ! WC()->cart->has_discount( $coupon_code ) ) {
                    WC()->cart->apply_coupon( $coupon_code );
                }
            }
        }

        WC()->cart->calculate_totals();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( "UPDATE `" . esc_sql( $table_name ) . "` SET views_count = views_count + 1, restored_count = restored_count + 1 WHERE id = %d", $row->id ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'Shared cart items loaded successfully! Happy shopping.', 'vantigo-share-shop-cart-for-woocommerce' ), 'success' );
        }

        // Handle redirection if configured
        $redirect_to = get_option( 'vantshsh_redirect_after_restore', 'cart' );
        add_action( 'template_redirect', function() use ( $redirect_to ) {
            wp_safe_redirect( 'checkout' === $redirect_to ? wc_get_checkout_url() : wc_get_cart_url() );
            exit;
        }, 1 );
    }

    /** Bind guest nonces for our actions to the visitor's WooCommerce session. */
    public static function guest_nonce_user( $uid, $action ) {
        if ( 0 !== strpos( $action, 'vantshsh_' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
            return $uid;
        }
        $token = WC()->session->get( 'vantshsh_nonce_user' );
        if ( ! $token ) {
            $token = wp_generate_password( 32, false );
            WC()->session->set( 'vantshsh_nonce_user', $token );
            WC()->session->set_customer_session_cookie( true );
        }
        return $token;
    }

    /** A shared URL opens a confirmation; only its nonce-protected POST alters a cart. */
    public static function render_restore_confirmation() {
        if ( 'yes' !== get_option( 'vantshsh_enable', 'yes' ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
            return;
        }
        // Reading a public link does not change the cart or database.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $value = '';
        if ( isset( $_GET['share_cart'] ) && is_string( $_GET['share_cart'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_GET['share_cart'] ) );
        } elseif ( isset( $_GET['share'] ) && is_string( $_GET['share'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_GET['share'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{32,64}$/', $value ) ) {
            return;
        }
        nocache_headers();
        $message = '<p>' . esc_html__( 'Load this shared cart? Depending on the store settings, its items will replace or merge with your current cart.', 'vantigo-share-shop-cart-for-woocommerce' ) . '</p>';
        $message .= '<form method="post" action="' . esc_url( wc_get_cart_url() ) . '">';
        $message .= '<input type="hidden" name="vantshsh_restore_hash" value="' . esc_attr( $value ) . '" />';
        $message .= wp_nonce_field( 'vantshsh_restore_cart_' . $value, 'vantshsh_restore_nonce', false, false );
        $message .= '<button type="submit">' . esc_html__( 'Load shared cart', 'vantigo-share-shop-cart-for-woocommerce' ) . '</button></form>';
        $message .= '<p><a href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'Keep my current cart', 'vantigo-share-shop-cart-for-woocommerce' ) . '</a></p>';
        wp_die(
            wp_kses(
                $message,
                array(
                    'p'      => array(),
                    'a'      => array( 'href' => true ),
                    'form'   => array( 'method' => true, 'action' => true ),
                    'input'  => array( 'type' => true, 'name' => true, 'value' => true, 'id' => true ),
                    'button' => array( 'type' => true ),
                ),
                array( 'http', 'https' )
            ),
            esc_html__( 'Load shared cart', 'vantigo-share-shop-cart-for-woocommerce' ),
            array( 'response' => 200 )
        );
    }

    /**
     * Clean up expired carts from DB.
     */
    public static function clean_expired_carts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        if ( $table_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DELETE FROM `" . esc_sql( $table_name ) . "` WHERE expires_at IS NOT NULL AND expires_at < UTC_TIMESTAMP()" );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get brief summary of current cart for modal display.
     *
     * @return array
     */
    public static function get_current_cart_summary() {
        $summary = array(
            'count'    => 0,
            'subtotal' => '',
            'total'    => '',
            'items'    => array(),
        );

        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            return $summary;
        }

        $summary['count']    = WC()->cart->get_cart_contents_count();
        $summary['subtotal'] = WC()->cart->get_cart_subtotal();
        $summary['total']    = WC()->cart->get_cart_total();

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            if ( ! $product ) {
                continue;
            }

            $summary['items'][] = array(
                'name'      => $product->get_name(),
                'quantity'  => $cart_item['quantity'],
                'subtotal'  => WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ),
                'thumbnail' => $product->get_image( 'thumbnail', array( 'class' => 'vantshsh-item-thumb' ) ),
            );
        }

        return $summary;
    }
}
