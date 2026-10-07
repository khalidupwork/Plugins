<?php
/**
 * Licenses admin: list, detail/edit, manual creation, bulk actions.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Domain\KeyGenerator;
use TWH\Email\Mailer;
use TWH\LicenseService;
use TWH\Repository\Activations;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every admin-post handler calls Admin::guard() (capability + check_admin_referer) before reading input; an id needed to build the nonce action is read with absint() first.

/**
 * Licenses screens.
 */
final class LicensesPage {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_save_license', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_twh_create_license', array( self::class, 'handle_create' ) );
		add_action( 'admin_post_twh_license_action', array( self::class, 'handle_action' ) );
		add_action( 'admin_init', array( self::class, 'handle_bulk' ) );
	}

	/**
	 * Router.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['license'] ) ? absint( $_GET['license'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'new' === $action ) {
			self::render_new();
			return;
		}
		if ( 'edit' === $action && $id ) {
			$license = Licenses::find( $id );
			if ( $license ) {
				self::render_edit( $license );
				return;
			}
		}
		self::render_list();
	}

	/**
	 * List screen.
	 */
	private static function render_list(): void {
		$table = new LicensesTable();
		$table->prepare_items();
		?>
		<div class="wrap twh-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Licenses', 'talkwyn-hub' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=twh-licenses&action=new' ) ); ?>"><?php esc_html_e( 'Create license', 'talkwyn-hub' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=twh_export&type=licenses' ), 'twh_export' ) ); ?>"><?php esc_html_e( 'Export licenses (CSV)', 'talkwyn-hub' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=twh_export&type=activations' ), 'twh_export' ) ); ?>"><?php esc_html_e( 'Export activations (CSV)', 'talkwyn-hub' ); ?></a>
			<hr class="wp-header-end">
			<form method="get">
				<input type="hidden" name="page" value="twh-licenses">
				<?php $table->search_box( __( 'Search key (last 4 or full), email, domain', 'talkwyn-hub' ), 'twh-license' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Bulk actions (GET form from WP_List_Table).
	 */
	public static function handle_bulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce checked below.
		if ( ! isset( $_REQUEST['page'] ) || 'twh-licenses' !== $_REQUEST['page'] || empty( $_REQUEST['license_ids'] ) ) {
			return;
		}
		$action = '';
		foreach ( array( 'action', 'action2' ) as $field ) {
			$value = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : '';
			if ( 0 === strpos( $value, 'bulk_' ) ) {
				$action = $value;
				break;
			}
		}
		if ( '' === $action ) {
			return;
		}
		if ( ! current_user_can( Admin::cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn-hub' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bulk-licenses' );
		$ids = array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['license_ids'] ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'bulk_export' === $action ) {
			Export::licenses( $ids );
			exit;
		}
		$map = array(
			'bulk_suspend'    => 'suspended',
			'bulk_reactivate' => 'active',
			'bulk_revoke'     => 'revoked',
		);
		if ( ! isset( $map[ $action ] ) ) {
			return;
		}
		foreach ( $ids as $id ) {
			$license = Licenses::find( $id );
			if ( $license ) {
				LicenseService::set_status( $license, $map[ $action ] );
			}
		}
		delete_transient( 'twh_dashboard_stats' );
		Admin::redirect( 'twh-licenses', 'bulk_done' );
	}

	/**
	 * Detail/edit screen.
	 *
	 * @param array<string, mixed> $license License.
	 */
	private static function render_edit( array $license ): void {
		$id          = (int) $license['id'];
		$activations = Activations::all_for( $id );
		$events      = Events::query(
			array(
				'license_id' => $id,
				'per_page'   => 50,
			)
		);
		$product     = Products::find( (int) $license['product_id'] );
		$status      = Licenses::effective_status( $license );
		$expires     = Licenses::expires_ts( $license );
		$orders      = array( (int) $license['order_id'] );
		foreach ( $events['rows'] as $event ) {
			$meta = json_decode( (string) $event['meta'], true );
			if ( is_array( $meta ) && ! empty( $meta['order_id'] ) ) {
				$orders[] = (int) $meta['order_id'];
			}
		}
		$orders = array_values( array_unique( array_filter( $orders ) ) );
		$action = static function ( string $task, array $extra = array() ) use ( $id ): string {
			return wp_nonce_url(
				add_query_arg(
					array_merge(
						array(
							'action'  => 'twh_license_action',
							'do'      => $task,
							'license' => $id,
						),
						$extra
					),
					admin_url( 'admin-post.php' )
				),
				'twh_license_action_' . $id
			);
		};
		?>
		<div class="wrap twh-wrap">
			<h1>
				<?php echo esc_html( sprintf( /* translators: %d: license id */ __( 'License #%d', 'talkwyn-hub' ), $id ) ); ?>
				<span class="twh-badge twh-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( LicenseService::status_label( $status ) ); ?></span>
			</h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=twh-licenses' ) ); ?>">&larr; <?php esc_html_e( 'All licenses', 'talkwyn-hub' ); ?></a></p>

			<div class="twh-columns">
				<div class="twh-panel">
					<h2><?php esc_html_e( 'License', 'talkwyn-hub' ); ?></h2>
					<p>
						<code class="twh-key-masked"><?php echo esc_html( KeyGenerator::mask( (string) $license['key_last4'] ) ); ?></code>
						<details class="twh-inline-details"><summary><?php esc_html_e( 'Show full key', 'talkwyn-hub' ); ?></summary>
							<code class="twh-copyable"><?php echo esc_html( (string) Licenses::plain_key( $license ) ); ?></code>
						</details>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( $action( 'resend' ) ); ?>"><?php esc_html_e( 'Resend license email', 'talkwyn-hub' ); ?></a>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="twh_save_license">
						<input type="hidden" name="license" value="<?php echo (int) $id; ?>">
						<?php wp_nonce_field( 'twh_save_license_' . $id ); ?>
						<table class="form-table" role="presentation">
							<tr><th><?php esc_html_e( 'Product', 'talkwyn-hub' ); ?></th><td><?php echo esc_html( $product ? $product['name'] : '?' ); ?></td></tr>
							<tr>
								<th><label for="twh-status"><?php esc_html_e( 'Status', 'talkwyn-hub' ); ?></label></th>
								<td><select id="twh-status" name="status">
									<?php foreach ( Licenses::STATUSES as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $license['status'], $s ); ?>><?php echo esc_html( LicenseService::status_label( $s ) ); ?></option>
									<?php endforeach; ?>
								</select></td>
							</tr>
							<tr>
								<th><label for="twh-plan"><?php esc_html_e( 'Plan slug', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-plan" name="plan_slug" type="text" class="regular-text" value="<?php echo esc_attr( (string) $license['plan_slug'] ); ?>"></td>
							</tr>
							<tr>
								<th><label for="twh-limit"><?php esc_html_e( 'Activation limit', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-limit" name="activation_limit" type="number" min="0" step="1" value="<?php echo (int) $license['activation_limit']; ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'talkwyn-hub' ); ?></span></td>
							</tr>
							<tr>
								<th><label for="twh-expires"><?php esc_html_e( 'Expires (UTC)', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-expires" name="expires_at" type="date" value="<?php echo esc_attr( null === $expires ? '' : gmdate( 'Y-m-d', $expires ) ); ?>"> <span class="description"><?php esc_html_e( 'Empty = lifetime', 'talkwyn-hub' ); ?></span></td>
							</tr>
							<tr>
								<th><label for="twh-duration"><?php esc_html_e( 'Renewal period (days)', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-duration" name="duration_days" type="number" min="0" step="1" value="<?php echo (int) $license['duration_days']; ?>"> <span class="description"><?php esc_html_e( 'Added on each renewal. 0 = lifetime.', 'talkwyn-hub' ); ?></span></td>
							</tr>
							<tr>
								<th><label for="twh-features"><?php esc_html_e( 'Features', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-features" name="features" type="text" class="regular-text" value="<?php echo esc_attr( (string) $license['features'] ); ?>"></td>
							</tr>
							<tr>
								<th><label for="twh-email"><?php esc_html_e( 'Customer email', 'talkwyn-hub' ); ?></label></th>
								<td>
									<input id="twh-email" name="customer_email" type="email" class="regular-text" value="<?php echo esc_attr( (string) $license['customer_email'] ); ?>">
									<?php if ( (int) $license['customer_id'] ) : ?>
										<p class="description"><?php echo esc_html( sprintf( /* translators: %d: user id */ __( 'Linked to user #%d', 'talkwyn-hub' ), (int) $license['customer_id'] ) ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><label for="twh-notes"><?php esc_html_e( 'Private notes', 'talkwyn-hub' ); ?></label></th>
								<td><textarea id="twh-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $license['notes'] ); ?></textarea></td>
							</tr>
						</table>
						<?php submit_button( __( 'Save license', 'talkwyn-hub' ) ); ?>
					</form>
				</div>

				<div class="twh-panel">
					<h2><?php esc_html_e( 'Activations', 'talkwyn-hub' ); ?></h2>
					<?php if ( ! $activations ) : ?>
						<p class="description"><?php esc_html_e( 'No activations yet.', 'talkwyn-hub' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr>
								<th><?php esc_html_e( 'Site', 'talkwyn-hub' ); ?></th>
								<th><?php esc_html_e( 'Versions', 'talkwyn-hub' ); ?></th>
								<th><?php esc_html_e( 'Last check', 'talkwyn-hub' ); ?></th>
								<th></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $activations as $a ) : ?>
								<tr class="<?php echo $a['deactivated_at'] ? 'twh-inactive' : ''; ?>">
									<td>
										<a href="<?php echo esc_url( (string) $a['site_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $a['domain_normalized'] ); ?></a>
										<?php if ( (int) $a['is_dev_site'] ) : ?>
											<span class="twh-badge twh-badge--dev"><?php esc_html_e( 'dev', 'talkwyn-hub' ); ?></span>
										<?php endif; ?>
										<br><small class="twh-muted"><?php echo esc_html( (string) $a['instance_id'] ); ?></small>
									</td>
									<td><small><?php echo esc_html( sprintf( 'Plugin %s · WP %s · PHP %s', $a['plugin_version'], $a['wp_version'], $a['php_version'] ) ); ?></small></td>
									<td><small><?php echo esc_html( $a['deactivated_at'] ? sprintf( /* translators: %s: date */ __( 'Deactivated %s', 'talkwyn-hub' ), Time::human( $a['deactivated_at'] ) ) : Time::human( $a['last_check_at'], '—' ) ); ?></small></td>
									<td>
										<?php if ( ! $a['deactivated_at'] ) : ?>
											<a class="button button-small twh-confirm" href="<?php echo esc_url( $action( 'deactivate', array( 'activation' => (int) $a['id'] ) ) ); ?>"><?php esc_html_e( 'Deactivate', 'talkwyn-hub' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Linked orders', 'talkwyn-hub' ); ?></h2>
					<?php if ( ! $orders ) : ?>
						<p class="description"><?php esc_html_e( 'Created manually.', 'talkwyn-hub' ); ?></p>
					<?php else : ?>
						<ul>
						<?php foreach ( $orders as $order_id ) : ?>
							<?php $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null; ?>
							<li>
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
									— <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
									— <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
								<?php else : ?>
									#<?php echo (int) $order_id; ?> <?php esc_html_e( '(deleted)', 'talkwyn-hub' ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $license['subscription_id'] ) : ?>
						<p><?php echo esc_html( sprintf( /* translators: %d: subscription id */ __( 'Subscription #%d', 'talkwyn-hub' ), (int) $license['subscription_id'] ) ); ?></p>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Event log', 'talkwyn-hub' ); ?></h2>
					<?php LogsPage::events_table( $events['rows'], false ); ?>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=twh-logs&license_id=' . $id ) ); ?>"><?php esc_html_e( 'Full log', 'talkwyn-hub' ); ?></a></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save license edits.
	 */
	public static function handle_save(): void {
		$id = absint( $_POST['license'] ?? 0 );
		Admin::guard( 'twh_save_license_' . $id );
		$license = Licenses::find( $id );
		if ( ! $license ) {
			Admin::redirect( 'twh-licenses', 'invalid' );
		}

		$expires_raw = sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) );
		$expires     = null;
		if ( '' !== $expires_raw ) {
			$ts = strtotime( $expires_raw . ' 23:59:59 UTC' );
			if ( false === $ts ) {
				Admin::redirect(
					'twh-licenses',
					'invalid',
					array(
						'action'  => 'edit',
						'license' => $id,
					)
				);
			}
			$expires = (int) $ts;
		}
		$email  = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$fields = array(
			'plan_slug'        => sanitize_key( wp_unslash( $_POST['plan_slug'] ?? '' ) ),
			'activation_limit' => absint( $_POST['activation_limit'] ?? 0 ),
			'duration_days'    => absint( $_POST['duration_days'] ?? 0 ),
			'features'         => sanitize_text_field( wp_unslash( $_POST['features'] ?? '' ) ),
			'expires_at'       => $expires,
			'customer_email'   => $email,
			'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);
		if ( Licenses::expires_ts( $license ) !== $expires ) {
			$fields['reminders_sent'] = '';
		}
		if ( '' !== $email && 0 === (int) $license['customer_id'] ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$fields['customer_id'] = (int) $user->ID;
			}
		}

		$changes = array();
		foreach ( $fields as $col => $value ) {
			$old = 'expires_at' === $col ? Licenses::expires_ts( $license ) : $license[ $col ];
			if ( (string) $old !== (string) $value ) {
				$changes[ $col ] = array(
					'from' => 'notes' === $col ? '…' : $old,
					'to'   => 'notes' === $col ? '…' : $value,
				);
			}
		}
		Licenses::update( $id, $fields );

		// Status after dates, so "active" is accepted when the new expiry is in the future.
		$new_status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$notice     = 'saved';
		if ( $new_status !== $license['status'] ) {
			$fresh = (array) Licenses::find( $id );
			if ( ! LicenseService::set_status( $fresh, $new_status ) ) {
				$notice = 'status_invalid';
			}
		} elseif ( 'expired' === $license['status'] && null !== $expires && $expires > time() ) {
			// Extending an expired license's date reactivates it.
			Licenses::update( $id, array( 'status' => 'active' ) );
			$changes['status'] = array(
				'from' => 'expired',
				'to'   => 'active',
			);
		}

		if ( $changes ) {
			Events::log(
				'admin_edit',
				$id,
				array(
					'by'      => get_current_user_id(),
					'changes' => $changes,
				)
			);
		}
		delete_transient( 'twh_dashboard_stats' );
		Admin::redirect(
			'twh-licenses',
			$notice,
			array(
				'action'  => 'edit',
				'license' => $id,
			)
		);
	}

	/**
	 * Single actions: resend, deactivate.
	 */
	public static function handle_action(): void {
		$id = absint( $_GET['license'] ?? 0 );
		Admin::guard( 'twh_license_action_' . $id );
		$license = Licenses::find( $id );
		if ( ! $license ) {
			Admin::redirect( 'twh-licenses', 'invalid' );
		}
		$do   = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) );
		$back = array(
			'action'  => 'edit',
			'license' => $id,
		);
		if ( 'resend' === $do ) {
			$key = Licenses::plain_key( $license );
			$ok  = null !== $key && Mailer::send_license( $license, $key );
			Events::log(
				'admin_edit',
				$id,
				array(
					'by'           => get_current_user_id(),
					'resend_email' => $ok,
				)
			);
			Admin::redirect( 'twh-licenses', $ok ? 'emailed' : 'email_failed', $back );
		}
		if ( 'deactivate' === $do ) {
			LicenseService::deactivate_site( $license, absint( $_GET['activation'] ?? 0 ), 'admin' );
			Admin::redirect( 'twh-licenses', 'deactivated', $back );
		}
		Admin::redirect( 'twh-licenses', 'invalid', $back );
	}

	/**
	 * Manual creation screen.
	 */
	private static function render_new(): void {
		$products = Products::all();
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Create license', 'talkwyn-hub' ); ?></h1>
			<p class="description"><?php esc_html_e( 'For giveaways, partners and support cases. The license is not linked to an order.', 'talkwyn-hub' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="twh_create_license">
				<?php wp_nonce_field( 'twh_create_license' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="twh-product"><?php esc_html_e( 'Software product', 'talkwyn-hub' ); ?></label></th>
						<td><select id="twh-product" name="product_id" required>
							<?php foreach ( $products as $p ) : ?>
								<option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html( $p['name'] ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr><th><label for="twh-email"><?php esc_html_e( 'Customer email', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-email" name="customer_email" type="email" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'If a user with this email exists, the license appears in their account.', 'talkwyn-hub' ); ?></p></td></tr>
					<tr><th><label for="twh-plan"><?php esc_html_e( 'Plan slug', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-plan" name="plan_slug" type="text" value="personal" class="regular-text" required></td></tr>
					<tr><th><label for="twh-limit"><?php esc_html_e( 'Activation limit', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-limit" name="activation_limit" type="number" min="0" value="1"> <span class="description"><?php esc_html_e( '0 = unlimited', 'talkwyn-hub' ); ?></span></td></tr>
					<tr><th><label for="twh-duration"><?php esc_html_e( 'Duration (days)', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-duration" name="duration_days" type="number" min="0" value="365"> <span class="description"><?php esc_html_e( '0 = lifetime', 'talkwyn-hub' ); ?></span></td></tr>
					<tr><th><label for="twh-features"><?php esc_html_e( 'Features', 'talkwyn-hub' ); ?></label></th>
						<td><input id="twh-features" name="features" type="text" value="pro" class="regular-text"></td></tr>
					<tr><th><label for="twh-notes"><?php esc_html_e( 'Private notes', 'talkwyn-hub' ); ?></label></th>
						<td><textarea id="twh-notes" name="notes" rows="3" class="large-text"></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'talkwyn-hub' ); ?></th>
						<td><label><input name="send_email" type="checkbox" value="1" checked> <?php esc_html_e( 'Send the license email to the customer', 'talkwyn-hub' ); ?></label></td></tr>
				</table>
				<?php submit_button( __( 'Create license', 'talkwyn-hub' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Create a manual license.
	 */
	public static function handle_create(): void {
		Admin::guard( 'twh_create_license' );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$email      = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		if ( ! Products::find( $product_id ) || ! is_email( $email ) ) {
			Admin::redirect( 'twh-licenses', 'invalid', array( 'action' => 'new' ) );
		}
		$user    = get_user_by( 'email', $email );
		$created = LicenseService::issue(
			array(
				'product_id'       => $product_id,
				'plan_slug'        => sanitize_key( wp_unslash( $_POST['plan_slug'] ?? 'personal' ) ),
				'customer_id'      => $user ? (int) $user->ID : 0,
				'customer_email'   => $email,
				'activation_limit' => absint( $_POST['activation_limit'] ?? 1 ),
				'duration_days'    => absint( $_POST['duration_days'] ?? 365 ),
				'features'         => sanitize_text_field( wp_unslash( $_POST['features'] ?? 'pro' ) ),
				'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			),
			! empty( $_POST['send_email'] ),
			array(
				'manual' => true,
				'by'     => get_current_user_id(),
			)
		);
		delete_transient( 'twh_dashboard_stats' );
		Admin::redirect(
			'twh-licenses',
			'created',
			array(
				'action'  => 'edit',
				'license' => $created['id'],
			)
		);
	}
}
