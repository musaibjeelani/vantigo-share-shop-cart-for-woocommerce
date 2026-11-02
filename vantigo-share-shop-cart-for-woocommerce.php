<?php
/**
 * Plugin Name: Vantigo Share Shop Cart for WooCommerce
 * Description: Share WooCommerce carts using links, QR codes, optional social channels, an Elementor widget, flexible placement, and local cart analytics.
 * Version: 1.0.3
 * Author: Musaib Jeelani - Vantigo Systems
 * Author URI: https://vantigosystems.com
 * Text Domain: vantigo-share-shop-cart-for-woocommerce
 * Domain Path: /languages
 * Requires plugins: woocommerce
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'No direct access allowed.' );
}

// Plugin Constants
define( 'VANTSHSH_VERSION', '1.0.3' );
define( 'VANTSHSH_PLUGIN_FILE', __FILE__ );
define( 'VANTSHSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VANTSHSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is installed and active.
 *
 * @return bool
 */
function vantshsh_is_woocommerce_active() {
    if ( class_exists( 'WooCommerce' ) ) {
        return true;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VANTSHSH_PLUGIN_FILE, true );
    }
} );

/**
 * Activation hook callback.
 */
function vantshsh_plugin_activation() {
    if ( ! vantshsh_is_woocommerce_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Vantigo Share Shop Cart requires WooCommerce to be installed and active.', 'vantigo-share-shop-cart-for-woocommerce' ) );
    }

    require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-upgrade.php';
    require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-activator.php';
    if ( ! VANTSHSH_Activator::activate() ) {
        wp_die( esc_html__( 'The shared-cart database could not be initialized. Please check database permissions and try again.', 'vantigo-share-shop-cart-for-woocommerce' ) );
    }
}
register_activation_hook( __FILE__, 'vantshsh_plugin_activation' );

/**
 * Deactivation hook callback.
 */
function vantshsh_plugin_deactivation() {
    wp_clear_scheduled_hook( 'vantshsh_daily_cleanup_carts_cron' );
}
register_deactivation_hook( __FILE__, 'vantshsh_plugin_deactivation' );

/**
 * Main Plugin Class.
 */

class VANTSHSH_Main {

    /**
     * Singleton instance.
     *
     * @var VANTSHSH_Main|null
     */
    private static $instance = null;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Get singleton instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->plugin_basename = plugin_basename( __FILE__ );
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Bootstrap plugin components.
     */
    public function init() {
        if ( ! vantshsh_is_woocommerce_active() ) {
            return;
        }

        // Core Includes
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-upgrade.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-activator.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-cart-manager.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-analytics.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-ajax.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-frontend.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-settings.php';
        require_once VANTSHSH_PLUGIN_DIR . 'includes/class-vantshsh-elementor.php';

        // Initialize modules
        if ( VANTSHSH_VERSION !== get_option( 'vantshsh_db_version' ) && ! VANTSHSH_Activator::activate() ) {
            return;
        }
        add_filter( 'the_content', array( 'VANTSHSH_Upgrade', 'legacy_shortcodes' ), 9 );
        add_filter( 'widget_text_content', array( 'VANTSHSH_Upgrade', 'legacy_shortcodes' ), 9 );
        VANTSHSH_Cart_Manager::init();
        VANTSHSH_Ajax::init();
        VANTSHSH_Frontend::init();
        VANTSHSH_Settings::init();
        VANTSHSH_Elementor::init();

        // Action & Meta Links
        add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
    }

    /**
     * Add plugin action links.
     *
     * @param array $links
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=vantigo-share-shop-cart-for-woocommerce' ) ) . '">' . esc_html__( 'Settings', 'vantigo-share-shop-cart-for-woocommerce' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Add plugin row meta links.
     *
     * @param array  $links
     * @param string $file
     * @return array
     */
    public function add_row_meta( $links, $file ) {
        if ( $file === $this->plugin_basename ) {
            $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=vantigo-share-shop-cart-for-woocommerce&tab=shortcodes' ) ) . '">' . esc_html__( 'Documentation', 'vantigo-share-shop-cart-for-woocommerce' ) . '</a>';
            $links[] = '<a href="https://vantigosystems.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Vantigo Systems', 'vantigo-share-shop-cart-for-woocommerce' ) . '</a>';
        }
        return $links;
    }
}

// Instantiate plugin
VANTSHSH_Main::get_instance();


/**
 * Public template function to render share cart button in PHP templates.
 *
 * @param array $args
 */
function vantshsh_render_share_cart( $args = array() ) {
    if ( class_exists( 'VANTSHSH_Frontend' ) ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo VANTSHSH_Frontend::get_share_cart_html( $args );
    }
}
