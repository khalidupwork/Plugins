<?php
/**
 * One-time corrections to page text that was imported before the matching
 * plugin facts changed. Each pair replaces an exact old string with a new one
 * in posts, excerpts and SEO fields, so the rest of an edited page is kept.
 * setup/content/pages.json carries the corrected text already. Fix set 2 lives in
 * content-fixes-2.json, generated from the same edits to pages.json.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

const TALKWYN_CONTENT_FIXES = 2;

/**
 * Old text => new text. Theme 2.12.0: white label is the Agency plan's (logo and
 * menu name in the admin, chat menu link); the "Powered by Talkwyn" badge is off by
 * default on every plan; there is no separate "lead inbox per client", each client's
 * leads stay in that client's own WordPress site.
 *
 * @return array<string, string>
 */
function talkwyn_content_fixes(): array {
	$li  = '<li><span class="tw-vcard__tick tw-vcard__tick--pro">[tw_icon name="sparkles" size="14"]</span><span>A lead inbox per client on the Agency plan</span></li>';
	$li2 = '<li><span class="tw-vcard__tick">[tw_icon name="check" size="14"]</span><span>A lead inbox for each client</span></li>';
	return array(
		$li  => '',
		$li2 => '',
		'Remove Talkwyn branding, copy settings from one site to the next, and give each client a lead inbox.' => 'Put your own logo and menu name on every client site, and copy settings from one site to the next.',
		'The Agency plan covers unlimited sites, and adds white label, copied settings from one site to the next, and a lead inbox per client.' => 'The Agency plan covers unlimited sites, and adds white label and copied settings from one site to the next.',
		'The Agency plan adds white label, copied settings, and a lead inbox per client.' => 'The Agency plan adds white label and copied settings.',
		'with Talkwyn branding removed and a lead inbox per client.' => 'with your own logo and menu name in each client\'s WordPress admin.',
		'White label, inbox per client' => 'White label, copied settings',
		'On the Agency plan, each client gets their own lead inbox.' => 'Each client\'s leads stay in that client\'s own WordPress site.',
		'White label and per-client inboxes on Agency' => 'White label and copied settings on Agency',
		'Agency: unlimited sites, with white label, copied settings, and a lead inbox per client.' => 'Agency: unlimited sites, with white label and copied settings.',
		'Agency plan: white label, copy settings between sites, a lead inbox per client' => 'Agency plan: white label and copy settings between sites',
		'A white label chatbot for agencies with unlimited client sites, no Talkwyn branding, copyable settings and a lead inbox per client.' => 'A white label chatbot for agencies with unlimited client sites, your own logo and menu name, and copyable settings.',
		'A white label chatbot for agencies: unlimited client sites, no Talkwyn branding, copy settings between sites, and a lead inbox for every client.' => 'A white label chatbot for agencies: unlimited client sites, your own logo and menu name, and settings you copy from one site to the next.',
		'Remove the Talkwyn branding, copy your settings from one site to the next, and give each client their own lead inbox.' => 'Put your own logo and menu name on every client site, and copy your settings from one site to the next.',
		'Sample client lead inbox' => 'Sample client site',
		'<h3>A lead inbox per client</h3><p>Each client sees their own leads. Leads are also emailed instantly and export to CSV.</p>' => '<h3>Leads stay with each client</h3><p>Each client site keeps its own leads in its own WordPress. Leads are also emailed instantly and export to CSV.</p>',
		'<h3>Remove Talkwyn branding</h3><p>The chat window shows your client\'s brand, not ours.</p>' => '<h3>White label</h3><p>Your logo and menu name in the WordPress admin, and a link to your agency in the chat menu. The chat shows your client\'s brand, not ours.</p>',
		'<td class="is-us">Branding removed</td>' => '<td class="is-us">Your logo</td>',
		'<td class="is-us">Inbox per client</td>' => '<td class="is-us">In each client\'s site</td>',
		'<span>Remove Talkwyn branding (white label)</span>' => '<span>White label: your logo and menu name</span>',
		'Each client gets their own lead inbox.' => 'Each client\'s leads stay in their own WordPress site.',
		'The chat window carries the client\'s look, with no Talkwyn branding.' => 'The chat window carries the client\'s look. The Talkwyn badge stays off unless you turn it on.',
		'On the Agency plan you can remove the Talkwyn branding from the chat window, so your clients see their own brand.' => 'On the Agency plan the WordPress admin shows your logo and your menu name instead of Talkwyn, and the chat menu can link to your agency. The "Powered by Talkwyn" badge in the chat is off by default on every plan, so clients see only their own brand.',
		'Yes. The Agency plan includes a lead inbox for each client. Leads are saved in that client\'s WordPress site.' => 'Yes. Leads are saved in each client\'s own WordPress site, so each client sees only their own leads. This works on every plan.',
		'Yes. The Agency plan includes a lead inbox per client.' => 'Yes. Each client\'s chats and leads are saved in that client\'s own WordPress site, so you see each client separately. This works on every plan.',
	);
}

