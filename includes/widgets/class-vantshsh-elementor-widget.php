<?php
/**
 * Elementor Widget for Vantigo Share Shop Cart.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class VANTSHSH_Elementor_Widget extends Widget_Base {

    /**
     * Get widget name.
     */
    public function get_name() {
        return 'vantshsh_share_cart_widget';
    }

    /**
     * Get widget title.
     */
    public function get_title() {
        return esc_html__( 'Vantigo Share Shop Cart', 'vantigo-share-shop-cart-for-woocommerce' );
    }

    /**
     * Get widget icon.
     */
    public function get_icon() {
        return 'eicon-cart';
    }

    /**
     * Get widget categories.
     */
    public function get_categories() {
        return array( 'vantshsh-elements', 'woocommerce-elements', 'general' );
    }

    /**
     * Get widget keywords.
     */
    public function get_keywords() {
        return array( 'cart', 'share', 'woocommerce', 'social', 'qr' );
    }

    /**
     * Register widget controls.
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            array(
                'label' => esc_html__( 'Share Button Settings', 'vantigo-share-shop-cart-for-woocommerce' ),
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label'       => esc_html__( 'Button Text', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ),
                'placeholder' => esc_html__( 'Enter button text', 'vantigo-share-shop-cart-for-woocommerce' ),
            )
        );

        $this->add_control(
            'show_icon',
            array(
                'label'        => esc_html__( 'Show Share Icon', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'vantigo-share-shop-cart-for-woocommerce' ),
                'label_off'    => esc_html__( 'No', 'vantigo-share-shop-cart-for-woocommerce' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'layout_style',
            array(
                'label'   => esc_html__( 'Display Layout', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'modal',
                'options' => array(
                    'modal'  => esc_html__( 'Popup Modal', 'vantigo-share-shop-cart-for-woocommerce' ),
                    'inline' => esc_html__( 'Inline Expandable', 'vantigo-share-shop-cart-for-woocommerce' ),
                ),
            )
        );

        $this->add_responsive_control(
            'align',
            array(
                'label'     => esc_html__( 'Alignment', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => array(
                    'left'    => array(
                        'title' => esc_html__( 'Left', 'vantigo-share-shop-cart-for-woocommerce' ),
                        'icon'  => 'eicon-text-align-left',
                    ),
                    'center'  => array(
                        'title' => esc_html__( 'Center', 'vantigo-share-shop-cart-for-woocommerce' ),
                        'icon'  => 'eicon-text-align-center',
                    ),
                    'right'   => array(
                        'title' => esc_html__( 'Right', 'vantigo-share-shop-cart-for-woocommerce' ),
                        'icon'  => 'eicon-text-align-right',
                    ),
                    'justify' => array(
                        'title' => esc_html__( 'Full Width', 'vantigo-share-shop-cart-for-woocommerce' ),
                        'icon'  => 'eicon-text-align-justify',
                    ),
                ),
                'default'   => 'left',
                'selectors' => array(
                    '{{WRAPPER}} .vantshsh-share-cart-wrapper' => 'text-align: {{VALUE}};',
                    '{{WRAPPER}} .vantshsh-share-btn'          => '{{VALUE}}' === 'justify' ? 'width: 100%; display: block;' : 'display: inline-flex;',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style_button',
            array(
                'label' => esc_html__( 'Button Style', 'vantigo-share-shop-cart-for-woocommerce' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name'     => 'typography',
                'selector' => '{{WRAPPER}} .vantshsh-share-btn',
            )
        );

        $this->start_controls_tabs( 'tabs_button_style' );

        // Normal State
        $this->start_controls_tab(
            'tab_button_normal',
            array(
                'label' => esc_html__( 'Normal', 'vantigo-share-shop-cart-for-woocommerce' ),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label'     => esc_html__( 'Text Color', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vantshsh-share-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_bg_color',
            array(
                'label'     => esc_html__( 'Background Color', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vantshsh-share-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        // Hover State
        $this->start_controls_tab(
            'tab_button_hover',
            array(
                'label' => esc_html__( 'Hover', 'vantigo-share-shop-cart-for-woocommerce' ),
            )
        );

        $this->add_control(
            'button_hover_text_color',
            array(
                'label'     => esc_html__( 'Text Color', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vantshsh-share-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_bg_color',
            array(
                'label'     => esc_html__( 'Background Color', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vantshsh-share-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name'      => 'border',
                'selector'  => '{{WRAPPER}} .vantshsh-share-btn',
                'separator' => 'before',
            )
        );

        $this->add_control(
            'border_radius',
            array(
                'label'      => esc_html__( 'Border Radius', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .vantshsh-share-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'box_shadow',
                'selector' => '{{WRAPPER}} .vantshsh-share-btn',
            )
        );

        $this->add_responsive_control(
            'padding',
            array(
                'label'      => esc_html__( 'Padding', 'vantigo-share-shop-cart-for-woocommerce' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .vantshsh-share-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget frontend output.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        $args = array(
            'text'   => ! empty( $settings['button_text'] ) ? $settings['button_text'] : '',
            'icon'   => $settings['show_icon'] === 'yes' ? 'yes' : 'no',
            'layout' => ! empty( $settings['layout_style'] ) ? $settings['layout_style'] : 'modal',
            'class'  => 'vantshsh-elementor-widget-rendered',
        );

        // In Elementor editor preview, cart might be empty, so display a demo button
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() && ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) ) {
            ?>
            <div class="vantshsh-share-cart-wrapper vantshsh-layout-<?php echo esc_attr( $args['layout'] ); ?>">
                <button type="button" class="button vantshsh-share-btn">
                    <?php if ( 'yes' === $args['icon'] ) : ?>
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
                    <span class="vantshsh-btn-text"><?php echo esc_html( $args['text'] ? $args['text'] : __( 'Share Cart', 'vantigo-share-shop-cart-for-woocommerce' ) ); ?></span>
                </button>
            </div>
            <?php
            return;
        }

        // Output actual widget HTML
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo VANTSHSH_Frontend::get_share_cart_html( $args );
    }
}
