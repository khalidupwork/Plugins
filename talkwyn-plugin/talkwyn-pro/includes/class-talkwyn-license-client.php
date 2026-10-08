<?php
/**
 * Talkwyn License Client: drop-in SDK for the Talkwyn plugin.
 *
 * Talks to Talkwyn Hub (talkwyn-hub/v1): activation, daily checks, signed
 * responses (Ed25519), a 7-day offline grace period and automatic updates.
 *
 * Requirements: WordPress 6.4+, PHP 8.0+, sodium (bundled with PHP 7.2+ and polyfilled by WordPress).
 *
 * @package Talkwyn
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Talkwyn_License_Client' ) ) :

	/**
	 * License + update client.
	 */
	final class Talkwyn_License_Client {

		public const SDK_VERSION    = '1.1.0';
		public const GRACE_DAYS     = 7;
		public const MAX_CLOCK_SKEW = 600;

		/**
		 * Signed error codes that are a definitive answer (not a connectivity problem).
		 */
		public const DEFINITIVE_ERRORS = array( 'invalid_key', 'wrong_product', 'expired', 'revoked', 'suspended', 'limit_reached' );

		/**
		 * Configuration.
		 *
		 * @var array<string, mixed>
		 */
		private array $cfg;

		/**
		 * Constructor.
		 *
		 * @param array<string, mixed> $config {
		 *     Client configuration.
		 *
		 *     @type string   $api_url      Hub API base URL, ending in talkwyn-hub/v1/.
		 *     @type string   $product      Software product slug, e.g. talkwyn-pro.
		 *     @type string   $plugin_file  Absolute path of the main plugin file (__FILE__).
		 *     @type string   $version      Current plugin version.
		 *     @type string[] $public_keys  Trusted Ed25519 public keys (base64), keyed by key id or as a plain list.
		 *     @type string   $prefix       Option/hook prefix. Default 'talkwyn_license'.
		 *     @type string   $manage_url   Customer account URL. Default https://talkwyn.com/my-account/licenses/.
		 *     @type string   $upgrade_url  Pricing page shown to trial users. Default https://talkwyn.com/pricing/.
		 *     @type string   $channel      Update channel: stable|beta. Default stable.
		 *     @type string   $capability   Capability for license actions. Default manage_options.
		 * }
		 */
		public function __construct( array $config ) {
			$this->cfg                = array_merge(
				array(
					'api_url'     => 'https://talkwyn.com/wp-json/talkwyn-hub/v1/',
					'product'     => 'talkwyn-pro',
					'plugin_file' => '',
					'version'     => '0.0.0',
					'public_keys' => array(),
					'prefix'      => 'talkwyn_license',
					'manage_url'  => 'https://talkwyn.com/my-account/licenses/',
					'upgrade_url' => 'https://talkwyn.com/pricing/',
					'channel'     => 'stable',
					'capability'  => 'manage_options',
				),
				$config
			);
			$this->cfg['api_url']     = trailingslashit( (string) $this->cfg['api_url'] );
			$this->cfg['plugin_base'] = plugin_basename( (string) $this->cfg['plugin_file'] );
			$this->cfg['slug']        = dirname( $this->cfg['plugin_base'] );
			if ( '.' === $this->cfg['slug'] ) {
				$this->cfg['slug'] = basename( $this->cfg['plugin_base'], '.php' );
			}
		}

		/**
		 * Register hooks. Call once, e.g. on plugins_loaded.
		 */
		public function init(): void {
			$p = $this->cfg['prefix'];
			add_action( $p . '_daily_check', array( $this, 'check' ) );
			add_action( 'init', array( $this, 'schedule' ) );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_transient' ) );
			add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 10, 3 );
			add_filter( 'upgrader_pre_download', array( $this, 'refresh_package' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'admin_post_' . $p . '_action', array( $this, 'handle_form' ) );
			add_action( 'upgrader_process_complete', array( $this, 'flush_update_cache' ), 10, 0 );
			add_action( 'load-update-core.php', array( $this, 'maybe_force_check' ) );
			if ( '' !== $this->cfg['plugin_file'] ) {
				register_deactivation_hook( (string) $this->cfg['plugin_file'], array( $this, 'on_plugin_deactivate' ) );
			}
		}

		// ---------------------------------------------------------------------
		// Public helpers
		// ---------------------------------------------------------------------

		/**
		 * Whether Pro features should be enabled on this site.
		 */
		public function is_pro(): bool {
			$state = $this->state();
			if ( 'active' !== ( $state['status'] ?? '' ) || empty( $state['site_active'] ) || '' === $this->get_key() ) {
				return false;
			}
			if ( ! empty( $state['expires_at'] ) ) {
				$exp = strtotime( (string) $state['expires_at'] );
				if ( false !== $exp && $exp + self::MAX_CLOCK_SKEW < time() ) {
					return false;
				}
			}
			$last = (int) ( $state['last_check'] ?? 0 );
			return $last > 0 && ( time() - $last ) <= self::GRACE_DAYS * DAY_IN_SECONDS;
		}

		/**
		 * Plan slug (personal, business, agency, lifetime…) or '' when not Pro.
		 */
		public function get_plan(): string {
			return $this->is_pro() ? (string) ( $this->state()['plan'] ?? '' ) : '';
		}

		/**
		 * Whether the license grants a feature flag.
		 *
		 * @param string $feature Feature, e.g. "pro".
		 */
		public function has_feature( string $feature ): bool {
			return $this->is_pro() && in_array( $feature, (array) ( $this->state()['features'] ?? array() ), true );
		}

		/**
		 * Whether this key is a running Pro trial.
		 */
		public function is_trial(): bool {
			return ! empty( $this->state()['is_trial'] ) && $this->is_pro();
		}

		/**
		 * Whole days left in a running trial (0 when not in a trial).
		 */
		public function trial_days_left(): int {
			$state = $this->state();
			if ( empty( $state['is_trial'] ) || empty( $state['trial_ends_at'] ) ) {
				return 0;
			}
			$left = (int) strtotime( (string) $state['trial_ends_at'] ) - time();
			return $left > 0 ? (int) ceil( $left / DAY_IN_SECONDS ) : 0;
		}

		/**
		 * Whether the hub said this key was a trial that has ended.
		 */
		public function trial_ended(): bool {
			return ! empty( $this->state()['trial_ended'] );
		}

		/**
		 * True while the hub is unreachable but Pro is kept active.
		 */
		public function in_grace_period(): bool {
			$state = $this->state();
			$last  = (int) ( $state['last_check'] ?? 0 );
			return $this->is_pro() && ! empty( $state['offline_since'] ) && ( time() - $last ) > DAY_IN_SECONDS;
		}

		/**
		 * Stored local state.
		 *
		 * @return array<string, mixed>
		 */
		public function state(): array {
			$state = get_option( $this->cfg['prefix'] . '_state', array() );
			return is_array( $state ) ? $state : array();
		}

		// ---------------------------------------------------------------------
		// License actions
		// ---------------------------------------------------------------------

		/**
		 * Activate a key on this site.
		 *
		 * @param string $key License key.
		 * @return true|WP_Error
		 */
		public function activate( string $key ) {
			$key = strtoupper( (string) preg_replace( '/\s+/', '', $key ) );
			if ( ! preg_match( '/^[A-Z]{2,10}(-[A-Z0-9]{4}){4}$/', $key ) ) {
				return new WP_Error( 'invalid_key', __( 'Please enter a valid license key (e.g. TALK-XXXX-XXXX-XXXX-XXXX).', 'talkwyn' ) );
			}
			$res = $this->request( 'license/activate', $key );
			if ( ! $res['ok'] ) {
				if ( empty( $res['network'] ) ) {
					$this->save_state( array_merge( $this->state(), array( 'last_error' => $res['code'] ) ) );
				}
				return new WP_Error( $res['code'], $res['message'] );
			}
			update_option( $this->cfg['prefix'] . '_key', $key, false );
			$this->store_success( $res['data'] );
			$this->flush_update_cache();
			return true;
		}

		/**
		 * Deactivate this site and forget the key locally (even if the hub is unreachable).
		 *
		 * @return true|WP_Error
		 */
		public function deactivate() {
			$key = $this->get_key();
			$res = '' !== $key ? $this->request( 'license/deactivate', $key ) : array( 'ok' => true );
			delete_option( $this->cfg['prefix'] . '_key' );
			delete_option( $this->cfg['prefix'] . '_state' );
			$this->flush_update_cache();
			if ( ! $res['ok'] && ! empty( $res['network'] ) ) {
				return new WP_Error( 'network', __( 'The license was removed from this site, but the license server could not be reached. Free the slot from your account if needed.', 'talkwyn' ) );
			}
			return true;
		}

		/**
		 * Daily heartbeat. Never creates a new activation on the hub (unless the
		 * site URL changed, see below).
		 */
		public function check(): void {
			$key = $this->get_key();
			if ( '' === $key ) {
				return;
			}
			// The site moved or was cloned: get a new instance id and activate it.
			$instance = $this->instance();
			if ( $instance['url'] !== $this->site_url() ) {
				delete_option( $this->cfg['prefix'] . '_instance' );
				$result = $this->activate( $key );
				if ( is_wp_error( $result ) && in_array( $result->get_error_code(), self::DEFINITIVE_ERRORS, true ) ) {
					$state                = $this->state();
					$state['site_active'] = false;
					$this->save_state( $state );
				}
				return;
			}

			$res   = $this->request( 'license/check', $key );
			$state = $this->state();
			if ( $res['ok'] ) {
				$this->store_success( $res['data'] );
				return;
			}
			if ( ! empty( $res['network'] ) ) {
				// Hub unreachable or response not trusted: keep the last good state (grace period).
				$state['offline_since'] = $state['offline_since'] ?? time();
				$state['last_error']    = $res['code'];
				$this->save_state( $state );
				return;
			}
			// A signed, definitive answer: invalid_key, revoked, suspended, expired, wrong_product.
			$state['status']      = $res['code'];
			$state['site_active'] = false;
			$state['last_error']  = $res['code'];
			$state['renew_url']   = (string) ( $res['data']['renew_url'] ?? '' );
			// A finished trial: show "Trial ended" and an upgrade link instead of "Renew".
			$state['trial_ended'] = 'trial_ended' === ( $res['data']['reason'] ?? '' );
			$state['upgrade_url'] = (string) ( $res['data']['upgrade_url'] ?? '' );
			unset( $state['offline_since'] );
			$this->save_state( $state );
		}

		// ---------------------------------------------------------------------
		// HTTP + signature verification
		// ---------------------------------------------------------------------

		/**
		 * Signed request to the hub.
		 *
		 * @param string               $endpoint Endpoint path.
		 * @param string               $key      License key.
		 * @param array<string, mixed> $extra    Extra body fields.
		 * @return array{ok: bool, data?: array<string, mixed>, code?: string, message?: string, network?: bool}
		 */
		private function request( string $endpoint, string $key, array $extra = array() ): array {
			global $wp_version;
			$nonce    = bin2hex( random_bytes( 16 ) );
			$body     = array_merge(
				array(
					'license_key'    => $key,
					'instance_id'    => $this->instance()['id'],
					'site_url'       => $this->site_url(),
					'product'        => (string) $this->cfg['product'],
					'plugin_version' => (string) $this->cfg['version'],
					'wp_version'     => (string) $wp_version,
					'php_version'    => PHP_VERSION,
					'nonce'          => $nonce,
				),
				$extra
			);
			$response = wp_remote_post(
				$this->cfg['api_url'] . $endpoint,
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);
			$network  = array(
				'ok'      => false,
				'network' => true,
				'code'    => 'network',
				'message' => __( 'The license server could not be reached. Please try again later.', 'talkwyn' ),
			);
			if ( is_wp_error( $response ) ) {
				return $network;
			}
			$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $json ) || ! isset( $json['data'], $json['signature'] ) || ! is_array( $json['data'] ) ) {
				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( 429 === $code ) {
					$network['code']    = 'rate_limited';
					$network['message'] = __( 'Too many requests. Please wait a few minutes and try again.', 'talkwyn' );
				}
				return $network;
			}
			if ( ! $this->verify( $json['data'], (string) $json['signature'] ) ) {
				$network['code']    = 'bad_signature';
				$network['message'] = __( 'The license server response could not be verified.', 'talkwyn' );
				return $network;
			}
			$data = $json['data'];
			if ( ! isset( $data['nonce'] ) || ! hash_equals( $nonce, (string) $data['nonce'] ) ) {
				$network['code'] = 'bad_nonce';
				return $network;
			}
			if ( abs( (int) ( $data['server_time'] ?? 0 ) - time() ) > self::MAX_CLOCK_SKEW ) {
				$network['code']    = 'clock_skew';
				$network['message'] = __( 'Your server clock differs from the license server by more than 10 minutes. Please fix the server time.', 'talkwyn' );
				return $network;
			}
			if ( empty( $json['success'] ) ) {
				$code = (string) ( $json['error']['code'] ?? ( $data['error_code'] ?? 'error' ) );
				// Only trust the code inside the signed data.
				if ( isset( $data['error_code'] ) && $data['error_code'] !== $code ) {
					return $network;
				}
				if ( in_array( $code, array( 'rate_limited', 'server_error', 'bad_request' ), true ) ) {
					$network['code']    = $code;
					$network['message'] = (string) ( $json['error']['message'] ?? $network['message'] );
					return $network;
				}
				return array(
					'ok'      => false,
					'code'    => $code,
					'message' => (string) ( $json['error']['message'] ?? $code ),
					'data'    => $data,
				);
			}
			return array(
				'ok'   => true,
				'data' => $data,
			);
		}

		/**
		 * Verify an Ed25519 signature over the canonical JSON of $data against any trusted key.
		 *
		 * @param array<string, mixed> $data      Data.
		 * @param string               $signature Base64 signature.
		 */
		public function verify( array $data, string $signature ): bool {
			$sig = base64_decode( $signature, true );
			if ( false === $sig || 64 !== strlen( $sig ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return false;
			}
			$message = self::canonical_json( $data );
			foreach ( (array) $this->cfg['public_keys'] as $public ) {
				$pk = base64_decode( (string) $public, true );
				if ( false !== $pk && 32 === strlen( $pk ) ) {
					try {
						if ( sodium_crypto_sign_verify_detached( $sig, $message, $pk ) ) {
							return true;
						}
					} catch ( \Throwable $e ) {
						continue;
					}
				}
			}
			return false;
		}

		/**
		 * Canonical JSON: recursively sorted object keys, no whitespace,
		 * unescaped slashes and unicode. Must match the hub exactly.
		 *
		 * @param mixed $data Data.
		 */
		public static function canonical_json( $data ): string {
			$data = json_decode( (string) wp_json_encode( $data ), true );
			$sort = static function ( $value ) use ( &$sort ) {
				if ( ! is_array( $value ) ) {
					return $value;
				}
				$i       = 0;
				$is_list = true;
				foreach ( array_keys( $value ) as $k ) {
					if ( $k !== $i++ ) {
						$is_list = false;
						break;
					}
				}
				if ( ! $is_list ) {
					ksort( $value, SORT_STRING );
				}
				foreach ( $value as $k => $v ) {
					$value[ $k ] = $sort( $v );
				}
				return $value;
			};
			return (string) json_encode( $sort( $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		// ---------------------------------------------------------------------
		// Updates
		// ---------------------------------------------------------------------

		/**
		 * Fetch update info (cached 6h; pass $force to bypass).
		 *
		 * @param bool $force Skip the cache.
		 * @return array<string, mixed>|null
		 */
		public function update_info( bool $force = false ): ?array {
			$key = $this->get_key();
			if ( '' === $key ) {
				return null;
			}
			$cache_key = $this->cfg['prefix'] . '_update_info';
			if ( ! $force ) {
				$cached = get_transient( $cache_key );
				if ( is_array( $cached ) ) {
					return $cached;
				}
			}
			$res = $this->request( 'update/check', $key, array( 'channel' => (string) $this->cfg['channel'] ) );
			if ( ! $res['ok'] ) {
				// Cache failures briefly to avoid hammering the hub from every admin page.
				set_transient( $cache_key, array( 'error' => $res['code'] ), HOUR_IN_SECONDS );
				return null;
			}
			set_transient( $cache_key, $res['data'], 6 * HOUR_IN_SECONDS );
			return $res['data'];
		}

		/**
		 * Inject our update into the plugins update transient.
		 *
		 * @param mixed $transient Transient value.
		 * @return mixed
		 */
		public function filter_update_transient( $transient ) {
			if ( ! is_object( $transient ) ) {
				return $transient;
			}
			$info = $this->update_info();
			if ( ! $info || empty( $info['new_version'] ) ) {
				return $transient;
			}
			$item = (object) array(
				'id'           => $this->cfg['plugin_base'],
				'slug'         => $this->cfg['slug'],
				'plugin'       => $this->cfg['plugin_base'],
				'new_version'  => (string) $info['new_version'],
				'url'          => (string) ( $info['homepage'] ?? '' ),
				'package'      => (string) ( $info['package'] ?? '' ),
				'requires'     => (string) ( $info['requires'] ?? '' ),
				'requires_php' => (string) ( $info['requires_php'] ?? '' ),
				'tested'       => (string) ( $info['tested'] ?? '' ),
				'icons'        => (array) ( $info['icons'] ?? array() ),
				'banners'      => (array) ( $info['banners'] ?? array() ),
			);
			if ( empty( $info['package'] ) ) {
				$item->upgrade_notice = ! empty( $info['renew_url'] )
					? __( 'Renew your license to get this update.', 'talkwyn' )
					: __( 'Activate your license to get this update.', 'talkwyn' );
			}
			if ( version_compare( (string) $this->cfg['version'], (string) $info['new_version'], '<' ) ) {
				$transient->response[ $this->cfg['plugin_base'] ] = $item;
			} else {
				$transient->no_update[ $this->cfg['plugin_base'] ] = $item;
			}
			return $transient;
		}

		/**
		 * "View details" modal.
		 *
		 * @param false|object|array $result Result.
		 * @param string             $action Action.
		 * @param object             $args   Args.
		 * @return false|object|array
		 */
		public function filter_plugins_api( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->cfg['slug'] ) {
				return $result;
			}
			$info = $this->update_info();
			if ( ! $info ) {
				return $result;
			}
			$sections = array(
				'description' => (string) ( $info['description_html'] ?? '' ),
				'changelog'   => (string) ( $info['changelog_html'] ?? '' ),
			);
			return (object) array(
				'name'          => (string) ( $info['name'] ?? $this->cfg['slug'] ),
				'slug'          => $this->cfg['slug'],
				'version'       => (string) ( $info['new_version'] ?? $this->cfg['version'] ),
				'author'        => '<a href="https://talkwyn.com">Talkwyn</a>',
				'homepage'      => (string) ( $info['homepage'] ?? '' ),
				'requires'      => (string) ( $info['requires'] ?? '' ),
				'requires_php'  => (string) ( $info['requires_php'] ?? '' ),
				'tested'        => (string) ( $info['tested'] ?? '' ),
				'last_updated'  => (string) ( $info['last_updated'] ?? '' ),
				'download_link' => (string) ( $info['package'] ?? '' ),
				'sections'      => array_filter( $sections ),
				'banners'       => (array) ( $info['banners'] ?? array() ),
				'icons'         => (array) ( $info['icons'] ?? array() ),
			);
		}

		/**
		 * Download links expire after 10 minutes, but WordPress caches the update
		 * transient for hours. Fetch a fresh package URL right before downloading.
		 *
		 * @param bool|string|WP_Error $reply    Short-circuit value.
		 * @param string               $package  Package URL.
		 * @param WP_Upgrader          $upgrader Upgrader.
		 * @return bool|string|WP_Error
		 */
		public function refresh_package( $reply, $package, $upgrader ) {
			if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, $this->cfg['api_url'] . 'download' ) ) {
				return $reply;
			}
			$info = $this->update_info( true );
			if ( empty( $info['package'] ) ) {
				return new WP_Error( 'talkwyn_no_package', __( 'Your license does not allow this update. Please check your license.', 'talkwyn' ) );
			}
			$file = download_url( (string) $info['package'], 300 );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			if ( isset( $upgrader->skin ) && method_exists( $upgrader->skin, 'feedback' ) ) {
				$upgrader->skin->feedback( 'downloading_package', '' );
			}
			return $file;
		}

		/**
		 * "Check again" on Dashboard → Updates bypasses our cache too.
		 */
		public function maybe_force_check(): void {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by core.
			if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
				delete_transient( $this->cfg['prefix'] . '_update_info' );
			}
		}

		/**
		 * Clear the update cache.
		 */
		public function flush_update_cache(): void {
			delete_transient( $this->cfg['prefix'] . '_update_info' );
			delete_site_transient( 'update_plugins' );
		}

		// ---------------------------------------------------------------------
		// Cron
		// ---------------------------------------------------------------------

		/**
		 * Schedule the daily check.
		 */
		public function schedule(): void {
			$hook = $this->cfg['prefix'] . '_daily_check';
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + wp_rand( 60, HOUR_IN_SECONDS ), 'daily', $hook );
			}
		}

		/**
		 * Unschedule on plugin deactivation.
		 */
		public function on_plugin_deactivate(): void {
			wp_clear_scheduled_hook( $this->cfg['prefix'] . '_daily_check' );
		}

		// ---------------------------------------------------------------------
		// Admin UI
		// ---------------------------------------------------------------------

		/**
		 * Ready-made settings partial: key input, Activate/Deactivate, status, expiry, manage link.
		 * Echo it inside your settings page.
		 */
		public function render_settings(): void {
			if ( ! current_user_can( (string) $this->cfg['capability'] ) ) {
				return;
			}
			$state  = $this->state();
			$key    = $this->get_key();
			$status = '' === $key ? 'inactive' : (string) ( $state['status'] ?? 'inactive' );
			if ( 'active' === $status && empty( $state['site_active'] ) ) {
				$status = 'inactive';
			}
			$colors = array(
				'active'   => '#00a32a',
				'expired'  => '#dba617',
				'inactive' => '#787c82',
			);
			$color  = $colors[ $status ] ?? '#d63638';
			$trial  = 'active' === $status && ! empty( $state['is_trial'] );
			$ended  = 'expired' === $status && ! empty( $state['trial_ended'] );
			$labels = array(
				'active'        => __( 'Active', 'talkwyn' ),
				'inactive'      => __( 'Not activated', 'talkwyn' ),
				'expired'       => __( 'Expired', 'talkwyn' ),
				'revoked'       => __( 'Revoked', 'talkwyn' ),
				'suspended'     => __( 'Suspended', 'talkwyn' ),
				'invalid_key'   => __( 'Invalid key', 'talkwyn' ),
				'wrong_product' => __( 'Wrong product', 'talkwyn' ),
			);
			$masked = '' === $key ? '' : substr( $key, 0, 5 ) . '****-****-****-' . substr( $key, -4 );
			$action = $this->cfg['prefix'] . '_action';
			?>
			<div class="talkwyn-license-box" style="max-width:640px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 20px">
				<h2 style="margin-top:0"><?php esc_html_e( 'License', 'talkwyn' ); ?>
					<span style="display:inline-block;margin-left:8px;padding:1px 10px;border-radius:999px;font-size:12px;color:#fff;background:<?php echo esc_attr( $color ); ?>">
					<?php
					if ( $trial ) {
						/* translators: %d: days left */
						echo esc_html( sprintf( _n( 'Trial: %d day left', 'Trial: %d days left', $this->trial_days_left(), 'talkwyn' ), $this->trial_days_left() ) );
					} elseif ( $ended ) {
						esc_html_e( 'Trial ended', 'talkwyn' );
					} else {
						echo esc_html( $labels[ $status ] ?? $status );
					}
					?>
					</span>
				</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
					<?php wp_nonce_field( $action ); ?>
					<?php if ( '' === $key ) : ?>
						<p>
							<label for="talkwyn-license-key" class="screen-reader-text"><?php esc_html_e( 'License key', 'talkwyn' ); ?></label>
							<input id="talkwyn-license-key" name="license_key" type="text" class="regular-text code" placeholder="TALK-XXXX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required>
							<button type="submit" name="do" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'talkwyn' ); ?></button>
						</p>
					<?php else : ?>
						<table class="form-table" role="presentation" style="margin-top:0">
							<tr><th><?php esc_html_e( 'License key', 'talkwyn' ); ?></th><td><code><?php echo esc_html( $masked ); ?></code></td></tr>
							<?php if ( ! empty( $state['plan'] ) ) : ?>
								<tr><th><?php esc_html_e( 'Plan', 'talkwyn' ); ?></th><td><?php echo esc_html( ucfirst( (string) $state['plan'] ) ); ?></td></tr>
							<?php endif; ?>
							<tr><th><?php esc_html_e( 'Expires', 'talkwyn' ); ?></th><td>
								<?php
								echo empty( $state['expires_at'] )
									? esc_html__( 'Never (lifetime)', 'talkwyn' )
									: esc_html( wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $state['expires_at'] ) ) );
								?>
							</td></tr>
							<?php if ( isset( $state['activation_limit'] ) ) : ?>
								<tr><th><?php esc_html_e( 'Sites', 'talkwyn' ); ?></th><td>
									<?php
									echo esc_html( (int) ( $state['activations_used'] ?? 0 ) . ' / ' . ( 0 === (int) $state['activation_limit'] ? __( 'Unlimited', 'talkwyn' ) : (int) $state['activation_limit'] ) );
									if ( ! empty( $state['is_dev_site'] ) ) {
										echo ' <em>(' . esc_html__( 'this dev/staging site does not count', 'talkwyn' ) . ')</em>';
									}
									?>
								</td></tr>
							<?php endif; ?>
							<?php if ( ! empty( $state['last_check'] ) ) : ?>
								<tr><th><?php esc_html_e( 'Last check', 'talkwyn' ); ?></th><td><?php echo esc_html( human_time_diff( (int) $state['last_check'] ) . ' ' . __( 'ago', 'talkwyn' ) ); ?></td></tr>
							<?php endif; ?>
						</table>
						<p>
							<button type="submit" name="do" value="check" class="button"><?php esc_html_e( 'Check now', 'talkwyn' ); ?></button>
							<button type="submit" name="do" value="deactivate" class="button" onclick="return confirm('<?php echo esc_js( __( 'Deactivate the license on this site?', 'talkwyn' ) ); ?>');"><?php esc_html_e( 'Deactivate', 'talkwyn' ); ?></button>
							<?php if ( $trial || $ended ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( $ended && ! empty( $state['upgrade_url'] ) ? (string) $state['upgrade_url'] : (string) $this->cfg['upgrade_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to keep Pro', 'talkwyn' ); ?></a>
							<?php elseif ( ! empty( $state['renew_url'] ) && 'expired' === $status ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( (string) $state['renew_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Renew license', 'talkwyn' ); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</form>
				<p><a href="<?php echo esc_url( (string) $this->cfg['manage_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage license', 'talkwyn' ); ?> &rarr;</a></p>
			</div>
			<?php
		}

		/**
		 * Form handler for the settings partial.
		 */
		public function handle_form(): void {
			$action = $this->cfg['prefix'] . '_action';
			if ( ! current_user_can( (string) $this->cfg['capability'] ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( $action );
			$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
			$result = true;
			if ( 'activate' === $do ) {
				$result = $this->activate( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
			} elseif ( 'deactivate' === $do ) {
				$result = $this->deactivate();
			} elseif ( 'check' === $do ) {
				$this->check();
				$state  = $this->state();
				$result = ( 'active' === ( $state['status'] ?? '' ) && empty( $state['offline_since'] ) ) ? true : new WP_Error( (string) ( $state['last_error'] ?? 'error' ), $this->error_message( (string) ( $state['last_error'] ?? '' ) ) );
			}
			$notice = is_wp_error( $result ) ? array( 'error', $result->get_error_message() ) : array( 'success', __( 'License updated.', 'talkwyn' ) );
			set_transient( $this->cfg['prefix'] . '_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}

		/**
		 * Admin notices: action result, grace period, expired/revoked.
		 */
		public function admin_notices(): void {
			if ( ! current_user_can( (string) $this->cfg['capability'] ) ) {
				return;
			}
			$flash_key = $this->cfg['prefix'] . '_notice_' . get_current_user_id();
			$flash     = get_transient( $flash_key );
			if ( is_array( $flash ) ) {
				delete_transient( $flash_key );
				printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $flash[0] ), esc_html( $flash[1] ) );
			}
			if ( '' === $this->get_key() ) {
				return;
			}
			$state = $this->state();
			if ( $this->in_grace_period() ) {
				$left = self::GRACE_DAYS - (int) floor( ( time() - (int) $state['last_check'] ) / DAY_IN_SECONDS );
				printf(
					'<div class="notice notice-warning"><p><strong>Talkwyn Pro:</strong> %s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: days */
							__( 'The license server could not be reached. Pro features stay active for %d more day(s). Please make sure your site can connect to talkwyn.com.', 'talkwyn' ),
							max( 0, $left )
						)
					)
				);
				return;
			}
			$status = (string) ( $state['status'] ?? '' );
			if ( 'expired' === $status && ! empty( $state['trial_ended'] ) ) {
				printf(
					'<div class="notice notice-warning"><p><strong>Talkwyn Pro:</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
					esc_html__( 'Your Pro trial has ended. Talkwyn keeps working on the free plan. Upgrade with the same key to bring Pro back.', 'talkwyn' ),
					esc_url( ! empty( $state['upgrade_url'] ) ? (string) $state['upgrade_url'] : (string) $this->cfg['manage_url'] ),
					esc_html__( 'Upgrade', 'talkwyn' )
				);
				return;
			}
			if ( $this->is_trial() && $this->trial_days_left() <= 5 ) {
				printf(
					'<div class="notice notice-info"><p><strong>Talkwyn Pro:</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
					esc_html(
						sprintf(
							/* translators: %d: days left */
							_n( 'Your Pro trial ends in %d day. Upgrade to keep your Pro features, leads and settings.', 'Your Pro trial ends in %d days. Upgrade to keep your Pro features, leads and settings.', $this->trial_days_left(), 'talkwyn' ),
							$this->trial_days_left()
						)
					),
					esc_url( ! empty( $this->cfg['upgrade_url'] ) ? (string) $this->cfg['upgrade_url'] : (string) $this->cfg['manage_url'] ),
					esc_html__( 'See plans', 'talkwyn' )
				);
			}
			if ( in_array( $status, array( 'expired', 'revoked', 'suspended', 'invalid_key' ), true ) ) {
				$link = 'expired' === $status && ! empty( $state['renew_url'] ) ? (string) $state['renew_url'] : (string) $this->cfg['manage_url'];
				printf(
					'<div class="notice notice-error"><p><strong>Talkwyn Pro:</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
					esc_html( $this->error_message( $status ) ),
					esc_url( $link ),
					esc_html( 'expired' === $status ? __( 'Renew now', 'talkwyn' ) : __( 'Manage license', 'talkwyn' ) )
				);
			} elseif ( 'active' === $status && empty( $state['site_active'] ) ) {
				printf(
					'<div class="notice notice-warning"><p><strong>Talkwyn Pro:</strong> %s</p></div>',
					esc_html__( 'This site was deactivated from your account. Activate the license again in Talkwyn → Settings → License.', 'talkwyn' )
				);
			}
		}

		// ---------------------------------------------------------------------
		// Internals
		// ---------------------------------------------------------------------

		/**
		 * Stored key (never printed in full).
		 */
		private function get_key(): string {
			return (string) get_option( $this->cfg['prefix'] . '_key', '' );
		}

		/**
		 * Instance id + URL it was created for. Generated once per site.
		 *
		 * @return array{id: string, url: string}
		 */
		private function instance(): array {
			$opt  = $this->cfg['prefix'] . '_instance';
			$inst = get_option( $opt );
			if ( ! is_array( $inst ) || empty( $inst['id'] ) ) {
				$inst = array(
					'id'  => wp_generate_uuid4(),
					'url' => $this->site_url(),
				);
				update_option( $opt, $inst, false );
			}
			return $inst;
		}

		/**
		 * Site URL used for activation.
		 */
		private function site_url(): string {
			return untrailingslashit( home_url() );
		}

		/**
		 * Persist a successful response.
		 *
		 * @param array<string, mixed> $data Response data.
		 */
		private function store_success( array $data ): void {
			$state = array(
				'status'           => (string) ( $data['status'] ?? '' ),
				'plan'             => (string) ( $data['plan'] ?? '' ),
				'expires_at'       => $data['expires_at'] ?? null,
				'features'         => array_values( array_map( 'strval', (array) ( $data['features'] ?? array() ) ) ),
				'activations_used' => (int) ( $data['activations_used'] ?? 0 ),
				'activation_limit' => (int) ( $data['activation_limit'] ?? 0 ),
				'is_dev_site'      => ! empty( $data['is_dev_site'] ),
				'site_active'      => ! empty( $data['site_active'] ),
				'is_trial'         => ! empty( $data['is_trial'] ),
				'trial_ends_at'    => $data['trial_ends_at'] ?? null,
				'last_check'       => time(),
			);
			$this->save_state( $state );
			$instance = $this->instance();
			if ( $instance['url'] !== $this->site_url() ) {
				$instance['url'] = $this->site_url();
				update_option( $this->cfg['prefix'] . '_instance', $instance, false );
			}
		}

		/**
		 * Save state.
		 *
		 * @param array<string, mixed> $state State.
		 */
		private function save_state( array $state ): void {
			update_option( $this->cfg['prefix'] . '_state', $state, false );
		}

		/**
		 * Message for an error code.
		 *
		 * @param string $code Code.
		 */
		private function error_message( string $code ): string {
			$messages = array(
				'expired'       => __( 'Your license has expired. Renew it to keep Pro features and updates.', 'talkwyn' ),
				'revoked'       => __( 'Your license has been revoked.', 'talkwyn' ),
				'suspended'     => __( 'Your license is suspended. Please contact support.', 'talkwyn' ),
				'invalid_key'   => __( 'Your license key is not valid.', 'talkwyn' ),
				'wrong_product' => __( 'This license key is for a different product.', 'talkwyn' ),
				'limit_reached' => __( 'Your license has reached its site limit.', 'talkwyn' ),
			);
			return $messages[ $code ] ?? __( 'The license server could not be reached. Please try again later.', 'talkwyn' );
		}
	}

endif;
