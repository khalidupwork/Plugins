<?php
/**
 * Waitlist for integrations that are not live yet.
 *
 * Sign-ups are stored as the private post type tw_waitlist (title = email, meta = platform and website),
 * listed under Waitlist in the admin with a CSV export. Each new sign-up gets a confirmation email.
 * Spam protection: nonce, honeypot, minimum fill time, and a per-IP rate limit.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

const TALKWYN_WAITLIST = 'tw_waitlist';

add_action(
	'init',
	static function () {
		register_post_type(
			TALKWYN_WAITLIST,
			array(
				'labels'          => array(
					'name'          => __( 'Waitlist', 'talkwyn' ),
					'singular_name' => __( 'Waitlist sign-up', 'talkwyn' ),
					'all_items'     => __( 'All sign-ups', 'talkwyn' ),
					'search_items'  => __( 'Search sign-ups', 'talkwyn' ),
					'not_found'     => __( 'No sign-ups yet.', 'talkwyn' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-list-view',
				'menu_position'   => 27,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/**
 * Sign-ups for a platform (or all).
 *
 * @param string $platform Platform name, empty for all.
 */
function talkwyn_waitlist_count( string $platform = '' ): int {
	$args = array(
		'post_type'      => TALKWYN_WAITLIST,
		'post_status'    => 'private',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( '' !== $platform ) {
		$args['meta_key']   = '_tw_platform'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small private list.
		$args['meta_value'] = $platform; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small private list.
	}
	$query = new WP_Query( $args );
	return (int) $query->found_posts;
}

/**
 * [tw_waitlist platform="Shopify"] The waitlist form: email, platform (prefilled), website.
 *
 * @param array<string, string>|string $atts Attributes: platform, title, text.
 */
function talkwyn_waitlist_shortcode( $atts ): string {
	$atts     = shortcode_atts(
		array(
			'platform' => '',
			'title'    => '',
			'text'     => '',
		),
		$atts,
		'tw_waitlist'
	);
	$platform = sanitize_text_field( $atts['platform'] );
	$options  = array();
	foreach ( talkwyn_integrations() as $row ) {
		if ( 'available' !== $row['status'] ) {
			$options[] = $row['name'];
		}
	}
	$options[] = __( 'Other platform', 'talkwyn' );
	if ( '' !== $platform && ! in_array( $platform, $options, true ) ) {
		array_unshift( $options, $platform );
	}
	$select = '';
	foreach ( $options as $opt ) {
		$select .= '<option value="' . esc_attr( $opt ) . '"' . selected( $opt, $platform, false ) . '>' . esc_html( $opt ) . '</option>';
	}
	$count = '';
	if ( talkwyn_setting( 'waitlist_show_count' ) && '' !== $platform ) {
		$n = talkwyn_waitlist_count( $platform );
		if ( $n > 0 ) {
			/* translators: 1: number of people, 2: platform */
			$count = '<p class="tw-waitlist__count">' . talkwyn_icon( 'users', 18 ) . esc_html( sprintf( _n( '%1$s person is waiting for %2$s.', '%1$s people are waiting for %2$s.', $n, 'talkwyn' ), number_format_i18n( $n ), $platform ) ) . '</p>';
		}
	}
	$title = '' !== $atts['title'] ? $atts['title'] : ( '' !== $platform ? sprintf( /* translators: %s: platform */ __( 'Join the %s waitlist', 'talkwyn' ), $platform ) : __( 'Join the waitlist', 'talkwyn' ) );
	$text  = '' !== $atts['text'] ? $atts['text'] : __( 'We email you once, the day it launches. No newsletter, no spam.', 'talkwyn' );
	$uid   = 'tw-wl-' . substr( md5( $platform . wp_rand() ), 0, 6 );

	return '<div class="tw-waitlist" id="waitlist">'
		. '<p class="tw-waitlist__title">' . esc_html( $title ) . '</p>'
		. '<p class="tw-small">' . esc_html( $text ) . '</p>'
		. $count
		. talkwyn_form_notice( 'waitlist' )
		. '<form class="tw-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '#waitlist" data-tw-waitlist data-tw-ajax="waitlist">'
		. talkwyn_form_hidden( 'waitlist' )
		. '<label for="' . esc_attr( $uid ) . '-email">' . esc_html__( 'Email', 'talkwyn' ) . '<input id="' . esc_attr( $uid ) . '-email" type="email" name="tw_email" autocomplete="email" required maxlength="190" placeholder="you@business.com"></label>'
		. '<label for="' . esc_attr( $uid ) . '-platform">' . esc_html__( 'Platform', 'talkwyn' ) . '<select id="' . esc_attr( $uid ) . '-platform" name="tw_platform">' . $select . '</select></label>'
		. '<label class="tw-form__full" for="' . esc_attr( $uid ) . '-site">' . esc_html__( 'Website (optional)', 'talkwyn' ) . '<input id="' . esc_attr( $uid ) . '-site" type="url" name="tw_site" autocomplete="url" maxlength="190" placeholder="https://"></label>'
		. ( talkwyn_captcha_on( 'waitlist' ) ? '<div class="tw-form__full">' . talkwyn_captcha_field( 'waitlist' ) . '</div>' : '' )
		. '<p class="tw-form__full"><button class="tw-pill tw-pill--red" type="submit" data-tw-event="waitlist_signup" data-tw-location="' . esc_attr( '' !== $platform ? $platform : 'waitlist' ) . '">' . esc_html__( 'Join the waitlist', 'talkwyn' ) . '</button></p>'
		. '<p class="tw-small tw-form__full">' . wp_kses_post( sprintf( /* translators: %s: privacy URL */ __( 'We use your email only to tell you about this launch. See our <a href="%s">privacy policy</a>.', 'talkwyn' ), esc_url( home_url( '/privacy/' ) ) ) ) . '</p>'
		. '</form></div>';
}
add_shortcode( 'tw_waitlist', 'talkwyn_waitlist_shortcode' );
// The older Shopify-only shortcode keeps working.
add_shortcode(
	'tw_waitlist_form',
	static function () {
		return talkwyn_waitlist_shortcode( array( 'platform' => 'Shopify' ) );
	}
);

/**
 * Store a sign-up (called from the shared form handler after its spam checks).
 *
 * @param string $email    Email.
 * @param string $platform Platform.
 * @param string $site     Website.
 * @return bool True when new.
 */
function talkwyn_waitlist_add( string $email, string $platform, string $site ): bool {
	$email    = strtolower( $email );
	$platform = '' !== $platform ? $platform : __( 'Other platform', 'talkwyn' );
	$existing = get_posts(
		array(
			'post_type'      => TALKWYN_WAITLIST,
			'post_status'    => 'private',
			'title'          => $email,
			'posts_per_page' => 20,
			'fields'         => 'ids',
		)
	);
	foreach ( $existing as $id ) {
		if ( get_post_meta( $id, '_tw_platform', true ) === $platform ) {
			return false;
		}
	}
	$id = wp_insert_post(
		array(
			'post_type'   => TALKWYN_WAITLIST,
			'post_status' => 'private',
			'post_title'  => $email,
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return false;
	}
	update_post_meta( $id, '_tw_platform', $platform );
	update_post_meta( $id, '_tw_site', $site );
	update_post_meta( $id, '_tw_source', isset( $_POST['tw_back'] ) ? esc_url_raw( wp_unslash( $_POST['tw_back'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the form handler.

	/* translators: %s: platform */
	$subject = sprintf( __( 'You are on the Talkwyn %s waitlist', 'talkwyn' ), $platform );
	$body    = sprintf(
		/* translators: 1: platform, 2: site URL */
		__( "Thanks for joining the waitlist for Talkwyn on %1\$s.\n\nWe will email you once, the day it launches. Until then, Talkwyn is live on WordPress and WooCommerce, with a free plan: %2\$s\n\nThe Talkwyn team", 'talkwyn' ),
		$platform,
		home_url( '/integrations/' )
	);
	wp_mail( $email, $subject, $body );
	/* translators: 1: platform, 2: email */
	wp_mail( talkwyn_value( 'contact_email' ), sprintf( '[Talkwyn] New %1$s waitlist sign-up: %2$s', $platform, $email ), $email . "\n" . $site . "\n\n" . sprintf( '%d people on the %s list.', talkwyn_waitlist_count( $platform ), $platform ) );
	return true;
}

/**
 * Move sign-ups stored by theme 1.x (option talkwyn_waitlist, Shopify only) into the post type, once.
 */
add_action(
	'admin_init',
	static function () {
		$old = get_option( 'talkwyn_waitlist' );
		if ( ! is_array( $old ) || ! $old ) {
			return;
		}
		foreach ( $old as $email => $date ) {
			$id = wp_insert_post(
				array(
					'post_type'   => TALKWYN_WAITLIST,
					'post_status' => 'private',
					'post_title'  => strtolower( (string) $email ),
					'post_date'   => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $date ) ) ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_tw_platform', 'Shopify' );
			}
		}
		delete_option( 'talkwyn_waitlist' );
	}
);

// Admin list: platform and website columns, platform filter, CSV export.
add_filter(
	'manage_' . TALKWYN_WAITLIST . '_posts_columns',
	static function () {
		return array(
			'cb'          => '<input type="checkbox">',
			'title'       => __( 'Email', 'talkwyn' ),
			'tw_platform' => __( 'Platform', 'talkwyn' ),
			'tw_site'     => __( 'Website', 'talkwyn' ),
			'date'        => __( 'Date', 'talkwyn' ),
		);
	}
);
add_action(
	'manage_' . TALKWYN_WAITLIST . '_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'tw_platform' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_tw_platform', true ) );
		} elseif ( 'tw_site' === $column ) {
			$site = (string) get_post_meta( $post_id, '_tw_site', true );
			echo '' !== $site ? '<a href="' . esc_url( $site ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $site ) . '</a>' : '';
		}
	},
	10,
	2
);
add_action(
	'restrict_manage_posts',
	static function ( $post_type ) {
		if ( TALKWYN_WAITLIST !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$current = isset( $_GET['tw_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['tw_platform'] ) ) : '';
		echo '<select name="tw_platform"><option value="">' . esc_html__( 'All platforms', 'talkwyn' ) . '</option>';
		foreach ( talkwyn_integrations() as $row ) {
			echo '<option value="' . esc_attr( $row['name'] ) . '"' . selected( $current, $row['name'], false ) . '>' . esc_html( $row['name'] . ' (' . talkwyn_waitlist_count( $row['name'] ) . ')' ) . '</option>';
		}
		echo '</select>';
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'talkwyn_waitlist_csv',
					'tw_platform' => $current,
				),
				admin_url( 'admin-post.php' )
			),
			'talkwyn_waitlist_csv'
		);
		echo ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'talkwyn' ) . '</a>';
	}
);
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || TALKWYN_WAITLIST !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$platform = isset( $_GET['tw_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['tw_platform'] ) ) : '';
		if ( '' !== $platform ) {
			$query->set( 'meta_key', '_tw_platform' );
			$query->set( 'meta_value', $platform );
		}
	}
);
add_action(
	'admin_post_talkwyn_waitlist_csv',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'talkwyn_waitlist_csv' );
		$platform = isset( $_GET['tw_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['tw_platform'] ) ) : '';
		$args     = array(
			'post_type'      => TALKWYN_WAITLIST,
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		);
		if ( '' !== $platform ) {
			$args['meta_key']   = '_tw_platform'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin export.
			$args['meta_value'] = $platform; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- admin export.
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="talkwyn-waitlist-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'email', 'platform', 'website', 'signed_up_at', 'page' ) );
		foreach ( get_posts( $args ) as $post ) {
			$row = array( $post->post_title, get_post_meta( $post->ID, '_tw_platform', true ), get_post_meta( $post->ID, '_tw_site', true ), get_post_time( 'c', true, $post ), get_post_meta( $post->ID, '_tw_source', true ) );
			// Neutralize spreadsheet formulas.
			$row = array_map(
				static function ( $v ) {
					$v = (string) $v;
					return '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ? "'" . $v : $v;
				},
				$row
			);
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);
