<?php
/**
 * Pro admin tabs inside the Talkwyn screen, the trial banner and notices.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

use Talkwyn_Admin as A;

/**
 * Admin.
 */
final class Admin {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_admin_tabs', array( self::class, 'tabs' ) );
		foreach ( array( 'license', 'extra', 'analytics', 'inbox', 'pro' ) as $tab ) {
			add_action( 'talkwyn_admin_tab_' . $tab, array( self::class, 'tab_' . $tab ) );
		}
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ), 20 );
		add_action( 'talkwyn_admin_dashboard', array( self::class, 'dashboard' ) );
	}

	/**
	 * Tabs.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tabs( array $tabs ): array {
		if ( ! License::active() ) {
			// Without a license, only the License tab is added. No locked features are shown.
			$tabs['license'] = __( 'License', 'talkwyn-pro' );
			return $tabs;
		}
		$privacy = $tabs['privacy'] ?? null;
		unset( $tabs['privacy'] );
		$tabs['extra']     = __( 'Extra knowledge', 'talkwyn-pro' );
		$tabs['analytics'] = __( 'Analytics', 'talkwyn-pro' );
		$tabs['inbox']     = __( 'Unanswered', 'talkwyn-pro' );
		$tabs['pro']       = __( 'Pro settings', 'talkwyn-pro' );
		if ( $privacy ) {
			$tabs['privacy'] = $privacy;
		}
		$tabs['license'] = __( 'License', 'talkwyn-pro' );
		return $tabs;
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'talkwyn' ) ) {
			return;
		}
		wp_enqueue_style( 'talkwyn-pro-admin', TALKWYN_PRO_URL . 'assets/css/pro-admin.css', array( 'talkwyn-admin' ), TALKWYN_PRO_VERSION );
		wp_enqueue_script( 'talkwyn-pro-admin', TALKWYN_PRO_URL . 'assets/js/pro-admin.js', array( 'talkwyn-admin' ), TALKWYN_PRO_VERSION, true );
	}

	/**
	 * Locked message when Pro is not active.
	 */
	private static function locked(): bool {
		if ( License::active() ) {
			return false;
		}
		A::card( __( 'Pro features are paused', 'talkwyn-pro' ), __( 'Activate a license or start a free 15-day trial to use this. Your Pro data and settings are kept.', 'talkwyn-pro' ) );
		echo '<p><a class="twa-btn twa-btn--ink" href="' . esc_url( admin_url( 'admin.php?page=talkwyn&tab=license' ) ) . '">' . esc_html__( 'Activate license', 'talkwyn-pro' ) . '</a> <a class="twa-btn twa-btn--light twa-btn--sm" href="https://talkwyn.com/pricing/" target="_blank" rel="noopener">' . esc_html__( 'Start a free trial', 'talkwyn-pro' ) . '</a></p></section>';
		return true;
	}

	/**
	 * Notices: trial banner, results.
	 */
	public static function notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'talkwyn' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$client = License::client();
		if ( $client->is_trial() ) {
			$days = $client->trial_days_left();
			echo '<div class="notice twp-trial"><p><strong>' . esc_html( sprintf( /* translators: %d: days */ _n( 'Pro trial: %d day left', 'Pro trial: %d days left', $days, 'talkwyn-pro' ), $days ) ) . '</strong> ' . esc_html__( 'Upgrade any time with the same key. Your settings and knowledge stay.', 'talkwyn-pro' ) . ' <a class="twa-btn twa-btn--red twa-btn--sm" href="https://talkwyn.com/pricing/" target="_blank" rel="noopener">' . esc_html__( 'Upgrade', 'talkwyn-pro' ) . '</a></p></div>';
		}
		$code = isset( $_GET['talkwyn_notice'] ) ? sanitize_key( wp_unslash( $_GET['talkwyn_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $code ) {
			return;
		}
		$messages = array(
			'saved'     => array( 'success', __( 'Answer saved.', 'talkwyn-pro' ) ),
			'answered'  => array( 'success', __( 'Answer saved. The question is marked as done.', 'talkwyn-pro' ) ),
			'deleted'   => array( 'success', __( 'Deleted.', 'talkwyn-pro' ) ),
			'missing'   => array( 'error', __( 'Add both a question and an answer.', 'talkwyn-pro' ) ),
			'nofile'    => array( 'error', __( 'Choose a file first.', 'talkwyn-pro' ) ),
			'upload'    => array( 'error', __( 'Upload failed.', 'talkwyn-pro' ) ),
			'uploaded'  => array( 'success', __( 'File added to the knowledge.', 'talkwyn-pro' ) ),
			'badurl'    => array( 'error', __( 'Enter a full URL that starts with https://', 'talkwyn-pro' ) ),
			'crawled'   => array( 'success', __( 'Page added to the knowledge.', 'talkwyn-pro' ) ),
			'queued'    => array( 'success', __( 'The first pages were added. The rest are read in the background, 10 every minute.', 'talkwyn-pro' ) ),
			'refreshed' => array( 'success', __( 'Source read again.', 'talkwyn-pro' ) ),
			'imported'  => array( 'success', __( 'Settings imported.', 'talkwyn-pro' ) ),
			'badfile'   => array( 'error', __( 'That file is not a Talkwyn settings export.', 'talkwyn-pro' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			$detail = isset( $_GET['detail'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['detail'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] . ( '' !== $detail ? ' ' . $detail : '' ) ) . '</p></div>';
		}
	}

	/**
	 * Dashboard teaser of Pro numbers.
	 */
	public static function dashboard(): void {
		if ( ! License::active() ) {
			return;
		}
		$inbox = count( Insights::inbox( 100 ) );
		A::card( __( 'Talkwyn Pro', 'talkwyn-pro' ) );
		echo '<p>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d question needs an answer.', '%d questions need an answer.', $inbox, 'talkwyn-pro' ), $inbox ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=talkwyn&tab=inbox' ) ) . '">' . esc_html__( 'Open the inbox', 'talkwyn-pro' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=talkwyn&tab=analytics' ) ) . '">' . esc_html__( 'See analytics', 'talkwyn-pro' ) . '</a></p></section>';
	}

	/**
	 * License tab.
	 */
	public static function tab_license(): void {
		A::card( __( 'Talkwyn Pro license', 'talkwyn-pro' ), __( 'Paste the key from your talkwyn.com account. Trial keys work the same way; when you upgrade, the same key keeps working.', 'talkwyn-pro' ) );
		echo '</section>';
		License::client()->render_settings();
		if ( ! License::public_keys() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This build has no Talkwyn Hub public key, so license responses cannot be verified. Add the key in includes/hub-keys.php or define TALKWYN_PRO_PUBLIC_KEYS.', 'talkwyn-pro' ) . '</p></div>';
		}
	}

	/**
	 * Extra knowledge tab.
	 */
	public static function tab_extra(): void {
		if ( self::locked() ) {
			return;
		}
		$edit = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( Knowledge::qa_all() as $row ) {
				if ( (int) $row['id'] === absint( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$edit = $row;
				}
			}
		}
		echo '<div class="twa-grid">';
		A::card( $edit ? __( 'Edit answer', 'talkwyn-pro' ) : __( 'Custom answers', 'talkwyn-pro' ), __( 'When a visitor asks something close to one of these questions, Talkwyn replies with your answer word for word.', 'talkwyn-pro' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="talkwyn_pro_qa_save"><input type="hidden" name="id" value="' . esc_attr( $edit ? (string) $edit['id'] : '0' ) . '">';
		wp_nonce_field( 'talkwyn_pro_qa_save' );
		echo '<div class="twa-field"><label for="twp-q">' . esc_html__( 'Question', 'talkwyn-pro' ) . '</label><input id="twp-q" type="text" name="question" required value="' . esc_attr( $edit ? (string) $edit['question'] : '' ) . '"></div>';
		echo '<div class="twa-field"><label for="twp-a">' . esc_html__( 'Answer', 'talkwyn-pro' ) . '</label><textarea id="twp-a" name="answer" rows="5" required>' . esc_textarea( $edit ? (string) $edit['answer'] : '' ) . '</textarea><p class="description">' . esc_html__( 'Markdown works: **bold**, lists and [links](https://example.com).', 'talkwyn-pro' ) . '</p></div>';
		echo '<div class="twa-field"><label for="twp-k">' . esc_html__( 'Also match these words (optional)', 'talkwyn-pro' ) . '</label><input id="twp-k" type="text" name="keywords" value="' . esc_attr( $edit ? (string) $edit['keywords'] : '' ) . '" placeholder="refund, money back"></div>';
		echo '<p><button class="twa-btn twa-btn--ink">' . esc_html__( 'Save answer', 'talkwyn-pro' ) . '</button></p></form></section>';

		A::card( __( 'Add files and pages', 'talkwyn-pro' ), __( 'PDF, DOCX and TXT files, or any public page or sitemap. Talkwyn reads the text and adds it to the knowledge.', 'talkwyn-pro' ) );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twp-inline-form"><input type="hidden" name="action" value="talkwyn_pro_upload">';
		wp_nonce_field( 'talkwyn_pro_upload' );
		echo '<label for="twp-file" class="twp-label">' . esc_html__( 'File (PDF, DOCX or TXT)', 'talkwyn-pro' ) . '</label><input id="twp-file" type="file" name="file" accept=".pdf,.docx,.txt" required> <button class="twa-btn twa-btn--light twa-btn--sm">' . esc_html__( 'Upload', 'talkwyn-pro' ) . '</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twp-inline-form"><input type="hidden" name="action" value="talkwyn_pro_crawl">';
		wp_nonce_field( 'talkwyn_pro_crawl' );
		echo '<label for="twp-url" class="twp-label">' . esc_html__( 'Page or sitemap URL', 'talkwyn-pro' ) . '</label><input id="twp-url" type="url" name="url" placeholder="https://example.com/sitemap.xml" required> <button class="twa-btn twa-btn--light twa-btn--sm">' . esc_html__( 'Read', 'talkwyn-pro' ) . '</button></form>';
		$queue = (array) get_option( Knowledge::CRAWL_QUEUE, array() );
		if ( $queue ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %d: pages */ _n( '%d page waiting to be read.', '%d pages waiting to be read.', count( $queue ), 'talkwyn-pro' ), count( $queue ) ) ) . '</p>';
		}
		echo '</section></div>';

		$qa = Knowledge::qa_all();
		A::card( __( 'Your answers', 'talkwyn-pro' ) );
		if ( ! $qa ) {
			echo '<p>' . esc_html__( 'No custom answers yet.', 'talkwyn-pro' ) . '</p>';
		} else {
			echo '<div class="twa-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Question', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Answer', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Used', 'talkwyn-pro' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $qa as $row ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_pro_qa_delete&id=' . (int) $row['id'] ), 'talkwyn_pro_qa_delete' );
				echo '<tr><td>' . esc_html( $row['question'] ) . '</td><td>' . esc_html( wp_trim_words( (string) $row['answer'], 20 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td><td class="twp-actions"><a href="' . esc_url( admin_url( 'admin.php?page=talkwyn&tab=extra&edit=' . (int) $row['id'] ) ) . '">' . esc_html__( 'Edit', 'talkwyn-pro' ) . '</a> <a class="twa-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this answer?', 'talkwyn-pro' ) ) . '\')">' . esc_html__( 'Delete', 'talkwyn-pro' ) . '</a></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';

		$sources = Knowledge::sources();
		A::card( __( 'Files and pages', 'talkwyn-pro' ) );
		if ( ! $sources ) {
			echo '<p>' . esc_html__( 'Nothing added yet.', 'talkwyn-pro' ) . '</p>';
		} else {
			echo '<div class="twa-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Type', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Chunks', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Status', 'talkwyn-pro' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $sources as $row ) {
				$del     = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_pro_source_delete&id=' . (int) $row['id'] ), 'talkwyn_pro_source_delete' );
				$refresh = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_pro_source_refresh&id=' . (int) $row['id'] ), 'talkwyn_pro_source_refresh' );
				$label   = 'url' === $row['type'] ? '<a href="' . esc_url( $row['ref'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $row['title'] ) . '</a>' : esc_html( (string) $row['title'] );
				echo '<tr><td>' . $label . '</td><td>' . esc_html( 'url' === $row['type'] ? __( 'Page', 'talkwyn-pro' ) : __( 'File', 'talkwyn-pro' ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['chunks'] ) ) . '</td><td>' . esc_html( 'ok' === $row['status'] ? __( 'Ready', 'talkwyn-pro' ) : ( (string) $row['message'] ? (string) $row['message'] : __( 'No text', 'talkwyn-pro' ) ) ) . '</td><td class="twp-actions"><a href="' . esc_url( $refresh ) . '">' . esc_html__( 'Read again', 'talkwyn-pro' ) . '</a> <a class="twa-danger" href="' . esc_url( $del ) . '">' . esc_html__( 'Remove', 'talkwyn-pro' ) . '</a></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label escaped above.
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
	}

	/**
	 * Analytics tab.
	 */
	public static function tab_analytics(): void {
		if ( self::locked() ) {
			return;
		}
		$d     = Insights::stats( 30 );
		$stats = array(
			array( __( 'Chats, 30 days', 'talkwyn-pro' ), number_format_i18n( $d['chats'] ) ),
			array( __( 'Leads, 30 days', 'talkwyn-pro' ), number_format_i18n( $d['leads'] ) ),
			array( __( 'Lead rate', 'talkwyn-pro' ), number_format_i18n( $d['lead_rate'] * 100, 1 ) . '%' ),
			array( __( 'Average reply time', 'talkwyn-pro' ), $d['avg_ms'] ? number_format_i18n( $d['avg_ms'] / 1000, 1 ) . ' s' : '0 s' ),
		);
		echo '<div class="twa-stats">';
		foreach ( $stats as $s ) {
			echo '<div class="twa-stat"><span>' . esc_html( $s[0] ) . '</span><strong>' . esc_html( $s[1] ) . '</strong></div>';
		}
		echo '</div>';

		A::card( __( 'Chats per day', 'talkwyn-pro' ), __( 'Conversations started in the last 30 days.', 'talkwyn-pro' ) );
		$max = max( 1, max( $d['days'] ) );
		echo '<div class="twp-bars" role="img" aria-label="' . esc_attr__( 'Chats per day', 'talkwyn-pro' ) . '">';
		foreach ( $d['days'] as $day => $count ) {
			echo '<span class="twp-bar" style="--h:' . esc_attr( (string) round( 100 * $count / $max ) ) . '%" title="' . esc_attr( wp_date( 'M j', strtotime( $day ) ) . ': ' . $count ) . '"><i></i></span>';
		}
		echo '</div></section><div class="twa-grid">';

		A::card( __( 'Top questions', 'talkwyn-pro' ) );
		self::list_rows( $d['top'], 'text', 'count', __( 'No questions yet.', 'talkwyn-pro' ) );
		echo '</section>';
		A::card( __( 'Pages where chats start', 'talkwyn-pro' ) );
		$pages = array_map(
			static function ( $row ) {
				$path = (string) wp_parse_url( (string) $row['url'], PHP_URL_PATH );
				return array(
					'text'  => '' !== $path ? $path : __( '(unknown)', 'talkwyn-pro' ),
					'count' => (int) $row['c'],
				);
			},
			$d['pages']
		);
		self::list_rows( $pages, 'text', 'count', __( 'No chats yet.', 'talkwyn-pro' ) );
		echo '</section>';

		A::card( __( 'AI providers', 'talkwyn-pro' ), sprintf( /* translators: %s: percent */ __( '%s of answers came from an AI provider; the rest used local fallback answers.', 'talkwyn-pro' ), number_format_i18n( $d['ai_rate'] * 100, 0 ) . '%' ) );
		if ( $d['providers'] ) {
			$reg = \Talkwyn_Providers::registry();
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Provider', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Answers', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Failures', 'talkwyn-pro' ) . '</th><th>' . esc_html__( 'Success rate', 'talkwyn-pro' ) . '</th></tr></thead><tbody>';
			foreach ( $d['providers'] as $id => $p ) {
				echo '<tr><td>' . esc_html( $reg[ $id ]['label'] ?? $id ) . '</td><td>' . esc_html( number_format_i18n( $p['served'] ) ) . '</td><td>' . esc_html( number_format_i18n( $p['failed'] ) ) . '</td><td>' . esc_html( number_format_i18n( $p['rate'] * 100, 0 ) ) . '%</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No AI answers yet.', 'talkwyn-pro' ) . '</p>';
		}
		echo '</section>';
		A::card( __( 'Feedback', 'talkwyn-pro' ) );
		echo '<p>' . esc_html( sprintf( /* translators: 1: helpful count, 2: not helpful count */ __( '%1$s helpful, %2$s not helpful.', 'talkwyn-pro' ), number_format_i18n( $d['helpful'] ), number_format_i18n( $d['unhelpful'] ) ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=talkwyn&tab=inbox' ) ) . '">' . esc_html__( 'Review unanswered questions', 'talkwyn-pro' ) . '</a></p></section></div>';
	}

	/**
	 * Ranked list.
	 *
	 * @param array  $rows  Rows.
	 * @param string $label Label key.
	 * @param string $count Count key.
	 * @param string $empty Empty text.
	 */
	private static function list_rows( array $rows, string $label, string $count, string $empty ): void {
		if ( ! $rows ) {
			echo '<p>' . esc_html( $empty ) . '</p>';
			return;
		}
		echo '<ol class="twp-rank">';
		foreach ( $rows as $row ) {
			echo '<li><span dir="auto">' . esc_html( (string) $row[ $label ] ) . '</span><b>' . esc_html( number_format_i18n( (int) $row[ $count ] ) ) . '</b></li>';
		}
		echo '</ol>';
	}

	/**
	 * Inbox tab.
	 */
	public static function tab_inbox(): void {
		if ( self::locked() ) {
			return;
		}
		$items = Insights::inbox( 100 );
		A::card( __( 'Unanswered questions', 'talkwyn-pro' ), __( 'Questions the assistant could not answer from your site, and answers visitors marked as not helpful. Add an answer once and Talkwyn uses it from then on.', 'talkwyn-pro' ) );
		if ( ! $items ) {
			echo '<p class="twp-empty">' . esc_html__( 'All caught up. New items appear here as visitors chat.', 'talkwyn-pro' ) . '</p></section>';
			return;
		}
		foreach ( $items as $item ) {
			$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_pro_dismiss&id=' . $item['id'] ), 'talkwyn_pro_dismiss_' . $item['id'] );
			echo '<article class="twp-inbox"><header><span class="twa-badge' . ( 'not_helpful' === $item['reason'] ? ' twp-badge--warn' : '' ) . '">' . esc_html( 'not_helpful' === $item['reason'] ? __( 'Not helpful', 'talkwyn-pro' ) : __( 'Not answered', 'talkwyn-pro' ) ) . '</span> <time>' . esc_html( get_date_from_gmt( $item['date'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time>';
			if ( $item['page'] ) {
				echo ' <a href="' . esc_url( $item['page'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $item['page'], PHP_URL_PATH ) ) . '</a>';
			}
			echo '</header><p class="twp-inbox__q" dir="auto"><strong>' . esc_html( $item['question'] ) . '</strong></p><p class="twp-inbox__a" dir="auto">' . esc_html( wp_trim_words( $item['reply'], 40 ) ) . '</p>';
			echo '<details><summary class="twa-btn twa-btn--ink twa-btn--sm">' . esc_html__( 'Add answer', 'talkwyn-pro' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="talkwyn_pro_qa_save"><input type="hidden" name="resolve_log" value="' . esc_attr( (string) $item['id'] ) . '">';
			wp_nonce_field( 'talkwyn_pro_qa_save' );
			echo '<div class="twa-field"><label>' . esc_html__( 'Question', 'talkwyn-pro' ) . '<input type="text" name="question" value="' . esc_attr( $item['question'] ) . '" required></label></div><div class="twa-field"><label>' . esc_html__( 'Answer', 'talkwyn-pro' ) . '<textarea name="answer" rows="4" required></textarea></label></div><p><button class="twa-btn twa-btn--ink">' . esc_html__( 'Save answer', 'talkwyn-pro' ) . '</button></p></form></details> <a class="twa-btn twa-btn--light twa-btn--sm" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'talkwyn-pro' ) . '</a></article>';
		}
		echo '</section>';
	}

	/**
	 * Pro settings tab.
	 */
	public static function tab_pro(): void {
		if ( self::locked() ) {
			return;
		}
		$s = \Talkwyn_Settings::all();
		A::form_open( 'pro' );
		echo '<div class="twa-grid">';

		A::card( __( 'Smart search', 'talkwyn-pro' ), __( 'Finds answers by meaning, not only matching words. Uses the embedding API of a provider you already have a key for.', 'talkwyn-pro' ) );
		A::select(
			'pro_embed_provider',
			__( 'Embedding provider', 'talkwyn-pro' ),
			array(
				''        => __( 'Off (keyword search)', 'talkwyn-pro' ),
				'openai'  => 'OpenAI',
				'mistral' => 'Mistral',
				'gemini'  => 'Google Gemini',
			)
		);
		A::field( 'pro_embed_model', __( 'Embedding model (optional)', 'talkwyn-pro' ), 'text', __( 'Leave empty for the provider default.', 'talkwyn-pro' ) );
		A::field( 'pro_semantic_weight', __( 'Meaning vs keywords (0 to 100)', 'talkwyn-pro' ), 'number', __( 'Higher trusts meaning more. 60 works well for most sites.', 'talkwyn-pro' ), array( 'min' => 0, 'max' => 100 ) );
		$st = Search::stats();
		echo '<div class="twp-embed"><button type="button" class="twa-btn twa-btn--light twa-btn--sm" id="twp-embed">' . esc_html__( 'Build smart search now', 'talkwyn-pro' ) . '</button> <span id="twp-embed-status" aria-live="polite">' . esc_html( sprintf( /* translators: 1: vectors, 2: chunks */ __( '%1$s of %2$s chunks ready', 'talkwyn-pro' ), number_format_i18n( $st['vectors'] ), number_format_i18n( $st['chunks'] ) ) ) . '</span></div><p class="description">' . esc_html__( 'New and changed content is added in the background every 10 minutes.', 'talkwyn-pro' ) . '</p>';
		echo '</section>';

		A::card( __( 'Replies and WooCommerce', 'talkwyn-pro' ) );
		A::toggle( 'pro_stream', __( 'Stream replies word by word', 'talkwyn-pro' ), __( 'Falls back to normal replies when your host buffers responses.', 'talkwyn-pro' ) );
		A::toggle( 'pro_woo_cards', __( 'Show product cards with price and Add to cart', 'talkwyn-pro' ) );
		A::toggle( 'pro_woo_orders', __( 'Order status lookup (order number plus billing email)', 'talkwyn-pro' ) );
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<p class="description">' . esc_html__( 'WooCommerce is not active on this site.', 'talkwyn-pro' ) . '</p>';
		}
		echo '</section>';

		A::card( __( 'Lead alerts', 'talkwyn-pro' ), __( 'Email alerts are set under Answers and leads. Add Slack or Telegram for instant alerts.', 'talkwyn-pro' ) );
		A::field( 'pro_slack_webhook', __( 'Slack incoming webhook URL', 'talkwyn-pro' ), 'url', '', array( 'placeholder' => 'https://hooks.slack.com/services/...' ) );
		A::field( 'pro_telegram_token', __( 'Telegram bot token', 'talkwyn-pro' ), 'password', '', array( 'autocomplete' => 'off' ) );
		A::field( 'pro_telegram_chat', __( 'Telegram chat ID', 'talkwyn-pro' ) );
		echo '<p><button type="button" class="twa-btn twa-btn--light twa-btn--sm" id="twp-test-alert">' . esc_html__( 'Send a test alert', 'talkwyn-pro' ) . '</button> <span id="twp-alert-status" aria-live="polite"></span></p>';
		echo '<p class="twp-soon"><span class="twa-badge">' . esc_html__( 'Coming soon', 'talkwyn-pro' ) . '</span> ' . esc_html__( 'WhatsApp alerts', 'talkwyn-pro' ) . '</p>';
		echo '</section>';

		A::card( __( 'White label', 'talkwyn-pro' ) );
		A::toggle( 'pro_hide_powered', __( 'Hide "Powered by Talkwyn" in the chat', 'talkwyn-pro' ) );
		A::field( 'pro_menu_name', __( 'Admin menu and screen name', 'talkwyn-pro' ), 'text', __( 'For client sites, for example "Website chat". Leave empty for Talkwyn.', 'talkwyn-pro' ) );
		A::field( 'pro_brand_logo', __( 'Admin logo URL', 'talkwyn-pro' ), 'url', __( 'Square image shown in the admin header instead of the Talkwyn mark.', 'talkwyn-pro' ) );
		echo '<div class="twa-row">';
		A::field( 'pro_menu_item_label', __( 'Chat menu link text', 'talkwyn-pro' ), 'text', __( 'For example "Website by Your Agency".', 'talkwyn-pro' ) );
		A::field( 'pro_menu_item_url', __( 'Chat menu link', 'talkwyn-pro' ), 'url' );
		echo '</div>';
		echo '</section>';

		A::card( __( 'Coming soon', 'talkwyn-pro' ), __( 'On the roadmap. Not available yet.', 'talkwyn-pro' ), '', 'rocket' );
		echo '<ul class="twp-soon-list">';
		foreach ( array(
			__( 'Live human takeover', 'talkwyn-pro' ),
			__( 'Booking integrations', 'talkwyn-pro' ),
			__( 'WhatsApp channel', 'talkwyn-pro' ),
			__( 'Multiple bots', 'talkwyn-pro' ),
			__( 'Lead scoring', 'talkwyn-pro' ),
			__( 'AI conversation summaries', 'talkwyn-pro' ),
		) as $soon ) {
			echo '<li><span class="twa-badge">' . esc_html__( 'Coming soon', 'talkwyn-pro' ) . '</span> ' . esc_html( $soon ) . '</li>';
		}
		echo '</ul></section>';

		A::card( __( 'Proactive messages', 'talkwyn-pro' ), __( 'Greet visitors with a short message near the chat button. Each message shows once per visit.', 'talkwyn-pro' ), 'twa-card--wide' );
		$rules = array_values( (array) $s['pro_proactive'] );
		echo '<div class="twp-rules"><div class="twp-rules__head"><span>' . esc_html__( 'Message', 'talkwyn-pro' ) . '</span><span>' . esc_html__( 'When', 'talkwyn-pro' ) . '</span><span>' . esc_html__( 'Value', 'talkwyn-pro' ) . '</span><span>' . esc_html__( 'Only on URLs containing', 'talkwyn-pro' ) . '</span><span>' . esc_html__( 'Open chat', 'talkwyn-pro' ) . '</span></div>';
		for ( $i = 0; $i < 5; $i++ ) {
			$r = $rules[ $i ] ?? array(
				'message' => '',
				'trigger' => 'time',
				'value'   => 15,
				'url'     => '',
				'open'    => 0,
			);
			$n = 'talkwyn[pro_proactive][' . $i . ']';
			echo '<div class="twp-rules__row"><input type="text" name="' . esc_attr( $n ) . '[message]" value="' . esc_attr( $r['message'] ) . '" aria-label="' . esc_attr__( 'Message', 'talkwyn-pro' ) . '" placeholder="' . esc_attr__( 'Questions about pricing? Ask me.', 'talkwyn-pro' ) . '">';
			echo '<select name="' . esc_attr( $n ) . '[trigger]" aria-label="' . esc_attr__( 'When', 'talkwyn-pro' ) . '">';
			foreach ( array(
				'time'   => __( 'After seconds on page', 'talkwyn-pro' ),
				'scroll' => __( 'After scrolling percent', 'talkwyn-pro' ),
				'exit'   => __( 'When leaving the page', 'talkwyn-pro' ),
			) as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['trigger'], $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><input type="number" min="0" max="600" name="' . esc_attr( $n ) . '[value]" value="' . esc_attr( (string) $r['value'] ) . '" aria-label="' . esc_attr__( 'Value', 'talkwyn-pro' ) . '"><input type="text" name="' . esc_attr( $n ) . '[url]" value="' . esc_attr( $r['url'] ) . '" placeholder="/pricing/" aria-label="' . esc_attr__( 'URL contains', 'talkwyn-pro' ) . '"><label class="twp-center"><input type="checkbox" name="' . esc_attr( $n ) . '[open]" value="1"' . checked( ! empty( $r['open'] ), true, false ) . '><span class="screen-reader-text">' . esc_html__( 'Open chat', 'talkwyn-pro' ) . '</span></label></div>';
		}
		echo '<input type="hidden" name="talkwyn[pro_proactive][99][message]" value=""></div></section>';

		A::card( __( 'Business hours', 'talkwyn-pro' ), sprintf( /* translators: %s: time zone */ __( 'Outside these hours the chat shows as away. Times use the site time zone (%s).', 'talkwyn-pro' ), wp_timezone_string() ), 'twa-card--wide' );
		A::toggle( 'pro_hours_enabled', __( 'Use business hours', 'talkwyn-pro' ) );
		$days  = array(
			'mon' => __( 'Monday', 'talkwyn-pro' ),
			'tue' => __( 'Tuesday', 'talkwyn-pro' ),
			'wed' => __( 'Wednesday', 'talkwyn-pro' ),
			'thu' => __( 'Thursday', 'talkwyn-pro' ),
			'fri' => __( 'Friday', 'talkwyn-pro' ),
			'sat' => __( 'Saturday', 'talkwyn-pro' ),
			'sun' => __( 'Sunday', 'talkwyn-pro' ),
		);
		$hours = (array) $s['pro_hours'];
		echo '<div class="twp-hours">';
		foreach ( $days as $key => $label ) {
			$h = $hours[ $key ] ?? Settings::default_hours()[ $key ];
			$n = 'talkwyn[pro_hours][' . $key . ']';
			echo '<div class="twp-hours__row"><label><input type="checkbox" name="' . esc_attr( $n ) . '[open]" value="1"' . checked( ! empty( $h['open'] ), true, false ) . '> ' . esc_html( $label ) . '</label><input type="time" name="' . esc_attr( $n ) . '[from]" value="' . esc_attr( $h['from'] ) . '" aria-label="' . esc_attr( $label . ' ' . __( 'opens', 'talkwyn-pro' ) ) . '"><span>' . esc_html__( 'to', 'talkwyn-pro' ) . '</span><input type="time" name="' . esc_attr( $n ) . '[until]" value="' . esc_attr( $h['until'] ) . '" aria-label="' . esc_attr( $label . ' ' . __( 'closes', 'talkwyn-pro' ) ) . '"></div>';
		}
		echo '</div>';
		A::field( 'pro_away_label', __( 'Status text when away', 'talkwyn-pro' ) );
		A::toggle( 'pro_away_lead_only', __( 'When away, collect contact details instead of chatting', 'talkwyn-pro' ) );
		A::field( 'pro_away_message', __( 'Away reply', 'talkwyn-pro' ), 'textarea' );
		echo '</section></div>';
		A::form_close( __( 'Save Pro settings', 'talkwyn-pro' ) );

		A::card( __( 'Export and import settings', 'talkwyn-pro' ), __( 'Move a setup to another site. Exports include settings and custom answers. API keys are left out unless you tick the box.', 'talkwyn-pro' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twp-inline-form"><input type="hidden" name="action" value="talkwyn_pro_export">';
		wp_nonce_field( 'talkwyn_pro_export' );
		echo '<label><input type="checkbox" name="with_secrets" value="1"> ' . esc_html__( 'Include API keys', 'talkwyn-pro' ) . '</label> <button class="twa-btn twa-btn--light twa-btn--sm">' . esc_html__( 'Download export', 'talkwyn-pro' ) . '</button></form>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twp-inline-form"><input type="hidden" name="action" value="talkwyn_pro_import">';
		wp_nonce_field( 'talkwyn_pro_import' );
		echo '<input type="file" name="file" accept=".json,application/json" required aria-label="' . esc_attr__( 'Settings file', 'talkwyn-pro' ) . '"> <label><input type="checkbox" name="with_answers" value="1" checked> ' . esc_html__( 'Also import custom answers', 'talkwyn-pro' ) . '</label> <button class="twa-btn twa-btn--light twa-btn--sm">' . esc_html__( 'Import', 'talkwyn-pro' ) . '</button></form></section>';
	}
}
