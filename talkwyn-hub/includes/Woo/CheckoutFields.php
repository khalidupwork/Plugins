<?php
/**
 * Optional "Website" field at checkout, for support context. The license is not tied
 * to it: sites are linked when the key is activated on them.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Register the field for the block checkout (WooCommerce 8.9+) and the classic one.
 */
final class CheckoutFields {

	public const BLOCK_ID  = 'talkwyn/website';
	public const CLASSIC   = 'twh_website';
	public const ORDER_KEY = '_twh_checkout_website';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_init', array( self::class, 'register_block_field' ) );
		add_filter( 'woocommerce_checkout_fields', array( self::class, 'classic_field' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'save_classic' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'admin_show' ) );
	}

	/**
	 * Whether a value looks like a website address (a domain, with or without https://).
	 *
	 * @param string $value Value.
	 */
	public static function looks_like_site( string $value ): bool {
		$host = (string) wp_parse_url( preg_match( '#^https?://#i', $value ) ? $value : 'https://' . $value, PHP_URL_HOST );
		return (bool) preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $host ) || 'localhost' === strtolower( $host );
	}

	/**
	 * Clean a typed address to "https://example.com/path".
	 *
	 * @param string $value Value.
	 */
	public static function clean( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		return esc_url_raw( preg_match( '#^https?://#i', $value ) ? $value : 'https://' . $value );
	}

	/**
	 * Block checkout field, in the "Additional order information" area.
	 */
	public static function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::BLOCK_ID,
				'label'             => __( 'Website (optional)', 'talkwyn-hub' ),
				'optionalLabel'     => __( 'Website (optional)', 'talkwyn-hub' ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'attributes'        => array(
					'autocomplete' => 'url',
				),
				'sanitize_callback' => static function ( $value ) {
					return self::clean( (string) $value );
				},
				'validate_callback' => static function ( $value ) {
					$value = (string) $value;
					if ( '' !== $value && ! self::looks_like_site( $value ) ) {
						return new \WP_Error( 'twh_website', __( 'Please enter a website address like example.com, or leave it empty.', 'talkwyn-hub' ) );
					}
					return null;
				},
			)
		);
	}

	/**
	 * Classic checkout (shortcode) version of the field.
	 *
	 * @param array<string, array<string, mixed>> $fields Fields.
	 * @return array<string, array<string, mixed>>
	 */
	public static function classic_field( $fields ) {
		if ( ! Cart::cart_has_license() ) {
			return $fields;
		}
		$fields['order'][ self::CLASSIC ] = array(
			'type'         => 'text',
			'label'        => __( 'Website (optional)', 'talkwyn-hub' ),
			'placeholder'  => 'example.com',
			'required'     => false,
			'autocomplete' => 'url',
			'priority'     => 5,
		);
		return $fields;
	}

	/**
	 * Save the classic field.
	 *
	 * @param \WC_Order            $order Order.
	 * @param array<string, mixed> $data  Posted checkout data.
	 */
	public static function save_classic( $order, $data ): void {
		$value = isset( $data[ self::CLASSIC ] ) ? self::clean( (string) $data[ self::CLASSIC ] ) : '';
		if ( '' !== $value && self::looks_like_site( $value ) ) {
			$order->update_meta_data( self::ORDER_KEY, $value );
		}
	}

	/**
	 * The website on the admin order screen (the block field shows there by itself).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function admin_show( $order ): void {
		$value = (string) $order->get_meta( self::ORDER_KEY );
		if ( '' !== $value ) {
			echo '<p><strong>' . esc_html__( 'Website', 'talkwyn-hub' ) . ':</strong> <a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a></p>';
		}
	}
}
