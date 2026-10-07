<?php
/**
 * Activation limit logic.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Decides what to do with an activation request, given the current active rows.
 *
 * Each activation row is an array with keys: id, instance_id, domain_normalized,
 * is_dev_site, deactivated_at. Rows with a non-empty deactivated_at are ignored.
 */
final class ActivationPolicy {

	public const ACTION_REUSE  = 'reuse';
	public const ACTION_CREATE = 'create';
	public const ACTION_DENY   = 'deny';

	/**
	 * Count active, non-dev activations.
	 *
	 * @param array<int, array<string, mixed>> $activations Activation rows.
	 */
	public static function count_used( array $activations ): int {
		$used = 0;
		foreach ( $activations as $row ) {
			if ( self::is_active( $row ) && empty( $row['is_dev_site'] ) ) {
				++$used;
			}
		}
		return $used;
	}

	/**
	 * Decide.
	 *
	 * Matching order: same instance_id, then same domain. A re-used row never
	 * consumes an extra slot, unless it moves from a dev domain to a production one.
	 *
	 * @param int                              $limit       Activation limit; 0 = unlimited.
	 * @param array<int, array<string, mixed>> $activations Rows for the license.
	 * @param string                           $instance_id Incoming instance id.
	 * @param string                           $domain      Incoming normalized domain.
	 * @param bool                             $is_dev      Whether the incoming domain is dev.
	 * @return array{action: string, activation_id: int|null}
	 */
	public static function decide( int $limit, array $activations, string $instance_id, string $domain, bool $is_dev ): array {
		$match = null;
		foreach ( $activations as $row ) {
			if ( self::is_active( $row ) && (string) $row['instance_id'] === $instance_id ) {
				$match = $row;
				break;
			}
		}
		if ( null === $match ) {
			foreach ( $activations as $row ) {
				if ( self::is_active( $row ) && (string) $row['domain_normalized'] === $domain ) {
					$match = $row;
					break;
				}
			}
		}

		if ( null !== $match ) {
			$was_dev = ! empty( $match['is_dev_site'] );
			// Moving a dev row to production needs a free slot.
			if ( $was_dev && ! $is_dev && $limit > 0 && self::count_used( $activations ) >= $limit ) {
				return array(
					'action'        => self::ACTION_DENY,
					'activation_id' => null,
				);
			}
			return array(
				'action'        => self::ACTION_REUSE,
				'activation_id' => (int) $match['id'],
			);
		}

		if ( ! $is_dev && $limit > 0 && self::count_used( $activations ) >= $limit ) {
			return array(
				'action'        => self::ACTION_DENY,
				'activation_id' => null,
			);
		}

		return array(
			'action'        => self::ACTION_CREATE,
			'activation_id' => null,
		);
	}

	/**
	 * Whether a row is active.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	private static function is_active( array $row ): bool {
		return empty( $row['deactivated_at'] );
	}
}
