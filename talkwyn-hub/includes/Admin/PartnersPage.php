<?php
/**
 * Admin screen for the Partners program.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Partners\Commissions;
use TWH\Partners\PartnerAccount;
use TWH\Partners\Program;
use TWH\Repository\Partners;
use TWH\Repository\Referrals;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every admin-post handler calls Admin::guard() (capability + check_admin_referer) before reading input.

/**
 * Tabs: partners, referrals, payouts.
 */
final class PartnersPage {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_partner_admin', array( self::class, 'handle' ) );
		add_action( 'admin_post_twh_referral_admin', array( self::class, 'handle_referral' ) );
		add_action( 'admin_post_twh_payouts_csv', array( self::class, 'csv' ) );
	}

	/**
	 * Partner actions: status, rate, coupon, code, pay.
	 */
	public static function handle(): void {
		Admin::guard( 'twh_partner_admin' );
		$id      = absint( wp_unslash( $_POST['partner_id'] ?? 0 ) );
		$do      = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$partner = Partners::find( $id );
		if ( ! $partner ) {
			Admin::redirect( 'twh-partners', 'invalid' );
		}
		switch ( $do ) {
			case 'approve':
			case 'suspend':
			case 'pending':
				$map = array(
					'approve' => 'approved',
					'suspend' => 'suspended',
					'pending' => 'pending',
				);
				Program::set_status( $id, $map[ $do ] );
				Admin::redirect( 'twh-partners', 'saved' );
				break;
			case 'save':
				$rate   = trim( sanitize_text_field( wp_unslash( $_POST['commission_rate'] ?? '' ) ) );
				$coupon = strtolower( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) ) );
				Partners::update(
					$id,
					array(
						'commission_rate' => '' === $rate ? null : (string) min( 100, max( 0, (float) $rate ) ),
						'coupon_code'     => $coupon,
					)
				);
				$code = sanitize_text_field( wp_unslash( $_POST['referral_code'] ?? '' ) );
				if ( '' !== $code && $code !== $partner['referral_code'] ) {
					$result = Program::change_code( $partner, $code, true );
					if ( is_wp_error( $result ) ) {
						Admin::redirect( 'twh-partners', 'invalid' );
					}
				}
				Admin::redirect( 'twh-partners', 'saved' );
				break;
			case 'pay':
				$result = Program::pay( $id, sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ) );
				Admin::redirect( 'twh-partners', is_wp_error( $result ) ? 'invalid' : 'paid', array( 'tab' => 'payouts' ) );
				break;
		}
		Admin::redirect( 'twh-partners', 'invalid' );
	}

	/**
	 * Approve or reject one referral (a reason is required to reject).
	 */
	public static function handle_referral(): void {
		Admin::guard( 'twh_referral_admin' );
		$id     = absint( wp_unslash( $_POST['referral_id'] ?? 0 ) );
		$do     = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$row    = Referrals::find( $id );
		if ( ! $row || ! in_array( $row['status'], array( 'pending', 'approved' ), true ) ) {
			Admin::redirect( 'twh-partners', 'invalid', array( 'tab' => 'referrals' ) );
		}
		if ( 'reject' === $do ) {
			if ( '' === $reason ) {
				Admin::redirect( 'twh-partners', 'reason_required', array( 'tab' => 'referrals' ) );
			}
			Referrals::set_status( $id, 'rejected', $reason );
		} elseif ( 'approve' === $do && 'pending' === $row['status'] ) {
			Referrals::set_status( $id, 'approved' );
			$partner = Partners::find( (int) $row['partner_id'] );
			if ( $partner ) {
				Commissions::email( $partner, 'partner_commission', $row );
			}
		}
		Admin::redirect( 'twh-partners', 'saved', array( 'tab' => 'referrals' ) );
	}

	/**
	 * CSV of partners due a payout.
	 */
	public static function csv(): void {
		Admin::guard( 'twh_payouts_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="talkwyn-payouts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'partner_id', 'email', 'name', 'amount', 'currency', 'method', 'details' ) );
		foreach ( self::due() as $p ) {
			$user = get_userdata( (int) $p['user_id'] );
			fputcsv(
				$out,
				array_map(
					array( Export::class, 'cell' ),
					array(
						$p['id'],
						$user ? $user->user_email : '',
						$user ? $user->display_name : '',
						number_format( (float) $p['approved_unpaid'], 2, '.', '' ),
						function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
						$p['payout_method'],
						Partners::payout_details( $p ),
					)
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Partners at or above the payout threshold.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function due(): array {
		$threshold = (float) Settings::get( 'partner_payout_threshold' );
		return array_values(
			array_filter(
				Partners::all( 'approved' ),
				static function ( $p ) use ( $threshold ) {
					return (float) $p['approved_unpaid'] > 0 && (float) $p['approved_unpaid'] >= $threshold;
				}
			)
		);
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'partners';
		$tabs = array(
			'partners'  => __( 'Partners', 'talkwyn-hub' ),
			'referrals' => __( 'Referrals', 'talkwyn-hub' ),
			'payouts'   => __( 'Payouts', 'talkwyn-hub' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'partners';
		}
		echo '<div class="wrap twh-wrap"><h1>' . esc_html__( 'Partners', 'talkwyn-hub' ) . '</h1>';
		if ( ! Settings::get( 'partners_enabled' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The partner program is turned off in Settings. Links stop tracking and applications are closed.', 'talkwyn-hub' ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url(
					add_query_arg(
						array(
							'page' => 'twh-partners',
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					)
				),
				$slug === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
		if ( 'referrals' === $tab ) {
			self::referrals();
		} elseif ( 'payouts' === $tab ) {
			self::payouts();
		} else {
			self::partners();
		}
		echo '</div>';
	}

	/**
	 * Partners tab.
	 */
	private static function partners(): void {
		$rows    = Partners::all();
		$default = (float) Settings::get( 'partner_rate_new' );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No applications yet. Partners apply from My Account, Partners.', 'talkwyn-hub' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped twh-table"><thead><tr>';
		foreach ( array( __( 'Partner', 'talkwyn-hub' ), __( 'Status', 'talkwyn-hub' ), __( 'Clicks', 'talkwyn-hub' ), __( 'Referrals', 'talkwyn-hub' ), __( 'Pending', 'talkwyn-hub' ), __( 'Approved, unpaid', 'talkwyn-hub' ), __( 'Paid', 'talkwyn-hub' ), __( 'Signals', 'talkwyn-hub' ), __( 'Settings', 'talkwyn-hub' ), __( 'Actions', 'talkwyn-hub' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $p ) {
			$user    = get_userdata( (int) $p['user_id'] );
			$signals = Referrals::signals( (int) $p['id'] );
			$form_id = 'twh-partner-' . (int) $p['id'];
			echo '<tr>';
			echo '<td><strong>' . esc_html( $user ? $user->display_name : '#' . $p['user_id'] ) . '</strong><br><span class="description">' . esc_html( $user ? $user->user_email : '' ) . '</span>';
			if ( '' !== (string) $p['website'] ) {
				echo '<br><a href="' . esc_url( (string) $p['website'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $p['website'] ) . '</a>';
			}
			if ( '' !== (string) $p['promotion'] ) {
				echo '<details><summary>' . esc_html__( 'How they will promote', 'talkwyn-hub' ) . '</summary><p>' . esc_html( (string) $p['promotion'] ) . '</p></details>';
			}
			echo '</td>';
			echo '<td><span class="twh-badge twh-badge--' . esc_attr( (string) $p['status'] ) . '">' . esc_html( PartnerAccount::status_label( (string) $p['status'] ) ) . '</span></td>';
			echo '<td>' . esc_html( (string) $p['clicks'] ) . '</td>';
			echo '<td>' . esc_html( (string) $p['referrals'] ) . ( $p['rejected'] ? ' <span class="description">(' . esc_html( sprintf( /* translators: %d: rejected count */ __( '%d rejected', 'talkwyn-hub' ), $p['rejected'] ) ) . ')</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( Commissions::money( (float) $p['pending'] ) ) . '</td>';
			echo '<td>' . esc_html( Commissions::money( (float) $p['approved_unpaid'] ) ) . '</td>';
			echo '<td>' . esc_html( Commissions::money( (float) $p['paid'] ) ) . '</td>';
			$flags = array();
			if ( $signals['top_ip_share'] >= 50 && $p['referrals'] >= 3 ) {
				/* translators: %s: percent */
				$flags[] = sprintf( __( '%s%% from one IP', 'talkwyn-hub' ), $signals['top_ip_share'] );
			}
			if ( $signals['fast_buys'] > 0 ) {
				/* translators: %d: count */
				$flags[] = sprintf( __( '%d bought under 60s after the click', 'talkwyn-hub' ), $signals['fast_buys'] );
			}
			if ( $signals['refund_rate'] >= 25 ) {
				/* translators: %s: percent */
				$flags[] = sprintf( __( '%s%% refunded', 'talkwyn-hub' ), $signals['refund_rate'] );
			}
			echo '<td>' . ( $flags ? '<span class="twh-badge is-warn">' . esc_html( implode( ', ', $flags ) ) . '</span>' : '<span class="description">' . esc_html__( 'None', 'talkwyn-hub' ) . '</span>' ) . '</td>';
			echo '<td><form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twh-inline-form">';
			wp_nonce_field( 'twh_partner_admin' );
			echo '<input type="hidden" name="action" value="twh_partner_admin"><input type="hidden" name="partner_id" value="' . esc_attr( (string) $p['id'] ) . '">';
			echo '<label>' . esc_html__( 'Code', 'talkwyn-hub' ) . ' <input type="text" class="small-text" style="width:9em" name="referral_code" value="' . esc_attr( (string) $p['referral_code'] ) . '"></label><br>';
			echo '<label>' . esc_html__( 'Rate %', 'talkwyn-hub' ) . ' <input type="number" step="0.01" min="0" max="100" class="small-text" name="commission_rate" value="' . esc_attr( null === $p['commission_rate'] ? '' : (string) $p['commission_rate'] ) . '" placeholder="' . esc_attr( (string) $default ) . '"></label><br>';
			echo '<label>' . esc_html__( 'Coupon', 'talkwyn-hub' ) . ' <input type="text" style="width:9em" name="coupon_code" value="' . esc_attr( (string) $p['coupon_code'] ) . '"></label><br>';
			echo '<button class="button" name="do" value="save">' . esc_html__( 'Save', 'talkwyn-hub' ) . '</button>';
			echo '</form></td><td>';
			if ( 'approved' !== $p['status'] ) {
				echo '<button class="button button-primary" form="' . esc_attr( $form_id ) . '" name="do" value="approve">' . esc_html__( 'Approve', 'talkwyn-hub' ) . '</button> ';
			}
			if ( 'suspended' !== $p['status'] ) {
				echo '<button class="button twh-confirm" form="' . esc_attr( $form_id ) . '" name="do" value="suspend">' . esc_html__( 'Suspend', 'talkwyn-hub' ) . '</button>';
			}
			echo '<p class="description"><a href="' . esc_url( Program::link( $p ) ) . '" target="_blank" rel="noopener">' . esc_html( Program::link( $p ) ) . '</a></p>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Referrals tab.
	 */
	private static function referrals(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter only.
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$statuses = array( '' => __( 'All', 'talkwyn-hub' ) ) + array_combine( Referrals::STATUSES, array_map( array( PartnerAccount::class, 'status_label' ), Referrals::STATUSES ) );
		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $statuses as $slug => $label ) {
			$links[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'twh-partners',
							'tab'    => 'referrals',
							'status' => $slug,
						),
						admin_url( 'admin.php' )
					)
				),
				$slug === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</ul><br class="clear">';
		$rows = Referrals::all( in_array( $status, Referrals::STATUSES, true ) ? $status : '' );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No referrals yet.', 'talkwyn-hub' ) . '</p>';
			return;
		}
		$names = array();
		echo '<table class="widefat striped twh-table"><thead><tr>';
		foreach ( array( __( 'Date', 'talkwyn-hub' ), __( 'Partner', 'talkwyn-hub' ), __( 'Order', 'talkwyn-hub' ), __( 'Type', 'talkwyn-hub' ), __( 'Plan', 'talkwyn-hub' ), __( 'Amount', 'talkwyn-hub' ), __( 'Commission', 'talkwyn-hub' ), __( 'Source', 'talkwyn-hub' ), __( 'Status', 'talkwyn-hub' ), __( 'Actions', 'talkwyn-hub' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$pid = (int) $r['partner_id'];
			if ( ! isset( $names[ $pid ] ) ) {
				$partner       = Partners::find( $pid );
				$user          = $partner ? get_userdata( (int) $partner['user_id'] ) : null;
				$names[ $pid ] = $user ? $user->display_name : '#' . $pid;
			}
			$order_link = $r['order_id'] && function_exists( 'wc_get_order' ) && wc_get_order( $r['order_id'] ) ? wc_get_order( $r['order_id'] )->get_edit_order_url() : '';
			echo '<tr>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), (string) $r['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( $names[ $pid ] ) . '</td>';
			echo '<td>' . ( $order_link ? '<a href="' . esc_url( $order_link ) . '">#' . esc_html( (string) $r['order_id'] ) . '</a>' : '#' . esc_html( (string) $r['order_id'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['type'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['plan_slug'] ) . '</td>';
			echo '<td>' . esc_html( Commissions::money( (float) $r['amount'], (string) $r['currency'] ) ) . '</td>';
			echo '<td><strong>' . esc_html( Commissions::money( (float) $r['commission'], (string) $r['currency'] ) ) . '</strong></td>';
			echo '<td>' . esc_html( (string) $r['source'] ) . '</td>';
			echo '<td><span class="twh-badge twh-badge--' . esc_attr( (string) $r['status'] ) . '">' . esc_html( PartnerAccount::status_label( (string) $r['status'] ) ) . '</span>';
			if ( '' !== (string) $r['reason'] ) {
				echo '<br><span class="description">' . esc_html( (string) $r['reason'] ) . '</span>';
			}
			echo '</td><td>';
			if ( in_array( $r['status'], array( 'pending', 'approved' ), true ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twh-inline-form">';
				wp_nonce_field( 'twh_referral_admin' );
				echo '<input type="hidden" name="action" value="twh_referral_admin"><input type="hidden" name="referral_id" value="' . esc_attr( (string) $r['id'] ) . '">';
				if ( 'pending' === $r['status'] ) {
					echo '<button class="button" name="do" value="approve">' . esc_html__( 'Approve now', 'talkwyn-hub' ) . '</button><br>';
				}
				echo '<label class="screen-reader-text" for="twh-reason-' . esc_attr( (string) $r['id'] ) . '">' . esc_html__( 'Reason', 'talkwyn-hub' ) . '</label>';
				echo '<input type="text" id="twh-reason-' . esc_attr( (string) $r['id'] ) . '" name="reason" placeholder="' . esc_attr__( 'Reason to reject', 'talkwyn-hub' ) . '" style="width:11em"> ';
				echo '<button class="button twh-confirm" name="do" value="reject">' . esc_html__( 'Reject', 'talkwyn-hub' ) . '</button>';
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Payouts tab.
	 */
	private static function payouts(): void {
		$due       = self::due();
		$threshold = (float) Settings::get( 'partner_payout_threshold' );
		$methods   = Settings::payout_methods();
		/* translators: %s: threshold */
		echo '<p>' . esc_html( sprintf( __( 'Partners with an approved balance of %s or more. Send the money with their method, then mark it paid with the payment reference.', 'talkwyn-hub' ), Commissions::money( $threshold ) ) ) . '</p>';
		if ( $due ) {
			printf(
				'<p><a class="button" href="%s">%s</a></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=twh_payouts_csv' ), 'twh_payouts_csv' ) ),
				esc_html__( 'Export CSV', 'talkwyn-hub' )
			);
		}
		if ( ! $due ) {
			echo '<p><em>' . esc_html__( 'Nobody is due a payout right now.', 'talkwyn-hub' ) . '</em></p>';
		} else {
			echo '<table class="widefat striped twh-table"><thead><tr>';
			foreach ( array( __( 'Partner', 'talkwyn-hub' ), __( 'Amount', 'talkwyn-hub' ), __( 'Method', 'talkwyn-hub' ), __( 'Details', 'talkwyn-hub' ), __( 'Mark paid', 'talkwyn-hub' ) ) as $h ) {
				echo '<th scope="col">' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $due as $p ) {
				$user = get_userdata( (int) $p['user_id'] );
				echo '<tr><td>' . esc_html( $user ? $user->display_name . ' <' . $user->user_email . '>' : '#' . $p['user_id'] ) . '</td>';
				echo '<td><strong>' . esc_html( Commissions::money( (float) $p['approved_unpaid'] ) ) . '</strong></td>';
				echo '<td>' . esc_html( $methods[ (string) $p['payout_method'] ] ?? (string) $p['payout_method'] ) . '</td>';
				echo '<td><code>' . esc_html( Partners::payout_details( $p ) ) . '</code></td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twh-inline-form">';
				wp_nonce_field( 'twh_partner_admin' );
				echo '<input type="hidden" name="action" value="twh_partner_admin"><input type="hidden" name="do" value="pay"><input type="hidden" name="partner_id" value="' . esc_attr( (string) $p['id'] ) . '">';
				echo '<input type="text" name="reference" placeholder="' . esc_attr__( 'Payment reference', 'talkwyn-hub' ) . '" required> ';
				echo '<button class="button button-primary twh-confirm">' . esc_html__( 'Mark paid', 'talkwyn-hub' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Payout history', 'talkwyn-hub' ) . '</h2>';
		$history = array();
		foreach ( Partners::all() as $p ) {
			foreach ( Referrals::payouts( (int) $p['id'] ) as $row ) {
				$row['partner'] = $p;
				$history[]      = $row;
			}
		}
		usort(
			$history,
			static function ( $a, $b ) {
				return (int) $b['id'] <=> (int) $a['id'];
			}
		);
		if ( ! $history ) {
			echo '<p><em>' . esc_html__( 'No payouts yet.', 'talkwyn-hub' ) . '</em></p>';
			return;
		}
		echo '<table class="widefat striped twh-table"><thead><tr><th>' . esc_html__( 'Date', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Partner', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Amount', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Method', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Reference', 'talkwyn-hub' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $history, 0, 200 ) as $row ) {
			$user = get_userdata( (int) $row['partner']['user_id'] );
			echo '<tr><td>' . esc_html( mysql2date( get_option( 'date_format' ), (string) $row['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : '#' . $row['partner_id'] ) . '</td>';
			echo '<td>' . esc_html( Commissions::money( (float) $row['amount'], (string) $row['currency'] ) ) . '</td>';
			echo '<td>' . esc_html( $methods[ (string) $row['method'] ] ?? (string) $row['method'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['reference'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
