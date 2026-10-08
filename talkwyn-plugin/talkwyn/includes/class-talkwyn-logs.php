<?php
/**
 * Chat logs (stored only when the owner keeps logging on).
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log writer.
 */
class Talkwyn_Logs {

	/**
	 * Insert a message.
	 *
	 * @param string $session  Session ID.
	 * @param string $role     user or assistant.
	 * @param string $message  Text.
	 * @param string $provider Provider ID.
	 * @param string $page_url Page URL.
	 * @param array  $meta     Extra data.
	 * @param int    $ms       Response time.
	 * @return int Row ID, 0 when logging is off.
	 */
	public static function add( $session, $role, $message, $provider = '', $page_url = '', array $meta = array(), $ms = 0 ) {
		if ( ! Talkwyn_Settings::get( 'logs_enabled' ) ) {
			return 0;
		}
		global $wpdb;
		$t  = Talkwyn_DB::tables();
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t['logs'],
			array(
				'created_gmt' => current_time( 'mysql', true ),
				'session_id'  => $session,
				'role'        => $role,
				'message'     => $message,
				'provider'    => substr( (string) $provider, 0, 40 ),
				'page_url'    => $page_url,
				'meta'        => $meta ? wp_json_encode( $meta ) : '',
				'feedback'    => '',
				'response_ms' => max( 0, (int) $ms ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Save helpful or not helpful.
	 *
	 * @param int    $id       Log ID.
	 * @param string $session  Session ID (must own the message).
	 * @param string $feedback helpful|not_helpful.
	 * @return bool
	 */
	public static function feedback( $id, $session, $feedback ) {
		global $wpdb;
		$t       = Talkwyn_DB::tables();
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t['logs'],
			array( 'feedback' => $feedback ),
			array(
				'id'         => absint( $id ),
				'session_id' => $session,
				'role'       => 'assistant',
			),
			array( '%s' ),
			array( '%d', '%s', '%s' )
		);
		return false !== $updated && $updated > 0;
	}
}
