<?php
/**
 * Admin Settings Dashboard for Vantigo Share Shop Cart.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Settings {

    /**
     * Allowed placement hooks and their defaults, shared by saving and rendering.
     */
    public static function placement_options() {
        return array(
            'vantshsh_button_position' => array(
                'woocommerce_before_cart_table', 'woocommerce_before_cart_contents',
                'woocommerce_after_cart_table', 'woocommerce_after_cart',
                'woocommerce_before_cart_totals', 'woocommerce_after_cart_totals',
                'woocommerce_proceed_to_checkout', 'woocommerce_cart_totals_after_order_total',
                'woocommerce_cart_totals_before_order_total', 'woocommerce_cart_coupon',
                'woocommerce_cart_actions', 'hide',
            ),
            'vantshsh_shop_position' => array( 'woocommerce_before_shop_loop', 'woocommerce_after_shop_loop' ),
            'vantshsh_product_position' => array( 'woocommerce_after_add_to_cart_button', 'woocommerce_before_add_to_cart_button' ),
        );
    }

    public static function validate_placement( $key, $value ) {
        $options = self::placement_options();
        if ( ! isset( $options[ $key ] ) ) {
            return '';
        }
        return in_array( $value, $options[ $key ], true ) ? $value : $options[ $key ][0];
    }

    public static function get_placement( $key ) {
        return self::validate_placement( $key, get_option( $key ) );
    }

    /**
     * Initialize settings hooks.
     */
    public static function init() {
        if ( ! is_admin() ) {
            return;
        }

        // Add menu page under WooCommerce
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

        // Add WooCommerce settings tab
        add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_wc_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_vantshsh_cart_url', array( __CLASS__, 'render_wc_settings_tab' ) );

        // Admin assets
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

        // Handle settings save & reset actions
        add_action( 'admin_init', array( __CLASS__, 'handle_form_actions' ) );
    }

    /**
     * Add admin menu item.
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Vantigo Share Shop Cart', 'vantigo-share-shop-cart-for-woocommerce' ),
            __( 'Vantigo Share Shop Cart', 'vantigo-share-shop-cart-for-woocommerce' ),
            'manage_woocommerce',
            'vantigo-share-shop-cart-for-woocommerce',
            array( __CLASS__, 'render_dashboard_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook
     */
    public static function enqueue_admin_assets( $hook ) {
        // Enqueue only on plugin settings pages
        $is_vantshsh_page = ( 'woocommerce_page_vantigo-share-shop-cart-for-woocommerce' === $hook );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_wc_tab    = ( 'woocommerce_page_wc-settings' === $hook && isset( $_GET['tab'] ) && 'vantshsh_cart_url' === sanitize_key( wp_unslash( $_GET['tab'] ) ) );

        if ( ! $is_vantshsh_page && ! $is_wc_tab ) {
            return;
        }

        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style(
            'vantshsh-admin-style',
            VANTSHSH_PLUGIN_URL . 'assets/css/vantshsh-admin' . $suffix . '.css',
            array( 'wp-color-picker' ),
            VANTSHSH_VERSION
        );

        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script(
            'vantshsh-admin-script',
            VANTSHSH_PLUGIN_URL . 'assets/js/vantshsh-admin' . $suffix . '.js',
            array( 'jquery', 'wp-color-picker' ),
            VANTSHSH_VERSION,
            true
        );
    }

    /**
     * Add settings tab to WooCommerce.
     *
     * @param array $tabs
     * @return array
     */
    public static function add_wc_settings_tab( $tabs ) {
        $tabs['vantshsh_cart_url'] = __( 'Vantigo Share Shop Cart', 'vantigo-share-shop-cart-for-woocommerce' );
        return $tabs;
    }

    /**
     * WooCommerce tab view.
     */
    public static function render_wc_settings_tab() {
        ?>
        <div class="vantshsh-wc-tab-banner">
            <p>
                <strong><?php esc_html_e( 'Notice:', 'vantigo-share-shop-cart-for-woocommerce' ); ?></strong>
                <?php esc_html_e( 'For advanced settings, live preview, analytics, and Elementor controls, please visit our dedicated dashboard:', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vantigo-share-shop-cart-for-woocommerce' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e( 'Open Vantigo Share Shop Cart Dashboard', 'vantigo-share-shop-cart-for-woocommerce' ); ?> &rarr;
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle custom form submissions.
     */
    public static function handle_form_actions() {
        if ( ! isset( $_POST['vantshsh_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Save settings action
        if ( 'save_settings' === $_POST['vantshsh_action'] && check_admin_referer( 'vantshsh_settings_nonce_action', 'vantshsh_settings_nonce' ) ) {
            self::save_settings();
            wp_safe_redirect( add_query_arg( array( 'page' => 'vantigo-share-shop-cart-for-woocommerce', 'tab' => isset( $_POST['active_tab'] ) ? sanitize_key( wp_unslash( $_POST['active_tab'] ) ) : 'general', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Reset analytics action
        if ( 'reset_analytics' === $_POST['vantshsh_action'] && check_admin_referer( 'vantshsh_reset_analytics_action', 'vantshsh_reset_nonce' ) ) {
            VANTSHSH_Analytics::reset_stats();
            wp_safe_redirect( add_query_arg( array( 'page' => 'vantigo-share-shop-cart-for-woocommerce', 'tab' => 'analytics', 'reset' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Save all plugin options.
     */
    public static function save_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        check_admin_referer( 'vantshsh_settings_nonce_action', 'vantshsh_settings_nonce' );
        // Explicit list of all checkbox fields
        $checkbox_keys = array(
            'vantshsh_enable',
            'vantshsh_button_icon',
            'vantshsh_show_cart_summary',
            'vantshsh_enable_qr_code',
            'vantshsh_enable_floating_button',
            'vantshsh_enable_shop_page',
            'vantshsh_enable_single_product',
            'vantshsh_enable_whatsapp',
            'vantshsh_enable_facebook',
            'vantshsh_enable_twitter',
            'vantshsh_enable_telegram',
            'vantshsh_enable_email',
            'vantshsh_enable_sms',
            'vantshsh_native_share_enabled',
            'vantshsh_restore_coupons',
        );

        foreach ( $checkbox_keys as $key ) {
            $val = ( isset( $_POST[ $key ] ) && 'yes' === $_POST[ $key ] ) ? 'yes' : 'no';
            update_option( $key, $val );
        }

        // Explicit list of select, text and number fields
        $text_fields = array(
            'vantshsh_button_position'        => 'woocommerce_before_cart_table',
            'vantshsh_button_text'            => 'Share Cart',
            'vantshsh_layout_style'           => 'modal',
            'vantshsh_shop_position'          => 'woocommerce_before_shop_loop',
            'vantshsh_product_position'       => 'woocommerce_after_add_to_cart_button',
            'vantshsh_floating_position'      => 'bottom-right',
            'vantshsh_restore_mode'           => 'replace',
            'vantshsh_redirect_after_restore' => 'cart',
            'vantshsh_cart_expiry_days'       => '30',
            'vantshsh_btn_bg_color'           => '#2271b1',
            'vantshsh_btn_text_color'         => '#ffffff',
            'vantshsh_btn_border_radius'      => '6',
        );

        foreach ( $text_fields as $field_key => $default_val ) {
            if ( ! isset( $_POST[ $field_key ] ) || ! is_string( $_POST[ $field_key ] ) ) {
                continue;
            }
            $value = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
            $placements = self::placement_options();
            $choices = array(
                'vantshsh_layout_style' => array( 'modal', 'inline' ),
                'vantshsh_floating_position' => array( 'bottom-right', 'bottom-left' ),
                'vantshsh_restore_mode' => array( 'replace', 'append' ),
                'vantshsh_redirect_after_restore' => array( 'cart', 'checkout' ),
                'vantshsh_cart_expiry_days' => array( '0', '1', '7', '14', '30', '90' ),
            );
            if ( isset( $placements[ $field_key ] ) ) {
                $value = self::validate_placement( $field_key, $value );
            } elseif ( isset( $choices[ $field_key ] ) ) {
                $value = in_array( $value, $choices[ $field_key ], true ) ? $value : $default_val;
            } elseif ( in_array( $field_key, array( 'vantshsh_btn_bg_color', 'vantshsh_btn_text_color' ), true ) ) {
                $value = sanitize_hex_color( $value );
                $value = $value ? $value : $default_val;
            } elseif ( 'vantshsh_btn_border_radius' === $field_key ) {
                $value = (string) min( 50, absint( $value ) );
            }
            update_option( $field_key, $value );
        }

        // Textarea fields
        if ( isset( $_POST['vantshsh_whatsapp_message'] ) && is_string( $_POST['vantshsh_whatsapp_message'] ) ) {
            update_option( 'vantshsh_whatsapp_message', sanitize_textarea_field( wp_unslash( $_POST['vantshsh_whatsapp_message'] ) ) );
        }


    }

    /**
     * Render main dashboard page.
     */
    public static function render_dashboard_page() {
        ?>
        <div class="wrap vantshsh-admin-wrap">
            <?php self::render_dashboard_content(); ?>
        </div>
        <?php
    }

    /**
     * Render the tabbed dashboard content.
     */
    public static function render_dashboard_content() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        $stats      = VANTSHSH_Analytics::get_stats_summary();

        // Notices
        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'vantigo-share-shop-cart-for-woocommerce' ) . '</p></div>';
        }
        if ( isset( $_GET['reset'] ) ) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Analytics data has been reset.', 'vantigo-share-shop-cart-for-woocommerce' ) . '</p></div>';
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>

        <div class="vantshsh-dashboard-header">
            <div class="vantshsh-header-left">
                <div class="vantshsh-logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                </div>
                <div>
                    <h1 class="vantshsh-dashboard-title"><?php esc_html_e( 'Vantigo Share Shop Cart for WooCommerce', 'vantigo-share-shop-cart-for-woocommerce' ); ?> <span class="vantshsh-version-badge">v<?php echo esc_html( VANTSHSH_VERSION ); ?></span></h1>
                    <p class="vantshsh-dashboard-subtitle"><?php esc_html_e( 'Supercharge cart sharing with QR codes, social channels, Elementor support & universal theme compatibility.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                </div>
            </div>
            <div class="vantshsh-header-right">
                <span class="vantshsh-badge-hpos">&#10003; <?php esc_html_e( 'HPOS Compatible', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                <span class="vantshsh-badge-elementor">&#10003; <?php esc_html_e( 'Elementor Ready', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                <button type="submit" form="vantshsh-settings-form" class="button button-primary vantshsh-header-save-btn">
                    <span class="dashicons dashicons-saved"></span>
                    <span class="vantshsh-btn-label"><?php esc_html_e( 'Save Settings', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="vantshsh-stats-grid">
            <div class="vantshsh-stat-card">
                <div class="vantshsh-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_shared'] ) ); ?></div>
                <div class="vantshsh-stat-label"><?php esc_html_e( 'Carts Shared', 'vantigo-share-shop-cart-for-woocommerce' ); ?></div>
            </div>
            <div class="vantshsh-stat-card">
                <div class="vantshsh-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_views'] ) ); ?></div>
                <div class="vantshsh-stat-label"><?php esc_html_e( 'Link Clicks / Visits', 'vantigo-share-shop-cart-for-woocommerce' ); ?></div>
            </div>
            <div class="vantshsh-stat-card">
                <div class="vantshsh-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_restored'] ) ); ?></div>
                <div class="vantshsh-stat-label"><?php esc_html_e( 'Carts Restored', 'vantigo-share-shop-cart-for-woocommerce' ); ?></div>
            </div>
            <div class="vantshsh-stat-card">
                <div class="vantshsh-stat-number"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</div>
                <div class="vantshsh-stat-label"><?php esc_html_e( 'Restore Rate', 'vantigo-share-shop-cart-for-woocommerce' ); ?></div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="vantshsh-nav-tabs">
            <a href="#tab-general" class="vantshsh-nav-tab <?php echo ( 'general' === $active_tab ) ? 'active' : ''; ?>" data-tab="general">
                <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'General', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-placement" class="vantshsh-nav-tab <?php echo ( 'placement' === $active_tab ) ? 'active' : ''; ?>" data-tab="placement">
                <span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Placement & Themes', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-social" class="vantshsh-nav-tab <?php echo ( 'social' === $active_tab ) ? 'active' : ''; ?>" data-tab="social">
                <span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Social & Channels', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-behavior" class="vantshsh-nav-tab <?php echo ( 'behavior' === $active_tab ) ? 'active' : ''; ?>" data-tab="behavior">
                <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Cart Behavior & Expiry', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-styling" class="vantshsh-nav-tab <?php echo ( 'styling' === $active_tab ) ? 'active' : ''; ?>" data-tab="styling">
                <span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Styling & Colors', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-analytics" class="vantshsh-nav-tab <?php echo ( 'analytics' === $active_tab ) ? 'active' : ''; ?>" data-tab="analytics">
                <span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Analytics & Log', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
            <a href="#tab-shortcodes" class="vantshsh-nav-tab <?php echo ( 'shortcodes' === $active_tab ) ? 'active' : ''; ?>" data-tab="shortcodes">
                <span class="dashicons dashicons-shortcode"></span> <?php esc_html_e( 'Elementor & Shortcode', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
            </a>
        </div>

        <form method="post" action="" id="vantshsh-settings-form">
            <?php wp_nonce_field( 'vantshsh_settings_nonce_action', 'vantshsh_settings_nonce' ); ?>
            <input type="hidden" name="vantshsh_action" value="save_settings" />
            <input type="hidden" name="active_tab" id="vantshsh_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

            <div class="vantshsh-tab-panels">

                <!-- 1. GENERAL TAB -->
                <div id="tab-general" class="vantshsh-tab-panel <?php echo ( 'general' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'General Settings', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Enable Plugin', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Master switch to enable or disable cart sharing across your store.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Button Text', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <input type="text" name="vantshsh_button_text" class="regular-text" value="<?php echo esc_attr( get_option( 'vantshsh_button_text', __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ) ) ); ?>" placeholder="<?php esc_attr_e( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" />
                                    <p class="description"><?php esc_html_e( 'Text label displayed on the share button.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Show Share Icon', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_button_icon" value="yes" <?php checked( 'yes', get_option( 'vantshsh_button_icon', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Display modern SVG share icon inside the button.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Display Layout', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <select name="vantshsh_layout_style" class="regular-text">
                                        <option value="modal" <?php selected( 'modal', get_option( 'vantshsh_layout_style', 'modal' ) ); ?>><?php esc_html_e( 'Interactive Popup Modal (Recommended)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="inline" <?php selected( 'inline', get_option( 'vantshsh_layout_style', 'modal' ) ); ?>><?php esc_html_e( 'Inline Expandable Bar', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Modal gives shoppers a rich popup with QR code, social channels, and mini cart preview.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Show Mini Cart Summary', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_show_cart_summary" value="yes" <?php checked( 'yes', get_option( 'vantshsh_show_cart_summary', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Displays item count and subtotal preview inside the share popup.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Enable QR Code Generator', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_qr_code" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_qr_code', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Allows mobile shoppers to scan a live QR code directly with their phone camera to load the cart.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 2. PLACEMENT & THEMES TAB -->
                <div id="tab-placement" class="vantshsh-tab-panel <?php echo ( 'placement' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Cart Page Hook Position', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Cart Page Position', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <?php $pos = get_option( 'vantshsh_button_position', 'woocommerce_before_cart_table' ); ?>
                                    <select name="vantshsh_button_position" class="regular-text">
                                        <option value="woocommerce_before_cart_table" <?php selected( 'woocommerce_before_cart_table', $pos ); ?>><?php esc_html_e( 'Before Cart Table (Classic / Default)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_before_cart_contents" <?php selected( 'woocommerce_before_cart_contents', $pos ); ?>><?php esc_html_e( 'Before Cart Contents', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_after_cart_table" <?php selected( 'woocommerce_after_cart_table', $pos ); ?>><?php esc_html_e( 'After Cart Table', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_after_cart" <?php selected( 'woocommerce_after_cart', $pos ); ?>><?php esc_html_e( 'After Cart Container', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_before_cart_totals" <?php selected( 'woocommerce_before_cart_totals', $pos ); ?>><?php esc_html_e( 'Before Cart Totals', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_after_cart_totals" <?php selected( 'woocommerce_after_cart_totals', $pos ); ?>><?php esc_html_e( 'After Cart Totals', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_proceed_to_checkout" <?php selected( 'woocommerce_proceed_to_checkout', $pos ); ?>><?php esc_html_e( 'Beside Proceed to Checkout Button', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_cart_totals_after_order_total" <?php selected( 'woocommerce_cart_totals_after_order_total', $pos ); ?>><?php esc_html_e( 'Cart Totals: After Order Total', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_cart_totals_before_order_total" <?php selected( 'woocommerce_cart_totals_before_order_total', $pos ); ?>><?php esc_html_e( 'Cart Totals: Before Order Total', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_cart_coupon" <?php selected( 'woocommerce_cart_coupon', $pos ); ?>><?php esc_html_e( 'Inside Coupon Area', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="woocommerce_cart_actions" <?php selected( 'woocommerce_cart_actions', $pos ); ?>><?php esc_html_e( 'Cart Actions (Next to Update Cart)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="hide" <?php selected( 'hide', $pos ); ?>><?php esc_html_e( 'Hide Automatic Cart Button (Use Shortcode / Elementor only)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Also automatically compatible with WooCommerce Block Cart (above or below cart).', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr />

                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Universal Paid Theme & Additional Pages Support', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php esc_html_e( 'Paid themes (like Flatsome, WoodMart, Astra Pro, Porto, OceanWP, Divi) often use custom templates or AJAX mini carts. These placement options ensure the button is always accessible.', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
                        </p>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Floating Action Button (Universal)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_floating_button" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_floating_button', 'no' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Displays a sleek sticky floating "Share Cart" badge whenever a customer has items in their cart. Works 100% on ANY paid theme and any page!', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Floating Button Position', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <select name="vantshsh_floating_position" class="regular-text">
                                        <option value="bottom-right" <?php selected( 'bottom-right', get_option( 'vantshsh_floating_position', 'bottom-right' ) ); ?>><?php esc_html_e( 'Bottom Right (Standard)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="bottom-left" <?php selected( 'bottom-left', get_option( 'vantshsh_floating_position', 'bottom-right' ) ); ?>><?php esc_html_e( 'Bottom Left', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Show on Shop / Catalog Pages', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_shop_page" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_shop_page', 'no' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Allows customers to share their cart directly while browsing the product catalog.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Show on Single Product Page', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_single_product" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_single_product', 'no' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Display share cart button right next to Add to Cart on single product pages.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 3. SOCIAL & CHANNELS TAB -->
                <div id="tab-social" class="vantshsh-tab-panel <?php echo ( 'social' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Social Sharing Channels', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'WhatsApp Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_whatsapp" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_whatsapp', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'WhatsApp Message Template', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <textarea name="vantshsh_whatsapp_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'vantshsh_whatsapp_message', __( 'Check out what I selected in my shopping cart! Click to view: {cart_url}', 'vantigo-share-shop-cart-for-woocommerce' ) ) ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Use {cart_url} where you want the link inserted.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Facebook Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_facebook" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_facebook', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'X / Twitter Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_twitter" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_twitter', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Telegram Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_telegram" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_telegram', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Email Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_email" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_email', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'SMS Sharing', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_enable_sms" value="yes" <?php checked( 'yes', get_option( 'vantshsh_enable_sms', 'no' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Native Share (Mobile)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_native_share_enabled" value="yes" <?php checked( 'yes', get_option( 'vantshsh_native_share_enabled', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Opens iOS / Android native system share sheet for sharing to Instagram, Messages, or any installed app.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 4. CART BEHAVIOR & EXPIRY TAB -->
                <div id="tab-behavior" class="vantshsh-tab-panel <?php echo ( 'behavior' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Cart Restoration Behavior', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Restoration Mode', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <select name="vantshsh_restore_mode" class="regular-text">
                                        <option value="replace" <?php selected( 'replace', get_option( 'vantshsh_restore_mode', 'replace' ) ); ?>><?php esc_html_e( 'Replace Current Cart (Standard)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="append" <?php selected( 'append', get_option( 'vantshsh_restore_mode', 'replace' ) ); ?>><?php esc_html_e( 'Merge / Append into Customer\'s Existing Cart', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Merge mode keeps whatever the friend already had in their cart and adds the shared items on top!', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Redirect After Loading Cart', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <select name="vantshsh_redirect_after_restore" class="regular-text">
                                        <option value="cart" <?php selected( 'cart', get_option( 'vantshsh_redirect_after_restore', 'cart' ) ); ?>><?php esc_html_e( 'Cart Page (Default)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="checkout" <?php selected( 'checkout', get_option( 'vantshsh_redirect_after_restore', 'cart' ) ); ?>><?php esc_html_e( 'Directly to Checkout Page (Faster Conversions)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Redirect to checkout for 1-click buying and express checkout flows.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Restore Applied Coupons', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <label class="vantshsh-switch">
                                        <input type="checkbox" name="vantshsh_restore_coupons" value="yes" <?php checked( 'yes', get_option( 'vantshsh_restore_coupons', 'yes' ) ); ?> />
                                        <span class="vantshsh-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Automatically re-apply coupon codes from the shared cart.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Shared Cart Link Expiration', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <select name="vantshsh_cart_expiry_days" class="regular-text">
                                        <option value="1" <?php selected( '1', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( '1 Day', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="7" <?php selected( '7', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( '7 Days', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="14" <?php selected( '14', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( '14 Days', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="30" <?php selected( '30', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( '30 Days (Recommended)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="90" <?php selected( '90', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( '90 Days', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                        <option value="0" <?php selected( '0', get_option( 'vantshsh_cart_expiry_days', '30' ) ); ?>><?php esc_html_e( 'Never Expire', 'vantigo-share-shop-cart-for-woocommerce' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Old links beyond this period will safely expire and auto-clean up database storage.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 5. STYLING & COLORS TAB -->
                <div id="tab-styling" class="vantshsh-tab-panel <?php echo ( 'styling' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Button & Theme Styling', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <table class="form-table vantshsh-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Button Background Color', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <input type="text" name="vantshsh_btn_bg_color" class="vantshsh-color-picker" value="<?php echo esc_attr( get_option( 'vantshsh_btn_bg_color', '#2271b1' ) ); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Button Text / Icon Color', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <input type="text" name="vantshsh_btn_text_color" class="vantshsh-color-picker" value="<?php echo esc_attr( get_option( 'vantshsh_btn_text_color', '#ffffff' ) ); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Button Border Radius', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                <td>
                                    <input type="number" name="vantshsh_btn_border_radius" min="0" max="50" value="<?php echo esc_attr( get_option( 'vantshsh_btn_border_radius', '6' ) ); ?>" class="small-text" /> px
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 6. ANALYTICS & LOG TAB -->
                <div id="tab-analytics" class="vantshsh-tab-panel <?php echo ( 'analytics' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <div class="vantshsh-card-header-flex">
                            <h2 class="vantshsh-card-title"><?php esc_html_e( 'Shared Cart Activity & History', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        </div>
                        <?php
                        $recent_carts = VANTSHSH_Analytics::get_recent_shared_carts( 15 );
                        if ( empty( $recent_carts ) ) :
                            ?>
                            <p class="vantshsh-empty-state"><?php esc_html_e( 'No shared carts recorded yet. When shoppers start sharing carts, activity will appear here.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                        <?php else : ?>
                            <table class="widefat striped vantshsh-table-activity">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Cart Hash', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Cart Value', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Shared Times', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Link Visits', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Restored', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Created At', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'vantigo-share-shop-cart-for-woocommerce' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_carts as $row ) :
                                        $is_exp = ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) < time() );
                                        ?>
                                        <tr>
                                            <td><code><?php echo esc_html( substr( $row->cart_hash, 0, 12 ) ); ?>...</code></td>
                                            <td><strong><?php echo wp_kses_post( wc_price( $row->cart_total ) ); ?></strong></td>
                                            <td><?php echo esc_html( $row->share_count ); ?></td>
                                            <td><?php echo esc_html( $row->views_count ); ?></td>
                                            <td><?php echo esc_html( $row->restored_count ); ?></td>
                                            <td><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i' ) ); ?></td>
                                            <td>
                                                <?php if ( $is_exp ) : ?>
                                                    <span class="vantshsh-status-badge vantshsh-badge-expired"><?php esc_html_e( 'Expired', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                                                <?php else : ?>
                                                    <span class="vantshsh-status-badge vantshsh-badge-active"><?php esc_html_e( 'Active', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 7. SHORTCODE & ELEMENTOR GUIDE TAB -->
                <div id="tab-shortcodes" class="vantshsh-tab-panel <?php echo ( 'shortcodes' === $active_tab ) ? 'active' : ''; ?>">
                    <div class="vantshsh-card">
                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Elementor Page Builder Integration', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <div class="vantshsh-guide-box">
                            <p><?php esc_html_e( 'You can place the Vantigo Share Shop Cart button anywhere in Elementor by searching for the "Vantigo Share Shop Cart" widget:', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                            <ol>
                                <li><?php esc_html_e( 'Open any page or Elementor Cart Template with Elementor.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></li>
                                <li><?php esc_html_e( 'In the Elementor panel, search for "Vantigo Share Shop Cart".', 'vantigo-share-shop-cart-for-woocommerce' ); ?></li>
                                <li><?php esc_html_e( 'Drag and drop it into your column or header.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></li>
                                <li><?php esc_html_e( 'Customize button colors, typography, hover animations, icon, and alignment in the Style tab.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></li>
                            </ol>
                        </div>

                        <hr />

                        <h2 class="vantshsh-card-title"><?php esc_html_e( 'Shortcode Documentation', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h2>
                        <p><?php esc_html_e( 'Display the button anywhere in your theme, Gutenberg blocks, or widgets using shortcodes:', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>

                        <div class="vantshsh-code-sample">
                            <code>[vantshsh_share_cart]</code>
                            <span class="vantshsh-copy-hint"><?php esc_html_e( 'Standard button opening modal popup', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                        </div>

                        <div class="vantshsh-code-sample">
                            <code>[vantshsh_share_cart layout="inline"]</code>
                            <span class="vantshsh-copy-hint"><?php esc_html_e( 'Inline expandable tray layout', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                        </div>

                        <div class="vantshsh-code-sample">
                            <code>[vantshsh_share_cart text="Share This Basket" show_qr="yes"]</code>
                            <span class="vantshsh-copy-hint"><?php esc_html_e( 'Custom text with QR code enabled', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                        </div>

                        <div class="vantshsh-code-sample">
                            <code>[vantshsh_cart_url]</code>
                            <span class="vantshsh-copy-hint"><?php esc_html_e( 'Legacy shortcode alias for 100% backward compatibility', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                        </div>

                        <h3 style="margin-top:20px;"><?php esc_html_e( 'PHP Theme Template Tag', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h3>
                        <div class="vantshsh-code-sample">
                            <code>&lt;?php if ( function_exists( 'vantshsh_render_share_cart' ) ) { vantshsh_render_share_cart(); } ?&gt;</code>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Save Action Bar -->
            <div class="vantshsh-save-bar">
                <button type="submit" class="button button-primary vantshsh-save-button">
                    <span class="dashicons dashicons-saved"></span>
                    <span class="vantshsh-btn-label"><?php esc_html_e( 'Save All Settings', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                </button>
            </div>
        </form>

        <?php if ( 'analytics' === $active_tab ) : ?>
            <form method="post" action="" class="vantshsh-reset-form" data-confirm="<?php echo esc_attr__( 'Are you sure you want to clear all analytics history?', 'vantigo-share-shop-cart-for-woocommerce' ); ?>">
                <?php wp_nonce_field( 'vantshsh_reset_analytics_action', 'vantshsh_reset_nonce' ); ?>
                <input type="hidden" name="vantshsh_action" value="reset_analytics" />
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Reset Analytics Data', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
                </button>
            </form>
        <?php endif; ?>

        <?php
    }
}
