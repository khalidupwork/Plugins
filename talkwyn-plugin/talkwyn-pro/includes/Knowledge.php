<?php
/**
 * Extra knowledge: custom answers that always win, file uploads (PDF, DOCX,
 * TXT) and pages crawled from a URL or sitemap.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge sources.
 */
final class Knowledge {

	const CRAWL_QUEUE = 'talkwyn_pro_crawl_queue';
	const CRAWL_CRON  = 'talkwyn_pro_crawl';
	const MATCH_MIN   = 0.72;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_pre_reply', array( self::class, 'pre_reply' ), 5, 2 );
		add_action( 'admin_post_talkwyn_pro_qa_save', array( self::class, 'qa_save' ) );
		add_action( 'admin_post_talkwyn_pro_qa_delete', array( self::class, 'qa_delete' ) );
		add_action( 'admin_post_talkwyn_pro_upload', array( self::class, 'upload' ) );
		add_action( 'admin_post_talkwyn_pro_crawl', array( self::class, 'crawl_request' ) );
		add_action( 'admin_post_talkwyn_pro_source_delete', array( self::class, 'source_delete' ) );
		add_action( 'admin_post_talkwyn_pro_source_refresh', array( self::class, 'source_refresh' ) );
		add_action( self::CRAWL_CRON, array( self::class, 'crawl_cron' ) );
	}

	/* ---------- Custom answers ---------- */

	/**
	 * All custom answers.
	 *
	 * @return array[]
	 */
	public static function qa_all(): array {
		global $wpdb;
		$t = Installer::tables()['qa'];
		return (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * How well a message matches a custom question (0 to 1). Pure, unit tested.
	 *
	 * @param string $message  Visitor message.
	 * @param string $question Saved question.
	 * @param string $keywords Extra keywords (comma separated).
	 */
	public static function match_score( string $message, string $question, string $keywords = '' ): float {
		$norm = static function ( string $s ): string {
			return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', \Talkwyn_Text::lower( $s ) ) );
		};
		$m = $norm( $message );
		$q = $norm( $question );
		if ( '' === $m || '' === $q ) {
			return 0.0;
		}
		if ( $m === $q ) {
			return 1.0;
		}
		$mt = \Talkwyn_Text::tokens( $m, 40 );
		$qt = \Talkwyn_Text::tokens( $q, 40 );
		if ( ! $mt || ! $qt ) {
			return 0.0;
		}
		$common = count( array_intersect( $mt, $qt ) );
		// Share of the question's words found in the message, weighted with the reverse.
		$score = 0.7 * ( $common / count( $qt ) ) + 0.3 * ( $common / count( $mt ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', \Talkwyn_Text::lower( $keywords ) ) ) ) as $kw ) {
			if ( '' !== $kw && false !== strpos( ' ' . $m . ' ', ' ' . $norm( $kw ) . ' ' ) ) {
				$score = max( $score, 0.85 );
			}
		}
		return min( 1.0, $score );
	}

	/**
	 * Answer directly from a custom answer when it clearly matches.
	 *
	 * @param array|null $preset Preset.
	 * @param array      $ctx    Turn context.
	 * @return array|null
	 */
	public static function pre_reply( $preset, $ctx ) {
		if ( is_array( $preset ) || ! empty( $ctx['is_social'] ) ) {
			return $preset;
		}
		$best  = null;
		$score = 0.0;
		foreach ( self::qa_all() as $qa ) {
			$s = self::match_score( (string) $ctx['message'], (string) $qa['question'], (string) $qa['keywords'] );
			if ( $s > $score ) {
				$score = $s;
				$best  = $qa;
			}
		}
		if ( ! $best || $score < (float) apply_filters( 'talkwyn_pro_qa_threshold', self::MATCH_MIN ) ) {
			return $preset;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Installer::tables()['qa'] . ' SET hits = hits + 1 WHERE id = %d', (int) $best['id'] ) ); // phpcs:ignore WordPress.DB
		return array(
			'text'     => (string) $best['answer'],
			'provider' => 'custom-answer',
		);
	}

	/**
	 * Index a custom answer as knowledge too, so close questions find it.
	 *
	 * @param int $id QA ID.
	 */
	private static function qa_index( int $id ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Installer::tables()['qa'] . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		\Talkwyn_Indexer::remove_source( 'qa:' . $id );
		if ( $row ) {
			\Talkwyn_Indexer::store_chunks( 'qa:' . $id, 'qa', $id, '', (string) $row['question'], 'Question: ' . $row['question'] . "\nAnswer: " . $row['answer'], current_time( 'mysql', true ), '' );
			do_action( 'talkwyn_pro_sources_changed' );
		}
	}

	/**
	 * Save a custom answer.
	 */
	public static function qa_save(): void {
		self::guard( 'talkwyn_pro_qa_save' );
		global $wpdb;
		$t        = Installer::tables()['qa'];
		$id       = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() checks it.
		$question = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$answer   = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$keywords = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$log_id   = absint( $_POST['resolve_log'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $question || '' === $answer ) {
			self::back( 'extra', 'missing' );
		}
		$now = current_time( 'mysql', true );
		if ( $id ) {
			$wpdb->update( $t, compact( 'question', 'answer', 'keywords' ) + array( 'updated_gmt' => $now ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $t, compact( 'question', 'answer', 'keywords' ) + array( 'created_gmt' => $now, 'updated_gmt' => $now ) ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}
		self::qa_index( $id );
		if ( $log_id ) {
			Insights::resolve( $log_id );
			self::back( 'inbox', 'answered' );
		}
		self::back( 'extra', 'saved' );
	}

	/**
	 * Delete a custom answer.
	 */
	public static function qa_delete(): void {
		self::guard( 'talkwyn_pro_qa_delete' );
		global $wpdb;
		$id = absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->delete( Installer::tables()['qa'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		\Talkwyn_Indexer::remove_source( 'qa:' . $id );
		self::back( 'extra', 'deleted' );
	}

	/* ---------- Sources ---------- */

	/**
	 * All extra sources.
	 *
	 * @return array[]
	 */
	public static function sources(): array {
		global $wpdb;
		$t = Installer::tables()['sources'];
		return (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Save a source and its chunks.
	 *
	 * @param string $type  file|url.
	 * @param string $ref   Attachment ID or URL.
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @param string $link  Link shown under answers.
	 * @param int    $id    Existing source ID.
	 * @return int Source ID.
	 */
	public static function store( string $type, string $ref, string $title, string $text, string $link, int $id = 0 ): int {
		global $wpdb;
		$t   = Installer::tables()['sources'];
		$now = current_time( 'mysql', true );
		if ( ! $id ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE type = %s AND ref = %s", $type, $ref ) ); // phpcs:ignore WordPress.DB
		}
		$row = array(
			'type'        => $type,
			'ref'         => $ref,
			'title'       => $title,
			'status'      => '' === trim( $text ) ? 'empty' : 'ok',
			'message'     => '',
			'updated_gmt' => $now,
		);
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}
		$key = 'src:' . $id;
		\Talkwyn_Indexer::remove_source( $key );
		$chunks = '' === trim( $text ) ? 0 : \Talkwyn_Indexer::store_chunks( $key, $type, $id, $link, $title, \Talkwyn_Text::normalize( $text ), $now, '' );
		$wpdb->update( $t, array( 'chunks' => $chunks ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		do_action( 'talkwyn_pro_sources_changed' );
		return $id;
	}

	/**
	 * Upload a PDF, DOCX or TXT file.
	 */
	public static function upload(): void {
		self::guard( 'talkwyn_pro_upload' );
		if ( empty( $_FILES['file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::back( 'extra', 'nofile' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$mimes = array(
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'  => 'text/plain',
		);
		$att   = media_handle_upload(
			'file',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);
		if ( is_wp_error( $att ) ) {
			self::back( 'extra', 'upload', $att->get_error_message() );
		}
		self::index_attachment( (int) $att );
		self::back( 'extra', 'uploaded' );
	}

	/**
	 * Extract and store an attachment.
	 *
	 * @param int $att Attachment ID.
	 * @param int $id  Existing source ID.
	 */
	private static function index_attachment( int $att, int $id = 0 ): void {
		$path  = (string) get_attached_file( $att );
		$title = get_the_title( $att );
		try {
			$text = Extract::file( $path );
		} catch ( \Exception $e ) {
			$text = '';
		}
		$sid = self::store( 'file', (string) $att, $title, $text, (string) wp_get_attachment_url( $att ), $id );
		if ( '' === trim( $text ) ) {
			global $wpdb;
			$wpdb->update( Installer::tables()['sources'], array( 'message' => __( 'No text found. Scanned PDFs are images; export a text PDF or upload a DOCX.', 'talkwyn-pro' ) ), array( 'id' => $sid ) ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Crawl a URL or sitemap.
	 */
	public static function crawl_request(): void {
		self::guard( 'talkwyn_pro_crawl' );
		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_http_validate_url( $url ) ) {
			self::back( 'extra', 'badurl' );
		}
		$queue = self::expand( $url );
		$now   = array_splice( $queue, 0, 10 );
		foreach ( $now as $page ) {
			self::crawl_one( $page );
		}
		if ( $queue ) {
			update_option( self::CRAWL_QUEUE, array_values( array_unique( array_merge( (array) get_option( self::CRAWL_QUEUE, array() ), $queue ) ) ), false );
			if ( ! wp_next_scheduled( self::CRAWL_CRON ) ) {
				wp_schedule_single_event( time() + 60, self::CRAWL_CRON );
			}
		}
		self::back( 'extra', $queue ? 'queued' : 'crawled' );
	}

	/**
	 * A sitemap becomes its page URLs (up to 200); a page stays itself.
	 *
	 * @param string $url URL.
	 * @return string[]
	 */
	public static function expand( string $url ): array {
		$res = wp_safe_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return array( $url );
		}
		$body = (string) wp_remote_retrieve_body( $res );
		if ( false === stripos( substr( $body, 0, 500 ), '<urlset' ) && false === stripos( substr( $body, 0, 500 ), '<sitemapindex' ) && false === stripos( $body, '<urlset' ) && false === stripos( $body, '<sitemapindex' ) ) {
			return array( $url );
		}
		$map  = Extract::sitemap( $body );
		$urls = $map['urls'];
		foreach ( array_slice( $map['sitemaps'], 0, 10 ) as $child ) {
			$r = wp_safe_remote_get( $child, array( 'timeout' => 15 ) );
			if ( ! is_wp_error( $r ) ) {
				$urls = array_merge( $urls, Extract::sitemap( (string) wp_remote_retrieve_body( $r ) )['urls'] );
			}
			if ( count( $urls ) >= 200 ) {
				break;
			}
		}
		return array_slice( array_values( array_unique( $urls ) ), 0, (int) apply_filters( 'talkwyn_pro_crawl_limit', 200 ) );
	}

	/**
	 * Fetch one page.
	 *
	 * @param string $url URL.
	 * @param int    $id  Existing source ID.
	 */
	public static function crawl_one( string $url, int $id = 0 ): void {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'TalkwynBot/1.0 (+' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			$sid = self::store( 'url', $url, $url, '', $url, $id );
			global $wpdb;
			$wpdb->update( Installer::tables()['sources'], array( 'status' => 'error', 'message' => is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) ), array( 'id' => $sid ) ); // phpcs:ignore WordPress.DB
			return;
		}
		$page = Extract::html( (string) wp_remote_retrieve_body( $res ) );
		self::store( 'url', $url, '' !== $page['title'] ? $page['title'] : $url, $page['text'], $url, $id );
	}

	/**
	 * Cron: crawl the next queued pages.
	 */
	public static function crawl_cron(): void {
		$queue = (array) get_option( self::CRAWL_QUEUE, array() );
		$now   = array_splice( $queue, 0, 10 );
		foreach ( $now as $url ) {
			self::crawl_one( (string) $url );
		}
		update_option( self::CRAWL_QUEUE, $queue, false );
		if ( $queue ) {
			wp_schedule_single_event( time() + 60, self::CRAWL_CRON );
		}
	}

	/**
	 * Delete a source.
	 */
	public static function source_delete(): void {
		self::guard( 'talkwyn_pro_source_delete' );
		global $wpdb;
		$id = absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->delete( Installer::tables()['sources'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		\Talkwyn_Indexer::remove_source( 'src:' . $id );
		self::back( 'extra', 'deleted' );
	}

	/**
	 * Re-read a source.
	 */
	public static function source_refresh(): void {
		self::guard( 'talkwyn_pro_source_refresh' );
		global $wpdb;
		$id  = absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Installer::tables()['sources'] . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( $row ) {
			if ( 'file' === $row['type'] ) {
				self::index_attachment( (int) $row['ref'], $id );
			} else {
				self::crawl_one( (string) $row['ref'], $id );
			}
		}
		self::back( 'extra', 'refreshed' );
	}

	/* ---------- Helpers ---------- */

	/**
	 * Capability and nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn-pro' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to a tab.
	 *
	 * @param string $tab    Tab.
	 * @param string $notice Notice code.
	 * @param string $detail Detail.
	 */
	private static function back( string $tab, string $notice, string $detail = '' ): void {
		$url = add_query_arg(
			array_filter(
				array(
					'page'           => 'talkwyn',
					'tab'            => $tab,
					'talkwyn_notice' => $notice,
					'detail'         => '' !== $detail ? rawurlencode( $detail ) : '',
				)
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
