<?php
/**
 * CSV export of licenses and activations.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Domain\KeyGenerator;
use TWH\Install\Schema;
use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * Streams CSV files. Keys are masked (never exported in full).
 */
final class Export {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_export', array( self::class, 'handle' ) );
	}

	/**
	 * Handler.
	 */
	public static function handle(): void {
		Admin::guard( 'twh_export' );
		$type = sanitize_key( wp_unslash( $_GET['type'] ?? 'licenses' ) );
		if ( 'activations' === $type ) {
			self::activations();
		} else {
			self::licenses( array() );
		}
		exit;
	}

	/**
	 * Export licenses.
	 *
	 * @param int[] $ids Only these ids; empty = all.
	 */
	public static function licenses( array $ids ): void {
		global $wpdb;
		$table    = Schema::table( 'licenses' );
		$products = array();
		foreach ( Products::all() as $p ) {
			$products[ (int) $p['id'] ] = (string) $p['slug'];
		}
		self::headers( 'talkwyn-licenses-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'id', 'key', 'product', 'plan', 'status', 'customer_id', 'customer_email', 'order_id', 'subscription_id', 'activation_limit', 'sites_used', 'duration_days', 'features', 'expires_at', 'created_at' ) );

		$last = 0;
		do {
			$where = $ids ? ' AND l.id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '';
			$rows  = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT l.*, (SELECT COUNT(*) FROM ' . Schema::table( 'activations' ) . " a WHERE a.license_id = l.id AND a.deactivated_at IS NULL AND a.is_dev_site = 0) AS sites_used FROM {$table} l WHERE l.id > %d{$where} ORDER BY l.id ASC LIMIT 500",
					$last
				),
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				$last  = (int) $r['id'];
				$email = (string) $r['customer_email'];
				if ( '' === $email && (int) $r['customer_id'] ) {
					$user  = get_userdata( (int) $r['customer_id'] );
					$email = $user ? (string) $user->user_email : '';
				}
				fputcsv(
					$out,
					array_map(
						array( self::class, 'cell' ),
						array(
							$r['id'],
							KeyGenerator::mask( (string) $r['key_last4'] ),
							$products[ (int) $r['product_id'] ] ?? '',
							$r['plan_slug'],
							$r['status'],
							$r['customer_id'],
							$email,
							$r['order_id'],
							$r['subscription_id'],
							$r['activation_limit'],
							$r['sites_used'],
							$r['duration_days'],
							$r['features'],
							$r['expires_at'],
							$r['created_at'],
						)
					)
				);
			}
			$more = count( $rows ) === 500;
		} while ( $more );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Export activations.
	 */
	private static function activations(): void {
		global $wpdb;
		$table = Schema::table( 'activations' );
		self::headers( 'talkwyn-activations-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'id', 'license_id', 'instance_id', 'site_url', 'domain', 'is_dev_site', 'plugin_version', 'wp_version', 'php_version', 'activated_at', 'last_check_at', 'deactivated_at' ) );
		$last = 0;
		do {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 1000", $last ), ARRAY_A );
			foreach ( $rows as $r ) {
				$last = (int) $r['id'];
				fputcsv(
					$out,
					array_map(
						array( self::class, 'cell' ),
						array( $r['id'], $r['license_id'], $r['instance_id'], $r['site_url'], $r['domain_normalized'], $r['is_dev_site'], $r['plugin_version'], $r['wp_version'], $r['php_version'], $r['activated_at'], $r['last_check_at'], $r['deactivated_at'] )
					)
				);
			}
			$more = count( $rows ) === 1000;
		} while ( $more );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Download headers.
	 *
	 * @param string $filename File name.
	 */
	private static function headers( string $filename ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	}

	/**
	 * Neutralize spreadsheet formula injection.
	 *
	 * @param mixed $value Cell.
	 */
	public static function cell( $value ): string {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
