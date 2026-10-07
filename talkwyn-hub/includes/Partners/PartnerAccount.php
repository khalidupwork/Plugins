<?php
/**
 * My Account → Partners.
 *
 * @package TalkwynHub
 */

namespace TWH\Partners;

use TWH\Repository\Licenses;
use TWH\Repository\Partners;
use TWH\Repository\Referrals;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Partner dashboard and join form inside WooCommerce My Account.
 */
final class PartnerAccount {

	public const ENDPOINT = 'partners';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'menu' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( self::class, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( self::class, 'render' ) );
		add_action( 'template_redirect', array( self::class, 'handle_post' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Rewrite endpoint.
	 */
	public static function endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );
	}

	/**
	 * WooCommerce query var.
	 *
	 * @param array<string, string> $vars Vars.
	 * @return array<string, string>
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Menu item (before Log out).
	 *
	 * @param array<string, string> $items Items.
	 * @return array<string, string>
	 */
	public static function menu( $items ) {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			return $items;
		}
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = __( 'Partners', 'talkwyn-hub' );
		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Title.
	 */
	public static function title(): string {
		return __( 'Talkwyn Partners', 'talkwyn-hub' );
	}

	/**
	 * Assets on the partners screen.
	 */
	public static function assets(): void {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( self::ENDPOINT ) ) {
			return;
		}
		wp_enqueue_script( 'twh-qrcode', TWH_URL . 'assets/vendor/qrcode.min.js', array(), '1.4.4', true );
		wp_enqueue_script( 'twh-partners', TWH_URL . 'assets/js/partners.js', array( 'twh-qrcode' ), TWH_VERSION, true );
	}

	/**
	 * Handle the join, payout details and code forms.
	 */
	public static function handle_post(): void {
		if ( empty( $_POST['twh_partner_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['twh_partner_action'] ) );
		if ( ! isset( $_POST['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_twh_nonce'] ) ), 'twh_partner_' . $action ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'talkwyn-hub' ), 'error' );
			return;
		}
		$user_id = get_current_user_id();
		$partner = Partners::find_by_user( $user_id );
		$back    = wc_get_account_endpoint_url( self::ENDPOINT );
		$methods = Settings::payout_methods();
		$method  = sanitize_key( wp_unslash( $_POST['payout_method'] ?? '' ) );
		$details = sanitize_textarea_field( wp_unslash( $_POST['payout_details'] ?? '' ) );

		if ( 'apply' === $action && ! $partner ) {
			if ( empty( $_POST['terms'] ) ) {
				wc_add_notice( __( 'Please accept the partner terms.', 'talkwyn-hub' ), 'error' );
				return;
			}
			$result = Program::apply(
				$user_id,
				array(
					'website'        => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
					'promotion'      => sanitize_textarea_field( wp_unslash( $_POST['promotion'] ?? '' ) ),
					'payout_method'  => isset( $methods[ $method ] ) ? $method : '',
					'payout_details' => $details,
				)
			);
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				return;
			}
			wc_add_notice( __( 'Thanks for applying. We review every application by hand and will email you soon.', 'talkwyn-hub' ) );
		} elseif ( 'payout' === $action && $partner ) {
			Partners::update(
				(int) $partner['id'],
				array(
					'payout_method'  => isset( $methods[ $method ] ) ? $method : (string) $partner['payout_method'],
					'payout_details' => $details,
				)
			);
			wc_add_notice( __( 'Payout details saved.', 'talkwyn-hub' ) );
		} elseif ( 'code' === $action && $partner ) {
			$result = Program::change_code( $partner, sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) );
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				return;
			}
			wc_add_notice( __( 'Your referral link was updated.', 'talkwyn-hub' ) );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Render the endpoint.
	 */
	public static function render(): void {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			echo '<p>' . esc_html__( 'The partner program is closed right now.', 'talkwyn-hub' ) . '</p>';
			return;
		}
		$partner = Partners::find_by_user( get_current_user_id() );
		echo '<div class="twh-partners">';
		if ( ! $partner ) {
			self::join_form();
		} elseif ( 'pending' === $partner['status'] ) {
			echo '<div class="twh-panel-card"><span class="twh-badge twh-badge--expiring">' . esc_html__( 'Pending review', 'talkwyn-hub' ) . '</span>';
			echo '<h2>' . esc_html__( 'Thanks for applying', 'talkwyn-hub' ) . '</h2><p>' . esc_html__( 'We review every application by hand, usually within two business days. You\'ll get an email as soon as you\'re approved, with your referral link.', 'talkwyn-hub' ) . '</p></div>';
		} elseif ( 'suspended' === $partner['status'] ) {
			echo '<div class="twh-panel-card"><span class="twh-badge twh-badge--expired">' . esc_html__( 'Suspended', 'talkwyn-hub' ) . '</span><p>' . esc_html__( 'Your partner account is paused. Please contact us if you think this is a mistake.', 'talkwyn-hub' ) . '</p></div>';
		} else {
			self::dashboard( $partner );
		}
		echo '</div>';
	}

	/**
	 * Join form.
	 */
	private static function join_form(): void {
		$t       = Program::terms();
		$methods = Settings::payout_methods();
		?>
		<section class="twh-panel-card twh-partner-join">
			<h2><?php esc_html_e( 'Join Talkwyn Partners', 'talkwyn-hub' ); ?></h2>
			<p><?php esc_html_e( 'Recommend Talkwyn to clients, readers or followers and earn a commission on every paid plan they buy through your link.', 'talkwyn-hub' ); ?></p>
			<ul class="twh-terms">
				<?php /* translators: %s: commission percent */ ?>
				<li><?php echo esc_html( sprintf( __( '%s commission on new paid plans', 'talkwyn-hub' ), self::pct( (float) $t['commission_rate'] ) ) ); ?></li>
				<?php if ( (float) $t['renewal_rate'] > 0 ) : ?>
					<?php /* translators: %s: commission percent */ ?>
					<li><?php echo esc_html( sprintf( __( '%s on renewals', 'talkwyn-hub' ), self::pct( (float) $t['renewal_rate'] ) ) ); ?></li>
				<?php endif; ?>
				<?php /* translators: %d: days */ ?>
				<li><?php echo esc_html( sprintf( __( '%d-day cookie, last click wins', 'talkwyn-hub' ), (int) $t['cookie_days'] ) ); ?></li>
				<?php /* translators: %s: amount */ ?>
				<li><?php echo esc_html( sprintf( __( 'Payouts from %s', 'talkwyn-hub' ), Commissions::money( (float) $t['payout_threshold'] ) ) ); ?></li>
			</ul>
			<form method="post" class="twh-form">
				<input type="hidden" name="twh_partner_action" value="apply">
				<?php wp_nonce_field( 'twh_partner_apply', '_twh_nonce' ); ?>
				<p><label for="twh-p-site"><?php esc_html_e( 'Your website or channel', 'talkwyn-hub' ); ?></label><input id="twh-p-site" name="website" type="url" placeholder="https://" required></p>
				<p><label for="twh-p-how"><?php esc_html_e( 'How will you promote Talkwyn?', 'talkwyn-hub' ); ?></label><textarea id="twh-p-how" name="promotion" rows="3" required></textarea></p>
				<p><label for="twh-p-method"><?php esc_html_e( 'Payout method', 'talkwyn-hub' ); ?></label>
					<select id="twh-p-method" name="payout_method">
					<?php
					foreach ( $methods as $slug => $label ) :
						?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
				<p><label for="twh-p-details"><?php esc_html_e( 'Payout details', 'talkwyn-hub' ); ?></label><textarea id="twh-p-details" name="payout_details" rows="2" placeholder="<?php esc_attr_e( 'PayPal or Wise email, or bank details', 'talkwyn-hub' ); ?>"></textarea><small><?php esc_html_e( 'Stored encrypted. You can change this later.', 'talkwyn-hub' ); ?></small></p>
				<p><label class="twh-check"><input type="checkbox" name="terms" value="1" required> <?php echo wp_kses_post( sprintf( /* translators: %s: link */ __( 'I agree to the <a href="%s">partner terms</a>, including no self-referrals and no paid ads on the Talkwyn brand name.', 'talkwyn-hub' ), esc_url( (string) apply_filters( 'twh_partner_terms_url', home_url( '/partners/#terms' ) ) ) ) ); ?></label></p>
				<p><button type="submit" class="button twh-btn" data-tw-event="partner_application"><?php esc_html_e( 'Apply to join', 'talkwyn-hub' ); ?></button></p>
			</form>
		</section>
		<?php
	}

	/**
	 * Approved partner dashboard.
	 *
	 * @param array<string, mixed> $partner Partner.
	 */
	private static function dashboard( array $partner ): void {
		$id      = (int) $partner['id'];
		$since30 = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$totals  = Referrals::totals( $id );
		$trials  = Licenses::referred_trials( $id );
		$refs    = Referrals::for_partner( $id );
		$link    = Program::link( $partner );
		$base    = add_query_arg( 'ref', (string) $partner['referral_code'], home_url( '/' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display filter only.
		$range     = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30;
		$range     = in_array( $range, array( 30, 90, 365 ), true ) ? $range : 30;
		$threshold = (float) Settings::get( 'partner_payout_threshold' );
		$methods   = Settings::payout_methods();
		$cards     = array(
			array( __( 'Clicks (30 days)', 'talkwyn-hub' ), number_format_i18n( Referrals::clicks_since( $id, $since30 ) ) ),
			array( __( 'Trials', 'talkwyn-hub' ), number_format_i18n( count( $trials ) ) ),
			array( __( 'Paid referrals', 'talkwyn-hub' ), number_format_i18n( count( array_filter( $refs, static fn( $r ) => 'rejected' !== $r['status'] ) ) ) ),
			array( __( 'Pending', 'talkwyn-hub' ), Commissions::money( $totals['pending'] ) ),
			array( __( 'Approved', 'talkwyn-hub' ), Commissions::money( $totals['approved'] ) ),
			array( __( 'Paid to date', 'talkwyn-hub' ), Commissions::money( $totals['paid'] ) ),
		);
		?>
		<div class="twh-stat-grid">
			<?php foreach ( $cards as $card ) : ?>
				<div class="twh-stat-card"><span class="twh-stat-card__label"><?php echo esc_html( $card[0] ); ?></span><span class="twh-stat-card__value"><?php echo esc_html( $card[1] ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<section class="twh-panel-card" aria-labelledby="twh-link-title">
			<h2 id="twh-link-title"><?php esc_html_e( 'Your referral link', 'talkwyn-hub' ); ?></h2>
			<div class="twh-linkbox">
				<div class="twh-linkbox__main">
					<div class="twh-key twh-key--full">
						<code class="twh-key-value" id="twh-ref-link"><?php echo esc_html( $link ); ?></code>
						<button type="button" class="twh-copy-btn twh-copy-text" data-copy="#twh-ref-link"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
					</div>
					<div class="twh-link-builder" data-base="<?php echo esc_url( home_url( '/' ) ); ?>" data-code="<?php echo esc_attr( (string) $partner['referral_code'] ); ?>">
						<label for="twh-link-path"><?php esc_html_e( 'Link to any page', 'talkwyn-hub' ); ?></label>
						<div class="twh-claim__row">
							<input id="twh-link-path" type="text" value="/pricing/" spellcheck="false">
							<button type="button" class="twh-copy-btn twh-copy-text" data-copy="#twh-built-link"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
						</div>
						<code class="twh-built" id="twh-built-link"><?php echo esc_html( add_query_arg( 'ref', (string) $partner['referral_code'], home_url( '/pricing/' ) ) ); ?></code>
					</div>
					<?php if ( ! (int) $partner['code_changed'] ) : ?>
						<details class="twh-code-change">
							<summary><?php esc_html_e( 'Change your link name (once)', 'talkwyn-hub' ); ?></summary>
							<form method="post" class="twh-claim__row">
								<input type="hidden" name="twh_partner_action" value="code">
								<?php wp_nonce_field( 'twh_partner_code', '_twh_nonce' ); ?>
								<label class="screen-reader-text" for="twh-code"><?php esc_html_e( 'New link name', 'talkwyn-hub' ); ?></label>
								<input id="twh-code" name="code" type="text" value="<?php echo esc_attr( (string) $partner['referral_code'] ); ?>" pattern="[a-z0-9-]{3,32}" required>
								<button type="submit" class="button twh-btn twh-btn--ghost twh-btn--sm"><?php esc_html_e( 'Save', 'talkwyn-hub' ); ?></button>
							</form>
						</details>
					<?php endif; ?>
				</div>
				<figure class="twh-qr" data-qr="<?php echo esc_url( $base ); ?>" aria-label="<?php esc_attr_e( 'QR code for your referral link', 'talkwyn-hub' ); ?>"></figure>
			</div>
		</section>

		<section class="twh-panel-card" aria-labelledby="twh-chart-title">
			<div class="twh-chart-head">
				<h2 id="twh-chart-title"><?php esc_html_e( 'Clicks and conversions', 'talkwyn-hub' ); ?></h2>
				<nav class="twh-tabs" aria-label="<?php esc_attr_e( 'Range', 'talkwyn-hub' ); ?>">
					<?php foreach ( array( 30, 90, 365 ) as $r ) : ?>
						<?php /* translators: %d: days */ ?>
						<a href="<?php echo esc_url( add_query_arg( 'range', $r, wc_get_account_endpoint_url( self::ENDPOINT ) ) ); ?>" <?php echo $r === $range ? 'aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( __( '%d days', 'talkwyn-hub' ), $r ) ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<?php echo self::chart( Referrals::series( $id, $range ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built from integers and escaped labels. ?>
		</section>

		<section class="twh-panel-card" aria-labelledby="twh-refs-title">
			<h2 id="twh-refs-title"><?php esc_html_e( 'Referrals', 'talkwyn-hub' ); ?></h2>
			<?php if ( ! $refs ) : ?>
				<p class="twh-muted"><?php esc_html_e( 'No paid referrals yet. Share your link and they show up here.', 'talkwyn-hub' ); ?></p>
			<?php else : ?>
				<div class="twh-table-scroll"><table class="twh-table">
					<thead><tr><th><?php esc_html_e( 'Date', 'talkwyn-hub' ); ?></th><th><?php esc_html_e( 'Plan', 'talkwyn-hub' ); ?></th><th><?php esc_html_e( 'Type', 'talkwyn-hub' ); ?></th><th><?php esc_html_e( 'Amount', 'talkwyn-hub' ); ?></th><th><?php esc_html_e( 'Commission', 'talkwyn-hub' ); ?></th><th><?php esc_html_e( 'Status', 'talkwyn-hub' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $refs as $r ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( $r['created_at'] . ' UTC' ) ) ); ?></td>
							<td><?php echo esc_html( \TWH\LicenseService::plan_label( (string) $r['plan_slug'] ) ); ?></td>
							<td><?php echo esc_html( 'renewal' === $r['type'] ? __( 'Renewal', 'talkwyn-hub' ) : __( 'New', 'talkwyn-hub' ) ); ?></td>
							<td><?php echo esc_html( Commissions::money( (float) $r['amount'], (string) $r['currency'] ) ); ?></td>
							<td><?php echo esc_html( Commissions::money( (float) $r['commission'], (string) $r['currency'] ) ); ?></td>
							<td><span class="twh-badge twh-badge--<?php echo esc_attr( self::status_class( (string) $r['status'] ) ); ?>"><?php echo esc_html( self::status_label( (string) $r['status'] ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>
			<?php if ( $trials ) : ?>
				<h3><?php esc_html_e( 'Referred trials', 'talkwyn-hub' ); ?></h3>
				<p class="twh-muted"><?php esc_html_e( 'Commission is created when a trial upgrades to a paid plan. For privacy, trial users are not named.', 'talkwyn-hub' ); ?></p>
				<ul class="twh-trials">
					<?php foreach ( $trials as $t ) : ?>
						<li><span><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( $t['created_at'] . ' UTC' ) ) ); ?></span>
						<span class="twh-badge twh-badge--<?php echo esc_attr( 'converted' === $t['state'] ? 'active' : ( 'running' === $t['state'] ? 'trial' : 'expired' ) ); ?>"><?php echo esc_html( 'converted' === $t['state'] ? __( 'Upgraded', 'talkwyn-hub' ) : ( 'running' === $t['state'] ? __( 'In trial', 'talkwyn-hub' ) : __( 'Ended', 'talkwyn-hub' ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="twh-panel-card" aria-labelledby="twh-payout-title">
			<h2 id="twh-payout-title"><?php esc_html_e( 'Payouts', 'talkwyn-hub' ); ?></h2>
			<p>
				<?php
				if ( $totals['approved'] >= $threshold && $totals['approved'] > 0 ) {
					/* translators: %s: amount */
					echo esc_html( sprintf( __( 'Your approved balance of %s is ready to pay out. We send payouts at the start of each month.', 'talkwyn-hub' ), Commissions::money( $totals['approved'] ) ) );
				} else {
					/* translators: 1: approved balance, 2: threshold */
					echo esc_html( sprintf( __( 'Approved balance: %1$s. Payouts go out once it reaches %2$s.', 'talkwyn-hub' ), Commissions::money( $totals['approved'] ), Commissions::money( $threshold ) ) );
				}
				?>
			</p>
			<?php $twh_payouts = Referrals::payouts( $id ); ?>
			<?php if ( $twh_payouts ) : ?>
				<ul class="twh-trials">
					<?php foreach ( $twh_payouts as $po ) : ?>
						<li><span><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( $po['created_at'] . ' UTC' ) ) ); ?></span><strong><?php echo esc_html( Commissions::money( (float) $po['amount'], (string) $po['currency'] ) ); ?></strong><span class="twh-muted"><?php echo esc_html( (string) ( $methods[ $po['method'] ] ?? $po['method'] ) . ( '' !== (string) $po['reference'] ? ' · ' . $po['reference'] : '' ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<form method="post" class="twh-form">
				<input type="hidden" name="twh_partner_action" value="payout">
				<?php wp_nonce_field( 'twh_partner_payout', '_twh_nonce' ); ?>
				<p><label for="twh-po-method"><?php esc_html_e( 'Payout method', 'talkwyn-hub' ); ?></label>
					<select id="twh-po-method" name="payout_method">
					<?php
					foreach ( $methods as $slug => $label ) :
						?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $partner['payout_method'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
				<p><label for="twh-po-details"><?php esc_html_e( 'Payout details', 'talkwyn-hub' ); ?></label><textarea id="twh-po-details" name="payout_details" rows="2"><?php echo esc_textarea( Partners::payout_details( $partner ) ); ?></textarea></p>
				<p><button type="submit" class="button twh-btn twh-btn--ghost"><?php esc_html_e( 'Save payout details', 'talkwyn-hub' ); ?></button></p>
			</form>
		</section>

		<?php self::assets_section( $partner ); ?>
		<?php
	}

	/**
	 * Marketing assets: banners and copy.
	 *
	 * @param array<string, mixed> $partner Partner.
	 */
	private static function assets_section( array $partner ): void {
		/**
		 * Banner images offered to partners. Each: url, width, height, label.
		 *
		 * @param array<int, array{url: string, width: int, height: int, label: string}> $assets Assets.
		 */
		$assets   = (array) apply_filters( 'twh_partner_assets', self::default_assets() );
		$link     = Program::link( $partner );
		$snippets = array(
			/* translators: %s: referral link */
			sprintf( __( 'I use Talkwyn to answer website visitors in their own language and capture leads overnight. Free plan, no monthly bill: %s', 'talkwyn-hub' ), $link ),
			/* translators: %s: referral link */
			sprintf( __( 'Looking for an AI chatbot for your WordPress site that speaks Arabic, Urdu, Hindi and Spanish? Try Talkwyn free: %s', 'talkwyn-hub' ), $link ),
		);
		?>
		<section class="twh-panel-card" aria-labelledby="twh-assets-title">
			<h2 id="twh-assets-title"><?php esc_html_e( 'Marketing assets', 'talkwyn-hub' ); ?></h2>
			<?php if ( $assets ) : ?>
				<div class="twh-assets">
					<?php foreach ( $assets as $a ) : ?>
						<figure><img src="<?php echo esc_url( (string) $a['url'] ); ?>" width="<?php echo (int) $a['width']; ?>" height="<?php echo (int) $a['height']; ?>" alt="<?php echo esc_attr( (string) $a['label'] ); ?>" loading="lazy"><figcaption><?php echo esc_html( (string) $a['label'] ); ?> · <a href="<?php echo esc_url( (string) $a['url'] ); ?>" download><?php esc_html_e( 'Download', 'talkwyn-hub' ); ?></a></figcaption></figure>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Copy you can use', 'talkwyn-hub' ); ?></h3>
			<?php foreach ( $snippets as $i => $snippet ) : ?>
				<div class="twh-snippet"><p id="twh-snippet-<?php echo (int) $i; ?>"><?php echo esc_html( $snippet ); ?></p><button type="button" class="twh-copy-btn twh-copy-text" data-copy="#twh-snippet-<?php echo (int) $i; ?>"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button></div>
			<?php endforeach; ?>
			<p class="twh-muted"><?php echo wp_kses_post( sprintf( /* translators: %s: link */ __( 'Please follow our <a href="%s">brand rules</a>: use the logo as provided, write "Talkwyn" with a capital T, and don\'t bid on the Talkwyn name in search ads.', 'talkwyn-hub' ), esc_url( (string) apply_filters( 'twh_partner_brand_url', home_url( '/partners/#brand' ) ) ) ) ); ?></p>
		</section>
		<?php
	}

	/**
	 * Default banners from the active theme's brand folder (Talkwyn theme), if present.
	 *
	 * @return array<int, array{url: string, width: int, height: int, label: string}>
	 */
	private static function default_assets(): array {
		$out   = array();
		$files = array(
			'og-image-1200x630.png'       => array( 1200, 630, __( 'Social card 1200 × 630', 'talkwyn-hub' ) ),
			'twitter-header-1500x500.png' => array( 1500, 500, __( 'Banner 1500 × 500', 'talkwyn-hub' ) ),
			'linkedin-cover-1584x396.png' => array( 1584, 396, __( 'Banner 1584 × 396', 'talkwyn-hub' ) ),
		);
		foreach ( $files as $file => $meta ) {
			$path = get_stylesheet_directory() . '/assets/brand/social/' . $file;
			if ( file_exists( $path ) ) {
				$out[] = array(
					'url'    => get_stylesheet_directory_uri() . '/assets/brand/social/' . $file,
					'width'  => $meta[0],
					'height' => $meta[1],
					'label'  => $meta[2],
				);
			}
		}
		return $out;
	}

	/**
	 * Lightweight SVG chart: click bars and a conversions line.
	 *
	 * @param array<string, array{clicks: int, conversions: int}> $series Series.
	 */
	public static function chart( array $series ): string {
		$w   = 720;
		$h   = 200;
		$pad = 28;
		$n   = max( 1, count( $series ) );
		$max = 1;
		foreach ( $series as $p ) {
			$max = max( $max, $p['clicks'], $p['conversions'] );
		}
		$bw    = ( $w - 2 * $pad ) / $n;
		$bars  = '';
		$line  = array();
		$i     = 0;
		$total = array(
			'clicks'      => 0,
			'conversions' => 0,
		);
		foreach ( $series as $label => $p ) {
			$x                     = $pad + $i * $bw;
			$bh                    = ( $h - 2 * $pad ) * $p['clicks'] / $max;
			$bars                 .= sprintf( '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" class="twh-chart__bar"><title>%s: %d</title></rect>', $x + $bw * 0.15, $h - $pad - $bh, max( 1, $bw * 0.7 ), $bh, esc_html( (string) $label ), $p['clicks'] );
			$line[]                = sprintf( '%.1f,%.1f', $x + $bw / 2, $h - $pad - ( $h - 2 * $pad ) * $p['conversions'] / $max );
			$total['clicks']      += $p['clicks'];
			$total['conversions'] += $p['conversions'];
			++$i;
		}
		$keys  = array_keys( $series );
		$title = sprintf(
			/* translators: 1: clicks, 2: conversions */
			__( '%1$d clicks and %2$d conversions in this range.', 'talkwyn-hub' ),
			$total['clicks'],
			$total['conversions']
		);
		return '<figure class="twh-chart"><svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( $title ) . '" preserveAspectRatio="none">'
			. '<line x1="' . $pad . '" y1="' . ( $h - $pad ) . '" x2="' . ( $w - $pad ) . '" y2="' . ( $h - $pad ) . '" class="twh-chart__axis"/>'
			. $bars
			. '<polyline points="' . esc_attr( implode( ' ', $line ) ) . '" class="twh-chart__line"/>'
			. '<text x="' . $pad . '" y="' . ( $h - 8 ) . '" class="twh-chart__label">' . esc_html( (string) reset( $keys ) ) . '</text>'
			. '<text x="' . ( $w - $pad ) . '" y="' . ( $h - 8 ) . '" text-anchor="end" class="twh-chart__label">' . esc_html( (string) end( $keys ) ) . '</text>'
			. '</svg><figcaption><span class="twh-legend twh-legend--bar">' . esc_html__( 'Clicks', 'talkwyn-hub' ) . '</span> <span class="twh-legend twh-legend--line">' . esc_html__( 'Conversions', 'talkwyn-hub' ) . '</span> · ' . esc_html( $title ) . '</figcaption></figure>';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'pending'   => __( 'Pending', 'talkwyn-hub' ),
			'approved'  => __( 'Approved', 'talkwyn-hub' ),
			'paid'      => __( 'Paid', 'talkwyn-hub' ),
			'rejected'  => __( 'Rejected', 'talkwyn-hub' ),
			'suspended' => __( 'Suspended', 'talkwyn-hub' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Badge modifier for a status.
	 *
	 * @param string $status Status.
	 */
	public static function status_class( string $status ): string {
		$map = array(
			'pending'  => 'expiring',
			'approved' => 'active',
			'paid'     => 'lifetime',
			'rejected' => 'expired',
		);
		return $map[ $status ] ?? 'dev';
	}

	/**
	 * Percent string without trailing zeros.
	 *
	 * @param float $v Value.
	 */
	public static function pct( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ) . '%';
	}
}
