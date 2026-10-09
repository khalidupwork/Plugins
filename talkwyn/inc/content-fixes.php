<?php
/**
 * One-time corrections to page text that was imported before the matching
 * plugin facts changed. Each pair replaces an exact old string with a new one
 * in posts, excerpts and SEO fields, so the rest of an edited page is kept.
 * setup/content/pages.json carries the corrected text already.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

const TALKWYN_CONTENT_FIXES = 1;

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
 * Apply the fixes once per fix version, after a theme update.
 */
add_action(
	'admin_init',
	static function () {
		if ( (int) get_option( 'talkwyn_content_fixes', 0 ) >= TALKWYN_CONTENT_FIXES || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
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
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			// Direct update: wp_update_post() would run the content through kses for users without unfiltered_html.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->posts,
				array(
					'post_content' => strtr( (string) $post->post_content, $fixes ),
					'post_excerpt' => strtr( (string) $post->post_excerpt, $fixes ),
				),
				array( 'ID' => $id )
			);
			clean_post_cache( $id );
		}
		update_option( 'talkwyn_content_fixes', TALKWYN_CONTENT_FIXES, false );
	}
);
