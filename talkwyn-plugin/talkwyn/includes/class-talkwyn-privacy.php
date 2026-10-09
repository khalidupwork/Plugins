<?php
/**
 * Personal data export and erase (Tools > Export / Erase Personal Data).
 *
 * Leads are found by email. Chat logs are found through the sessions of those leads.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tools.
 */
class Talkwyn_Privacy {

	const PAGE = 50;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['talkwyn'] = array(
			'exporter_friendly_name' => __( 'Talkwyn chat leads and messages', 'talkwyn' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['talkwyn'] = array(
			'eraser_friendly_name' => __( 'Talkwyn chat leads and messages', 'talkwyn' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Leads for an email.
	 *
	 * @param string $email Email.
	 * @return array[]
	 */
	private static function leads( $email ) {
		global $wpdb;
		$t = Talkwyn_DB::tables();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE email = %s ORDER BY id ASC", $t['leads'], $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$t     = Talkwyn_DB::tables();
		$items = array();
		$leads = self::leads( $email );
		if ( 1 === (int) $page ) {
			foreach ( $leads as $lead ) {
				$items[] = array(
					'group_id'    => 'talkwyn-leads',
					'group_label' => __( 'Chat leads', 'talkwyn' ),
					'item_id'     => 'talkwyn-lead-' . $lead['id'],
					'data'        => array(
						array(
							'name'  => __( 'Date (GMT)', 'talkwyn' ),
							'value' => $lead['created_gmt'],
						),
						array(
							'name'  => __( 'Name', 'talkwyn' ),
							'value' => $lead['name'],
						),
						array(
							'name'  => __( 'Email', 'talkwyn' ),
							'value' => $lead['email'],
						),
						array(
							'name'  => __( 'Phone', 'talkwyn' ),
							'value' => $lead['phone'],
						),
						array(
							'name'  => __( 'Question', 'talkwyn' ),
							'value' => $lead['message'],
						),
						array(
							'name'  => __( 'Page', 'talkwyn' ),
							'value' => $lead['page_url'],
						),
					),
				);
			}
		}
		$sessions = array_values( array_unique( array_filter( wp_list_pluck( $leads, 'session_id' ) ) ) );
		$done     = true;
		if ( $sessions ) {
			$holder = implode( ',', array_fill( 0, count( $sessions ), '%s' ) );
			$offset = ( max( 1, (int) $page ) - 1 ) * self::PAGE;
			$rows   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE session_id IN ({$holder}) ORDER BY id ASC LIMIT %d OFFSET %d", array_merge( array( $t['logs'] ), $sessions, array( self::PAGE, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $holder is a list of %s placeholders.
			foreach ( $rows as $row ) {
				$items[] = array(
					'group_id'    => 'talkwyn-messages',
					'group_label' => __( 'Chat messages', 'talkwyn' ),
					'item_id'     => 'talkwyn-message-' . $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Date (GMT)', 'talkwyn' ),
							'value' => $row['created_gmt'],
						),
						array(
							'name'  => __( 'From', 'talkwyn' ),
							'value' => 'user' === $row['role'] ? __( 'Visitor', 'talkwyn' ) : __( 'Assistant', 'talkwyn' ),
						),
						array(
							'name'  => __( 'Message', 'talkwyn' ),
							'value' => $row['message'],
						),
					),
				);
			}
			$done = count( $rows ) < self::PAGE;
		}
		return array(
			'data' => $items,
			'done' => $done,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;
		$t        = Talkwyn_DB::tables();
		$leads    = self::leads( $email );
		$removed  = 0;
		$sessions = array_values( array_unique( array_filter( wp_list_pluck( $leads, 'session_id' ) ) ) );
		foreach ( $sessions as $session ) {
			$removed += (int) $wpdb->delete( $t['logs'], array( 'session_id' => $session ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Talkwyn_History::clear( $session );
		}
		$removed += (int) $wpdb->delete( $t['leads'], array( 'email' => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires after a visitor's data was erased.
		 *
		 * @param string   $email    Email.
		 * @param string[] $sessions Session IDs.
		 */
		do_action( 'talkwyn_personal_data_erased', $email, $sessions );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 *
	 * @return void
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . __( 'When you use the chat on this website, your messages and the page you were on are stored so we can answer you and improve the answers. Messages are kept for a limited time and then deleted automatically.', 'talkwyn' ) . '</p>'
			. '<p>' . __( 'To answer, your message and relevant text from this website are sent to the AI provider the site owner configured (for example Groq, OpenRouter, Google Gemini or Cloudflare Workers AI).', 'talkwyn' ) . '</p>'
			. '<p>' . __( 'If you share your name, email or phone number in the chat form, we store them to reply to you. You can ask us to export or delete this data.', 'talkwyn' ) . '</p>';
		wp_add_privacy_policy_content( 'Talkwyn', wp_kses_post( $text ) );
	}
}
