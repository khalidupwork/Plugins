<?php
/**
 * License lifecycle operations.
 *
 * @package TalkwynHub
 */

namespace TWH;

use TWH\Domain\ExpiryCalculator;
use TWH\Email\Mailer;
use TWH\Repository\Activations;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

/**
 * High-level operations shared by WooCommerce hooks, the admin and My Account.
 */
final class LicenseService {

	/**
	 * Issue a new license.
	 *
	 * @param array<string, mixed> $args       See Licenses::create().
	 * @param bool                 $send_email Send the "Your license" email.
	 * @param array<string, mixed> $log_meta   Extra meta for the purchase event.
	 * @return array{id: int, key: string}
	 */
	public static function issue( array $args, bool $send_email = true, array $log_meta = array() ): array {
		$created = Licenses::create( $args );
		Events::log( 'purchase', $created['id'], array_merge( array( 'plan' => (string) ( $args['plan_slug'] ?? '' ) ), $log_meta ), '' );

		/**
		 * Fires after a license is issued.
		 *
		 * @param int                  $license_id License id.
		 * @param array<string, mixed> $args       Creation args.
		 */
		do_action( 'twh_license_issued', $created['id'], $args );

		if ( $send_email ) {
			$license = Licenses::find( $created['id'] );
			if ( $license ) {
				Mailer::send_license( $license, $created['key'] );
			}
		}
		return $created;
	}

	/**
	 * Revoke a license.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $reason  Reason (refund, cancelled, chargeback, admin).
	 * @param array<string, mixed> $meta    Log meta.
	 * @param bool                 $notify  Notify the admin.
	 */
	public static function revoke( array $license, string $reason, array $meta = array(), bool $notify = true ): bool {
		if ( 'revoked' === $license['status'] ) {
			return false;
		}
		Licenses::update( (int) $license['id'], array( 'status' => 'revoked' ) );
		Events::log( 'revoke', (int) $license['id'], array_merge( array( 'reason' => $reason ), $meta ), '' );
		do_action( 'twh_license_revoked', (int) $license['id'], $reason );
		if ( $notify ) {
			Mailer::notify_admin_revoked( $license, $reason );
		}
		return true;
	}

	/**
	 * Extend a license by its plan duration from the later of now and the current expiry.
	 *
	 * @param array<string, mixed> $license License.
	 * @param array<string, mixed> $meta    Log meta (order_id, amount, ...).
	 * @param bool                 $notify  Send the "renewed" email.
	 * @return int|null New expiry timestamp; null for lifetime or when nothing changed.
	 */
	public static function renew( array $license, array $meta = array(), bool $notify = true ): ?int {
		$current = Licenses::expires_ts( $license );
		$new     = ExpiryCalculator::renew( time(), $current, (int) $license['duration_days'] );
		if ( null === $new ) {
			Events::log( 'renewal', (int) $license['id'], array_merge( $meta, array( 'skipped' => 'lifetime' ) ), '' );
			return null;
		}

		$fields = array(
			'expires_at'     => $new,
			'reminders_sent' => '',
		);
		// Revoked or suspended licenses are not silently re-enabled by a renewal payment.
		if ( in_array( $license['status'], array( 'active', 'expired' ), true ) ) {
			$fields['status'] = 'active';
		} else {
			Mailer::notify_admin(
				/* translators: 1: license status, 2: license id */
				sprintf( __( 'Renewal paid for a %1$s license (#%2$d)', 'talkwyn-hub' ), $license['status'], (int) $license['id'] ),
				__( 'A renewal was paid for a license that is not active or expired. The expiry was extended but the status was not changed. Please review it.', 'talkwyn-hub' ),
				$license
			);
		}
		Licenses::update( (int) $license['id'], $fields );
		Events::log(
			'renewal',
			(int) $license['id'],
			array_merge(
				$meta,
				array(
					'from' => $license['expires_at'],
					'to'   => gmdate( 'Y-m-d H:i:s', $new ),
				)
			),
			''
		);
		do_action( 'twh_license_renewed', (int) $license['id'], $new );

		if ( $notify ) {
			$fresh = Licenses::find( (int) $license['id'] );
			if ( $fresh ) {
				Mailer::send_template( 'renewed', $fresh );
			}
		}
		return $new;
	}

