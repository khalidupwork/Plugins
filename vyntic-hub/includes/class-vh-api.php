<?php
/**
 * Public API used by installed Vyntic plugins:
 *
 *   GET  ?rest_route=/vyntic-hub/v1/plugins        catalog of published plugins
 *   POST ?rest_route=/vyntic-hub/v1/check          latest versions for a site
 *   GET  ?rest_route=/vyntic-hub/v1/info/{slug}    "View details" popup content
 *   GET  /?vyntic_download={slug}&version={x}       the zip
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_API {

	const NS = 'vyntic-hub/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'init', array( __CLASS__, 'maybe_download' ), 1 );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'plugins' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'check' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/info/(?P<slug>[a-z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'info' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Published plugins, cached until something changes.
	 */
	public static function catalog() {
		$cached = get_transient( 'vh_catalog' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$items = VH_Plugins::published();
		set_transient( 'vh_catalog', $items, DAY_IN_SECONDS );
		return $items;
	}

	public static function flush() {
		delete_transient( 'vh_catalog' );
	}

	public static function plugins() {
		return rest_ensure_response( array_values( self::catalog() ) );
	}

	public static function check( WP_REST_Request $request ) {
		$body    = $request->get_json_params();
		$plugins = isset( $body['plugins'] ) && is_array( $body['plugins'] ) ? array_slice( $body['plugins'], 0, 50, true ) : array();
		$catalog = self::catalog();
		$out     = array();

		foreach ( $plugins as $slug => $version ) {
			$slug = sanitize_key( $slug );
			if ( isset( $catalog[ $slug ] ) ) {
				$out[ $slug ] = $catalog[ $slug ];
				VH_Sites::record(
					isset( $body['site'] ) ? (string) $body['site'] : '',
					$slug,
					sanitize_text_field( (string) $version ),
					isset( $body['wp'] ) ? sanitize_text_field( (string) $body['wp'] ) : '',
					isset( $body['php'] ) ? sanitize_text_field( (string) $body['php'] ) : ''
				);
			}
		}
		return rest_ensure_response( array( 'plugins' => (object) $out ) );
	}

	public static function info( WP_REST_Request $request ) {
		$post = VH_Plugins::find_by_slug( $request['slug'] );
		$item = $post ? VH_Plugins::item( $post ) : null;
		if ( ! $item ) {
			return new WP_Error( 'vh_not_found', 'Plugin not found', array( 'status' => 404 ) );
		}

		$changelog = '';
		foreach ( VH_Plugins::releases( $post->ID ) as $release ) {
			$changelog .= '<h4>' . esc_html( $release['version'] ) . ' <small>(' . esc_html( mysql2date( 'Y-m-d', $release['date'] ) ) . ')</small></h4>';
			$changelog .= $release['changelog'] ? wpautop( esc_html( $release['changelog'] ) ) : '';
		}

		$item['sections'] = array(
			'description' => wpautop( wp_kses_post( strip_shortcodes( $post->post_content ) ) ),
			'changelog'   => $changelog,
		);
		$item['banner'] = '';
		return rest_ensure_response( $item );
	}

	/**
	 * Streams a release zip and counts the download.
	 */
	public static function maybe_download() {
		if ( empty( $_GET['vyntic_download'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$slug    = sanitize_key( wp_unslash( $_GET['vyntic_download'] ) ); // phpcs:ignore
		$version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : ''; // phpcs:ignore
		$post    = VH_Plugins::find_by_slug( $slug );
		if ( ! $post ) {
			status_header( 404 );
			exit;
		}
		$releases = VH_Plugins::releases( $post->ID );
		$release  = ( $version && isset( $releases[ $version ] ) ) ? $releases[ $version ] : VH_Plugins::live_release( $post->ID );
		$file     = $release ? get_attached_file( (int) $release['attachment'] ) : '';
		if ( ! $file || ! is_file( $file ) ) {
			status_header( 404 );
			exit;
		}

		update_post_meta( $post->ID, '_vh_downloads', (int) get_post_meta( $post->ID, '_vh_downloads', true ) + 1 );

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.zip"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Robots-Tag: noindex' );
		readfile( $file ); // phpcs:ignore
		exit;
	}
}
