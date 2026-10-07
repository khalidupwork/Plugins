<?php
/**
 * Event log screen.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Repository\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Filterable event log.
 */
final class LogsPage {

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$type       = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$license_id = isset( $_GET['license_id'] ) ? absint( $_GET['license_id'] ) : 0;
		$from       = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to         = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$per_page = 50;
		$result   = Events::query(
			array(
				'type'       => in_array( $type, Events::TYPES, true ) ? $type : '',
				'license_id' => $license_id,
				'from'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from . ' 00:00:00' : '',
				'to'         => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to . ' 23:59:59' : '',
				'per_page'   => $per_page,
				'page'       => $page,
			)
		);
		$pages    = (int) ceil( $result['total'] / $per_page );
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Event log', 'talkwyn-hub' ); ?></h1>
			<form method="get" class="twh-filters">
				<input type="hidden" name="page" value="twh-logs">
				<select name="type">
					<option value=""><?php esc_html_e( 'All types', 'talkwyn-hub' ); ?></option>
					<?php foreach ( Events::TYPES as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $t ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="number" name="license_id" min="0" placeholder="<?php esc_attr_e( 'License ID', 'talkwyn-hub' ); ?>" value="<?php echo $license_id ? (int) $license_id : ''; ?>">
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				<?php submit_button( __( 'Filter', 'talkwyn-hub' ), '', '', false ); ?>
				<span class="twh-muted"><?php echo esc_html( sprintf( /* translators: %s: count */ _n( '%s event', '%s events', $result['total'], 'talkwyn-hub' ), number_format_i18n( $result['total'] ) ) ); ?></span>
			</form>
			<?php self::events_table( $result['rows'], true ); ?>
			<?php
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Events table.
	 *
	 * @param array<int, array<string, mixed>> $rows         Rows.
	 * @param bool                             $show_license Show license column.
	 */
	public static function events_table( array $rows, bool $show_license ): void {
		?>
		<table class="widefat striped twh-events">
			<thead><tr>
				<th><?php esc_html_e( 'Date (UTC)', 'talkwyn-hub' ); ?></th>
				<th><?php esc_html_e( 'Type', 'talkwyn-hub' ); ?></th>
				<?php if ( $show_license ) : ?>
					<th><?php esc_html_e( 'License', 'talkwyn-hub' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Details', 'talkwyn-hub' ); ?></th>
				<th><?php esc_html_e( 'IP hash', 'talkwyn-hub' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No events.', 'talkwyn-hub' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php $meta = json_decode( (string) $row['meta'], true ); ?>
				<tr>
					<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
					<td><span class="twh-event twh-event--<?php echo esc_attr( (string) $row['type'] ); ?>"><?php echo esc_html( (string) $row['type'] ); ?></span></td>
					<?php if ( $show_license ) : ?>
						<td>
							<?php if ( $row['license_id'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=twh-licenses&action=edit&license=' . (int) $row['license_id'] ) ); ?>">#<?php echo (int) $row['license_id']; ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					<?php endif; ?>
					<td><code class="twh-meta-json"><?php echo esc_html( is_array( $meta ) ? (string) wp_json_encode( $meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '' ); ?></code></td>
					<td><small class="twh-muted"><?php echo esc_html( substr( (string) $row['ip_hash'], 0, 10 ) ); ?></small></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
