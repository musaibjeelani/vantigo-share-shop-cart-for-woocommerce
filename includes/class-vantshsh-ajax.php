<?php
/**
 * AJAX endpoints for Vantigo Share Shop Cart.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Ajax {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_vantshsh_generate_share_link', array( __CLASS__, 'generate_share_link' ) );
        add_action( 'wp_ajax_nopriv_vantshsh_generate_share_link', array( __CLASS__, 'generate_share_link' ) );
    }

    /**
     * Generate or refresh share link via AJAX.
     */
    public static function generate_share_link() {
        if ( ! check_ajax_referer( 'vantshsh_share_cart_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'vantigo-share-shop-cart-for-woocommerce' ) ), 403 );
        }
        if ( 'yes' !== get_option( 'vantshsh_enable', 'yes' ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => __( 'Cart sharing is unavailable.', 'vantigo-share-shop-cart-for-woocommerce' ) ), 403 );
        }

        $share_url = VANTSHSH_Cart_Manager::generate_share_url();

        if ( '' === $share_url ) {
            wp_send_json_error( array( 'message' => __( 'Your cart is empty or the link could not be generated.', 'vantigo-share-shop-cart-for-woocommerce' ) ) );
        }

        $summary = VANTSHSH_Cart_Manager::get_current_cart_summary();

        wp_send_json_success( array(
            'url'     => esc_url_raw( $share_url ),
            'summary' => $summary,
        ) );
    }
}