	/**
	 * Apply an upgrade: same key, new plan/limit/features/product mapping. Expiry unchanged.
	 *
	 * @param array<string, mixed> $license License.
	 * @param array<string, mixed> $mapping Target mapping (see Woo\Mapping::for_product()).
	 * @param int                  $wc_product_id Target WooCommerce product/variation id.
	 * @param array<string, mixed> $meta    Log meta.
	 */
	public static function upgrade( array $license, array $mapping, int $wc_product_id, array $meta = array() ): void {
		Licenses::update(
			(int) $license['id'],
			array(
				'plan_slug'        => (string) $mapping['plan_slug'],
				'activation_limit' => (int) $mapping['activation_limit'],
				'features'         => (string) $mapping['features'],
				'wc_product_id'    => $wc_product_id,
			)
		);
		Events::log(
			'upgrade',
			(int) $license['id'],
			array_merge(
				$meta,
				array(
					'from_plan'  => $license['plan_slug'],
					'to_plan'    => $mapping['plan_slug'],
					'from_limit' => (int) $license['activation_limit'],
					'to_limit'   => (int) $mapping['activation_limit'],
				)
			),
			''
		);
		do_action( 'twh_license_upgraded', (int) $license['id'], $mapping );
	}

	/**
	 * Deactivate one site of a license.
	 *
	 * @param array<string, mixed> $license       License.
	 * @param int                  $activation_id Activation id.
	 * @param string               $by            customer|admin|api.
	 */
	public static function deactivate_site( array $license, int $activation_id, string $by ): bool {
		$activation = Activations::find( $activation_id );
		if ( ! $activation || (int) $activation['license_id'] !== (int) $license['id'] ) {
			return false;
		}
		$ok = Activations::deactivate( $activation_id );
		if ( $ok ) {
			Events::log(
				'deactivate',
				(int) $license['id'],
				array(
					'domain' => $activation['domain_normalized'],
					'by'     => $by,
				)
			);
		}
		return $ok;
	}

	/**
	 * Admin status change.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $status  New status.
	 */
	public static function set_status( array $license, string $status ): bool {
		if ( ! in_array( $status, Licenses::STATUSES, true ) || $status === $license['status'] ) {
			return false;
		}
		if ( 'revoked' === $status ) {
			return self::revoke( $license, 'admin', array( 'by' => get_current_user_id() ), false );
		}
		// Reactivating an expired license without changing its date would expire again tonight.
		if ( 'active' === $status && ExpiryCalculator::is_expired( Licenses::expires_ts( $license ), time() ) ) {
			return false;
		}
		Licenses::update( (int) $license['id'], array( 'status' => $status ) );
		Events::log(
			'admin_edit',
			(int) $license['id'],
			array(
				'status_from' => $license['status'],
				'status_to'   => $status,
				'by'          => get_current_user_id(),
			)
		);
		return true;
	}

	/**
	 * Product display name for a license.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function product_name( array $license ): string {
		$product = Products::find( (int) $license['product_id'] );
		return $product ? (string) $product['name'] : __( 'Unknown product', 'talkwyn-hub' );
	}

	/**
	 * Human plan label.
	 *
	 * @param string $plan_slug Plan slug.
	 */
	public static function plan_label( string $plan_slug ): string {
		$labels = array(
			'personal' => __( 'Personal', 'talkwyn-hub' ),
			'business' => __( 'Business', 'talkwyn-hub' ),
			'agency'   => __( 'Agency', 'talkwyn-hub' ),
			'lifetime' => __( 'Lifetime', 'talkwyn-hub' ),
		);
		$label  = $labels[ $plan_slug ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $plan_slug ) );
		return (string) apply_filters( 'twh_plan_label', $label, $plan_slug );
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'active'    => __( 'Active', 'talkwyn-hub' ),
			'expired'   => __( 'Expired', 'talkwyn-hub' ),
			'suspended' => __( 'Suspended', 'talkwyn-hub' ),
			'revoked'   => __( 'Revoked', 'talkwyn-hub' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Limit label (0 = unlimited).
	 *
	 * @param int $limit Limit.
	 */
	public static function limit_label( int $limit ): string {
		return 0 === $limit ? __( 'Unlimited', 'talkwyn-hub' ) : (string) $limit;
	}
}
