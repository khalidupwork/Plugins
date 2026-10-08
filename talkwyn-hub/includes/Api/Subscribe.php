<?php
/**
 * POST talkwyn-hub/v1/subscribe: email opt-in from the Talkwyn plugin setup wizard.
 *
 * The plugin only calls this when the site owner ticks the (unchecked by default)
 * box. Stores the email, site and source; nothing else.
 *
 * @package TalkwynHub
 */

namespace TWH\Api;

use TWH\Install\Schema;
use TWH\Support\Request;
use TWH\Support\Secrets;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

/**
 * Newsletter opt-in endpoint.
 */
final class Subscribe {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'route' ) );
		add_action( 'admin_post_twh_export_subscribers', array( self::class, 'export' ) );
	}

	/**
	 * Route.
	 */
	public static function route(): void {
		register_rest_route(
			RestController::NS,
			'/subscribe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => '__return_true', // Public opt-in; rate limited by IP.
			)
		);
	}

	/**
	 * Validate input. Pure, unit tested.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool,email:string,site_url:string,source:string,locale:string,error:string}
	 */
	public static function validate( array $p ): array {
		$email  = strtolower( trim( (string) ( $p['email'] ?? '' ) ) );
		$site   = trim( (string) ( $p['site_url'] ?? '' ) );
		$source = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) ( $p['source'] ?? 'plugin' ) ) );
		$locale = preg_match( '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})?$/', (string) ( $p['locale'] ?? '' ) ) ? (string) $p['locale'] : '';
		$out    = array(
			'ok'       => false,
			'email'    => $email,
			'site_url' => preg_match( '#^https?://#i', $site ) ? substr( $site, 0, 255 ) : '',
			'source'   => '' !== $source ? substr( $source, 0, 40 ) : 'plugin',
			'locale'   => $locale,
			'error'    => '',
		);
		if ( empty( $p['consent'] ) ) {
			$out['error'] = 'consent_required';
		} elseif ( strlen( $email ) > 190 || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$out['error'] = 'invalid_email';
		} else {
			$out['ok'] = true;
		}
		return $out;
	}

	/**
	 * Handle.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$ip_hash = Secrets::hash_ip( Request::ip() );
		if ( ! RateLimiter::hit( 'subscribe', $ip_hash ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'code' => 'rate_limited' ), 429 );
		}
		$in = self::validate( (array) $request->get_json_params() + (array) $request->get_body_params() );
		if ( ! $in['ok'] ) {
			return new \WP_REST_Response( array( 'success' => false, 'code' => $in['error'] ), 400 );
		}
		global $wpdb;
		$table = Schema::table( 'subscribers' );
		$now   = Time::now_mysql();
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$table} (email, site_url, source, locale, ip_hash, status, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, 'subscribed', %s, %s) ON DUPLICATE KEY UPDATE site_url = VALUES(site_url), status = 'subscribed', updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$in['email'],
				$in['site_url'],
				$in['source'],
				$in['locale'],
				$ip_hash,
				$now,
				$now
			)
		);

		/**
		 * Fires after an email opted in (connect a mailing list here).
		 *
		 * @param array $in Email, site_url, source, locale.
		 */
		do_action( 'twh_subscriber_added', $in );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * CSV export.
	 */
	public static function export(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn-hub' ) );
		}
		check_admin_referer( 'twh_export_subscribers' );
		global $wpdb;
		$table = Schema::table( 'subscribers' );
		$rows  = (array) $wpdb->get_results( "SELECT email, site_url, source, locale, status, created_at FROM {$table} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=talkwyn-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'email', 'site_url', 'source', 'locale', 'status', 'created_at' ) );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					static function ( $v ) {
						$v = (string) $v;
						return preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
					},
					$r
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
