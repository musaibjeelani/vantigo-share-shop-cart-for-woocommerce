<?php
/**
 * Elementor compatibility integration.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Elementor {

    /**
     * Initialize Elementor hooks.
     */
    public static function init() {
        // Check if Elementor installed and active
        add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
        add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_elementor_category' ) );
    }

    /**
     * Add category if not existing.
     *
     * @param object $elements_manager
     */
    public static function add_elementor_category( $elements_manager ) {
        $elements_manager->add_category(
            'vantshsh-elements',
            array(
                'title' => esc_html__( 'Vantigo Share Shop Cart', 'vantigo-share-shop-cart-for-woocommerce' ),
                'icon'  => 'fa fa-plug',
            )
        );
    }

    /**
     * Register the custom Elementor widget.
     *
     * @param object $widgets_manager
     */
    public static function register_widgets( $widgets_manager ) {
        require_once VANTSHSH_PLUGIN_DIR . 'includes/widgets/class-vantshsh-elementor-widget.php';
        $widgets_manager->register( new VANTSHSH_Elementor_Widget() );
    }
}