/**
 * Apply fix set 1: exact pairs anywhere in posts, excerpts and post meta.
 */
function talkwyn_content_fixes_1(): void {
	global $wpdb;
	$fixes = talkwyn_content_fixes();
	$ids   = array();
	foreach ( array_keys( $fixes ) as $old ) {
		$like = '%' . $wpdb->esc_like( $old ) . '%';
		$ids  = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_excerpt LIKE %s", $like, $like ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) ) as $meta ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => str_replace( $old, $fixes[ $old ], (string) $meta->meta_value ) ), array( 'meta_id' => (int) $meta->meta_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
	}
	foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
		talkwyn_content_fixes_update( $id, $fixes );
	}
}

/**
 * Write new content and excerpt straight to the posts table.
 *
 * @param int                   $id    Post ID.
 * @param array<string, string> $pairs Old => new.
 */
function talkwyn_content_fixes_update( int $id, array $pairs ): void {
	global $wpdb;
	$post = get_post( $id );
	if ( ! $post || ! $pairs ) {
		return;
	}
	// Direct update: wp_update_post() would run the content through kses for users without unfiltered_html.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->posts,
		array(
			'post_content' => strtr( (string) $post->post_content, $pairs ),
			'post_excerpt' => strtr( (string) $post->post_excerpt, $pairs ),
		),
		array( 'ID' => $id )
	);
	clean_post_cache( $id );
}

/**
 * Find an imported page, post or doc by its path in setup/content/pages.json.
 *
 * @param string $path Path, e.g. /compare/tidio-alternative/.
 * @param string $type Post type.
 */
function talkwyn_content_fixes_post( string $path, string $type ): ?WP_Post {
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
	if ( 'doc' === $type || 'post' === $type ) {
		array_shift( $segments );
	}
	$post = $segments ? get_page_by_path( implode( '/', $segments ), OBJECT, $type ) : null;
	if ( ! $post && $segments ) {
		// The page may have been moved to another parent: fall back to the slug alone.
		$found = get_posts(
			array(
				'post_type'        => $type,
				'name'             => end( $segments ),
				'post_status'      => 'any',
				'numberposts'      => 2,
				'suppress_filters' => true,
			)
		);
		$post  = 1 === count( $found ) ? $found[0] : null;
	}
	return $post instanceof WP_Post ? $post : null;
}

/**
 * Apply fix set 2 (theme 2.13.0, launch playbook): per page, so a sentence is only
 * changed on the page it was written for. A page whose text was edited by hand since the
 * import is left alone and listed in an admin notice. Also sets the brand name and tagline when still unset, and keeps
 * the WooCommerce system pages out of search results.
 */
