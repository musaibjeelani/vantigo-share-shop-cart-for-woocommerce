<?php
/**
 * Frontend display handler for Vantigo Share Shop Cart.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Frontend {

    /**
     * Track widget instances.
     *
     * @var int
     */
    private static $instance_count = 0;

    /**
     * Whether widget was rendered on classic cart hook.
     *
     * @var bool
     */
    private static $rendered_on_hook = false;

    /**
     * Positions considered above cart in block rendering.
     *
     * @var array
     */
    private static $positions_above_cart = array(
        'woocommerce_before_cart_table',
        'woocommerce_before_cart_contents',
        'woocommerce_cart_coupon',
    );

    /**
     * Initialize frontend hooks.
     */
    public static function init() {
        if ( 'yes' !== get_option( 'vantshsh_enable', 'yes' ) ) {
            return;
        }

        // Cart page button hook
        $position = VANTSHSH_Settings::get_placement( 'vantshsh_button_position' );
        if ( 'hide' !== $position && ! empty( $position ) ) {
            add_action( $position, array( __CLASS__, 'render_cart_button' ) );
            // Block cart fallback
            add_filter( 'render_block', array( __CLASS__, 'render_in_cart_block' ), 10, 2 );
        }

        // Shop / Archive page hook
        if ( 'yes' === get_option( 'vantshsh_enable_shop_page', 'no' ) ) {
            $shop_pos = VANTSHSH_Settings::get_placement( 'vantshsh_shop_position' );
            add_action( $shop_pos, array( __CLASS__, 'render_shop_button' ) );
        }

        // Single product page hook
        if ( 'yes' === get_option( 'vantshsh_enable_single_product', 'no' ) ) {
            $prod_pos = VANTSHSH_Settings::get_placement( 'vantshsh_product_position' );
            add_action( $prod_pos, array( __CLASS__, 'render_product_button' ) );
        }

        // Floating Action Button (Universal Paid Theme Support)
        if ( 'yes' === get_option( 'vantshsh_enable_floating_button', 'no' ) ) {
            add_action( 'wp_footer', array( __CLASS__, 'render_floating_button' ) );
        }

        // Shortcodes
        add_shortcode( 'vantshsh_share_cart', array( __CLASS__, 'render_shortcode' ) );
        add_shortcode( 'vantshsh_cart_url', array( __CLASS__, 'render_shortcode' ) ); // Legacy alias

        // Enqueue frontend scripts and styles
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue styles and scripts.
     */
    public static function enqueue_assets() {
        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        // Enqueue styles
        wp_enqueue_style(
            'vantshsh-frontend-style',
            VANTSHSH_PLUGIN_URL . 'assets/css/vantshsh-frontend' . $suffix . '.css',
            array(),
            VANTSHSH_VERSION
        );

        self::output_custom_styles();

        // Enqueue QR Code library
        wp_enqueue_script(
            'vantshsh-qrcode',
            VANTSHSH_PLUGIN_URL . 'assets/js/qrcode.min.js',
            array(),
            '1.0.0',
            true
        );

        // Enqueue main frontend script
        wp_enqueue_script(
            'vantshsh-frontend-script',
            VANTSHSH_PLUGIN_URL . 'assets/js/vantshsh-frontend' . $suffix . '.js',
            array( 'jquery', 'vantshsh-qrcode' ),
            VANTSHSH_VERSION,
            true
        );

        // Localized parameters for JS
        $whatsapp_template = get_option( 'vantshsh_whatsapp_message', __( 'Check out what I selected in my shopping cart! Click to view: {cart_url}', 'vantigo-share-shop-cart-for-woocommerce' ) );

        wp_localize_script(
            'vantshsh-frontend-script',
            'vantshsh_data',
            array(
                'ajax_url'          => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'vantshsh_share_cart_nonce' ),
                'site_name'         => get_bloginfo( 'name' ),
                'whatsapp_template' => $whatsapp_template,
                'i18n'              => array(
                    'copied'         => __( 'Link copied to clipboard!', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'copy_failed'    => __( 'Please press and hold the link to copy.', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'refresh_err'    => __( 'Could not refresh link. Please reload page.', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'cart_empty'     => __( 'Your shopping cart is currently empty.', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'loading'        => __( 'Generating share link...', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'download_qr'    => __( 'Download QR Code', 'vantigo-share-shop-cart-for-woocommerce' ),
                ),
                'social'            => array(
                    'whatsapp' => 'yes' === get_option( 'vantshsh_enable_whatsapp', 'yes' ),
                    'facebook' => 'yes' === get_option( 'vantshsh_enable_facebook', 'yes' ),
                    'twitter'  => 'yes' === get_option( 'vantshsh_enable_twitter', 'yes' ),
                    'telegram' => 'yes' === get_option( 'vantshsh_enable_telegram', 'yes' ),
                    'email'    => 'yes' === get_option( 'vantshsh_enable_email', 'yes' ),
                    'sms'      => 'yes' === get_option( 'vantshsh_enable_sms', 'no' ),
                    'qr'       => 'yes' === get_option( 'vantshsh_enable_qr_code', 'yes' ),
                    'native'   => 'yes' === get_option( 'vantshsh_native_share_enabled', 'yes' ),
                ),
            )
        );
    }

    /**
     * Enqueue CSS generated only from validated color and radius settings.
     */
    public static function output_custom_styles() {
        $bg_color      = sanitize_hex_color( get_option( 'vantshsh_btn_bg_color', '#2271b1' ) );
        $text_color    = sanitize_hex_color( get_option( 'vantshsh_btn_text_color', '#ffffff' ) );
        $border_radius = absint( get_option( 'vantshsh_btn_border_radius', '6' ) );
        $css = ':root{--vantshsh-primary:' . ( $bg_color ? $bg_color : '#2271b1' ) . ';';
        $css .= '--vantshsh-text:' . ( $text_color ? $text_color : '#ffffff' ) . ';';
        $css .= '--vantshsh-radius:' . min( 50, $border_radius ) . 'px;}';
        wp_add_inline_style( 'vantshsh-frontend-style', $css );
    }

    /**
     * Render button on Cart page.
     */
    public static function render_cart_button() {
        $html = self::get_share_cart_html();
        if ( '' === $html ) {
            return;
        }

        self::$rendered_on_hook = true;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
    }

    /**
     * Render button in Shop / Archive pages.
     */
    public static function render_shop_button() {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::get_share_cart_html( array( 'context' => 'shop' ) );
    }

    /**
     * Render button on Single Product page.
     */
    public static function render_product_button() {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::get_share_cart_html( array( 'context' => 'product' ) );
    }

    /**
     * Render Floating Action Button (Universal theme fallback).
     */
    public static function render_floating_button() {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return;
        }

        $position = get_option( 'vantshsh_floating_position', 'bottom-right' );
        $button_text = get_option( 'vantshsh_button_text', __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ) );
        $share_url   = VANTSHSH_Cart_Manager::generate_share_url( false );

        if ( empty( $share_url ) ) {
            return;
        }

        $cart_count = WC()->cart->get_cart_contents_count();
        ?>
        <div class="vantshsh-floating-wrapper vantshsh-pos-<?php echo esc_attr( $position ); ?>" data-share-url="<?php echo esc_attr( $share_url ); ?>">
            <button type="button" class="vantshsh-floating-btn vantshsh-trigger-modal" aria-label="<?php esc_attr_e( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="vantshsh-icon-share">
                    <circle cx="18" cy="5" r="3"></circle>
                    <circle cx="6" cy="12" r="3"></circle>
                    <circle cx="18" cy="19" r="3"></circle>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                </svg>
                <span class="vantshsh-floating-label"><?php echo esc_html( $button_text ); ?></span>
                <?php if ( $cart_count > 0 ) : ?>
                    <span class="vantshsh-floating-badge"><?php echo esc_html( $cart_count ); ?></span>
                <?php endif; ?>
            </button>
        </div>
        <?php
        self::render_share_modal( $share_url );
    }

    /**
     * Support WooCommerce Cart block rendering.
     *
     * @param string $block_content
     * @param array  $block
     * @return string
     */
    public static function render_in_cart_block( $block_content, $block ) {
        if ( empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
            return $block_content;
        }

        if ( self::$rendered_on_hook ) {
            return $block_content;
        }

        $html = self::get_share_cart_html();
        if ( '' === $html ) {
            return $block_content;
        }

        self::$rendered_on_hook = true;
        $position = get_option( 'vantshsh_button_position', 'woocommerce_before_cart_table' );

        if ( in_array( $position, self::$positions_above_cart, true ) ) {
            return $html . $block_content;
        }

        return $block_content . $html;
    }

    /**
     * Shortcode callback.
     *
     * @param array $atts
     * @return string
     */
    public static function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'layout'       => get_option( 'vantshsh_layout_style', 'modal' ),
                'text'         => get_option( 'vantshsh_button_text', __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ) ),
                'icon'         => 'yes',
                'show_qr'      => get_option( 'vantshsh_enable_qr_code', 'yes' ),
                'show_social'  => 'yes',
                'class'        => '',
            ),
            $atts,
            'vantshsh_share_cart'
        );

        return self::get_share_cart_html( $atts );
    }

    /**
     * Build the share widget markup.
     *
     * @param array $args Custom display arguments.
     * @return string
     */
    public static function get_share_cart_html( $args = array() ) {
        $share_url = VANTSHSH_Cart_Manager::generate_share_url( false );

        if ( '' === $share_url ) {
            return '';
        }

        self::$instance_count++;
        $is_first = ( 1 === self::$instance_count );

        $defaults = array(
            'layout'       => get_option( 'vantshsh_layout_style', 'modal' ),
            'text'         => get_option( 'vantshsh_button_text', __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ) ),
            'icon'         => get_option( 'vantshsh_button_icon', 'yes' ),
            'show_qr'      => get_option( 'vantshsh_enable_qr_code', 'yes' ),
            'show_social'  => 'yes',
            'context'      => 'cart',
            'class'        => '',
        );

        $params = wp_parse_args( $args, $defaults );

        if ( empty( trim( $params['text'] ) ) ) {
            $params['text'] = __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' );
        }

        $native_share_enabled = 'yes' === get_option( 'vantshsh_native_share_enabled', 'yes' );

        ob_start();
        ?>
        <div class="vantshsh-share-cart-wrapper scurl-share-cart <?php echo esc_attr( $params['class'] ); ?> vantshsh-layout-<?php echo esc_attr( $params['layout'] ); ?>" data-share-url="<?php echo esc_attr( $share_url ); ?>">
            
            <button type="button" class="button vantshsh-share-btn scurl-share-btn vantshsh-trigger-modal" <?php echo $is_first ? 'id="vantshsh-share-cart-btn"' : ''; ?>>
                <?php if ( 'yes' === $params['icon'] ) : ?>
                    <span class="vantshsh-btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </span>
                <?php endif; ?>
                <span class="vantshsh-btn-text"><?php echo esc_html( $params['text'] ); ?></span>
            </button>

            <?php if ( 'inline' === $params['layout'] ) : ?>
                <!-- Inline expandable bar for inline layout -->
                <div class="vantshsh-share-output scurl-share-output" <?php echo $is_first ? 'id="vantshsh-share-cart-url"' : ''; ?> hidden>
                    <div class="vantshsh-share-row scurl-share-row">
                        <input type="text" class="vantshsh-share-input scurl-share-input" value="<?php echo esc_attr( $share_url ); ?>" readonly aria-label="<?php esc_attr_e( 'Shared cart link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" />
                        <span class="vantshsh-share-actions scurl-share-actions">
                            <button type="button" class="vantshsh-icon-btn vantshsh-copy-btn scurl-icon-btn scurl-copy-btn" aria-label="<?php esc_attr_e( 'Copy link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Copy link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                            <?php if ( $native_share_enabled ) : ?>
                                <button type="button" class="vantshsh-icon-btn vantshsh-native-share-btn scurl-icon-btn scurl-native-share-btn" aria-label="<?php esc_attr_e( 'Share link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Share link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" hidden>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="18" cy="5" r="3"></circle>
                                        <circle cx="6" cy="12" r="3"></circle>
                                        <circle cx="18" cy="19" r="3"></circle>
                                        <path d="M8.59 13.51 15.42 17.49"></path>
                                        <path d="M15.41 6.51 8.59 10.49"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="vantshsh-share-feedback scurl-share-feedback" role="status" aria-live="polite"></span>
                </div>
            <?php endif; ?>

        </div>
        <?php
        // Modal is rendered once per page in footer or adjacent
        self::render_share_modal( $share_url );

        return ob_get_clean();
    }

    /**
     * Render the beautiful share modal markup.
     *
     * @param string $share_url
     */
    public static function render_share_modal( $share_url ) {
        static $modal_rendered = false;
        if ( $modal_rendered ) {
            return;
        }
        $modal_rendered = true;

        $native_share_enabled = 'yes' === get_option( 'vantshsh_native_share_enabled', 'yes' );
        $enable_qr            = 'yes' === get_option( 'vantshsh_enable_qr_code', 'yes' );
        $enable_summary       = 'yes' === get_option( 'vantshsh_show_cart_summary', 'yes' );
        $expiry_days          = (int) get_option( 'vantshsh_cart_expiry_days', '30' );

        $cart_summary = VANTSHSH_Cart_Manager::get_current_cart_summary();
        ?>
        <!-- Vantigo Share Shop Cart Interactive Modal -->
        <div id="vantshsh-modal" class="vantshsh-modal-container" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="vantshsh-modal-title">
            <div class="vantshsh-modal-backdrop" tabindex="-1"></div>
            <div class="vantshsh-modal-dialog">
                <div class="vantshsh-modal-header">
                    <div class="vantshsh-modal-heading-wrap">
                        <span class="vantshsh-modal-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </span>
                        <h3 id="vantshsh-modal-title" class="vantshsh-modal-title"><?php esc_html_e( 'Share Your Shopping Cart', 'vantigo-share-shop-cart-for-woocommerce' ); ?></h3>
                    </div>
                    <button type="button" class="vantshsh-modal-close" aria-label="<?php esc_attr_e( 'Close', 'vantigo-share-shop-cart-for-woocommerce' ); ?>">&times;</button>
                </div>

                <div class="vantshsh-modal-body">
                    <?php if ( $enable_summary && ! empty( $cart_summary['items'] ) ) : ?>
                        <!-- Mini Cart Summary -->
                        <div class="vantshsh-cart-overview">
                            <div class="vantshsh-overview-info">
                                <span class="vantshsh-overview-count">
                                    <?php
                                    /* translators: %d: number of items in cart */
                                    echo sprintf( esc_html__( '%d item(s) in cart', 'vantigo-share-shop-cart-for-woocommerce' ), (int) $cart_summary['count'] );
                                    ?>
                                </span>
                                <span class="vantshsh-overview-total"><?php echo wp_kses_post( $cart_summary['subtotal'] ); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Copy Link Section -->
                    <div class="vantshsh-copy-section">
                        <label class="vantshsh-field-label" for="vantshsh-modal-link-input"><?php esc_html_e( 'Cart Link', 'vantigo-share-shop-cart-for-woocommerce' ); ?></label>
                        <div class="vantshsh-input-group">
                            <input type="text" id="vantshsh-modal-link-input" class="vantshsh-copy-input" value="<?php echo esc_attr( $share_url ); ?>" readonly />
                            <button type="button" class="button vantshsh-modal-copy-btn" title="<?php esc_attr_e( 'Copy link', 'vantigo-share-shop-cart-for-woocommerce' ); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="vantshsh-copy-svg">
                                    <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <span class="vantshsh-copy-text"><?php esc_html_e( 'Copy', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                            </button>
                        </div>
                        <span class="vantshsh-modal-feedback" role="status" aria-live="polite"></span>
                    </div>

                    <!-- Social Share Grid -->
                    <div class="vantshsh-social-section">
                        <span class="vantshsh-section-title"><?php esc_html_e( 'Share via Social Channels', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                        <div class="vantshsh-social-buttons">
                            <?php if ( 'yes' === get_option( 'vantshsh_enable_whatsapp', 'yes' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-whatsapp" data-channel="whatsapp" title="WhatsApp">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2M12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.81 13.47 3.81 11.91C3.81 7.37 7.5 3.67 12.05 3.67M9.53 7.34C9.36 7.34 9.09 7.4 8.87 7.65C8.65 7.89 8.02 8.48 8.02 9.7C8.02 10.92 8.91 12.1 9.03 12.26C9.15 12.42 10.74 14.88 13.18 15.93C15.22 16.81 15.63 16.63 16.08 16.59C16.53 16.55 17.52 16 17.73 15.42C17.93 14.83 17.93 14.33 17.87 14.23C17.81 14.12 17.65 14.06 17.41 13.94C17.16 13.82 15.97 13.23 15.75 13.15C15.53 13.07 15.36 13.03 15.2 13.27C15.04 13.51 14.58 14.06 14.44 14.22C14.3 14.38 14.16 14.4 13.92 14.28C13.68 14.16 12.9 13.9 11.97 13.07C11.25 12.43 10.76 11.64 10.62 11.4C10.48 11.16 10.6 11.02 10.72 10.9C10.83 10.79 10.97 10.61 11.09 10.47C11.21 10.33 11.25 10.23 11.33 10.07C11.41 9.91 11.37 9.77 11.31 9.65C11.25 9.53 10.76 8.34 10.56 7.85C10.36 7.37 10.16 7.43 10.01 7.42C9.87 7.42 9.71 7.34 9.53 7.34Z"></path>
                                    </svg>
                                    <span>WhatsApp</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( 'yes' === get_option( 'vantshsh_enable_facebook', 'yes' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-facebook" data-channel="facebook" title="Facebook">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"></path>
                                    </svg>
                                    <span>Facebook</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( 'yes' === get_option( 'vantshsh_enable_twitter', 'yes' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-twitter" data-channel="twitter" title="X / Twitter">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path>
                                    </svg>
                                    <span>X (Twitter)</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( 'yes' === get_option( 'vantshsh_enable_telegram', 'yes' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-telegram" data-channel="telegram" title="Telegram">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.52 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"></path>
                                    </svg>
                                    <span>Telegram</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( 'yes' === get_option( 'vantshsh_enable_email', 'yes' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-email" data-channel="email" title="Email">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <span>Email</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( 'yes' === get_option( 'vantshsh_enable_sms', 'no' ) ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-sms" data-channel="sms" title="SMS">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <span>SMS</span>
                                </button>
                            <?php endif; ?>

                            <?php if ( $native_share_enabled ) : ?>
                                <button type="button" class="vantshsh-social-btn vantshsh-btn-native vantshsh-native-share-btn" data-channel="native" title="<?php esc_attr_e( 'More Sharing Options', 'vantigo-share-shop-cart-for-woocommerce' ); ?>" hidden>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="18" cy="5" r="3"></circle>
                                        <circle cx="6" cy="12" r="3"></circle>
                                        <circle cx="18" cy="19" r="3"></circle>
                                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                    </svg>
                                    <span><?php esc_html_e( 'More Apps', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $enable_qr ) : ?>
                        <!-- QR Code Generator Section -->
                        <div class="vantshsh-qr-section">
                            <button type="button" class="vantshsh-qr-toggle" aria-expanded="false">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="vantshsh-qr-icon">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                <span><?php esc_html_e( 'Scan with Phone (QR Code)', 'vantigo-share-shop-cart-for-woocommerce' ); ?></span>
                                <span class="vantshsh-arrow">&#9662;</span>
                            </button>
                            <div class="vantshsh-qr-container" hidden>
                                <div id="vantshsh-qr-canvas-wrap" class="vantshsh-qr-canvas-wrap"></div>
                                <p class="vantshsh-qr-hint"><?php esc_html_e( 'Scan this QR code with your mobile camera to instantly open and restore this cart.', 'vantigo-share-shop-cart-for-woocommerce' ); ?></p>
                                <button type="button" class="button vantshsh-download-qr-btn">
                                    <?php esc_html_e( 'Download QR Code', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="vantshsh-modal-footer">
                    <?php if ( $expiry_days > 0 ) : ?>
                        <span class="vantshsh-expiry-notice">
                            &#9201;
                            <?php
                            /* translators: %d: number of days until link expires */
                            echo sprintf( esc_html__( 'Cart link is active for %d days.', 'vantigo-share-shop-cart-for-woocommerce' ), (int) $expiry_days );
                            ?>
                        </span>
                    <?php else : ?>
                        <span class="vantshsh-expiry-notice">
                            &#9873; <?php esc_html_e( 'Cart link does not expire.', 'vantigo-share-shop-cart-for-woocommerce' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
