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
	 * How long a confirmation link works.
	 */
	public const CONFIRM_TTL = 2 * DAY_IN_SECONDS;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle_form' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_form' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( self::class, 'handle_ajax' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_twh_trial_nonce', array( self::class, 'ajax_nonce' ) );
		add_action( 'wp_ajax_twh_trial_nonce', array( self::class, 'ajax_nonce' ) );
		add_action( 'wp_loaded', array( self::class, 'handle_confirm_link' ), 25 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
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
			'captcha'          => __( 'Please complete the spam check and try again.', 'talkwyn-hub' ),
			'rate_limited'     => __( 'Too many attempts. Please wait a few minutes and try again.', 'talkwyn-hub' ),
			'confirm_sent'     => __( 'Almost there. We sent a confirmation link to your email. Open it to start your trial (check spam too).', 'talkwyn-hub' ),
			'confirm_resent'   => __( 'We already sent you a confirmation link a moment ago. Please check your inbox and spam folder.', 'talkwyn-hub' ),
			'link_expired'     => __( 'This confirmation link has expired or was already used. Please start the trial again.', 'talkwyn-hub' ),
			'mail_failed'      => __( 'We could not send the confirmation email right now. Please try again in a few minutes.', 'talkwyn-hub' ),
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
		$partner = isset( $input['partner_id'] ) ? (int) $input['partner_id'] : ( class_exists( Tracking::class ) ? Tracking::current_partner_id() : 0 );
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
			Mailer::send_trial_welcome( $license, $created['key'], sanitize_text_field( $input['name'] ), (string) ( $input['login_details'] ?? '' ) );
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
	 * @param array<string, string>|string $atts Attributes: button (label), labels ("hidden" shows placeholders only).
	 */
	public static function shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'button' => __( 'Start my free trial', 'talkwyn-hub' ),
				'labels' => 'visible',
			),
			$atts,
			self::SHORTCODE
		);
		wp_enqueue_style( 'twh-account', TWH_URL . 'assets/css/account.css', array(), TWH_VERSION );
		if ( ! self::enabled() ) {
			return '<p class="twh-trial-off">' . esc_html( self::message( 'disabled' ) ) . '</p>';
		}
		if ( 'card' === self::card_mode() && self::card_mode_ready() ) {
			$url = add_query_arg( 'add-to-cart', (int) Settings::get( 'trial_product_id' ), wc_get_checkout_url() );
			return '<p><a class="twh-btn tw-btn tw-btn--brand" href="' . esc_url( $url ) . '" data-tw-event="trial_start" data-tw-location="trial_form">' . esc_html( $atts['button'] ) . '</a></p>';
		}
		self::assets();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display of a redirect result only.
		$status = isset( $_GET['twh_trial'] ) ? sanitize_key( wp_unslash( $_GET['twh_trial'] ) ) : '';
		// phpcs:enable
		$user   = wp_get_current_user();
		$hidden = 'hidden' === $atts['labels'];
		$out    = '';
		if ( 'started' === $status ) {
			return '<div class="twh-trial-done" role="status"><p class="twh-trial-done__title">' . esc_html__( 'Your trial has started.', 'talkwyn-hub' ) . '</p><p>' . esc_html__( 'We emailed your license key and your account details. Your key is also in your account.', 'talkwyn-hub' ) . '</p>'
				. ( is_user_logged_in() ? '<p><a href="' . esc_url( wc_get_account_endpoint_url( 'licenses' ) ) . '">' . esc_html__( 'See it in your account', 'talkwyn-hub' ) . '</a></p>' : '' ) . '</div>';
		}
		if ( in_array( $status, array( 'confirm_sent', 'confirm_resent' ), true ) ) {
			return self::sent_box( self::message( $status ) );
		}
		$field = static function ( string $id, string $name, string $type, string $label, string $placeholder, string $value, string $extra ) use ( $hidden ): string {
			return '<p class="twh-field"><label for="' . esc_attr( $id ) . '"' . ( $hidden ? ' class="screen-reader-text"' : '' ) . '>' . esc_html( $label ) . '</label>'
				. '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" placeholder="' . esc_attr( $hidden ? $label : $placeholder ) . '" value="' . esc_attr( $value ) . '" required ' . $extra . '></p>';
		};
		$uid  = wp_unique_id( 'twh-trial-' );
		$out .= '<div class="twh-trial" id="' . esc_attr( $uid ) . '">';
		$out .= '<p class="twh-notice twh-notice--error" role="alert"' . ( '' === $status ? ' hidden' : '' ) . '>' . ( '' !== $status ? esc_html( self::message( $status ) ) : '' ) . '</p>';
		$out .= '<form class="twh-trial-form' . ( $hidden ? ' twh-trial-form--compact' : '' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-twh-trial novalidate>'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. wp_nonce_field( self::ACTION, '_twh_nonce', true, false )
			. '<input type="hidden" name="twh_t" value="' . esc_attr( (string) time() ) . '">'
			. '<input type="hidden" name="twh_back" value="' . esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ) . '">'
			. '<p class="twh-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'talkwyn-hub' ) . ' <input type="text" name="twh_website" tabindex="-1" autocomplete="off"></label></p>'
			. $field( $uid . '-name', 'twh_name', 'text', __( 'First name', 'talkwyn-hub' ), '', (string) $user->first_name, 'autocomplete="given-name"' )
			. $field( $uid . '-email', 'twh_email', 'email', __( 'Email', 'talkwyn-hub' ), '', (string) $user->user_email, 'autocomplete="email"' )
			. $field( $uid . '-site', 'twh_site', 'text', __( 'Website address (example.com)', 'talkwyn-hub' ), 'example.com', '', 'inputmode="url" autocomplete="url"' )
			/**
			 * Extra markup before the button, for example a spam check widget.
			 *
			 * @param string $html Markup.
			 */
			. wp_kses_post( (string) apply_filters( 'twh_trial_form_extra', '' ) )
			. '<p><button type="submit" class="twh-btn tw-btn tw-btn--brand" data-tw-event="trial_start" data-tw-location="trial_form">' . esc_html( $atts['button'] ) . '</button></p>'
			. '<p class="twh-trial-fine">' . esc_html( self::card_policy_text() ) . ' '
			/* translators: %d: trial days */
			. esc_html( sprintf( __( 'Every Pro feature for %d days on one site. We email you a link to confirm your address first.', 'talkwyn-hub' ), self::days() ) ) . '</p>'
			. '</form></div>';
		return $out;
	}

	/**
	 * Form assets. Loaded on every front-end page while trials are on, because the
	 * website's "Start free trial" popup prints the form in the footer.
	 */
	public static function assets(): void {
		if ( is_admin() || ! self::enabled() || wp_script_is( 'twh-trial', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'twh-account', TWH_URL . 'assets/css/account.css', array(), TWH_VERSION );
		wp_enqueue_script( 'twh-trial', TWH_URL . 'assets/js/trial.js', array(), TWH_VERSION, true );
		wp_localize_script(
			'twh-trial',
			'twhTrial',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'sending' => __( 'Sending...', 'talkwyn-hub' ),
				'error'   => self::message( 'spam' ),
			)
		);
	}

	/**
	 * "Check your inbox" box shown after the form.
	 *
	 * @param string $message Message.
	 */
	public static function sent_box( string $message ): string {
		return '<div class="twh-trial-done twh-trial-done--sent" role="status"><p class="twh-trial-done__title">' . esc_html__( 'Check your inbox', 'talkwyn-hub' ) . '</p><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Fresh nonce for the trial form (pages may be cached for longer than a nonce lives).
	 */
	public static function ajax_nonce(): void {
		nocache_headers();
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::ACTION ) ) );
	}

	/**
	 * Handle the start-trial form without JavaScript (redirects back with a status).
	 */
	public static function handle_form(): void {
		$back = isset( $_POST['twh_back'] ) ? esc_url_raw( wp_unslash( $_POST['twh_back'] ) ) : home_url( '/pricing/' );
		$back = wp_validate_redirect( $back, home_url( '/pricing/' ) );
		$code = self::request_trial( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside request_trial().
		wp_safe_redirect( add_query_arg( 'twh_trial', $code, $back ) . '#trial' );
		exit;
	}

	/**
	 * Handle the start-trial form over AJAX.
	 */
	public static function handle_ajax(): void {
		nocache_headers();
		$code = self::request_trial( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside request_trial().
		$ok   = in_array( $code, array( 'confirm_sent', 'confirm_resent' ), true );
		$data = array(
			'code'    => $code,
			'message' => self::message( $code ),
			'html'    => $ok ? self::sent_box( self::message( $code ) ) : '',
		);
		if ( $ok ) {
			wp_send_json_success( $data );
		}
		wp_send_json_error( $data, 'rate_limited' === $code ? 429 : 400 );
	}

	/**
	 * Step 1 of 2: check the form and email a confirmation link. No license or account yet.
	 *
	 * @param array<string, mixed> $post Raw POST data.
	 * @return string Status code (confirm_sent, confirm_resent or an error code).
	 */
	public static function request_trial( array $post ): string {
		if ( ! isset( $post['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $post['_twh_nonce'] ) ), self::ACTION ) ) {
			return 'spam';
		}
		// Honeypot and minimum fill time (bots submit instantly).
		$started = absint( $post['twh_t'] ?? 0 );
		if ( ! empty( $post['twh_website'] ) || ( $started && time() - $started < 3 ) ) {
			return 'spam';
		}
		$ip_key = 'twh_trial_rl_' . md5( Secrets::hash_ip( \TWH\Support\Request::ip() ) );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 5 ) {
			return 'rate_limited';
		}
		set_transient( $ip_key, $hits + 1, HOUR_IN_SECONDS );

		/**
		 * Extra check before a trial request is accepted, for example a captcha.
		 * Return true to accept.
		 *
		 * @param bool                 $ok   True so far.
		 * @param array<string, mixed> $post Submitted fields.
		 */
		if ( true !== apply_filters( 'twh_trial_verify', true, $post ) ) {
			return 'captcha';
		}

		$name  = sanitize_text_field( wp_unslash( $post['twh_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $post['twh_email'] ?? '' ) );
		$site  = sanitize_text_field( wp_unslash( $post['twh_site'] ?? '' ) );
		$code  = self::eligibility( $email, $site );
		if ( '' !== $code ) {
			return $code;
		}

		// One confirmation email per address every few minutes.
		$throttle = 'twh_trial_mail_' . md5( TrialPolicy::canonical_email( $email ) );
		if ( get_transient( $throttle ) ) {
			return 'confirm_resent';
		}

		$token = wp_generate_password( 40, false, false );
		set_transient(
			self::request_key( $token ),
			array(
				'name'       => $name,
				'email'      => $email,
				'site_url'   => $site,
				'partner_id' => class_exists( Tracking::class ) ? Tracking::current_partner_id() : 0,
				'back'       => isset( $post['twh_back'] ) ? esc_url_raw( wp_unslash( $post['twh_back'] ) ) : '',
				'created'    => time(),
			),
			self::CONFIRM_TTL
		);
		$url = add_query_arg( 'twh_trial_confirm', rawurlencode( $token ), home_url( '/' ) );
		if ( ! Mailer::send_trial_confirm( $email, $name, Domain::normalize( $site ), $url ) ) {
			delete_transient( self::request_key( $token ) );
			return 'mail_failed';
		}
		set_transient( $throttle, 1, 2 * MINUTE_IN_SECONDS );
		Events::log( 'trial_request', null, array( 'domain' => Domain::normalize( $site ) ), '' );
		return 'confirm_sent';
	}

	/**
	 * Storage key for a pending request. Only a hash of the token is stored.
	 *
	 * @param string $token Token from the link.
	 */
	private static function request_key( string $token ): string {
		return 'twh_trial_req_' . substr( hash( 'sha256', $token . '|' . wp_salt( 'nonce' ) ), 0, 40 );
	}

	/**
	 * Step 2 of 2: the link in the confirmation email. Creates or finds the account,
	 * issues the trial license and emails the key with login details.
	 */
	public static function handle_confirm_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the one-time token replaces a nonce so the link works from email.
		if ( empty( $_GET['twh_trial_confirm'] ) || is_admin() || wp_doing_ajax() ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['twh_trial_confirm'] ) );
		// phpcs:enable
		nocache_headers();
		$key     = self::request_key( $token );
		$request = get_transient( $key );
		$pricing = home_url( '/pricing/' );
		if ( ! is_array( $request ) ) {
			wp_safe_redirect( add_query_arg( 'twh_trial', 'link_expired', $pricing ) . '#trial' );
			exit;
		}
		delete_transient( $key ); // Single use.
		$back = wp_validate_redirect( (string) $request['back'], $pricing );

		list( $user_id, $is_new ) = self::account_for( (string) $request['email'], (string) $request['name'] );
		$details                  = self::login_details( $user_id, $is_new );

		$result = self::start(
			array(
				'name'          => (string) $request['name'],
				'email'         => (string) $request['email'],
				'site_url'      => (string) $request['site_url'],
				'user_id'       => $user_id,
				'partner_id'    => (int) $request['partner_id'],
				'login_details' => $details,
			)
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'twh_trial', $result->get_error_code(), $back ) . '#trial' );
			exit;
		}

		// A brand-new customer account was just proven by this email link: sign it in.
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( $is_new && $user && ! user_can( $user, 'edit_posts' ) && ! is_user_logged_in() ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}
		if ( is_user_logged_in() && get_current_user_id() === $user_id && function_exists( 'wc_get_account_endpoint_url' ) ) {
			wp_safe_redirect( add_query_arg( 'twh_trial', 'started', wc_get_account_endpoint_url( 'licenses' ) ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'twh_trial', 'started', $back ) . '#trial' );
		exit;
	}

	/**
	 * Find the account for a confirmed email, or create a customer account.
	 *
	 * @param string $email Confirmed email.
	 * @param string $name  First name.
	 * @return array{0: int, 1: bool} User id (0 if none could be made) and whether it is new.
	 */
	private static function account_for( string $email, string $name ): array {
		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			return array( (int) $existing->ID, false );
		}
		$base  = sanitize_user( (string) strstr( $email, '@', true ), true );
		$base  = '' !== $base ? $base : 'customer';
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . ( ++$i );
		}
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 24 ),
				'first_name' => $name,
				'role'       => get_role( 'customer' ) ? 'customer' : (string) get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			return array( 0, false );
		}
		/**
		 * Fires after a trial created a customer account.
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'twh_trial_account_created', (int) $id );
		return array( (int) $id, true );
	}

	/**
	 * Login lines for the welcome email.
	 *
	 * @param int  $user_id User id.
	 * @param bool $is_new  Whether the account was just created.
	 */
	private static function login_details( int $user_id, bool $is_new ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return '';
		}
		$account = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'licenses' ) : home_url( '/' );
		if ( ! $is_new ) {
			/* translators: 1: email, 2: URL */
			return sprintf( __( "Your key is also saved in your account (%1\$s). Log in any time: %2\$s", 'talkwyn-hub' ), $user->user_email, $account );
		}
		$reset = get_password_reset_key( $user );
		if ( is_wp_error( $reset ) ) {
			/* translators: 1: email, 2: URL */
			return sprintf( __( "We created your account with %1\$s. Use \"Lost password\" on %2\$s to set a password.", 'talkwyn-hub' ), $user->user_email, $account );
		}
		$set = function_exists( 'wc_get_page_permalink' )
			? add_query_arg(
				array(
					'key' => $reset,
					'id'  => $user->ID,
				),
				wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
			)
			: network_site_url( 'wp-login.php?action=rp&key=' . $reset . '&login=' . rawurlencode( $user->user_login ), 'login' );
		/* translators: 1: site name, 2: email, 3: set password URL, 4: account URL */
		return sprintf( __( "Your account\nWe created a %1\$s account for you. Log in with your email: %2\$s\nSet your password (link works for 24 hours): %3\$s\nAfter that, see your license, downloads and invoices at %4\$s", 'talkwyn-hub' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), $user->user_email, $set, $account );
	}
}
