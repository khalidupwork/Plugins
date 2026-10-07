<?php
/**
 * WooCommerce product → software product mapping.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Repository\Licenses;
use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the "Talkwyn Hub" product data stored as post meta.
 *
 * Simple products store everything on the product. Variations inherit the
 * parent's software product and override limit/duration/plan/features.
 */
final class Mapping {

	public const META_ENABLED  = '_twh_enabled';
	public const META_SOFTWARE = '_twh_software_product';
	public const META_LIMIT    = '_twh_activation_limit';
	public const META_DURATION = '_twh_duration_days';
	public const META_PLAN     = '_twh_plan_slug';
	public const META_FEATURES = '_twh_features';

	/**
	 * Resolve the mapping for a product or variation.
	 *
	 * @param \WC_Product|int|null $product Product or id.
	 * @return array{product_id: int, product_slug: string, activation_limit: int, duration_days: int, plan_slug: string, features: string}|null
	 */
	public static function for_product( $product ): ?array {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( (int) $product );
		}
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
		$base   = $parent instanceof \WC_Product ? $parent : $product;

		if ( 'yes' !== $base->get_meta( self::META_ENABLED ) ) {
			return null;
		}
		$slug     = (string) $base->get_meta( self::META_SOFTWARE );
		$software = '' !== $slug ? Products::find_by_slug( $slug ) : null;
		if ( ! $software ) {
			return null;
		}

		$value = static function ( string $key, string $fallback ) use ( $product, $base ): string {
			$own = (string) $product->get_meta( $key );
			if ( '' !== $own ) {
				return $own;
			}
			$inherited = (string) $base->get_meta( $key );
			return '' !== $inherited ? $inherited : $fallback;
		};

		return array(
			'product_id'       => (int) $software['id'],
			'product_slug'     => (string) $software['slug'],
			'activation_limit' => absint( $value( self::META_LIMIT, '1' ) ),
			'duration_days'    => absint( $value( self::META_DURATION, '365' ) ),
			'plan_slug'        => sanitize_key( $value( self::META_PLAN, 'personal' ) ),
			'features'         => Licenses::sanitize_features( $value( self::META_FEATURES, 'pro' ) ),
		);
	}

	/**
	 * Purchasable WooCommerce products/variations mapped to a software product.
	 *
	 * @param string $software_slug Software product slug.
	 * @return array<int, array{wc_product: \WC_Product, mapping: array<string, mixed>}>
	 */
	public static function products_for_software( string $software_slug ): array {
		$parents = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small result set, admin/account only.
					'relation' => 'AND',
					array(
						'key'   => self::META_ENABLED,
						'value' => 'yes',
					),
					array(
						'key'   => self::META_SOFTWARE,
						'value' => $software_slug,
					),
				),
			)
		);
		$out     = array();
		foreach ( $parents as $parent_id ) {
			$parent = wc_get_product( $parent_id );
			if ( ! $parent ) {
				continue;
			}
			$candidates = $parent->is_type( 'variable' ) ? array_map( 'wc_get_product', $parent->get_children() ) : array( $parent );
			foreach ( $candidates as $candidate ) {
				if ( ! $candidate instanceof \WC_Product || ! $candidate->is_purchasable() ) {
					continue;
				}
				$mapping = self::for_product( $candidate );
				if ( $mapping ) {
					$out[] = array(
						'wc_product' => $candidate,
						'mapping'    => $mapping,
					);
				}
			}
		}
		return $out;
	}
}
