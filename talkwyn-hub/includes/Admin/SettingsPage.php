<?php
/**
 * Settings screen.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Domain\Domain;
use TWH\Email\Mailer;
use TWH\Support\Secrets;
use TWH\Support\Settings;
use TWH\Support\SigningKeys;
use TWH\Support\Storage;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every admin-post handler calls Admin::guard() (capability + check_admin_referer) before reading input; an id needed to build the nonce action is read with absint() first.

/**
 * Settings: emails, reminders, renewal discount, rate limits, dev domains,
 * log retention, signing keys, uninstall behavior.
 */
final class SettingsPage {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_save_settings', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_twh_signing_keys', array( self::class, 'handle_keys' ) );
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		$s     = Settings::all();
		$keys  = SigningKeys::all();
		$texts = self::email_types();
		$plans = array(
			'personal' => __( 'Personal', 'talkwyn-hub' ),
			'business' => __( 'Business', 'talkwyn-hub' ),
			'agency'   => __( 'Agency', 'talkwyn-hub' ),
		);
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Settings', 'talkwyn-hub' ); ?></h1>
			<nav class="twh-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'talkwyn-hub' ); ?>">
				<?php
				foreach ( array(
					'general'  => __( 'General', 'talkwyn-hub' ),
					'trial'    => __( 'Trial', 'talkwyn-hub' ),
					'partners' => __( 'Partners', 'talkwyn-hub' ),
					'invoices' => __( 'Invoices', 'talkwyn-hub' ),
					'emails'   => __( 'Emails', 'talkwyn-hub' ),
					'keys'     => __( 'Signing keys', 'talkwyn-hub' ),
					'status'   => __( 'System status', 'talkwyn-hub' ),
				) as $twh_tab => $twh_label ) :
					?>
					<button type="button" class="twh-tabs__item" role="tab" data-tab="<?php echo esc_attr( $twh_tab ); ?>"><?php echo esc_html( $twh_label ); ?></button>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="twh_save_settings">
				<input type="hidden" name="twh_tab" value="general">
				<?php wp_nonce_field( 'twh_save_settings' ); ?>

				<section class="twh-tabpanel" data-tab="general">
				<h2><?php esc_html_e( 'Renewals & reminders', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="twh-reminder-days"><?php esc_html_e( 'Reminder days', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-reminder-days" name="reminder_days" type="text" value="<?php echo esc_attr( (string) $s['reminder_days'] ); ?>">
						<p class="description"><?php esc_html_e( 'Comma-separated days before expiry, e.g. 30,7. An "expired" email is always sent on expiry.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-discount"><?php esc_html_e( 'Renewal discount (%)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-discount" name="renewal_discount" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( (string) $s['renewal_discount'] ); ?>">
						<p class="description"><?php esc_html_e( 'Applied to manual renewals (when WooCommerce Subscriptions is not used).', 'talkwyn-hub' ); ?></p></td></tr>
				</table>

				</section>

				<section class="twh-tabpanel" data-tab="trial">
				<h2 id="trial"><?php esc_html_e( 'Trial', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Free Pro trial', 'talkwyn-hub' ); ?></th>
						<td><label><input name="trial_enabled" type="checkbox" value="1" <?php checked( (int) $s['trial_enabled'], 1 ); ?>> <?php esc_html_e( 'Let new customers try Pro for free', 'talkwyn-hub' ); ?></label>
						<p class="description"><?php esc_html_e( 'Put the [twh_trial_form] shortcode on your pricing page. One trial per email and per website.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-trial-days"><?php esc_html_e( 'Trial length (days)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-trial-days" name="trial_days" type="number" min="1" max="90" value="<?php echo (int) $s['trial_days']; ?>" class="small-text"></td></tr>
					<tr><th><label for="twh-trial-plan"><?php esc_html_e( 'Plan the trial includes', 'talkwyn-hub' ); ?></label></th>
						<td><select id="twh-trial-plan" name="trial_plan">
						<?php
						foreach ( $plans as $slug => $label ) :
							?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $s['trial_plan'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
						<p class="description"><?php esc_html_e( 'Features come from that plan. Trials are always limited to one site.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Card policy', 'talkwyn-hub' ); ?></th>
						<td><fieldset>
							<label><input type="radio" name="trial_card_mode" value="none" <?php checked( (string) $s['trial_card_mode'], 'none' ); ?>> <?php esc_html_e( 'No card required: the trial starts from a short form (name, email, website) and ends on the free plan.', 'talkwyn-hub' ); ?></label><br>
							<label><input type="radio" name="trial_card_mode" value="card" <?php checked( (string) $s['trial_card_mode'], 'card' ); ?>> <?php esc_html_e( 'Card on file: the trial starts at checkout with a $0 first period and converts to paid automatically. Needs WooCommerce Subscriptions.', 'talkwyn-hub' ); ?></label>
						</fieldset>
						<?php if ( 'card' === $s['trial_card_mode'] && ! \TWH\Trial\Trial::card_mode_ready() ) : ?>
							<p class="description twh-status-bad"><?php esc_html_e( 'Not ready: activate WooCommerce Subscriptions and choose a subscription product with a free trial below.', 'talkwyn-hub' ); ?></p>
						<?php endif; ?></td></tr>
					<tr><th><label for="twh-trial-product"><?php esc_html_e( 'Trial product (card mode)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-trial-product" name="trial_product_id" type="number" min="0" value="<?php echo (int) $s['trial_product_id']; ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'ID of a WooCommerce Subscriptions product (or variation) with a free trial period. Ignored in no-card mode.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-trial-reminders"><?php esc_html_e( 'Reminder emails', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-trial-reminders" name="trial_reminder_days" type="text" value="<?php echo esc_attr( (string) $s['trial_reminder_days'] ); ?>">
						<p class="description"><?php esc_html_e( 'Days left when a reminder is sent, comma separated. 5,2 means day 10 and day 13 of a 15-day trial. A welcome email goes out on day 0 and a "trial ended" email on the last day.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-trial-disposable"><?php esc_html_e( 'Blocked email domains', 'talkwyn-hub' ); ?></label></th>
						<td><textarea id="twh-trial-disposable" name="trial_disposable" rows="6" class="large-text code"><?php echo esc_textarea( (string) $s['trial_disposable'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Disposable email services, one per line. Subdomains are blocked too.', 'talkwyn-hub' ); ?></p></td></tr>
				</table>

				</section>

				<section class="twh-tabpanel" data-tab="partners">
				<h2 id="partners"><?php esc_html_e( 'Partners', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Talkwyn Partners', 'talkwyn-hub' ); ?></th>
						<td><label><input name="partners_enabled" type="checkbox" value="1" <?php checked( (int) $s['partners_enabled'], 1 ); ?>> <?php esc_html_e( 'Run the referral program (My Account, Partners)', 'talkwyn-hub' ); ?></label></td></tr>
					<tr><th><label for="twh-rate-new"><?php esc_html_e( 'Commission on new sales (%)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-rate-new" name="partner_rate_new" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( (string) $s['partner_rate_new'] ); ?>" class="small-text"></td></tr>
					<tr><th><label for="twh-rate-renewal"><?php esc_html_e( 'Commission on renewals (%)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-rate-renewal" name="partner_rate_renewal" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( (string) $s['partner_rate_renewal'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( '0 pays on the first payment only.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-cookie"><?php esc_html_e( 'Cookie (days)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-cookie" name="partner_cookie_days" type="number" min="1" max="365" value="<?php echo (int) $s['partner_cookie_days']; ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Attribution is last click: the newest referral link (or partner coupon) wins.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-approval"><?php esc_html_e( 'Approve commissions after (days)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-approval" name="partner_approval_days" type="number" min="0" max="365" value="<?php echo (int) $s['partner_approval_days']; ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Match your refund window. Fully refunded or charged-back orders reject the commission automatically.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-threshold"><?php esc_html_e( 'Payout threshold', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-threshold" name="partner_payout_threshold" type="number" min="0" step="0.01" value="<?php echo esc_attr( (string) $s['partner_payout_threshold'] ); ?>" class="small-text"> <?php echo esc_html( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Payout methods', 'talkwyn-hub' ); ?></th>
						<td><?php $twh_methods = array_filter( array_map( 'trim', explode( ',', (string) $s['partner_methods'] ) ) ); ?>
						<?php
						foreach ( array(
							'paypal'   => 'PayPal',
							'wise'     => 'Wise',
							'bank'     => __( 'Bank transfer', 'talkwyn-hub' ),
							'payoneer' => 'Payoneer',
						) as $slug => $label ) :
							?>
							<label style="margin-inline-end:16px"><input type="checkbox" name="partner_methods[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $twh_methods, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?></td></tr>
					<tr><th><?php esc_html_e( 'Links', 'talkwyn-hub' ); ?></th>
						<td><label><input name="partner_pretty_links" type="checkbox" value="1" <?php checked( (int) $s['partner_pretty_links'], 1 ); ?>> <?php echo esc_html( sprintf( /* translators: %s: example URL */ __( 'Also accept short links like %s', 'talkwyn-hub' ), home_url( '/r/code' ) ) ); ?></label><br>
						<label><input name="partner_respect_consent" type="checkbox" value="1" <?php checked( (int) $s['partner_respect_consent'], 1 ); ?>> <?php esc_html_e( 'Only set the referral cookie after cookie consent when a consent plugin is active', 'talkwyn-hub' ); ?></label></td></tr>
				</table>

				</section>

				<section class="twh-tabpanel" data-tab="invoices">
				<h2 id="invoices"><?php esc_html_e( 'Invoices', 'talkwyn-hub' ); ?></h2>
				<p class="twh-panel-intro"><?php esc_html_e( 'Every paid order gets the next invoice number. Customers open a printable invoice from My Account, Orders and from their order email, and can save it as a PDF.', 'talkwyn-hub' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="twh-inv-prefix"><?php esc_html_e( 'Number prefix', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-inv-prefix" name="invoice_prefix" type="text" value="<?php echo esc_attr( (string) $s['invoice_prefix'] ); ?>" class="small-text">
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: example number */ __( 'Next number looks like %s.', 'talkwyn-hub' ), \TWH\Woo\Invoices::format_number( (int) get_option( \TWH\Woo\Invoices::COUNTER, 0 ) + 1, (string) $s['invoice_prefix'] ) ) ); ?></p></td></tr>
					<tr><th><label for="twh-inv-company"><?php esc_html_e( 'Company name', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-inv-company" name="invoice_company" type="text" value="<?php echo esc_attr( (string) $s['invoice_company'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th><label for="twh-inv-address"><?php esc_html_e( 'Company address', 'talkwyn-hub' ); ?></label></th>
						<td><textarea id="twh-inv-address" name="invoice_address" rows="3" class="large-text"><?php echo esc_textarea( (string) $s['invoice_address'] ); ?></textarea></td></tr>
					<tr><th><label for="twh-inv-tax"><?php esc_html_e( 'Tax or VAT ID', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-inv-tax" name="invoice_tax_id" type="text" value="<?php echo esc_attr( (string) $s['invoice_tax_id'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="twh-inv-email"><?php esc_html_e( 'Billing email', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-inv-email" name="invoice_email" type="email" value="<?php echo esc_attr( (string) $s['invoice_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"></td></tr>
					<tr><th><label for="twh-inv-note"><?php esc_html_e( 'Note at the bottom', 'talkwyn-hub' ); ?></label></th>
						<td><textarea id="twh-inv-note" name="invoice_note" rows="2" class="large-text"><?php echo esc_textarea( (string) $s['invoice_note'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'For example payment terms or a thank-you line.', 'talkwyn-hub' ); ?></p></td></tr>
				</table>

				</section>

				<section class="twh-tabpanel" data-tab="general">
				<h2><?php esc_html_e( 'API and domains', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Rate limit', 'talkwyn-hub' ); ?></th>
						<td><input name="rate_limit_requests" type="number" min="1" value="<?php echo (int) $s['rate_limit_requests']; ?>" class="small-text">
						<?php esc_html_e( 'requests per', 'talkwyn-hub' ); ?>
						<input name="rate_limit_window" type="number" min="1" value="<?php echo (int) $s['rate_limit_window']; ?>" class="small-text">
						<?php esc_html_e( 'minutes, per IP and per license key', 'talkwyn-hub' ); ?></td></tr>
					<tr><th><label for="twh-dev"><?php esc_html_e( 'Dev/staging domains', 'talkwyn-hub' ); ?></label></th>
						<td><textarea id="twh-dev" name="dev_domains" rows="8" class="large-text code"><?php echo esc_textarea( (string) $s['dev_domains'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One pattern per line; * matches anything. These sites never count toward the activation limit. IP addresses always count as dev. Developers can also use the twh_dev_domain_patterns filter.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-retention"><?php esc_html_e( 'Log retention (days)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-retention" name="log_retention_days" type="number" min="0" value="<?php echo (int) $s['log_retention_days']; ?>" class="small-text">
						<p class="description"><?php esc_html_e( '0 keeps logs forever.', 'talkwyn-hub' ); ?></p></td></tr>
				</table>

				</section>

				<section class="twh-tabpanel" data-tab="emails">
				<h2><?php esc_html_e( 'Emails', 'talkwyn-hub' ); ?></h2>
				<p class="twh-panel-intro"><?php esc_html_e( 'Emails use the branded Talkwyn layout (override it from your theme at talkwyn-hub/emails/branded.php). You can use these placeholders in any subject or text:', 'talkwyn-hub' ); ?></p>
				<p class="twh-placeholders">
					<?php foreach ( Mailer::placeholders() as $twh_ph ) : ?>
						<code class="twh-copyable"><?php echo esc_html( $twh_ph ); ?></code>
					<?php endforeach; ?>
				</p>
				<table class="form-table" role="presentation">
					<tr><th><label for="twh-from"><?php esc_html_e( 'From name', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-from" name="email_from_name" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['email_from_name'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th><label for="twh-admin-email"><?php esc_html_e( 'Admin notifications to', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-admin-email" name="admin_notify_email" type="email" class="regular-text" value="<?php echo esc_attr( (string) $s['admin_notify_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"></td></tr>
				</table>
				<div class="twh-emails">
					<?php foreach ( $texts as $type => $label ) : ?>
						<details class="twh-email">
							<summary><span><?php echo esc_html( $label ); ?></span><span class="twh-email__subject"><?php echo esc_html( (string) $s[ 'email_' . $type . '_subject' ] ); ?></span></summary>
							<div class="twh-email__body">
								<label for="twh-email-<?php echo esc_attr( $type ); ?>-subject"><?php esc_html_e( 'Subject', 'talkwyn-hub' ); ?></label>
								<input id="twh-email-<?php echo esc_attr( $type ); ?>-subject" name="email_<?php echo esc_attr( $type ); ?>_subject" type="text" class="large-text" value="<?php echo esc_attr( (string) $s[ 'email_' . $type . '_subject' ] ); ?>">
								<label for="twh-email-<?php echo esc_attr( $type ); ?>-body"><?php esc_html_e( 'Text', 'talkwyn-hub' ); ?></label>
								<textarea id="twh-email-<?php echo esc_attr( $type ); ?>-body" name="email_<?php echo esc_attr( $type ); ?>_body" rows="8" class="large-text"><?php echo esc_textarea( (string) $s[ 'email_' . $type . '_body' ] ); ?></textarea>
							</div>
						</details>
					<?php endforeach; ?>
				</div>
				</section>

				<section class="twh-tabpanel" data-tab="general">
				<h2><?php esc_html_e( 'Uninstall', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Data', 'talkwyn-hub' ); ?></th>
						<td><label><input name="delete_on_uninstall" type="checkbox" value="1" <?php checked( (int) $s['delete_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Delete all data on uninstall (licenses, activations, releases, logs, signing keys, settings and release ZIPs). This cannot be undone.', 'talkwyn-hub' ); ?></label></td></tr>
				</table>
				</section>
				<div class="twh-savebar"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'talkwyn-hub' ); ?></button></div>
			</form>

			<section class="twh-tabpanel" data-tab="keys">
			<h2><?php esc_html_e( 'Signing keys (Ed25519)', 'talkwyn-hub' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Embed the public key(s) in the client plugin. To rotate: 1) generate the next key, 2) ship a client release that trusts both keys, 3) once most sites have updated, promote the next key.', 'talkwyn-hub' ); ?></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Key ID', 'talkwyn-hub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'talkwyn-hub' ); ?></th>
					<th><?php esc_html_e( 'Public key (base64)', 'talkwyn-hub' ); ?></th>
					<th><?php esc_html_e( 'Created', 'talkwyn-hub' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $keys as $key ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) $key['kid'] ); ?></code></td>
						<td><span class="twh-badge twh-badge--<?php echo esc_attr( 'active' === $key['status'] ? 'active' : ( 'next' === $key['status'] ? 'dev' : 'expired' ) ); ?>"><?php echo esc_html( (string) $key['status'] ); ?></span></td>
						<td><code class="twh-copyable"><?php echo esc_html( (string) $key['public'] ); ?></code></td>
						<td><?php echo esc_html( wp_date( 'Y-m-d', (int) $key['created_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="twh-confirm-form" style="margin-top:12px">
				<input type="hidden" name="action" value="twh_signing_keys">
				<?php wp_nonce_field( 'twh_signing_keys' ); ?>
				<button type="submit" name="do" value="generate_next" class="button"><?php esc_html_e( 'Generate next key', 'talkwyn-hub' ); ?></button>
				<?php if ( SigningKeys::find( 'next' ) ) : ?>
					<button type="submit" name="do" value="promote_next" class="button button-primary"><?php esc_html_e( 'Promote next key to active', 'talkwyn-hub' ); ?></button>
				<?php endif; ?>
			</form>

			</section>

			<section class="twh-tabpanel" data-tab="status">
			<h2><?php esc_html_e( 'System status', 'talkwyn-hub' ); ?></h2>
			<table class="widefat striped twh-status">
				<tr><td><?php esc_html_e( 'TWH_SECRET_KEY defined', 'talkwyn-hub' ); ?></td><td><?php echo Secrets::has_constant() ? '<span class="twh-status-ok">' . esc_html__( 'Yes', 'talkwyn-hub' ) . '</span>' : '<span class="twh-status-bad">' . esc_html__( 'No, using the WordPress salt fallback', 'talkwyn-hub' ) . '</span>'; ?></td></tr>
				<tr><td><?php esc_html_e( 'Release directory', 'talkwyn-hub' ); ?></td><td><code><?php echo esc_html( Storage::dir() ); ?></code> <?php echo Storage::ensure_dir() ? '<span class="twh-status-ok">' . esc_html__( 'Writable', 'talkwyn-hub' ) . '</span>' : '<span class="twh-status-bad">' . esc_html__( 'Not writable', 'talkwyn-hub' ) . '</span>'; ?></td></tr>
				<tr><td><?php esc_html_e( 'WooCommerce Subscriptions', 'talkwyn-hub' ); ?></td><td><?php echo \TWH\Woo\Subscriptions::active() ? esc_html__( 'Active, automatic renewals', 'talkwyn-hub' ) : esc_html__( 'Not active, manual renewal flow', 'talkwyn-hub' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Daily cron scheduled', 'talkwyn-hub' ); ?></td><td><?php echo wp_next_scheduled( 'twh_daily' ) ? esc_html( wp_date( 'Y-m-d H:i', (int) wp_next_scheduled( 'twh_daily' ) ) ) : '<span class="twh-status-bad">' . esc_html__( 'Not scheduled', 'talkwyn-hub' ) . '</span>'; ?></td></tr>
				<tr><td><?php esc_html_e( 'API base URL', 'talkwyn-hub' ); ?></td><td><code><?php echo esc_html( rest_url( 'talkwyn-hub/v1/' ) ); ?></code></td></tr>
				<tr><td><?php esc_html_e( 'Default dev patterns', 'talkwyn-hub' ); ?></td><td><small><?php echo esc_html( implode( ', ', Domain::DEFAULT_DEV_PATTERNS ) ); ?></small></td></tr>
			</table>
			</section>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	public static function handle_save(): void {
		Admin::guard( 'twh_save_settings' );
		$defaults = Settings::defaults();
		$days     = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['reminder_days'] ?? '' ) ) ) ) );
		$values   = array(
			'reminder_days'       => implode( ',', array_unique( $days ) ),
			'renewal_discount'    => min( 100, max( 0, (float) sanitize_text_field( wp_unslash( $_POST['renewal_discount'] ?? '0' ) ) ) ),
			'rate_limit_requests' => max( 1, absint( $_POST['rate_limit_requests'] ?? 30 ) ),
			'rate_limit_window'   => max( 1, absint( $_POST['rate_limit_window'] ?? 10 ) ),
			'dev_domains'         => implode( "\n", Domain::parse_patterns( sanitize_textarea_field( wp_unslash( $_POST['dev_domains'] ?? '' ) ) ) ),
			'log_retention_days'  => absint( $_POST['log_retention_days'] ?? 180 ),
			'delete_on_uninstall' => empty( $_POST['delete_on_uninstall'] ) ? 0 : 1,
			'admin_notify_email'  => sanitize_email( wp_unslash( $_POST['admin_notify_email'] ?? '' ) ),
			'email_from_name'     => sanitize_text_field( wp_unslash( $_POST['email_from_name'] ?? '' ) ),
		);
		$methods  = array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['partner_methods'] ?? array() ) ), array( 'paypal', 'wise', 'bank', 'payoneer' ) );
		$values   = array_merge(
			$values,
			array(
				'trial_enabled'            => empty( $_POST['trial_enabled'] ) ? 0 : 1,
				'trial_days'               => min( 90, max( 1, absint( $_POST['trial_days'] ?? 15 ) ) ),
				'trial_plan'               => in_array( sanitize_key( wp_unslash( $_POST['trial_plan'] ?? '' ) ), array( 'personal', 'business', 'agency' ), true ) ? sanitize_key( wp_unslash( $_POST['trial_plan'] ) ) : 'business',
				'trial_card_mode'          => 'card' === sanitize_key( wp_unslash( $_POST['trial_card_mode'] ?? '' ) ) ? 'card' : 'none',
				'trial_product_id'         => absint( $_POST['trial_product_id'] ?? 0 ),
				'trial_reminder_days'      => implode( ',', array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['trial_reminder_days'] ?? '' ) ) ) ) ) ) ),
				'trial_disposable'         => implode( "\n", \TWH\Domain\TrialPolicy::parse_list( sanitize_textarea_field( wp_unslash( $_POST['trial_disposable'] ?? '' ) ) ) ),
				'partners_enabled'         => empty( $_POST['partners_enabled'] ) ? 0 : 1,
				'partner_rate_new'         => min( 100, max( 0, (float) sanitize_text_field( wp_unslash( $_POST['partner_rate_new'] ?? '0' ) ) ) ),
				'partner_rate_renewal'     => min( 100, max( 0, (float) sanitize_text_field( wp_unslash( $_POST['partner_rate_renewal'] ?? '0' ) ) ) ),
				'partner_cookie_days'      => min( 365, max( 1, absint( $_POST['partner_cookie_days'] ?? 60 ) ) ),
				'partner_approval_days'    => min( 365, absint( $_POST['partner_approval_days'] ?? 30 ) ),
				'partner_payout_threshold' => max( 0, (float) sanitize_text_field( wp_unslash( $_POST['partner_payout_threshold'] ?? '0' ) ) ),
				'partner_methods'          => implode( ',', $methods ),
				'partner_pretty_links'     => empty( $_POST['partner_pretty_links'] ) ? 0 : 1,
				'partner_respect_consent'  => empty( $_POST['partner_respect_consent'] ) ? 0 : 1,
				'invoice_prefix'           => substr( preg_replace( '/[^A-Za-z0-9\-\/_.]/', '', sanitize_text_field( wp_unslash( $_POST['invoice_prefix'] ?? 'TW-' ) ) ), 0, 12 ),
				'invoice_company'          => sanitize_text_field( wp_unslash( $_POST['invoice_company'] ?? '' ) ),
				'invoice_address'          => sanitize_textarea_field( wp_unslash( $_POST['invoice_address'] ?? '' ) ),
				'invoice_tax_id'           => sanitize_text_field( wp_unslash( $_POST['invoice_tax_id'] ?? '' ) ),
				'invoice_email'            => sanitize_email( wp_unslash( $_POST['invoice_email'] ?? '' ) ),
				'invoice_note'             => sanitize_textarea_field( wp_unslash( $_POST['invoice_note'] ?? '' ) ),
			)
		);
		foreach ( array_keys( self::email_types() ) as $type ) {
			$subject                                 = sanitize_text_field( wp_unslash( $_POST[ 'email_' . $type . '_subject' ] ?? '' ) );
			$body                                    = sanitize_textarea_field( wp_unslash( $_POST[ 'email_' . $type . '_body' ] ?? '' ) );
			$values[ 'email_' . $type . '_subject' ] = '' !== $subject ? $subject : $defaults[ 'email_' . $type . '_subject' ];
			$values[ 'email_' . $type . '_body' ]    = '' !== $body ? $body : $defaults[ 'email_' . $type . '_body' ];
		}
		Settings::save( $values );
		flush_rewrite_rules( false ); // The /r/CODE rule depends on the pretty links setting.
		$tab = sanitize_key( wp_unslash( $_POST['twh_tab'] ?? 'general' ) );
		Admin::redirect( 'twh-settings', 'saved', array( 'tab' => in_array( $tab, array( 'general', 'trial', 'partners', 'invoices', 'emails' ), true ) ? $tab : 'general' ) );
	}

	/**
	 * Editable email types.
	 *
	 * @return array<string, string>
	 */
	public static function email_types(): array {
		return array(
			'license'             => __( 'License issued', 'talkwyn-hub' ),
			'reminder'            => __( 'Expiry reminder (before expiry)', 'talkwyn-hub' ),
			'expired'             => __( 'License expired', 'talkwyn-hub' ),
			'renewed'             => __( 'License renewed', 'talkwyn-hub' ),
			'trial_welcome'       => __( 'Trial started (day 0)', 'talkwyn-hub' ),
			'trial_reminder'      => __( 'Trial reminder (days left)', 'talkwyn-hub' ),
			'trial_ended'         => __( 'Trial ended', 'talkwyn-hub' ),
			'trial_converted'     => __( 'Trial upgraded to paid', 'talkwyn-hub' ),
			'partner_approved'    => __( 'Partner approved', 'talkwyn-hub' ),
			'partner_referral'    => __( 'Partner: new referral (pending)', 'talkwyn-hub' ),
			'partner_commission'  => __( 'Partner: commission approved', 'talkwyn-hub' ),
			'partner_payout'      => __( 'Partner: payout sent', 'talkwyn-hub' ),
			'partner_application' => __( 'Admin: new partner application', 'talkwyn-hub' ),
		);
	}

	/**
	 * Signing key rotation.
	 */
	public static function handle_keys(): void {
		Admin::guard( 'twh_signing_keys' );
		$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		if ( 'generate_next' === $do ) {
			SigningKeys::generate_next();
			Admin::redirect( 'twh-settings', 'key_next', array( 'tab' => 'keys' ) );
		}
		if ( 'promote_next' === $do && SigningKeys::promote_next() ) {
			Admin::redirect( 'twh-settings', 'key_promoted', array( 'tab' => 'keys' ) );
		}
		Admin::redirect( 'twh-settings', 'invalid' );
	}
}
