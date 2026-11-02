<?php
/**
 * Fired during plugin activation
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Activator {

    /**
     * Run plugin activation tasks.
     */
    public static function activate() {
        if ( ! self::create_database_table() || ! VANTSHSH_Upgrade::migrate_carts() || ! VANTSHSH_Upgrade::migrate_elementor() ) {
            return false;
        }
        self::set_default_options();
        wp_clear_scheduled_hook( 'sscw_daily_cleanup_carts_cron' );
        self::schedule_cleanup_cron();
        update_option( 'vantshsh_db_version', VANTSHSH_VERSION );
        return true;
    }

    /**
     * Create the shared carts database table for high-performance and persistence.
     */
    public static function create_database_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'vantshsh_shared_carts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_hash VARCHAR(64) NOT NULL,
            cart_data LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            share_count INT UNSIGNED DEFAULT 1,
            views_count INT UNSIGNED DEFAULT 0,
            restored_count INT UNSIGNED DEFAULT 0,
            cart_total DECIMAL(10,2) DEFAULT 0.00,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY cart_hash (cart_hash),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
    }

    /**
     * Set default plugin configuration options if not present.
     */
    public static function set_default_options() {
        $defaults = array(
            'vantshsh_enable'                   => 'yes',
            'vantshsh_button_position'          => 'woocommerce_before_cart_table',
            'vantshsh_button_text'              => __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ),
            'vantshsh_button_icon'              => 'yes',
            'vantshsh_layout_style'             => 'modal', // 'modal', 'inline', 'dropdown'
            'vantshsh_native_share_enabled'     => 'yes',
            'vantshsh_enable_qr_code'           => 'yes',
            'vantshsh_show_cart_summary'        => 'yes',
            
            // Placement in other pages
            'vantshsh_enable_shop_page'         => 'no',
            'vantshsh_shop_position'            => 'woocommerce_before_shop_loop',
            'vantshsh_enable_single_product'    => 'no',
            'vantshsh_product_position'         => 'woocommerce_after_add_to_cart_button',
            'vantshsh_enable_floating_button'   => 'no',
            'vantshsh_floating_position'        => 'bottom-right', // 'bottom-right', 'bottom-left'
            
            // Social channels
            'vantshsh_enable_whatsapp'          => 'yes',
            'vantshsh_enable_facebook'          => 'yes',
            'vantshsh_enable_twitter'           => 'yes',
            'vantshsh_enable_telegram'          => 'yes',
            'vantshsh_enable_email'             => 'yes',
            'vantshsh_enable_sms'               => 'no',
            'vantshsh_whatsapp_message'         => __( 'Check out what I selected in my shopping cart! Click to view: {cart_url}', 'vantigo-share-shop-cart-for-woocommerce' ),
            
            // Cart behavior
            'vantshsh_restore_mode'             => 'replace', // 'replace' or 'append'
            'vantshsh_redirect_after_restore'   => 'cart', // 'cart' or 'checkout'
            'vantshsh_restore_coupons'          => 'yes',
            'vantshsh_cart_expiry_days'         => '30', // '1', '7', '14', '30', '90', '0' (never)
            
            // Styling
            'vantshsh_btn_bg_color'             => '#2271b1',
            'vantshsh_btn_text_color'           => '#ffffff',
            'vantshsh_btn_border_radius'        => '6',
        );

        VANTSHSH_Upgrade::migrate_options( $defaults );
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Schedule cron job to clean up expired shared carts.
     */
    public static function schedule_cleanup_cron() {
        if ( ! wp_next_scheduled( 'vantshsh_daily_cleanup_carts_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'vantshsh_daily_cleanup_carts_cron' );
        }
    }
}
