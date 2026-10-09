<?php
/**
 * Analytics dashboard and the unanswered questions inbox.
 *
 * Everything is computed from the chat logs and leads in this site's database.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Insights.
 */
final class Insights {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_talkwyn_pro_dismiss', array( self::class, 'dismiss' ) );
	}

	/**
	 * Numbers for the dashboard.
	 *
	 * @param int $days Period.
	 * @return array
	 */
	public static function stats( int $days = 30 ): array {
		global $wpdb;
		$t     = \Talkwyn_DB::tables();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		$per_day = (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_gmt) AS d, COUNT(DISTINCT session_id) AS c FROM %i WHERE role = 'user' AND created_gmt >= %s GROUP BY d ORDER BY d ASC", $t['logs'], $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$days_map = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$days_map[ gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = 0;
		}
		foreach ( $per_day as $row ) {
			if ( isset( $days_map[ $row['d'] ] ) ) {
				$days_map[ $row['d'] ] = (int) $row['c'];
			}
		}
		$chats = array_sum( $days_map );
		$leads = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE created_gmt >= %s", $t['leads'], $since ) ); // phpcs:ignore WordPress.DB

		$questions = (array) $wpdb->get_results( $wpdb->prepare( "SELECT message FROM %i WHERE role = 'user' AND created_gmt >= %s ORDER BY id DESC LIMIT 5000", $t['logs'], $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$top       = array();
		foreach ( $questions as $q ) {
			$text = trim( (string) $q['message'] );
			if ( '' === $text || 0 === strpos( \Talkwyn_Conversation::intent( $text ), 'social_' ) ) {
				continue;
			}
			$key = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', \Talkwyn_Text::lower( $text ) ) );
			if ( ! isset( $top[ $key ] ) ) {
				$top[ $key ] = array(
					'text'  => \Talkwyn_Text::sub( $text, 0, 140 ),
					'count' => 0,
				);
			}
			++$top[ $key ]['count'];
		}
		uasort(
			$top,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		$pages = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.page_url AS url, COUNT(*) AS c FROM %i l INNER JOIN (SELECT session_id, MIN(id) AS mid FROM %i WHERE role = 'user' AND created_gmt >= %s GROUP BY session_id) f ON f.mid = l.id GROUP BY l.page_url ORDER BY c DESC LIMIT 10", $t['logs'], $t['logs'], $since ), ARRAY_A ); // phpcs:ignore WordPress.DB

		$replies   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT provider, meta, response_ms, feedback FROM %i WHERE role = 'assistant' AND created_gmt >= %s ORDER BY id DESC LIMIT 10000", $t['logs'], $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$providers = array();
		$ai        = 0;
		$fallback  = 0;
		$ms_sum    = 0;
		$ms_n      = 0;
		$helpful   = 0;
		$unhelpful = 0;
		$ids       = array_keys( \Talkwyn_Providers::registry() );
		foreach ( $replies as $r ) {
			$p = (string) $r['provider'];
			if ( in_array( $p, $ids, true ) ) {
				++$ai;
				$providers[ $p ]['served'] = ( $providers[ $p ]['served'] ?? 0 ) + 1;
				if ( (int) $r['response_ms'] > 0 ) {
					$ms_sum += (int) $r['response_ms'];
					++$ms_n;
				}
			} elseif ( 'local' === $p ) {
				++$fallback;
			}
			$meta = json_decode( (string) $r['meta'], true );
			if ( is_array( $meta ) && ! empty( $meta['error'] ) ) {
				foreach ( $ids as $id ) {
					if ( false !== strpos( (string) $meta['error'], $id . ':' ) ) {
						$providers[ $id ]['failed'] = ( $providers[ $id ]['failed'] ?? 0 ) + 1;
					}
				}
			}
			if ( 'helpful' === $r['feedback'] ) {
				++$helpful;
			} elseif ( 'not_helpful' === $r['feedback'] ) {
				++$unhelpful;
			}
		}
		foreach ( $providers as $id => $p ) {
			$served                     = (int) ( $p['served'] ?? 0 );
			$failed                     = (int) ( $p['failed'] ?? 0 );
			$providers[ $id ]['served'] = $served;
			$providers[ $id ]['failed'] = $failed;
			$providers[ $id ]['rate']   = $served + $failed > 0 ? $served / ( $served + $failed ) : 0;
		}
		return array(
			'days'      => $days_map,
			'chats'     => $chats,
			'leads'     => $leads,
			'lead_rate' => $chats > 0 ? $leads / $chats : 0,
			'top'       => array_slice( array_values( $top ), 0, 10 ),
			'pages'     => $pages,
			'providers' => $providers,
			'ai_rate'   => $ai + $fallback > 0 ? $ai / ( $ai + $fallback ) : 0,
			'avg_ms'    => $ms_n ? (int) round( $ms_sum / $ms_n ) : 0,
			'helpful'   => $helpful,
			'unhelpful' => $unhelpful,
		);
	}

	/**
	 * Questions the bot could not answer or that got "not helpful".
	 *
	 * @param int $limit Limit.
	 * @return array[]
	 */
	public static function inbox( int $limit = 100 ): array {
		global $wpdb;
		$t    = \Talkwyn_DB::tables();
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created_gmt, session_id, message, page_url, meta, feedback FROM %i WHERE role = 'assistant' AND ( meta LIKE %s OR feedback = 'not_helpful' ) AND meta NOT LIKE %s ORDER BY id DESC LIMIT %d", $t['logs'], '%"answered":0%', '%"resolved":1%', $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( $rows as $row ) {
			$meta     = json_decode( (string) $row['meta'], true );
			$question = is_array( $meta ) ? (string) ( $meta['question'] ?? '' ) : '';
			if ( '' === $question ) {
				$question = (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM %i WHERE session_id = %s AND role = 'user' AND id < %d ORDER BY id DESC LIMIT 1", $t['logs'], $row['session_id'], $row['id'] ) ); // phpcs:ignore WordPress.DB
			}
			$out[] = array(
				'id'       => (int) $row['id'],
				'date'     => $row['created_gmt'],
				'question' => $question,
				'reply'    => (string) $row['message'],
				'page'     => (string) $row['page_url'],
				'reason'   => 'not_helpful' === $row['feedback'] ? 'not_helpful' : 'unanswered',
			);
		}
		return $out;
	}

	/**
	 * Mark an inbox item done.
	 *
	 * @param int $log_id Log ID.
	 */
	public static function resolve( int $log_id ): void {
		global $wpdb;
		$t    = \Talkwyn_DB::tables();
		$meta = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM %i WHERE id = %d", $t['logs'], $log_id ) ), true ); // phpcs:ignore WordPress.DB
		$meta = is_array( $meta ) ? $meta : array();
		$meta['resolved'] = 1;
		$wpdb->update( $t['logs'], array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $log_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Dismiss an inbox item.
	 */
	public static function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn-pro' ) );
		}
		$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'talkwyn_pro_dismiss_' . $id );
		self::resolve( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=talkwyn&tab=inbox' ) );
		exit;
	}
}