function talkwyn_content_fixes_2(): void {
	$file  = __DIR__ . '/content-fixes-2.json';
	$fixes = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	$skipped = array();
	foreach ( (array) $fixes as $path => $fix ) {
		$post = talkwyn_content_fixes_post( (string) $path, (string) ( $fix['post_type'] ?? 'page' ) );
		if ( ! $post ) {
			continue;
		}
		// All or nothing per page, trying the text as imported by this theme version and by
		// older ones. A page edited by hand since the import is listed for a manual check.
		$content = (string) $post->post_content;
		$chosen  = null;
		foreach ( array_merge( array( $fix ), (array) ( $fix['variants'] ?? array() ) ) as $variant ) {
			$pairs   = (array) ( $variant['content'] ?? array() );
			$missing = array_filter( array_keys( $pairs ), static fn( $old ) => false === strpos( $content, (string) $old ) );
			if ( ! $missing ) {
				$chosen = $variant;
				break;
			}
		}
		if ( null === $chosen ) {
			$skipped[] = (int) $post->ID;
			continue;
		}
		$fix = $chosen;
		talkwyn_content_fixes_update( (int) $post->ID, (array) ( $fix['content'] ?? array() ) );
		if ( isset( $fix['title'] ) && $post->post_title === $fix['title'][0] ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_title' => $fix['title'][1] ), array( 'ID' => (int) $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( (int) $post->ID );
		}
		$meta = array(
			'seo_title' => array( '_tw_seo_title', 'rank_math_title' ),
			'seo_desc'  => array( '_tw_seo_desc', 'rank_math_description' ),
		);
		foreach ( $meta as $field => $keys ) {
			if ( ! isset( $fix[ $field ] ) ) {
				continue;
			}
			foreach ( $keys as $key ) {
				if ( get_post_meta( (int) $post->ID, $key, true ) === $fix[ $field ][0] ) {
					update_post_meta( (int) $post->ID, $key, $fix[ $field ][1] );
				}
			}
		}
	}

	update_option( 'talkwyn_content_fixes_skipped', $skipped, false );

	// Brand: "Talkwyn" with a capital T, and the tagline, unless the owner set their own.
	if ( 'talkwyn' === get_option( 'blogname' ) ) {
		update_option( 'blogname', 'Talkwyn' );
	}
	if ( '' === trim( (string) get_option( 'blogdescription' ) ) ) {
		update_option( 'blogdescription', 'AI chatbot for WordPress that answers in every language' );
	}
	$rank_math = get_option( 'rank-math-options-titles' );
	if ( is_array( $rank_math ) ) {
		foreach ( array( 'knowledgegraph_name', 'website_name' ) as $key ) {
			if ( empty( $rank_math[ $key ] ) || 'talkwyn' === $rank_math[ $key ] ) {
				$rank_math[ $key ] = 'Talkwyn';
			}
		}
		update_option( 'rank-math-options-titles', $rank_math );
	}

	// WooCommerce system pages: noindex, also in Rank Math and the theme sitemap.
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				update_post_meta( $id, '_tw_noindex', 1 );
				update_post_meta( $id, 'rank_math_robots', array( 'noindex', 'follow' ) );
			}
		}
		// WooCommerce's sample "Refund and Returns Policy" draft: talkwyn.com has /refund-policy/.
		$sample = get_page_by_path( 'refund_returns', OBJECT, 'page' );
		if ( $sample && 'draft' === $sample->post_status ) {
			wp_trash_post( (int) $sample->ID );
		}
	}
}

/**
 * Apply each fix set once, after a theme update.
 */
add_action(
	'admin_init',
	static function () {
		$done = (int) get_option( 'talkwyn_content_fixes', 0 );
		if ( $done >= TALKWYN_CONTENT_FIXES || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( $done < 1 ) {
			talkwyn_content_fixes_1();
		}
		if ( $done < 2 ) {
			talkwyn_content_fixes_2();
		}
		update_option( 'talkwyn_content_fixes', TALKWYN_CONTENT_FIXES, false );
	}
);

// Pages fix set 2 could not update because their text was edited by hand.
add_action(
	'admin_notices',
	static function () {
		$ids = (array) get_option( 'talkwyn_content_fixes_skipped', array() );
		if ( ! $ids || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( isset( $_GET['tw_fixes_seen'] ) && check_admin_referer( 'tw_fixes_seen' ) ) {
			delete_option( 'talkwyn_content_fixes_skipped' );
			return;
		}
		$links = array();
		foreach ( $ids as $id ) {
			if ( get_post( (int) $id ) ) {
				$links[] = '<a href="' . esc_url( (string) get_edit_post_link( (int) $id ) ) . '">' . esc_html( get_the_title( (int) $id ) ) . '</a>';
			}
		}
		if ( ! $links ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Talkwyn theme: some pages need a manual copy check.', 'talkwyn' ) . '</strong> ' . esc_html__( 'Their text was edited after the import, so the launch copy fixes (install steps, setup order, Slack and Telegram alerts, Pro features, smart search, legal details) were not applied automatically. Compare them with setup/content/pages.json in the theme:', 'talkwyn' ) . ' ' . implode( ', ', $links ) . '. <a href="' . esc_url( wp_nonce_url( add_query_arg( 'tw_fixes_seen', 1 ), 'tw_fixes_seen' ) ) . '">' . esc_html__( 'Hide this notice', 'talkwyn' ) . '</a></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
);
