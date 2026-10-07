<?php
/**
 * "Talkwyn Hub" product data tab.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Adds licensing fields to products and variations.
 */
final class ProductTab {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( self::class, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( self::class, 'save_variation' ), 10, 2 );
	}

	/**
	 * Add the tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function tab( array $tabs ): array {
		$tabs['talkwyn_hub'] = array(
			'label'    => __( 'Talkwyn Hub', 'talkwyn-hub' ),
			'target'   => 'twh_product_data',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Software product options.
	 *
	 * @return array<string, string>
	 */
	private static function software_options(): array {
		$options = array( '' => __( '— Select —', 'talkwyn-hub' ) );
		foreach ( Products::all() as $product ) {
			$options[ (string) $product['slug'] ] = (string) $product['name'] . ' (' . $product['slug'] . ')';
		}
		return $options;
	}

	/**
	 * Render the panel.
	 */
	public static function panel(): void {
		global $product_object;
		echo '<div id="twh_product_data" class="panel woocommerce_options_panel hidden"><div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => Mapping::META_ENABLED,
				'label'       => __( 'Issue license keys', 'talkwyn-hub' ),
				'description' => __( 'Create a license key for each unit purchased.', 'talkwyn-hub' ),
				'value'       => $product_object ? $product_object->get_meta( Mapping::META_ENABLED ) : '',
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Mapping::META_SOFTWARE,
				'label'   => __( 'Software product', 'talkwyn-hub' ),
				'options' => self::software_options(),
				'value'   => $product_object ? $product_object->get_meta( Mapping::META_SOFTWARE ) : '',
			)
		);
		self::numeric_fields( $product_object, '', '' );

		echo '<p class="form-field"><em>' . esc_html__( 'For variable products, each variation can override activation limit, duration, plan and features. Empty variation fields inherit these values.', 'talkwyn-hub' ) . '</em></p>';
		wp_nonce_field( 'twh_product_mapping', 'twh_product_mapping_nonce' );
		echo '</div></div>';
	}

	/**
	 * Limit/duration/plan/features fields.
	 *
	 * @param \WC_Product|null $product Product.
	 * @param string           $suffix  Field name suffix (variation index).
	 * @param string           $wrapper Wrapper class.
	 */
	private static function numeric_fields( $product, string $suffix, string $wrapper ): void {
		$get    = static function ( string $key ) use ( $product ): string {
			return $product ? (string) $product->get_meta( $key ) : '';
		};
		$id     = static function ( string $key ) use ( $suffix ): string {
			return '' === $suffix ? $key : $key . '_' . trim( $suffix, '[]' );
		};
		$name   = static function ( string $key ) use ( $suffix ): string {
			return '' === $suffix ? $key : $key . $suffix;
		};
		$is_var = '' !== $suffix;

		woocommerce_wp_text_input(
			array(
				'id'                => $id( Mapping::META_LIMIT ),
				'name'              => $name( Mapping::META_LIMIT ),
				'label'             => __( 'Activation limit', 'talkwyn-hub' ),
				'description'       => __( 'Number of production sites. 0 = unlimited. Dev/staging sites never count.', 'talkwyn-hub' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'value'             => $get( Mapping::META_LIMIT ),
				'placeholder'       => $is_var ? __( 'Inherit', 'talkwyn-hub' ) : '1',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'wrapper_class'     => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => $id( Mapping::META_DURATION ),
				'name'              => $name( Mapping::META_DURATION ),
				'label'             => __( 'License duration (days)', 'talkwyn-hub' ),
				'description'       => __( '0 = lifetime (never expires).', 'talkwyn-hub' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'value'             => $get( Mapping::META_DURATION ),
				'placeholder'       => $is_var ? __( 'Inherit', 'talkwyn-hub' ) : '365',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'wrapper_class'     => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => $id( Mapping::META_PLAN ),
				'name'          => $name( Mapping::META_PLAN ),
				'label'         => __( 'Plan slug', 'talkwyn-hub' ),
				'description'   => __( 'e.g. personal, business, agency, lifetime. Sent to the client plugin.', 'talkwyn-hub' ),
				'desc_tip'      => true,
				'value'         => $get( Mapping::META_PLAN ),
				'placeholder'   => $is_var ? __( 'Inherit', 'talkwyn-hub' ) : 'personal',
				'wrapper_class' => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => $id( Mapping::META_FEATURES ),
				'name'          => $name( Mapping::META_FEATURES ),
				'label'         => __( 'Features', 'talkwyn-hub' ),
				'description'   => __( 'Comma-separated feature flags sent to the client, e.g. pro,white_label.', 'talkwyn-hub' ),
				'desc_tip'      => true,
				'value'         => $get( Mapping::META_FEATURES ),
				'placeholder'   => $is_var ? __( 'Inherit', 'talkwyn-hub' ) : 'pro',
				'wrapper_class' => $wrapper,
			)
		);
	}

	/**
	 * Save product-level fields. WooCommerce verified its own meta box nonce already.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function save( $product ): void {
		if ( ! isset( $_POST['twh_product_mapping_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twh_product_mapping_nonce'] ) ), 'twh_product_mapping' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		$product->update_meta_data( Mapping::META_ENABLED, isset( $_POST[ Mapping::META_ENABLED ] ) ? 'yes' : 'no' );
		$product->update_meta_data( Mapping::META_SOFTWARE, sanitize_title( wp_unslash( $_POST[ Mapping::META_SOFTWARE ] ?? '' ) ) );
		self::save_common( $product, '' );
	}

	/**
	 * Variation fields.
	 *
	 * @param int      $loop           Index.
	 * @param array    $variation_data Data.
	 * @param \WP_Post $variation      Variation post.
	 */
	public static function variation_fields( $loop, $variation_data, $variation ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		$product = wc_get_product( $variation->ID );
		echo '<div class="twh-variation-fields" style="clear:both;border-top:1px solid #eee;padding-top:8px"><strong>' . esc_html__( 'Talkwyn Hub (leave empty to inherit)', 'talkwyn-hub' ) . '</strong>';
		self::numeric_fields( $product ? $product : null, '[' . (int) $loop . ']', 'form-row form-row-first' );
		echo '</div>';
	}

	/**
	 * Save variation fields. WooCommerce verifies the save-variations nonce.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 * @param int                   $i         Index.
	 */
	public static function save_variation( $variation, $i ): void {
		if ( ! current_user_can( 'edit_product', $variation->get_parent_id() ) ) {
			return;
		}
		self::save_common( $variation, (string) $i );
	}

	/**
	 * Save limit/duration/plan/features.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $index   Variation index ('' for parent).
	 */
	private static function save_common( $product, string $index ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the callers.
		$read = static function ( string $key ) use ( $index ): ?string {
			if ( '' === $index ) {
				return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : null;
			}
			return isset( $_POST[ $key ][ $index ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ][ $index ] ) ) : null;
		};
		// phpcs:enable

		foreach ( array( Mapping::META_LIMIT, Mapping::META_DURATION ) as $key ) {
			$raw = $read( $key );
			if ( null === $raw ) {
				continue;
			}
			$raw = trim( $raw );
			$product->update_meta_data( $key, '' === $raw ? '' : (string) absint( $raw ) );
		}
		$plan = $read( Mapping::META_PLAN );
		if ( null !== $plan ) {
			$product->update_meta_data( Mapping::META_PLAN, sanitize_key( $plan ) );
		}
		$features = $read( Mapping::META_FEATURES );
		if ( null !== $features ) {
			$product->update_meta_data( Mapping::META_FEATURES, \TWH\Repository\Licenses::sanitize_features( $features ) );
		}
	}
}
