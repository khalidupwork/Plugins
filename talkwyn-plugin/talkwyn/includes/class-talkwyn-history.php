<?php
/**
 * Server-side conversation history, keyed by the visitor's session ID.
 *
 * The model only ever sees this history. Nothing the browser sends as
 * "history" is trusted.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conversation history store.
 */
class Talkwyn_History {

	const MAX_ENTRIES = 40;

	/**
	 * Valid session ID (UUID-like, 16 to 64 characters).
	 *
	 * @param string $session Session ID.
	 * @return string Empty when invalid.
	 */
	public static function clean_session( $session ) {
		$session = strtolower( trim( (string) $session ) );
		return preg_match( '/^[a-z0-9][a-z0-9\-]{15,63}$/', $session ) ? $session : '';
	}

	/**
	 * New session ID.
	 *
	 * @return string
	 */
	public static function new_session() {
		return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Storage key.
	 *
	 * @param string $session Session ID.
	 * @return string
	 */
	private static function key( $session ) {
		return 'talkwyn_h_' . md5( $session );
	}

	/**
	 * How long a conversation is kept for continuity, in seconds.
	 *
	 * @return int
	 */
	public static function ttl() {
		return (int) apply_filters( 'talkwyn_history_ttl', DAY_IN_SECONDS );
	}

	/**
	 * All entries for a session.
	 *
	 * @param string $session Session ID.
	 * @return array[] Each: role, content, id, sources, time, kind.
	 */
	public static function get( $session ) {
		$session = self::clean_session( $session );
		if ( '' === $session ) {
			return array();
		}
		$data = get_transient( self::key( $session ) );
		return is_array( $data ) ? array_values( $data ) : array();
	}

	/**
	 * Append entries.
	 *
	 * @param string  $session Session ID.
	 * @param array[] $entries Entries (role, content, optional id, sources, kind).
	 * @return void
	 */
	public static function add( $session, array $entries ) {
		$session = self::clean_session( $session );
		if ( '' === $session ) {
			return;
		}
		$all = self::get( $session );
		foreach ( $entries as $entry ) {
			$role = in_array( $entry['role'] ?? '', array( 'user', 'assistant' ), true ) ? $entry['role'] : 'user';
			$all[] = array(
				'role'    => $role,
				'content' => Talkwyn_Text::sub( (string) ( $entry['content'] ?? '' ), 0, 6000 ),
				'id'      => (int) ( $entry['id'] ?? 0 ),
				'sources' => array_slice( (array) ( $entry['sources'] ?? array() ), 0, 4 ),
				'kind'    => (string) ( $entry['kind'] ?? 'message' ),
				'extra'   => (array) ( $entry['extra'] ?? array() ),
				'time'    => time(),
			);
		}
		$all = array_slice( $all, -self::MAX_ENTRIES );
		set_transient( self::key( $session ), $all, self::ttl() );
	}

	/**
	 * Messages for the model: the last $max chat messages as role and content.
	 *
	 * @param string $session Session ID.
	 * @param int    $max     Maximum messages.
	 * @return array[]
	 */
	public static function for_model( $session, $max ) {
		$out = array();
		foreach ( self::get( $session ) as $entry ) {
			if ( 'message' !== $entry['kind'] || '' === trim( $entry['content'] ) ) {
				continue;
			}
			$out[] = array(
				'role'    => $entry['role'],
				'content' => Talkwyn_Text::sub( $entry['content'], 0, 3000 ),
			);
		}
		return $max > 0 ? array_slice( $out, -$max ) : array();
	}

	/**
	 * Forget a session.
	 *
	 * @param string $session Session ID.
	 * @return void
	 */
	public static function clear( $session ) {
		$session = self::clean_session( $session );
		if ( '' !== $session ) {
			delete_transient( self::key( $session ) );
		}
	}

	/**
	 * Flags stored with the session (lead offered, declined, submitted).
	 *
	 * @param string $session Session ID.
	 * @return array<string, mixed>
	 */
	public static function flags( $session ) {
		$session = self::clean_session( $session );
		$data    = '' === $session ? false : get_transient( 'talkwyn_f_' . md5( $session ) );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Set a flag.
	 *
	 * @param string $session Session ID.
	 * @param string $flag    Flag.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	public static function set_flag( $session, $flag, $value ) {
		$session = self::clean_session( $session );
		if ( '' === $session ) {
			return;
		}
		$flags          = self::flags( $session );
		$flags[ $flag ] = $value;
		set_transient( 'talkwyn_f_' . md5( $session ), $flags, self::ttl() );
	}
}
