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
		$texts = array(
			'license'  => __( 'License issued', 'talkwyn-hub' ),
			'reminder' => __( 'Expiry reminder (before expiry)', 'talkwyn-hub' ),
			'expired'  => __( 'License expired', 'talkwyn-hub' ),
			'renewed'  => __( 'License renewed', 'talkwyn-hub' ),
		);
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Talkwyn Hub settings', 'talkwyn-hub' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="twh_save_settings">
				<?php wp_nonce_field( 'twh_save_settings' ); ?>

				<h2><?php esc_html_e( 'Renewals & reminders', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="twh-reminder-days"><?php esc_html_e( 'Reminder days', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-reminder-days" name="reminder_days" type="text" value="<?php echo esc_attr( (string) $s['reminder_days'] ); ?>">
						<p class="description"><?php esc_html_e( 'Comma-separated days before expiry, e.g. 30,7. An "expired" email is always sent on expiry.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-discount"><?php esc_html_e( 'Renewal discount (%)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-discount" name="renewal_discount" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( (string) $s['renewal_discount'] ); ?>">
						<p class="description"><?php esc_html_e( 'Applied to manual renewals (when WooCommerce Subscriptions is not used).', 'talkwyn-hub' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'API', 'talkwyn-hub' ); ?></h2>
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

				<h2><?php esc_html_e( 'Emails', 'talkwyn-hub' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Emails use the WooCommerce email template (Settings → Emails) for header, footer and colors. Placeholders:', 'talkwyn-hub' ); ?>
					<code><?php echo esc_html( implode( ' ', Mailer::placeholders() ) ); ?></code>
				</p>
				<table class="form-table" role="presentation">
					<tr><th><label for="twh-from"><?php esc_html_e( 'From name', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-from" name="email_from_name" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['email_from_name'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th><label for="twh-admin-email"><?php esc_html_e( 'Admin notifications to', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-admin-email" name="admin_notify_email" type="email" class="regular-text" value="<?php echo esc_attr( (string) $s['admin_notify_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"></td></tr>
					<?php foreach ( $texts as $type => $label ) : ?>
						<tr><th><?php echo esc_html( $label ); ?></th>
							<td>
								<input name="email_<?php echo esc_attr( $type ); ?>_subject" type="text" class="large-text" value="<?php echo esc_attr( (string) $s[ 'email_' . $type . '_subject' ] ); ?>">
								<textarea name="email_<?php echo esc_attr( $type ); ?>_body" rows="8" class="large-text"><?php echo esc_textarea( (string) $s[ 'email_' . $type . '_body' ] ); ?></textarea>
							</td></tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Uninstall', 'talkwyn-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Data', 'talkwyn-hub' ); ?></th>
						<td><label><input name="delete_on_uninstall" type="checkbox" value="1" <?php checked( (int) $s['delete_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Delete all data on uninstall (licenses, activations, releases, logs, signing keys, settings and release ZIPs). This cannot be undone.', 'talkwyn-hub' ); ?></label></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
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

			<h2><?php esc_html_e( 'System status', 'talkwyn-hub' ); ?></h2>
			<table class="widefat striped twh-status">
				<tr><td><?php esc_html_e( 'TWH_SECRET_KEY defined', 'talkwyn-hub' ); ?></td><td><?php echo Secrets::has_constant() ? '✅' : '⚠️ ' . esc_html__( 'Using the WordPress salt fallback', 'talkwyn-hub' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Release directory', 'talkwyn-hub' ); ?></td><td><code><?php echo esc_html( Storage::dir() ); ?></code> <?php echo Storage::ensure_dir() ? '✅' : '❌'; ?></td></tr>
				<tr><td><?php esc_html_e( 'WooCommerce Subscriptions', 'talkwyn-hub' ); ?></td><td><?php echo \TWH\Woo\Subscriptions::active() ? esc_html__( 'Active – automatic renewals', 'talkwyn-hub' ) : esc_html__( 'Not active – manual renewal flow', 'talkwyn-hub' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Daily cron scheduled', 'talkwyn-hub' ); ?></td><td><?php echo wp_next_scheduled( 'twh_daily' ) ? esc_html( wp_date( 'Y-m-d H:i', (int) wp_next_scheduled( 'twh_daily' ) ) ) : '❌'; ?></td></tr>
				<tr><td><?php esc_html_e( 'API base URL', 'talkwyn-hub' ); ?></td><td><code><?php echo esc_html( rest_url( 'talkwyn-hub/v1/' ) ); ?></code></td></tr>
				<tr><td><?php esc_html_e( 'Default dev patterns', 'talkwyn-hub' ); ?></td><td><small><?php echo esc_html( implode( ', ', Domain::DEFAULT_DEV_PATTERNS ) ); ?></small></td></tr>
			</table>
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
		foreach ( array( 'license', 'reminder', 'expired', 'renewed' ) as $type ) {
			$subject                                 = sanitize_text_field( wp_unslash( $_POST[ 'email_' . $type . '_subject' ] ?? '' ) );
			$body                                    = sanitize_textarea_field( wp_unslash( $_POST[ 'email_' . $type . '_body' ] ?? '' ) );
			$values[ 'email_' . $type . '_subject' ] = '' !== $subject ? $subject : $defaults[ 'email_' . $type . '_subject' ];
			$values[ 'email_' . $type . '_body' ]    = '' !== $body ? $body : $defaults[ 'email_' . $type . '_body' ];
		}
		Settings::save( $values );
		Admin::redirect( 'twh-settings', 'saved' );
	}

	/**
	 * Signing key rotation.
	 */
	public static function handle_keys(): void {
		Admin::guard( 'twh_signing_keys' );
		$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		if ( 'generate_next' === $do ) {
			SigningKeys::generate_next();
			Admin::redirect( 'twh-settings', 'key_next' );
		}
		if ( 'promote_next' === $do && SigningKeys::promote_next() ) {
			Admin::redirect( 'twh-settings', 'key_promoted' );
		}
		Admin::redirect( 'twh-settings', 'invalid' );
	}
}
