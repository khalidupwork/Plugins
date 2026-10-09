<?php
/**
 * One-time migration from Nabia AI Chatbot (nac_* options and tables).
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Copies settings, knowledge, leads and logs, then offers to remove the old data.
 */
class Talkwyn_Migration {

	const DONE_OPTION = 'talkwyn_migrated_from_nabia';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_talkwyn_delete_nabia', array( __CLASS__, 'delete_old' ) );
		add_action( 'admin_post_talkwyn_dismiss_nabia', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Old table names.
	 *
	 * @return array<string, string>
	 */
	public static function old_tables() {
		global $wpdb;
		return array(
			'chunks' => $wpdb->prefix . 'nac_chunks',
			'leads'  => $wpdb->prefix . 'nac_leads',
			'logs'   => $wpdb->prefix . 'nac_logs',
		);
	}

	/**
	 * Whether Nabia data exists.
	 *
	 * @return bool
	 */
	public static function has_old_data() {
		global $wpdb;
		if ( false !== get_option( 'nac_settings', false ) ) {
			return true;
		}
		$t = self::old_tables();
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t['leads'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Run once, for an administrator, when old data exists.
	 *
	 * @return void
	 */
	public static function maybe_run() {
		if ( get_option( self::DONE_OPTION ) || ! current_user_can( 'manage_options' ) || ! self::has_old_data() ) {
			return;
		}
		self::run();
	}

	/**
	 * Map old settings to the new keys and values.
	 *
	 * @param array $old Old nac_settings.
	 * @return array
	 */
	public static function map_settings( array $old ) {
		$known = Talkwyn_Settings::defaults();
		$out   = array();
		foreach ( $old as $key => $value ) {
			if ( array_key_exists( $key, $known ) ) {
				$out[ $key ] = $value;
			}
		}
		$avatars = array(
			'ai'       => 'initials',
			'robot'    => 'talkwyn',
			'sparkles' => 'talkwyn',
			'headset'  => 'headset',
			'chat'     => 'chat',
			'custom'   => 'custom',
		);
		if ( isset( $old['avatar_type'] ) ) {
			$out['avatar_type'] = isset( $avatars[ $old['avatar_type'] ] ) ? $avatars[ $old['avatar_type'] ] : 'talkwyn';
		}
		$icons = array(
			'spark_chat' => 'spark_chat',
			'chat_dots'  => 'chat_dots',
			'headset'    => 'headset',
			'question'   => 'question',
		);
		if ( isset( $old['launcher_icon'] ) ) {
			$out['launcher_icon'] = isset( $icons[ $old['launcher_icon'] ] ) ? $icons[ $old['launcher_icon'] ] : 'talkwyn';
		}
		// Old default colour moves to the Talkwyn default; a custom colour is kept.
		if ( isset( $old['brand_color'] ) && in_array( strtolower( (string) $old['brand_color'] ), array( '#111111', '#111' ), true ) ) {
			unset( $out['brand_color'] );
		}
		if ( isset( $old['typing_label'] ) && 'AI is typing' === $old['typing_label'] ) {
			unset( $out['typing_label'] );
		}
		// Public custom fields are opt-in now, with an allow list.
		$out['index_custom_fields'] = 0;
		if ( isset( $out['retention_days'] ) ) {
			$out['retention_days'] = max( 1, absint( $out['retention_days'] ) );
		}
		return $out;
	}

	/**
	 * Copy everything.
	 *
	 * @return array<string, int> Rows copied per table.
	 */
	public static function run() {
		global $wpdb;
		Talkwyn_DB::install();
		$copied = array(
			'chunks' => 0,
			'leads'  => 0,
			'logs'   => 0,
		);

		$old = get_option( 'nac_settings', array() );
		if ( is_array( $old ) && $old ) {
			Talkwyn_Settings::update( self::map_settings( $old ) );
		}

		$old_t = self::old_tables();
		$new_t = Talkwyn_DB::tables();
		$cols  = array(
			'chunks' => array( 'source_key', 'source_type', 'source_id', 'source_url', 'source_lang', 'title', 'chunk_text', 'checksum', 'modified_gmt' ),
			'leads'  => array( 'created_gmt', 'session_id', 'name', 'email', 'phone', 'message', 'page_url', 'status' ),
			'logs'   => array( 'created_gmt', 'session_id', 'role', 'message', 'provider', 'page_url', 'meta', 'feedback' ),
		);
		foreach ( $cols as $key => $list ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_t[ $key ] ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}
			$ids    = implode( ', ', array_fill( 0, count( $list ), '%i' ) );
			$sql    = "INSERT INTO %i ({$ids}) SELECT {$ids} FROM %i";
			$result = $wpdb->query( $wpdb->prepare( $sql, array_merge( array( $new_t[ $key ] ), $list, $list, array( $old_t[ $key ] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql only holds %i placeholders.
			$copied[ $key ] = (int) $result;
		}

		update_option(
			self::DONE_OPTION,
			array(
				'time'   => time(),
				'copied' => $copied,
			),
			false
		);
		update_option( 'talkwyn_nabia_notice', 1, false );

		/**
		 * Fires after Nabia data was copied.
		 *
		 * @param array $copied Rows copied per table.
		 */
		do_action( 'talkwyn_migrated_from_nabia', $copied );
		return $copied;
	}

	/**
	 * Admin notice after migration.
	 *
	 * @return void
	 */
	public static function notice() {
		if ( ! get_option( 'talkwyn_nabia_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$done   = get_option( self::DONE_OPTION, array() );
		$copied = isset( $done['copied'] ) ? (array) $done['copied'] : array();
		$delete = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_delete_nabia' ), 'talkwyn_delete_nabia' );
		$keep   = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_dismiss_nabia' ), 'talkwyn_dismiss_nabia' );
		echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Talkwyn imported your Nabia AI Chatbot data.', 'talkwyn' ) . '</strong> ';
		printf(
			/* translators: 1: knowledge chunks, 2: leads, 3: chat messages */
			esc_html__( 'Copied your settings plus knowledge chunks: %1$d, leads: %2$d, chat messages: %3$d.', 'talkwyn' ),
			(int) ( $copied['chunks'] ?? 0 ),
			(int) ( $copied['leads'] ?? 0 ),
			(int) ( $copied['logs'] ?? 0 )
		);
		echo '</p>';
		if ( self::nabia_active() ) {
			echo '<p>' . esc_html__( 'Deactivate Nabia AI Chatbot so visitors see only one chat widget.', 'talkwyn' ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( $delete ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete the old Nabia tables and settings? Talkwyn keeps its own copy.', 'talkwyn' ) ) . '\')">' . esc_html__( 'Delete old Nabia data', 'talkwyn' ) . '</a> <a class="button" href="' . esc_url( $keep ) . '">' . esc_html__( 'Keep it for now', 'talkwyn' ) . '</a></p></div>';
	}

	/**
	 * Whether Nabia AI Chatbot is still active.
	 *
	 * @return bool
	 */
	private static function nabia_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'nabia-ai-chatbot/nabia-ai-chatbot.php' );
	}

	/**
	 * Delete the old tables and options.
	 *
	 * @return void
	 */
	public static function delete_old() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_delete_nabia' );
		// Nabia recreates its tables while active, so switch it off first.
		if ( self::nabia_active() ) {
			deactivate_plugins( 'nabia-ai-chatbot/nabia-ai-chatbot.php' );
		}
		global $wpdb;
		foreach ( self::old_tables() as $table ) {
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		foreach ( array( 'nac_settings', 'nac_db_version', 'nac_do_onboarding' ) as $option ) {
			delete_option( $option );
		}
		delete_transient( 'nac_last_good_provider' );
		wp_clear_scheduled_hook( 'nac_daily_cleanup' );
		delete_option( 'talkwyn_nabia_notice' );
		wp_safe_redirect( admin_url( 'admin.php?page=talkwyn&updated=1' ) );
		exit;
	}

	/**
	 * Hide the notice and keep the old data.
	 *
	 * @return void
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_dismiss_nabia' );
		delete_option( 'talkwyn_nabia_notice' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=talkwyn' ) );
		exit;
	}
}

