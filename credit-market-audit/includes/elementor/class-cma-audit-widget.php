<?php
/**
 * Elementor "Free Website Audit" widget.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Drag-and-drop audit form with full content + style controls.
 */
class CMA_Audit_Widget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cma-free-audit';
	}

	/**
	 * Title in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Free Website Audit', 'credit-market-audit' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-site-search';
	}

	/**
	 * Panel categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'credit-market', 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'audit', 'seo', 'pagespeed', 'speed', 'report', 'lead', 'form', 'credit market' );
	}

	/**
	 * Scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'cma-frontend' );
	}

	/**
	 * Styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'cma-frontend' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$defaults = CMA_Frontend::defaults();

		/* ---------------- Content: form ---------------- */
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Form', 'credit-market-audit' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'heading',
			array(
				'label'       => __( 'Heading', 'credit-market-audit' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $defaults['heading'],
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'heading_tag',
			array(
				'label'   => __( 'Heading HTML tag', 'credit-market-audit' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
			)
		);

		$this->add_control(
			'subheading',
			array(
				'label'   => __( 'Sub heading', 'credit-market-audit' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => $defaults['subheading'],
				'rows'    => 3,
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Fields layout', 'credit-market-audit' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'stacked',
				'options' => array(
					'stacked' => __( 'Stacked', 'credit-market-audit' ),
					'inline'  => __( 'Inline (one row)', 'credit-market-audit' ),
				),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Colour scheme', 'credit-market-audit' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'dark',
				'options' => array(
					'dark'  => __( 'For dark backgrounds', 'credit-market-audit' ),
					'light' => __( 'For light backgrounds', 'credit-market-audit' ),
				),
			)
		);

		$this->add_control(
			'show_name',
			array(
				'label'        => __( 'Show name field', 'credit-market-audit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'name_placeholder',
			array(
				'label'     => __( 'Name placeholder', 'credit-market-audit' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $defaults['name_placeholder'],
				'condition' => array( 'show_name' => 'yes' ),
			)
		);

		$this->add_control(
			'email_placeholder',
			array(
				'label'   => __( 'Email placeholder', 'credit-market-audit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['email_placeholder'],
			)
		);

		$this->add_control(
			'url_placeholder',
			array(
				'label'   => __( 'Website URL placeholder', 'credit-market-audit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['url_placeholder'],
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'Button text', 'credit-market-audit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['button_text'],
			)
		);

		$this->add_control(
			'show_consent',
			array(
				'label'        => __( 'Show consent checkbox', 'credit-market-audit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'consent_text',
			array(
				'label'     => __( 'Consent text', 'credit-market-audit' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => $defaults['consent_text'],
				'rows'      => 2,
				'condition' => array( 'show_consent' => 'yes' ),
			)
		);

		$this->add_control(
			'settings_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: settings URL */
					__( 'API key, email texts, branding and the call-to-action are configured in <a href="%s" target="_blank">Free Audit → Settings</a>.', 'credit-market-audit' ),
					esc_url( admin_url( 'admin.php?page=cma-settings' ) )
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'separator'       => 'before',
			)
		);

		$this->end_controls_section();

		/* ---------------- Style: box ---------------- */
		$this->start_controls_section(
			'section_style_box',
			array(
				'label' => __( 'Box', 'credit-market-audit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'       => __( 'Accent colour', 'credit-market-audit' ),
				'description' => __( 'Used for focus rings, progress bar and report header.', 'credit-market-audit' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .cma-audit, {{WRAPPER}} .cma-report' => '--cma-accent: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'box_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .cma-form-wrap',
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => __( 'Padding', 'credit-market-audit' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .cma-form-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} .cma-form-wrap',
			)
		);

		$this->add_control(
			'box_radius',
			array(
				'label'      => __( 'Border radius', 'credit-market-audit' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .cma-form-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .cma-form-wrap',
			)
		);

		$this->add_responsive_control(
			'text_align',
			array(
				'label'     => __( 'Text alignment', 'credit-market-audit' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'credit-market-audit' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'credit-market-audit' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'credit-market-audit' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .cma-heading, {{WRAPPER}} .cma-subheading' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ---------------- Style: heading ---------------- */
		$this->start_controls_section(
			'section_style_heading',
			array(
				'label' => __( 'Heading', 'credit-market-audit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Heading colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-heading' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'selector' => '{{WRAPPER}} .cma-heading',
			)
		);

		$this->add_control(
			'subheading_color',
			array(
				'label'     => __( 'Sub heading colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-subheading, {{WRAPPER}} .cma-consent' => 'color: {{VALUE}};' ),
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'subheading_typography',
				'selector' => '{{WRAPPER}} .cma-subheading',
			)
		);

		$this->end_controls_section();

		/* ---------------- Style: fields ---------------- */
		$this->start_controls_section(
			'section_style_fields',
			array(
				'label' => __( 'Fields', 'credit-market-audit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'field_typography',
				'selector' => '{{WRAPPER}} .cma-field input',
			)
		);

		$this->add_control(
			'field_text_color',
			array(
				'label'     => __( 'Text colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-field input' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'field_bg_color',
			array(
				'label'     => __( 'Background colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-field input' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'field_border_color',
			array(
				'label'     => __( 'Border colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-field input' => 'border-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'field_height',
			array(
				'label'      => __( 'Height', 'credit-market-audit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 32,
						'max' => 90,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .cma-field input, {{WRAPPER}} .cma-btn' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'field_gap',
			array(
				'label'      => __( 'Spacing between fields', 'credit-market-audit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .cma-fields' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'field_radius',
			array(
				'label'      => __( 'Border radius', 'credit-market-audit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .cma-audit' => '--cma-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ---------------- Style: button ---------------- */
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => __( 'Button', 'credit-market-audit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .cma-btn',
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab( 'button_normal', array( 'label' => __( 'Normal', 'credit-market-audit' ) ) );
		$this->add_control(
			'button_text_color',
			array(
				'label'     => __( 'Text colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-btn' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_bg_color',
			array(
				'label'     => __( 'Background colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-btn' => 'background: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'button_hover', array( 'label' => __( 'Hover', 'credit-market-audit' ) ) );
		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => __( 'Text colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-btn:hover' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'     => __( 'Background colour', 'credit-market-audit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cma-btn:hover' => 'background: {{VALUE}}; filter: none;' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'button_border',
				'selector'  => '{{WRAPPER}} .cma-btn',
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_shadow',
				'selector' => '{{WRAPPER}} .cma-btn',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Front-end / editor render.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$html = CMA_Frontend::render_form(
			array(
				'heading'           => isset( $s['heading'] ) ? $s['heading'] : '',
				'subheading'        => isset( $s['subheading'] ) ? $s['subheading'] : '',
				'show_name'         => isset( $s['show_name'] ) && 'yes' === $s['show_name'] ? 'yes' : 'no',
				'name_placeholder'  => isset( $s['name_placeholder'] ) ? $s['name_placeholder'] : '',
				'email_placeholder' => isset( $s['email_placeholder'] ) ? $s['email_placeholder'] : '',
				'url_placeholder'   => isset( $s['url_placeholder'] ) ? $s['url_placeholder'] : '',
				'button_text'       => isset( $s['button_text'] ) ? $s['button_text'] : '',
				'show_consent'      => isset( $s['show_consent'] ) && 'yes' === $s['show_consent'] ? 'yes' : 'no',
				'consent_text'      => isset( $s['consent_text'] ) ? $s['consent_text'] : '',
				'layout'            => isset( $s['layout'] ) ? $s['layout'] : 'stacked',
				'theme'             => isset( $s['theme'] ) ? $s['theme'] : 'dark',
				'heading_tag'       => isset( $s['heading_tag'] ) ? $s['heading_tag'] : 'h3',
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template.
	}
}
