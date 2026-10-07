<?php
/**
 * REST API: talkwyn-hub/v1.
 *
 * @package TalkwynHub
 */

namespace TWH\Api;

use TWH\Domain\ActivationPolicy;
use TWH\Domain\Domain;
use TWH\Domain\DownloadToken;
use TWH\Domain\KeyGenerator;
use TWH\Domain\Markdown;
use TWH\Repository\Activations;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Repository\Releases;
use TWH\Support\Request;
use TWH\Support\Secrets;
use TWH\Support\Settings;
use TWH\Support\Storage;
use TWH\Support\Time;
use TWH\Woo\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Public, unauthenticated endpoints used by the client plugin. Every response is signed.
 */
final class RestController {

	public const NS = 'talkwyn-hub/v1';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes(): void {
		$post = array(
			'license/activate'   => 'activate',
			'license/deactivate' => 'deactivate',
			'license/check'      => 'check',
			'update/check'       => 'update_check',
		);
		foreach ( $post as $route => $method ) {
			register_rest_route(
				self::NS,
				'/' . $route,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $request ) use ( $method ) {
						return self::dispatch( $request, $method );
					},
					'permission_callback' => '__return_true', // Public API; authenticated by license key.
				)
			);
		}
		register_rest_route(
			self::NS,
			'/download',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'download' ),
				'permission_callback' => '__return_true', // Authenticated by HMAC token.
				'args'                => array(
					'token' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- ApiError is caught and returned as a signed response.
	/**
	 * Parse, rate limit, run a handler and wrap errors.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $method  Handler name.
	 */
	private static function dispatch( \WP_REST_Request $request, string $method ): \WP_REST_Response {
		// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		$nonce = isset( $params['nonce'] ) && is_string( $params['nonce'] ) && preg_match( '/^[A-Za-z0-9_-]{8,128}$/', $params['nonce'] ) ? $params['nonce'] : '';

		try {
			$ip_hash = Secrets::hash_ip( Request::ip() );
			if ( ! RateLimiter::hit( 'ip', $ip_hash ) ) {
				self::log_rate_limited( null, 'ip' );
				throw new ApiError( 'rate_limited', __( 'Too many requests. Please try again later.', 'talkwyn-hub' ) );
			}
			if ( '' === $nonce ) {
				throw new ApiError( 'bad_request', __( 'Missing or invalid nonce.', 'talkwyn-hub' ) );
			}
			$input = self::parse( $params, 'update_check' === $method );

			if ( ! RateLimiter::hit( 'key', KeyGenerator::hash( $input['license_key'] ) ) ) {
				self::log_rate_limited( null, 'key' );
				throw new ApiError( 'rate_limited', __( 'Too many requests for this license. Please try again later.', 'talkwyn-hub' ) );
			}

			$license = self::load_license( $input );
			$data    = self::$method( $license, $input );
			return Responder::success( $data, $nonce );
		} catch ( ApiError $e ) {
			return Responder::error( $e->error_code, $e->getMessage(), $nonce, $e->extra );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Talkwyn Hub API error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return Responder::error( 'server_error', __( 'Internal error.', 'talkwyn-hub' ), $nonce );
		}
	}

	/**
	 * Validate and sanitize common fields.
	 *
	 * @param array<string, mixed> $p              Params.
	 * @param bool                 $with_channel   Parse channel.
	 * @return array<string, string>
	 * @throws ApiError On invalid input.
	 */
	private static function parse( array $p, bool $with_channel ): array {
		$str = static function ( string $key, int $max ) use ( $p ): string {
			if ( ! isset( $p[ $key ] ) || ! is_scalar( $p[ $key ] ) ) {
				return '';
			}
			return substr( sanitize_text_field( (string) $p[ $key ] ), 0, $max );
		};

		$key = KeyGenerator::normalize( $str( 'license_key', 64 ) );
		if ( '' === $key ) {
			throw new ApiError( 'bad_request', __( 'license_key is required.', 'talkwyn-hub' ) );
		}
		$instance = $str( 'instance_id', 64 );
		if ( ! preg_match( '/^[A-Za-z0-9-]{8,64}$/', $instance ) ) {
			throw new ApiError( 'bad_request', __( 'instance_id is missing or invalid.', 'talkwyn-hub' ) );
		}
		$site_url = esc_url_raw( $str( 'site_url', 255 ), array( 'http', 'https' ) );
		$domain   = Domain::normalize( $site_url );
		if ( '' === $domain ) {
			throw new ApiError( 'bad_request', __( 'site_url is missing or invalid.', 'talkwyn-hub' ) );
		}
		$product = sanitize_title( $str( 'product', 100 ) );
		if ( '' === $product ) {
			throw new ApiError( 'bad_request', __( 'product is required.', 'talkwyn-hub' ) );
		}
		$version = static function ( string $v ): string {
			return (string) preg_replace( '/[^0-9A-Za-z.+-]/', '', $v );
		};

		$channel = $with_channel ? $str( 'channel', 10 ) : 'stable';
		return array(
			'license_key'    => $key,
			'instance_id'    => $instance,
			'site_url'       => $site_url,
			'domain'         => $domain,
			'product'        => $product,
			'plugin_version' => $version( $str( 'plugin_version', 32 ) ),
			'wp_version'     => $version( $str( 'wp_version', 20 ) ),
			'php_version'    => $version( $str( 'php_version', 20 ) ),
			'channel'        => 'beta' === $channel ? 'beta' : 'stable',
		);
	}

	/**
	 * Find the license and check product; status checks are per endpoint.
	 *
	 * @param array<string, string> $input Input.
	 * @return array<string, mixed>
	 * @throws ApiError When the key or product is invalid.
	 */
	private static function load_license( array $input ): array {
		$license = KeyGenerator::is_valid_format( $input['license_key'] ) || (bool) apply_filters( 'twh_accept_key_format', false, $input['license_key'] )
			? Licenses::find_by_key( $input['license_key'] )
			: null;
		if ( ! $license ) {
			Events::log(
				'invalid_key',
				null,
				array(
					'key_last4' => substr( $input['license_key'], -4 ),
					'domain'    => $input['domain'],
				)
			);
			throw new ApiError( 'invalid_key', __( 'This license key is not valid.', 'talkwyn-hub' ) );
		}
		$product = Products::find( (int) $license['product_id'] );
		if ( ! $product || $product['slug'] !== $input['product'] ) {
			throw new ApiError( 'wrong_product', __( 'This license key is for a different product.', 'talkwyn-hub' ) );
		}
		return $license;
	}

	/**
	 * Throw unless the license can be used right now.
	 *
	 * @param array<string, mixed> $license License.
	 * @throws ApiError When not active.
	 */
	private static function assert_usable( array $license ): void {
		switch ( Licenses::effective_status( $license ) ) {
			case 'revoked':
				throw new ApiError( 'revoked', __( 'This license has been revoked.', 'talkwyn-hub' ) );
			case 'suspended':
				throw new ApiError( 'suspended', __( 'This license is suspended. Please contact support.', 'talkwyn-hub' ) );
			case 'expired':
				if ( ! empty( $license['is_trial'] ) ) {
					throw new ApiError(
						'expired',
						__( 'Your Pro trial has ended. Talkwyn keeps working on the free plan, and you can upgrade any time with the same key.', 'talkwyn-hub' ),
						array(
							'reason'      => 'trial_ended',
							'renew_url'   => \TWH\Trial\Trial::upgrade_url( $license ),
							'upgrade_url' => \TWH\Trial\Trial::upgrade_url( $license ),
						)
					);
				}
				throw new ApiError(
					'expired',
					__( 'This license has expired. Renew it to keep Pro features and updates.', 'talkwyn-hub' ),
					array( 'renew_url' => Cart::can_renew( $license ) ? Cart::renew_url( $license ) : '' )
				);
		}
	}

	/**
	 * POST license/activate.
	 *
	 * @param array<string, mixed>  $license License.
	 * @param array<string, string> $input   Input.
	 * @return array<string, mixed>
	 * @throws ApiError On failure.
	 * @throws \Throwable Database errors are rethrown after rollback.
	 */
	public static function activate( array $license, array $input ): array {
		global $wpdb;
		self::assert_usable( $license );

		$is_dev = Domain::is_dev( $input['domain'], Settings::dev_patterns() );
		$table  = Licenses::t();

		// Serialize concurrent activations of the same license (InnoDB row lock).
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d FOR UPDATE", (int) $license['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$rows     = Activations::active_for( (int) $license['id'] );
			$decision = ActivationPolicy::decide( (int) $license['activation_limit'], $rows, $input['instance_id'], $input['domain'], $is_dev );

			if ( ActivationPolicy::ACTION_DENY === $decision['action'] ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				throw new ApiError(
					'limit_reached',
					sprintf(
						/* translators: %d: activation limit */
						__( 'This license is already active on %d site(s). Deactivate one in your account or upgrade your plan.', 'talkwyn-hub' ),
						(int) $license['activation_limit']
					),
					array(
						'activations_used' => ActivationPolicy::count_used( $rows ),
						'activation_limit' => (int) $license['activation_limit'],
						'manage_url'       => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'licenses' ) : '',
					)
				);
			}

			$fields = array(
				'instance_id'       => $input['instance_id'],
				'site_url'          => $input['site_url'],
				'domain_normalized' => $input['domain'],
				'is_dev_site'       => $is_dev ? 1 : 0,
				'wp_version'        => $input['wp_version'],
				'php_version'       => $input['php_version'],
				'plugin_version'    => $input['plugin_version'],
				'last_check_at'     => Time::now_mysql(),
			);
			if ( ActivationPolicy::ACTION_REUSE === $decision['action'] ) {
				$activation_id = (int) $decision['activation_id'];
				Activations::update( $activation_id, $fields );
				$reused = true;
			} else {
				$activation_id = Activations::create( array_merge( $fields, array( 'license_id' => (int) $license['id'] ) ) );
				$reused        = false;
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( ApiError $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw $e;
		}

		Events::log(
			'activate',
			(int) $license['id'],
			array(
				'domain'  => $input['domain'],
				'dev'     => $is_dev,
				'reused'  => $reused,
				'version' => $input['plugin_version'],
			)
		);
		return self::license_data( $license, true, $is_dev );
	}

	/**
	 * POST license/deactivate. Succeeds for any existing license (even expired),
	 * so customers can always free a slot.
	 *
	 * @param array<string, mixed>  $license License.
	 * @param array<string, string> $input   Input.
	 * @return array<string, mixed>
	 */
	public static function deactivate( array $license, array $input ): array {
		$deactivated = false;
		foreach ( Activations::active_for( (int) $license['id'] ) as $row ) {
			if ( $row['instance_id'] === $input['instance_id'] ) {
				$deactivated = Activations::deactivate( (int) $row['id'] ) || $deactivated;
			}
		}
		if ( $deactivated ) {
			Events::log(
				'deactivate',
				(int) $license['id'],
				array(
					'domain' => $input['domain'],
					'by'     => 'api',
				)
			);
		}
		$data                = self::license_data( $license, false, Domain::is_dev( $input['domain'], Settings::dev_patterns() ) );
		$data['deactivated'] = $deactivated;
		return $data;
	}

	/**
	 * POST license/check: heartbeat. Never creates an activation.
	 *
	 * @param array<string, mixed>  $license License.
	 * @param array<string, string> $input   Input.
	 * @return array<string, mixed>
	 * @throws ApiError When the license is not usable.
	 */
	public static function check( array $license, array $input ): array {
		self::assert_usable( $license );
		$activation = Activations::find_active_instance( (int) $license['id'], $input['instance_id'] );
		$is_dev     = Domain::is_dev( $input['domain'], Settings::dev_patterns() );
		if ( $activation ) {
			Activations::update(
				(int) $activation['id'],
				array(
					'wp_version'     => $input['wp_version'],
					'php_version'    => $input['php_version'],
					'plugin_version' => $input['plugin_version'],
					'last_check_at'  => Time::now_mysql(),
				)
			);
			$is_dev = (bool) $activation['is_dev_site'];
		}
		Events::log(
			'check',
			(int) $license['id'],
			array(
				'domain'      => $input['domain'],
				'site_active' => (bool) $activation,
				'version'     => $input['plugin_version'],
			)
		);
		return self::license_data( $license, (bool) $activation, $is_dev );
	}

	/**
	 * POST update/check.
	 *
	 * @param array<string, mixed>  $license License.
	 * @param array<string, string> $input   Input.
	 * @return array<string, mixed>
	 * @throws ApiError For revoked/suspended licenses.
	 */
	public static function update_check( array $license, array $input ): array {
		$status = Licenses::effective_status( $license );
		if ( 'revoked' === $status || 'suspended' === $status ) {
			self::assert_usable( $license );
		}

		$product    = (array) Products::find( (int) $license['product_id'] );
		$release    = Releases::latest( (int) $license['product_id'], $input['channel'] );
		$activation = Activations::find_active_instance( (int) $license['id'], $input['instance_id'] );
		$meta       = (array) ( $product['meta'] ?? array() );

		$data = array(
			'slug'             => (string) $product['slug'],
			'name'             => (string) $product['name'],
			'homepage'         => (string) $product['homepage'],
			'new_version'      => null,
			'package'          => null,
			'requires'         => '',
			'requires_php'     => '',
			'tested'           => '',
			'last_updated'     => null,
			'changelog_html'   => '',
			'description_html' => isset( $meta['description'] ) ? wp_kses_post( Markdown::to_html( (string) $meta['description'] ) ) : '',
			'icons'            => self::image_map( $meta['icons'] ?? array() ),
			'banners'          => self::image_map( $meta['banners'] ?? array() ),
			'license_status'   => $status,
			'site_active'      => (bool) $activation,
			'renew_url'        => null,
		);

		if ( $release ) {
			$data['new_version']    = (string) $release['version'];
			$data['requires']       = (string) $release['requires_wp'];
			$data['requires_php']   = (string) $release['requires_php'];
			$data['tested']         = (string) $release['tested_wp'];
			$data['last_updated']   = Time::to_iso( (string) $release['released_at'] );
			$data['changelog_html'] = self::changelog_html( (int) $license['product_id'], $input['channel'] );

			if ( 'active' === $status && $activation ) {
				$token           = DownloadToken::create( (int) $license['id'], (int) $release['id'], Secrets::token_secret(), time() );
				$data['package'] = add_query_arg( 'token', rawurlencode( $token ), rest_url( self::NS . '/download' ) );
			}
		}
		if ( 'expired' === $status && ! empty( $license['is_trial'] ) ) {
			$data['renew_url'] = \TWH\Trial\Trial::upgrade_url( $license );
		} elseif ( 'expired' === $status && Cart::can_renew( $license ) ) {
			$data['renew_url'] = Cart::renew_url( $license );
		}

		if ( $activation ) {
			Activations::update(
				(int) $activation['id'],
				array(
					'wp_version'     => $input['wp_version'],
					'php_version'    => $input['php_version'],
					'plugin_version' => $input['plugin_version'],
					'last_check_at'  => Time::now_mysql(),
				)
			);
		}
		return $data;
	}

	/**
	 * GET download?token=…: stream the ZIP.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Only on error; success streams and exits.
	 */
	public static function download( \WP_REST_Request $request ) {
		if ( ! RateLimiter::hit( 'download', Secrets::hash_ip( Request::ip() ) ) ) {
			self::log_rate_limited( null, 'download' );
			return Responder::error( 'rate_limited', __( 'Too many requests. Please try again later.', 'talkwyn-hub' ) );
		}
		$claims = DownloadToken::validate( (string) $request->get_param( 'token' ), Secrets::token_secret(), time() );
		if ( ! $claims ) {
			return Responder::error( 'bad_request', __( 'This download link is invalid or has expired.', 'talkwyn-hub' ) );
		}
		$license = Licenses::find( $claims['license_id'] );
		$release = Releases::find( $claims['release_id'] );
		if ( ! $license || ! $release || ! (int) $release['is_active'] || (int) $release['product_id'] !== (int) $license['product_id'] ) {
			return Responder::error( 'not_found', __( 'This release is not available.', 'talkwyn-hub' ) );
		}
		$status = Licenses::effective_status( $license );
		if ( 'active' !== $status ) {
			return Responder::error( $status, __( 'Downloads require an active license.', 'talkwyn-hub' ) );
		}
		$path = Storage::path( (string) $release['zip_path'] );
		if ( null === $path || ! is_readable( $path ) ) {
			return Responder::error( 'not_found', __( 'The release file is missing.', 'talkwyn-hub' ) );
		}

		Events::log(
			'update_download',
			(int) $license['id'],
			array(
				'release_id' => (int) $release['id'],
				'version'    => (string) $release['version'],
			)
		);

		$product  = Products::find( (int) $license['product_id'] );
		$filename = ( $product ? $product['slug'] : 'plugin' ) . '-' . preg_replace( '/[^0-9A-Za-z.-]/', '', (string) $release['version'] ) . '.zip';
		self::stream( $path, $filename );
		exit;
	}

	/**
	 * Stream a file with download headers.
	 *
	 * @param string $path     Absolute path.
	 * @param string $filename Download file name.
	 */
	private static function stream( string $path, string $filename ): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:disable WordPress.WP.AlternativeFunctions -- streaming a large binary file.
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return;
		}
		while ( ! feof( $handle ) ) {
			echo fread( $handle, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file.
			flush();
		}
		fclose( $handle );
		// phpcs:enable
	}

	/**
	 * Common license payload for activate/check/deactivate.
	 *
	 * @param array<string, mixed> $license     License.
	 * @param bool                 $site_active Whether this instance is active.
	 * @param bool                 $is_dev      Whether this site is a dev site.
	 * @return array<string, mixed>
	 */
	public static function license_data( array $license, bool $site_active, bool $is_dev ): array {
		$product = Products::find( (int) $license['product_id'] );
		return array(
			'status'           => Licenses::effective_status( $license ),
			'plan'             => (string) $license['plan_slug'],
			'product'          => $product ? (string) $product['slug'] : '',
			'expires_at'       => Time::to_iso( $license['expires_at'] ),
			'activations_used' => Activations::count_used( (int) $license['id'] ),
			'activation_limit' => (int) $license['activation_limit'],
			'is_dev_site'      => $is_dev,
			'site_active'      => $site_active,
			'features'         => Licenses::features( $license ),
			'is_trial'         => ! empty( $license['is_trial'] ),
			'trial_ends_at'    => ! empty( $license['is_trial'] ) ? Time::to_iso( $license['trial_ends_at'] ) : null,
			'trial_days_left'  => ! empty( $license['is_trial'] ) ? \TWH\Trial\Trial::days_left( $license ) : null,
		);
	}

	/**
	 * Changelog of recent releases as HTML.
	 *
	 * @param int    $product_id Product id.
	 * @param string $channel    Channel.
	 */
	private static function changelog_html( int $product_id, string $channel ): string {
		$html  = '';
		$count = 0;
		foreach ( Releases::list( $product_id ) as $release ) {
			if ( ! (int) $release['is_active'] || ( 'stable' === $channel && 'beta' === $release['channel'] ) ) {
				continue;
			}
			$html .= '<h4>' . esc_html( (string) $release['version'] ) . ' <small>(' . esc_html( substr( (string) $release['released_at'], 0, 10 ) ) . ')</small></h4>';
			$html .= Markdown::to_html( (string) $release['changelog'] );
			if ( ++$count >= 10 ) {
				break;
			}
		}
		return wp_kses_post( $html );
	}

	/**
	 * Keep only string URL values for icons/banners.
	 *
	 * @param mixed $map Map.
	 * @return array<string, string>
	 */
	private static function image_map( $map ): array {
		$out = array();
		if ( is_array( $map ) ) {
			foreach ( $map as $size => $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$out[ sanitize_key( (string) $size ) ] = esc_url_raw( $url );
				}
			}
		}
		return $out;
	}

	/**
	 * Log a rate-limited request (at most once per minute per IP to avoid log floods).
	 *
	 * @param int|null $license_id License id.
	 * @param string   $scope      Scope.
	 */
	private static function log_rate_limited( ?int $license_id, string $scope ): void {
		$flag = 'twh_rl_logged_' . substr( md5( Request::ip() . $scope ), 0, 16 );
		if ( get_transient( $flag ) ) {
			return;
		}
		set_transient( $flag, 1, MINUTE_IN_SECONDS );
		Events::log( 'rate_limited', $license_id, array( 'scope' => $scope ) );
	}
}
