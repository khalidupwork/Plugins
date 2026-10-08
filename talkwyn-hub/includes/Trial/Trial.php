<?php
/**
 * 15-day Pro trial.
 *
 * @package TalkwynHub
 */

namespace TWH\Trial;

use TWH\Domain\Domain;
use TWH\Domain\TrialPolicy;
use TWH\Email\Mailer;
use TWH\LicenseService;
use TWH\Partners\Tracking;
use TWH\Repository\Activations;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Support\Secrets;
use TWH\Support\Settings;
use TWH\Support\Time;
use TWH\Woo\Cart;
use TWH\Woo\Mapping;
use TWH\Woo\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * A trial is a normal license with is_trial = 1, trial_ends_at and a one site limit.
 *
 * Mode "none" (no card): the trial starts from a short form (name, email, site URL)
 * and expires to the free plan. Mode "card": the trial starts through checkout of a
 * WooCommerce Subscriptions product with a free trial period and renews into the
 * paid plan automatically.
 */
final class Trial {

	public const SHORTCODE = 'twh_trial_form';
	public const ACTION    = 'twh_start_trial';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle_form' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_form' ) );
		add_action( 'wp_loaded', array( self::class, 'handle_upgrade_link' ), 30 );
		add_action( 'twh_license_issued', array( self::class, 'mark_card_trial' ), 10, 2 );
		add_action( 'twh_license_renewed', array( self::class, 'end_card_trial_on_renewal' ), 5 );
		add_action( 'admin_notices', array( self::class, 'notice_card_mode' ) );
		add_filter( 'talkwyn_show_trial_cta', array( self::class, 'show_cta' ) );
	}

	/**
	 * Whether the current visitor should see "Start free trial" buttons.
	 * Visitors who are logged out always do. A logged-in customer does not once they
	 * have had a trial on their email, or already hold any license.
	 *
	 * @param bool $show Current value.
	 */
	public static function show_cta( $show = true ): bool {
		static $cache = null;
		if ( ! $show || ! self::enabled() ) {
			return false;
		}
		if ( ! is_user_logged_in() ) {
			return true;
		}
		if ( null === $cache ) {
			$user  = wp_get_current_user();
			$email = TrialPolicy::canonical_email( (string) $user->user_email );
			$cache = '' === Licenses::trial_exists( $email, '' )
				&& '' === Licenses::trial_exists( strtolower( trim( (string) $user->user_email ) ), '' )
				&& ! Licenses::for_customer( (int) $user->ID );
		}
		return $cache;
	}

	/**
	 * Whether trials are on.
	 */
	public static function enabled(): bool {
		return (bool) Settings::get( 'trial_enabled' );
	}

	/**
	 * Trial length in days.
	 */
	public static function days(): int {
		return max( 1, (int) Settings::get( 'trial_days' ) );
	}

	/**
	 * Card policy: 'none' or 'card'.
	 */
	public static function card_mode(): string {
		return 'card' === Settings::get( 'trial_card_mode' ) ? 'card' : 'none';
	}

	/**
	 * Mode "card" needs WooCommerce Subscriptions and a subscription product with a free trial.
	 */
	public static function card_mode_ready(): bool {
		if ( ! Subscriptions::active() ) {
			return false;
		}
		$product = wc_get_product( (int) Settings::get( 'trial_product_id' ) );
		if ( ! $product instanceof \WC_Product || ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}
		return \WC_Subscriptions_Product::is_subscription( $product ) && (int) \WC_Subscriptions_Product::get_trial_length( $product ) > 0;
	}

	/**
	 * Public card policy text for the website (used by the theme).
	 */
	public static function card_policy_text(): string {
		return 'card' === self::card_mode()
			/* translators: %d: trial length in days */
			? sprintf( __( 'Cancel anytime before day %d and you won\'t be charged.', 'talkwyn-hub' ), self::days() )
			: __( 'No credit card needed.', 'talkwyn-hub' );
	}

	/**
	 * Admin notice when card mode is selected but cannot work.
	 */
	public static function notice_card_mode(): void {
		if ( 'card' !== self::card_mode() || ! current_user_can( 'manage_woocommerce' ) || self::card_mode_ready() ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Talkwyn Hub trial:', 'talkwyn-hub' ) . '</strong> ' . esc_html__( 'The "Card on file" trial needs WooCommerce Subscriptions and a subscription product with a free trial period, selected in Talkwyn Hub, Settings, Trial. Until then the trial button sends people to the no-card form.', 'talkwyn-hub' ) . '</p></div>';
	}

	/**
	 * Mapping and WooCommerce product of the plan a trial includes.
	 *
	 * @return array{mapping: array<string, mixed>, wc_product: \WC_Product}|null
	 */
	public static function plan_target(): ?array {
		$software = Products::find_by_slug( (string) apply_filters( 'twh_trial_software', 'talkwyn-pro' ) );
		if ( ! $software || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$plan = (string) Settings::get( 'trial_plan' );
		foreach ( Mapping::products_for_software( (string) $software['slug'] ) as $candidate ) {
			if ( (string) $candidate['mapping']['plan_slug'] === $plan && 0 !== (int) $candidate['mapping']['duration_days'] ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * Check eligibility.
	 *
	 * @param string $email    Email.
	 * @param string $site_url Site URL.
	 * @return string '' or an error code.
	 */
	public static function eligibility( string $email, string $site_url ): string {
		if ( ! self::enabled() ) {
			return 'disabled';
		}
		$domain = Domain::normalize( $site_url );
		$used   = Licenses::trial_exists( TrialPolicy::canonical_email( $email ), $domain );
		if ( '' === $used && TrialPolicy::canonical_email( $email ) !== strtolower( trim( $email ) ) ) {
			$used = Licenses::trial_exists( strtolower( trim( $email ) ), '' );
		}
		return TrialPolicy::eligibility(
			$email,
			$domain,
			$used,
			TrialPolicy::parse_list( (string) Settings::get( 'trial_disposable' ) ),
			'' !== $domain && Domain::is_dev( $domain, Settings::dev_patterns() )
		);
	}

	/**
	 * Human message for an eligibility code.
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		$messages = array(
			'disabled'         => __( 'Trials are not available right now.', 'talkwyn-hub' ),
			'invalid_email'    => __( 'Please enter a valid email address.', 'talkwyn-hub' ),
			'disposable_email' => __( 'Please use your work or personal email. Temporary inboxes can\'t start a trial.', 'talkwyn-hub' ),
			'invalid_site'     => __( 'Please enter your live website address, like example.com. Local and staging sites are always free and don\'t need a trial.', 'talkwyn-hub' ),
			'email_used'       => __( 'This email has already used its free trial. You can pick a plan any time.', 'talkwyn-hub' ),
			'domain_used'      => __( 'This website has already had a free trial. You can pick a plan any time.', 'talkwyn-hub' ),
			'not_ready'        => __( 'Trials are being set up. Please try again soon.', 'talkwyn-hub' ),
			'spam'             => __( 'Please try again.', 'talkwyn-hub' ),
			'rate_limited'     => __( 'Too many attempts. Please wait a few minutes and try again.', 'talkwyn-hub' ),
		);
		return $messages[ $code ] ?? __( 'Something went wrong. Please try again.', 'talkwyn-hub' );
	}

	/**
	 * Start a no-card trial.
	 *
	 * @param array{name: string, email: string, site_url: string, user_id?: int} $input Input.
	 * @return array{id: int, key: string}|\WP_Error
	 */
	public static function start( array $input ) {
		$email = sanitize_email( $input['email'] );
		$code  = self::eligibility( $email, $input['site_url'] );
		if ( '' !== $code ) {
			return new \WP_Error( $code, self::message( $code ) );
		}
		$target = self::plan_target();
		$soft   = Products::find_by_slug( (string) apply_filters( 'twh_trial_software', 'talkwyn-pro' ) );
		if ( ! $soft ) {
			return new \WP_Error( 'not_ready', self::message( 'not_ready' ) );
		}
		$partner = class_exists( Tracking::class ) ? Tracking::current_partner_id() : 0;
		$ends    = time() + self::days() * DAY_IN_SECONDS;

		$created = LicenseService::issue(
			array(
				'product_id'       => (int) $soft['id'],
				'plan_slug'        => (string) Settings::get( 'trial_plan' ),
				'customer_id'      => (int) ( $input['user_id'] ?? 0 ),
				'customer_email'   => $email,
				'wc_product_id'    => $target ? $target['wc_product']->get_id() : 0,
				'activation_limit' => 1,
				'duration_days'    => $target ? (int) $target['mapping']['duration_days'] : 365,
				'features'         => $target ? (string) $target['mapping']['features'] : 'pro',
				'expires_at'       => $ends,
				'is_trial'         => 1,
				'site_domain'      => Domain::normalize( $input['site_url'] ),
				'partner_id'       => $partner,
				'notes'            => sprintf( 'Trial for %s (%s)', sanitize_text_field( $input['name'] ), esc_url_raw( $input['site_url'] ) ),
			),
			false,
			array( 'trial' => true )
		);
		Events::log(
			'trial_start',
			$created['id'],
			array(
				'domain'  => Domain::normalize( $input['site_url'] ),
				'partner' => $partner,
			)
		);
		$license = Licenses::find( $created['id'] );
		if ( $license ) {
			Mailer::send_trial_welcome( $license, $created['key'], sanitize_text_field( $input['name'] ) );
		}

		/**
		 * Fires after a trial starts.
		 *
		 * @param int $license_id License id.
		 * @param int $partner_id Referring partner id (0 for none).
		 */
		do_action( 'twh_trial_started', $created['id'], $partner );
		return $created;
	}

	/**
	 * Days left in a trial license.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function days_left( array $license ): int {
		$ends = Time::to_ts( $license['trial_ends_at'] ?? null );
		return null === $ends ? 0 : TrialPolicy::days_left( $ends, time() );
	}

	/**
	 * Whether a license is a running trial.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function is_running( array $license ): bool {
		return ! empty( $license['is_trial'] ) && 'active' === Licenses::effective_status( $license );
	}

	/**
	 * One-click upgrade link (works logged out, used in trial emails).
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $plan    Plan slug ('' = the trial plan).
	 */
	public static function upgrade_url( array $license, string $plan = '' ): string {
		$args = array(
			'twh_trial_upgrade' => (int) $license['id'],
			't'                 => self::token( (int) $license['id'] ),
		);
		if ( '' !== $plan ) {
			$args['plan'] = $plan;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * HMAC token for upgrade links.
	 *
	 * @param int $license_id License id.
	 */
	public static function token( int $license_id ): string {
		return substr( Secrets::crypto()->hmac( 'trial|' . $license_id, 'trial' ), 0, 24 );
	}

	/**
	 * Paid plans a trial can convert to.
	 *
	 * @param array<string, mixed> $license License.
	 * @return array<int, array{wc_product: \WC_Product, mapping: array<string, mixed>, price: float}>
	 */
	public static function conversion_targets( array $license ): array {
		if ( empty( $license['is_trial'] ) || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$software = Products::find( (int) $license['product_id'] );
		if ( ! $software ) {
			return array();
		}
		$out = array();
		foreach ( Mapping::products_for_software( (string) $software['slug'] ) as $candidate ) {
			$out[] = array(
				'wc_product' => $candidate['wc_product'],
				'mapping'    => $candidate['mapping'],
				'price'      => (float) $candidate['wc_product']->get_price( 'edit' ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['price'] <=> $b['price'];
			}
		);
		return $out;
	}

	/**
	 * Handle ?twh_trial_upgrade=ID&t=TOKEN[&plan=slug]: add the plan to the cart as a conversion.
	 */
	public static function handle_upgrade_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- HMAC token replaces a nonce so links work from emails.
		if ( empty( $_GET['twh_trial_upgrade'] ) || empty( $_GET['t'] ) || ! function_exists( 'WC' ) || is_admin() ) {
			return;
		}
		$license_id = absint( $_GET['twh_trial_upgrade'] );
		$token      = sanitize_text_field( wp_unslash( $_GET['t'] ) );
		$plan       = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : (string) Settings::get( 'trial_plan' );
		// phpcs:enable
		$license = Licenses::find( $license_id );
		if ( ! $license || ! hash_equals( self::token( $license_id ), $token ) ) {
			wp_safe_redirect( home_url( '/pricing/' ) );
			exit;
		}
		self::to_checkout( $license, $plan );
	}

	/**
	 * Put the conversion in the cart and go to checkout.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $plan    Plan slug.
	 */
	public static function to_checkout( array $license, string $plan ): void {
		$targets = self::conversion_targets( $license );
		$chosen  = null;
		foreach ( $targets as $t ) {
			if ( (string) $t['mapping']['plan_slug'] === $plan ) {
				$chosen = $t['wc_product'];
				break;
			}
		}
		if ( ! $chosen && $targets ) {
			$chosen = $targets[0]['wc_product'];
		}
		if ( ! $chosen || ! Cart::add_conversion( $chosen, (int) $license['id'] ) ) {
			wp_safe_redirect( home_url( '/pricing/' ) );
			exit;
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Turn a trial into a paid license (same key).
	 *
	 * @param array<string, mixed> $license       License.
	 * @param array<string, mixed> $mapping       Purchased plan mapping.
	 * @param int                  $wc_product_id Purchased product id.
	 * @param array<string, mixed> $meta          order_id, amount, currency.
	 */
	public static function convert( array $license, array $mapping, int $wc_product_id, array $meta = array() ): void {
		$duration = (int) $mapping['duration_days'];
		Licenses::update(
			(int) $license['id'],
			array(
				'is_trial'         => 0,
				'converted_at'     => time(),
				'status'           => 'active',
				'plan_slug'        => (string) $mapping['plan_slug'],
				'activation_limit' => (int) $mapping['activation_limit'],
				'duration_days'    => $duration,
				'features'         => (string) $mapping['features'],
				'wc_product_id'    => $wc_product_id,
				'expires_at'       => 0 === $duration ? null : time() + $duration * DAY_IN_SECONDS,
				'reminders_sent'   => '',
			)
		);
		Events::log( 'trial_convert', (int) $license['id'], array_merge( $meta, array( 'to_plan' => $mapping['plan_slug'] ) ), '' );

		/**
		 * Fires when a trial becomes a paid license.
		 *
		 * @param int                  $license_id License id.
		 * @param array<string, mixed> $meta       order_id, amount, currency.
		 */
		do_action( 'twh_trial_converted', (int) $license['id'], $meta );

		$fresh = Licenses::find( (int) $license['id'] );
		if ( $fresh ) {
			Mailer::send_template( 'trial_converted', $fresh );
		}
	}

	/**
	 * Daily: "N days left" emails.
	 */
	public static function cron(): int {
		$thresholds = Settings::trial_reminder_days();
		if ( ! $thresholds ) {
			return 0;
		}
		$sent = 0;
		foreach ( Licenses::active_trials() as $license ) {
			$ends = Time::to_ts( $license['trial_ends_at'] );
			if ( null === $ends || Subscriptions::auto_renews( $license ) ) {
				continue;
			}
			$already = array_filter( array_map( 'intval', explode( ',', (string) $license['trial_emails'] ) ) );
			$due     = TrialPolicy::due_reminder( $ends, time(), $thresholds, $already );
			if ( null === $due ) {
				continue;
			}
			$mark = $already;
			foreach ( $thresholds as $t ) {
				if ( $t >= $due ) {
					$mark[] = $t;
				}
			}
			Licenses::update( (int) $license['id'], array( 'trial_emails' => implode( ',', array_unique( $mark ) ) ) );
			Mailer::send_template(
				'trial_reminder',
				$license,
				array(
					'{days_left}'   => (string) TrialPolicy::days_left( $ends, time() ),
					'{trial_usage}' => self::usage_text( $license ),
				)
			);
			Events::log( 'reminder', (int) $license['id'], array( 'trial_days_left' => $due ), '' );
			++$sent;
		}
		return $sent;
	}

	/**
	 * Short usage summary for the reminder email.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function usage_text( array $license ): string {
		$sites = Activations::all_for( (int) $license['id'] );
		if ( ! $sites ) {
			return __( 'Talkwyn isn\'t connected to a site yet. Paste your key in the License tab to start.', 'talkwyn-hub' );
		}
		$domains = array();
		$last    = null;
		foreach ( $sites as $site ) {
			$domains[] = (string) $site['domain_normalized'];
			$check     = Time::to_ts( $site['last_check_at'] );
			if ( null !== $check && ( null === $last || $check > $last ) ) {
				$last = $check;
			}
		}
		return sprintf(
			/* translators: 1: site list, 2: date */
			__( 'Talkwyn Pro is running on %1$s, last seen %2$s.', 'talkwyn-hub' ),
			implode( ', ', array_unique( $domains ) ),
			null === $last ? __( 'not yet', 'talkwyn-hub' ) : wp_date( get_option( 'date_format' ), $last )
		);
	}

	/**
	 * Card mode: a license issued from the trial subscription product becomes a trial.
	 *
	 * @param int                  $license_id License id.
	 * @param array<string, mixed> $args       Creation args.
	 */
	public static function mark_card_trial( $license_id, $args ): void {
		if ( 'card' !== self::card_mode() || empty( $args['subscription_id'] ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		$subscription = wcs_get_subscription( (int) $args['subscription_id'] );
		if ( ! $subscription ) {
			return;
		}
		$trial_end = (int) $subscription->get_time( 'trial_end' );
		if ( $trial_end <= time() ) {
			return;
		}
		$order = ! empty( $args['order_id'] ) ? wc_get_order( (int) $args['order_id'] ) : null;
		Licenses::update(
			(int) $license_id,
			array(
				'is_trial'         => 1,
				'trial_ends_at'    => $trial_end,
				'expires_at'       => $trial_end,
				'activation_limit' => 1,
				'partner_id'       => $order instanceof \WC_Order ? (int) $order->get_meta( '_twh_partner_id' ) : 0,
			)
		);
		Events::log( 'trial_start', (int) $license_id, array( 'mode' => 'card' ), '' );
	}

	/**
	 * Card mode: the first paid renewal ends the trial (keeps the key).
	 *
	 * @param int $license_id License id.
	 */
	public static function end_card_trial_on_renewal( $license_id ): void {
		$license = Licenses::find( (int) $license_id );
		if ( ! $license || empty( $license['is_trial'] ) || empty( $license['subscription_id'] ) ) {
			return;
		}
		$target = null;
		$wc     = (int) $license['wc_product_id'] ? wc_get_product( (int) $license['wc_product_id'] ) : null;
		if ( $wc ) {
			$target = Mapping::for_product( $wc );
		}
		Licenses::update(
			(int) $license['id'],
			array(
				'is_trial'         => 0,
				'converted_at'     => time(),
				'activation_limit' => $target ? (int) $target['activation_limit'] : (int) $license['activation_limit'],
			)
		);
		Events::log( 'trial_convert', (int) $license['id'], array( 'mode' => 'card' ), '' );
		do_action( 'twh_trial_converted', (int) $license['id'], array( 'subscription_id' => (int) $license['subscription_id'] ) );
	}

	/**
	 * [twh_trial_form] The start-trial form (no-card mode) or a checkout button (card mode).
	 *
	 * @param array<string, string>|string $atts Attributes: button (label).
	 */
	public static function shortcode( $atts = array() ): string {
		$atts = shortcode_atts( array( 'button' => __( 'Start my free trial', 'talkwyn-hub' ) ), $atts, self::SHORTCODE );
		wp_enqueue_style( 'twh-account', TWH_URL . 'assets/css/account.css', array(), TWH_VERSION );
		if ( ! self::enabled() ) {
			return '<p class="twh-trial-off">' . esc_html( self::message( 'disabled' ) ) . '</p>';
		}
		if ( 'card' === self::card_mode() && self::card_mode_ready() ) {
			$url = add_query_arg( 'add-to-cart', (int) Settings::get( 'trial_product_id' ), wc_get_checkout_url() );
			return '<p><a class="twh-btn tw-btn tw-btn--brand" href="' . esc_url( $url ) . '" data-tw-event="trial_start" data-tw-location="trial_form">' . esc_html( $atts['button'] ) . '</a></p>';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display of a redirect result only.
		$status = isset( $_GET['twh_trial'] ) ? sanitize_key( wp_unslash( $_GET['twh_trial'] ) ) : '';
		// phpcs:enable
		$user = wp_get_current_user();
		$out  = '';
		if ( 'started' === $status ) {
			return '<div class="twh-trial-done" role="status"><p class="twh-trial-done__title">' . esc_html__( 'Your trial has started.', 'talkwyn-hub' ) . '</p><p>' . esc_html__( 'We emailed your license key and the setup steps. Check your inbox (and spam folder) in a minute.', 'talkwyn-hub' ) . '</p>'
				. ( is_user_logged_in() ? '<p><a href="' . esc_url( wc_get_account_endpoint_url( 'licenses' ) ) . '">' . esc_html__( 'See it in your account', 'talkwyn-hub' ) . '</a></p>' : '' ) . '</div>';
		}
		if ( '' !== $status ) {
			$out .= '<p class="twh-notice twh-notice--error" role="alert">' . esc_html( self::message( $status ) ) . '</p>';
		}
		$out .= '<form class="twh-trial-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. wp_nonce_field( self::ACTION, '_twh_nonce', true, false )
			. '<input type="hidden" name="twh_t" value="' . esc_attr( (string) time() ) . '">'
			. '<input type="hidden" name="twh_back" value="' . esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ) . '">'
			. '<p class="twh-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'talkwyn-hub' ) . ' <input type="text" name="twh_website" tabindex="-1" autocomplete="off"></label></p>'
			. '<p><label for="twh-trial-name">' . esc_html__( 'First name', 'talkwyn-hub' ) . '</label><input id="twh-trial-name" name="twh_name" type="text" autocomplete="given-name" required value="' . esc_attr( $user->first_name ) . '"></p>'
			. '<p><label for="twh-trial-email">' . esc_html__( 'Email', 'talkwyn-hub' ) . '</label><input id="twh-trial-email" name="twh_email" type="email" autocomplete="email" required value="' . esc_attr( $user->user_email ) . '"></p>'
			. '<p><label for="twh-trial-site">' . esc_html__( 'Website address', 'talkwyn-hub' ) . '</label><input id="twh-trial-site" name="twh_site" type="text" inputmode="url" placeholder="example.com" required></p>'
			. '<p><button type="submit" class="twh-btn tw-btn tw-btn--brand" data-tw-event="trial_start" data-tw-location="trial_form">' . esc_html( $atts['button'] ) . '</button></p>'
			. '<p class="twh-trial-fine">' . esc_html( self::card_policy_text() ) . ' '
			/* translators: %d: trial days */
			. esc_html( sprintf( __( 'Every Pro feature for %d days on one site. Then you choose: upgrade, or keep the free plan.', 'talkwyn-hub' ), self::days() ) ) . '</p>'
			. '</form>';
		return $out;
	}

	/**
	 * Handle the start-trial form.
	 */
	public static function handle_form(): void {
		$back = isset( $_POST['twh_back'] ) ? esc_url_raw( wp_unslash( $_POST['twh_back'] ) ) : home_url( '/pricing/' );
		$back = wp_validate_redirect( $back, home_url( '/pricing/' ) );
		$fail = static function ( string $code ) use ( $back ): void {
			wp_safe_redirect( add_query_arg( 'twh_trial', $code, $back ) . '#trial' );
			exit;
		};
		if ( ! isset( $_POST['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_twh_nonce'] ) ), self::ACTION ) ) {
			$fail( 'spam' );
		}
		// Honeypot and minimum fill time (bots submit instantly).
		$started = absint( $_POST['twh_t'] ?? 0 );
		if ( ! empty( $_POST['twh_website'] ) || ( $started && time() - $started < 3 ) ) {
			$fail( 'spam' );
		}
		$ip_key = 'twh_trial_rl_' . md5( Secrets::hash_ip( \TWH\Support\Request::ip() ) );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 5 ) {
			$fail( 'rate_limited' );
		}
		set_transient( $ip_key, $hits + 1, HOUR_IN_SECONDS );

		$result = self::start(
			array(
				'name'     => sanitize_text_field( wp_unslash( $_POST['twh_name'] ?? '' ) ),
				'email'    => sanitize_email( wp_unslash( $_POST['twh_email'] ?? '' ) ),
				'site_url' => sanitize_text_field( wp_unslash( $_POST['twh_site'] ?? '' ) ),
				'user_id'  => self::owner_for( sanitize_email( wp_unslash( $_POST['twh_email'] ?? '' ) ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			$fail( $result->get_error_code() );
		}
		wp_safe_redirect( add_query_arg( 'twh_trial', 'started', $back ) . '#trial' );
		exit;
	}

	/**
	 * Attach the trial to the logged-in customer only when the email is theirs.
	 * Guests receive the key by email and can add it to an account later.
	 *
	 * @param string $email Email entered.
	 */
	private static function owner_for( string $email ): int {
		$user = wp_get_current_user();
		if ( $user->ID && 0 === strcasecmp( (string) $user->user_email, $email ) ) {
			return (int) $user->ID;
		}
		return 0;
	}
}
