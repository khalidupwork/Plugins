<?php
/**
 * One-click store setup: the software products and the "Talkwyn Pro" WooCommerce
 * product with a variation per plan, mapped to licenses.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Admin\Admin;
use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Creates what the pricing page needs to sell. Safe to run twice: existing pieces are kept.
 */
final class StoreSetup {

	/**
	 * Plans: label, plan slug, sites (0 = unlimited), features, regular and founding price.
	 * Prices come from the Talkwyn theme's Site Settings when it is active.
	 *
	 * @return array<string, array{label: string, limit: int, features: string, regular: float, price: float}>
	 */
	public static function plans(): array {
		$plans = array(
			'personal' => array( 'Personal', 1, 'pro', 79.0, 49.0 ),
			'business' => array( 'Business', 5, 'pro', 179.0, 109.0 ),
			'agency'   => array( 'Agency', 0, 'pro,white_label', 399.0, 239.0 ),
		);
		$num   = static function ( $raw ): ?float {
			return preg_match( '/(\d+(?:[.,]\d{1,2})?)/', (string) $raw, $m ) ? (float) str_replace( ',', '.', $m[1] ) : null;
		};
		$out   = array();
		foreach ( $plans as $slug => $p ) {
			$regular = function_exists( 'talkwyn_setting' ) ? $num( talkwyn_setting( 'regular_' . $slug ) ) : null;
			$price   = function_exists( 'talkwyn_setting' ) ? $num( talkwyn_setting( 'price_' . $slug ) ) : null;
			$out[ $slug ] = array(
				'label'    => $p[0],
				'limit'    => $p[1],
				'features' => $p[2],
				'regular'  => null !== $regular ? $regular : $p[3],
				'price'    => null !== $price ? $price : $p[4],
			);
		}
		return $out;
	}

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_create_plans', array( self::class, 'handle' ) );
	}

	/**
	 * The "Talkwyn Pro" WooCommerce product, if one is mapped already.
	 */
	public static function existing(): int {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one admin lookup.
					array(
						'key'   => Mapping::META_SOFTWARE,
						'value' => 'talkwyn-pro',
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Admin card on the Software products page.
	 */
	public static function render_card(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$existing = self::existing();
		echo '<div class="twh-panel" style="margin-bottom:20px"><h2>' . esc_html__( 'Store setup', 'talkwyn-hub' ) . '</h2>';
		if ( $existing ) {
			echo '<p>' . esc_html__( 'The "Talkwyn Pro" product with its plans is set up.', 'talkwyn-hub' ) . ' <a href="' . esc_url( (string) get_edit_post_link( $existing ) ) . '">' . esc_html__( 'Edit it in WooCommerce', 'talkwyn-hub' ) . '</a></p>';
			$product = wc_get_product( $existing );
			if ( $product && ! $product->get_image_id() ) {
				echo '<p>' . esc_html__( 'It has no product image yet, so WooCommerce shows its default picture in the cart, at checkout and in emails.', 'talkwyn-hub' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="twh_create_plans">';
				wp_nonce_field( 'twh_create_plans' );
				echo '<button type="submit" class="button button-primary">' . esc_html__( 'Add the Talkwyn product image', 'talkwyn-hub' ) . '</button></form>';
			}
		} else {
			echo '<p>' . esc_html__( 'Creates the software products "talkwyn-pro" and "talkwyn", and a WooCommerce product "Talkwyn Pro" with one variation per plan: regular price, founding (sale) price, sites, one year, and the license mapping. The Talkwyn theme\'s pricing buttons then go straight to checkout.', 'talkwyn-hub' ) . '</p><ul style="list-style:disc;padding-left:20px">';
			foreach ( self::plans() as $slug => $p ) {
				/* translators: 1: plan, 2: regular price, 3: founding price, 4: sites */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$s, founding %3$s, %4$s', 'talkwyn-hub' ), $p['label'], wp_strip_all_tags( wc_price( $p['regular'] ) ), wp_strip_all_tags( wc_price( $p['price'] ) ), $p['limit'] ? sprintf( _n( '%d site', '%d sites', $p['limit'], 'talkwyn-hub' ), $p['limit'] ) : __( 'unlimited sites', 'talkwyn-hub' ) ) ) . '</li>';
			}
			echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="twh_create_plans">';
			wp_nonce_field( 'twh_create_plans' );
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Create the Talkwyn plans', 'talkwyn-hub' ) . '</button></form>';
		}
		EmailBrand::render();
		echo '</div>';
	}

	/**
	 * Put the Talkwyn app icon in the media library and use it as the product image,
	 * so the cart, checkout and emails show it instead of WooCommerce's default picture.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function attach_image( \WC_Product $product ): bool {
		$src = TWH_DIR . 'assets/img/talkwyn-pro-product.png';
		if ( ! is_readable( $src ) ) {
			return false;
		}
		$upload = wp_upload_bits( 'talkwyn-pro.png', null, (string) file_get_contents( $src ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Talkwyn Pro',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Talkwyn Pro' );
		$product->set_image_id( $id );
		$product->save();
		return true;
	}

	/**
	 * Create everything.
	 */
	public static function handle(): void {
		Admin::guard( 'twh_create_plans' );
		if ( ! class_exists( 'WC_Product_Variable' ) ) {
			Admin::redirect( 'twh-products', 'invalid' );
		}
		foreach ( array(
			'talkwyn-pro' => 'Talkwyn Pro',
			'talkwyn'     => 'Talkwyn',
		) as $slug => $name ) {
			if ( ! Products::find_by_slug( $slug ) ) {
				Products::create( $slug, $name, home_url( '/' ) );
			}
		}
		$existing = self::existing();
		if ( $existing ) {
			$product = wc_get_product( $existing );
			if ( $product && ! $product->get_image_id() && self::attach_image( $product ) ) {
				Admin::redirect( 'twh-products', 'plans_image' );
			}
			Admin::redirect( 'twh-products', 'plans_exist' );
		}
		$plans = self::plans();

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Plan' );
		$attribute->set_options( array_column( $plans, 'label' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new \WC_Product_Variable();
		$product->set_name( 'Talkwyn Pro' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' ); // Sold from the pricing page.
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_short_description( __( 'Pro features for the free Talkwyn plugin, with one year of updates and support.', 'talkwyn-hub' ) );
		$product->set_attributes( array( $attribute ) );
		$product->update_meta_data( Mapping::META_ENABLED, 'yes' );
		$product->update_meta_data( Mapping::META_SOFTWARE, 'talkwyn-pro' );
		$product->update_meta_data( Mapping::META_DURATION, '365' );
		$product->update_meta_data( Mapping::META_FEATURES, 'pro' );
		$parent_id = $product->save();
		self::attach_image( $product );

		$ids = array();
		foreach ( $plans as $slug => $p ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $parent_id );
			$v->set_attributes( array( 'plan' => $p['label'] ) );
			$v->set_virtual( true );
			$v->set_regular_price( (string) $p['regular'] );
			if ( $p['price'] < $p['regular'] ) {
				$v->set_sale_price( (string) $p['price'] );
			}
			$v->set_status( 'publish' );
			$v->update_meta_data( Mapping::META_PLAN, $slug );
			$v->update_meta_data( Mapping::META_LIMIT, (string) $p['limit'] );
			$v->update_meta_data( Mapping::META_FEATURES, $p['features'] );
			$ids[ $slug ] = $v->save();
		}
		\WC_Product_Variable::sync( $parent_id );

		// The Talkwyn theme's pricing buttons use these variation IDs.
		$theme = get_option( 'talkwyn_site_settings' );
		if ( is_array( $theme ) || function_exists( 'talkwyn_setting' ) ) {
			$theme                 = is_array( $theme ) ? $theme : array();
			$theme['price_source'] = 'woocommerce';
			foreach ( $ids as $slug => $id ) {
				$theme[ 'product_' . $slug ] = (int) $id;
			}
			update_option( 'talkwyn_site_settings', $theme );
		}
		Admin::redirect( 'twh-products', 'plans_created' );
	}
}
