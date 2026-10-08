<?php
/**
 * Settings export and import (JSON), for agencies moving a setup between sites.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Export and import.
 */
final class Transfer {

	/**
	 * Keys that hold secrets.
	 *
	 * @var string[]
	 */
	const SECRETS = array( 'groq_key', 'gemini_key', 'openrouter_key', 'cloudflare_token', 'cloudflare_account_id', 'openai_key', 'anthropic_key', 'mistral_key', 'deepseek_key', 'turnstile_secret', 'pro_telegram_token', 'pro_slack_webhook' );

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_talkwyn_pro_export', array( self::class, 'export' ) );
		add_action( 'admin_post_talkwyn_pro_import', array( self::class, 'import' ) );
	}

	/**
	 * Export payload. Pure apart from reading settings and Q&A.
	 *
	 * @param bool $with_secrets Include API keys.
	 * @return array
	 */
	public static function payload( bool $with_secrets ): array {
		$settings = (array) get_option( \Talkwyn_Settings::OPTION, array() );
		if ( ! $with_secrets ) {
			foreach ( self::SECRETS as $key ) {
				unset( $settings[ $key ] );
			}
		}
		// Page IDs do not carry over to another site.
		unset( $settings['hidden_page_ids'] );
		$qa = array();
		foreach ( Knowledge::qa_all() as $row ) {
			$qa[] = array(
				'question' => $row['question'],
				'answer'   => $row['answer'],
				'keywords' => $row['keywords'],
			);
		}
		return array(
			'format'      => 'talkwyn-settings',
			'version'     => 1,
			'exported_at' => gmdate( 'c' ),
			'settings'    => $settings,
			'answers'     => $qa,
		);
	}

	/**
	 * Download JSON.
	 */
	public static function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn-pro' ) );
		}
		check_admin_referer( 'talkwyn_pro_export' );
		$with = ! empty( $_POST['with_secrets'] );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=talkwyn-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( self::payload( $with ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Import JSON.
	 */
	public static function import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn-pro' ) );
		}
		check_admin_referer( 'talkwyn_pro_import' );
		$back = admin_url( 'admin.php?page=talkwyn&tab=pro' );
		$tmp  = isset( $_FILES['file']['tmp_name'] ) ? (string) $_FILES['file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_safe_redirect( add_query_arg( 'talkwyn_notice', 'nofile', $back ) );
			exit;
		}
		$data = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $data ) || 'talkwyn-settings' !== ( $data['format'] ?? '' ) || ! is_array( $data['settings'] ?? null ) ) {
			wp_safe_redirect( add_query_arg( 'talkwyn_notice', 'badfile', $back ) );
			exit;
		}
		$schema = \Talkwyn_Settings::schema();
		$clean  = \Talkwyn_Settings::sanitize( $data['settings'], array_intersect( $schema['bool'], array_keys( $data['settings'] ) ) );
		\Talkwyn_Settings::update( $clean );
		if ( ! empty( $_POST['with_answers'] ) && is_array( $data['answers'] ?? null ) ) {
			global $wpdb;
			$t   = Installer::tables()['qa'];
			$now = current_time( 'mysql', true );
			foreach ( $data['answers'] as $row ) {
				$q = sanitize_textarea_field( (string) ( $row['question'] ?? '' ) );
				$a = sanitize_textarea_field( (string) ( $row['answer'] ?? '' ) );
				if ( '' === $q || '' === $a ) {
					continue;
				}
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$t,
					array(
						'question'    => $q,
						'answer'      => $a,
						'keywords'    => sanitize_text_field( (string) ( $row['keywords'] ?? '' ) ),
						'created_gmt' => $now,
						'updated_gmt' => $now,
					)
				);
				$id = (int) $wpdb->insert_id;
				\Talkwyn_Indexer::store_chunks( 'qa:' . $id, 'qa', $id, '', $q, 'Question: ' . $q . "\nAnswer: " . $a, $now, '' );
			}
		}
		wp_safe_redirect( add_query_arg( 'talkwyn_notice', 'imported', $back ) );
		exit;
	}
}
