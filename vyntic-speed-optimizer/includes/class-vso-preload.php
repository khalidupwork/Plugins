<?php
/**
 * Cache preloading: visits the site's URLs in the background so real visitors
 * always get a cached, already-optimized page.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Preload {

	const QUEUE  = 'vso_preload_queue';
	const HOOK   = 'vso_preload_batch';
	const BATCH  = 8;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
	}

	/**
	 * Rebuilds the full queue and starts the background run.
	 */
	public static function schedule() {
		if ( ! VSO_Settings::enabled( 'page_cache' ) || ! VSO_Settings::enabled( 'cache_preload' ) ) {
			return;
		}
		update_option( self::QUEUE, self::collect_urls(), false );
		self::kick( 30 );
	}

	public static function queue_urls( array $urls ) {
		if ( ! VSO_Settings::enabled( 'page_cache' ) || ! VSO_Settings::enabled( 'cache_preload' ) ) {
			return;
		}
		$queue = (array) get_option( self::QUEUE, array() );
		update_option( self::QUEUE, array_values( array_unique( array_merge( $urls, $queue ) ) ), false );
		self::kick( 15 );
	}

	private static function kick( $delay ) {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}

	/**
	 * Home page, public pages/posts/products and taxonomy archives.
	 */
	public static function collect_urls() {
		$urls  = array( home_url( '/' ) );
		$types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$types = array_diff( $types, array( 'attachment', 'elementor_library', 'e-landing-page' ) );

		$ids = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => (int) apply_filters( 'vso_preload_limit', 500 ),
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'has_password'   => false,
			)
		);
		foreach ( $ids as $id ) {
			$urls[] = get_permalink( $id );
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => 100,
				)
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						$urls[] = $link;
					}
				}
			}
		}

		$exclude = VSO_Settings::lines( 'cache_exclude_urls' );
		$urls    = array_filter(
			array_unique( $urls ),
			static function ( $url ) use ( $exclude ) {
				$path = (string) wp_parse_url( $url, PHP_URL_PATH );
				return $url && ! VSO_Cache_Engine::is_excluded_url( '' === $path ? '/' : $path, $exclude );
			}
		);
		return array_values( $urls );
	}

	public static function run_batch() {
		$queue = (array) get_option( self::QUEUE, array() );
		if ( empty( $queue ) ) {
			return;
		}
		$batch = array_splice( $queue, 0, self::BATCH );
		update_option( self::QUEUE, $queue, false );

		$agents = array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 VynticPreload' );
		if ( VSO_Settings::enabled( 'cache_mobile' ) ) {
			$agents[] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 VynticPreload';
		}

		foreach ( $batch as $url ) {
			foreach ( $agents as $agent ) {
				wp_remote_get(
					$url,
					array(
						'timeout'    => 15,
						'blocking'   => true,
						'sslverify'  => false,
						'user-agent' => $agent,
						'cookies'    => array(),
					)
				);
			}
		}

		if ( ! empty( $queue ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK );
		}
	}

	public static function remaining() {
		return count( (array) get_option( self::QUEUE, array() ) );
	}
}
