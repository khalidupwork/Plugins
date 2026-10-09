<?php
/**
 * Contact form messages, saved in the admin (Contact messages) as well as emailed,
 * so nothing is lost when an email does not arrive.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

const TALKWYN_MESSAGE = 'tw_message';

add_action(
	'init',
	static function () {
		register_post_type(
			TALKWYN_MESSAGE,
			array(
				'labels'          => array(
					'name'          => __( 'Contact messages', 'talkwyn' ),
					'singular_name' => __( 'Contact message', 'talkwyn' ),
					'all_items'     => __( 'All messages', 'talkwyn' ),
					'edit_item'     => __( 'Message', 'talkwyn' ),
					'search_items'  => __( 'Search messages', 'talkwyn' ),
					'not_found'     => __( 'No messages yet.', 'talkwyn' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/**
 * Save a contact message.
 *
 * @param array{name: string, email: string, site: string, topic: string, message: string, page: string} $m Message.
 * @return int Post ID, 0 on failure.
 */
function talkwyn_message_add( array $m ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => TALKWYN_MESSAGE,
			'post_status'  => 'private',
			/* translators: 1: sender name, 2: topic */
			'post_title'   => sprintf( __( '%1$s: %2$s', 'talkwyn' ), $m['name'], $m['topic'] ),
			'post_content' => $m['message'],
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	foreach ( array( 'name', 'email', 'site', 'topic', 'page' ) as $key ) {
		update_post_meta( $id, '_tw_' . $key, $m[ $key ] );
	}
	update_post_meta( $id, '_tw_unread', 1 );
	return (int) $id;
}

/**
 * Unread messages.
 */
function talkwyn_message_unread(): int {
	$q = new WP_Query(
		array(
			'post_type'      => TALKWYN_MESSAGE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_tw_unread', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin badge, small table.
			'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- admin badge, small table.
			'no_found_rows'  => false,
		)
	);
	return (int) $q->found_posts;
}

// Unread count next to the menu item.
add_action(
	'admin_menu',
	static function () {
		global $menu, $pagenow;
		// Opening a message marks it read. Done here because the menu is built before load-post.php.
		$id = 'post.php' === $pagenow && isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- marks a message read, nothing else.
		if ( $id && TALKWYN_MESSAGE === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
			delete_post_meta( $id, '_tw_unread' );
		}
		$count = talkwyn_message_unread();
		if ( ! $count || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . TALKWYN_MESSAGE === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- standard way to add a menu badge.
			}
		}
	},
	99
);

// List columns.
add_filter(
	'manage_' . TALKWYN_MESSAGE . '_posts_columns',
	static function () {
		return array(
			'cb'         => '<input type="checkbox">',
			'title'      => __( 'Message', 'talkwyn' ),
			'tw_from'    => __( 'From', 'talkwyn' ),
			'tw_topic'   => __( 'Topic', 'talkwyn' ),
			'tw_site'    => __( 'Website', 'talkwyn' ),
			'date'       => __( 'Date', 'talkwyn' ),
		);
	}
);
add_action(
	'manage_' . TALKWYN_MESSAGE . '_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'tw_from' === $column ) {
			$email = (string) get_post_meta( $post_id, '_tw_email', true );
			echo esc_html( (string) get_post_meta( $post_id, '_tw_name', true ) ) . '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		} elseif ( 'tw_topic' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_tw_topic', true ) );
		} elseif ( 'tw_site' === $column ) {
			$site = (string) get_post_meta( $post_id, '_tw_site', true );
			echo '' !== $site ? '<a href="' . esc_url( $site ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $site ) . '</a>' : '';
		}
	},
	10,
	2
);

// Bold title for unread messages.
add_filter(
	'post_class',
	static function ( $classes, $class, $post_id ) {
		if ( is_admin() && TALKWYN_MESSAGE === get_post_type( $post_id ) && get_post_meta( $post_id, '_tw_unread', true ) ) {
			$classes[] = 'tw-unread';
		}
		return $classes;
	},
	10,
	3
);
add_action(
	'admin_head-edit.php',
	static function () {
		if ( TALKWYN_MESSAGE === get_current_screen()->post_type ) {
			echo '<style>.tw-unread .row-title{font-weight:700}.tw-unread .row-title::before{content:"";display:inline-block;width:8px;height:8px;margin-right:6px;border-radius:50%;background:#D7263D;vertical-align:middle}</style>';
		}
	}
);

// Read view: the message and a reply button, read-only.
add_action(
	'add_meta_boxes_' . TALKWYN_MESSAGE,
	static function ( $post ) {
		add_meta_box(
			'tw-message',
			__( 'Message', 'talkwyn' ),
			static function ( $post ) {
				$email = (string) get_post_meta( $post->ID, '_tw_email', true );
				$site  = (string) get_post_meta( $post->ID, '_tw_site', true );
				$page  = (string) get_post_meta( $post->ID, '_tw_page', true );
				$rows  = array(
					__( 'Name', 'talkwyn' )    => esc_html( (string) get_post_meta( $post->ID, '_tw_name', true ) ),
					__( 'Email', 'talkwyn' )   => '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>',
					__( 'Website', 'talkwyn' ) => '' !== $site ? '<a href="' . esc_url( $site ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $site ) . '</a>' : '',
					__( 'Topic', 'talkwyn' )   => esc_html( (string) get_post_meta( $post->ID, '_tw_topic', true ) ),
					__( 'Sent from', 'talkwyn' ) => '' !== $page ? '<a href="' . esc_url( $page ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $page ) . '</a>' : '',
				);
				echo '<table class="form-table" role="presentation">';
				foreach ( $rows as $label => $value ) {
					echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				}
				echo '</table><div style="padding:12px 16px;background:#F7F3F3;border-radius:8px;white-space:pre-wrap;font-size:14px;line-height:1.6">' . esc_html( $post->post_content ) . '</div>';
				$subject = rawurlencode( 'Re: ' . (string) get_post_meta( $post->ID, '_tw_topic', true ) );
				echo '<p><a class="button button-primary" href="mailto:' . esc_attr( $email ) . '?subject=' . esc_attr( $subject ) . '">' . esc_html__( 'Reply by email', 'talkwyn' ) . '</a></p>';
			},
			TALKWYN_MESSAGE,
			'normal',
			'high'
		);
	}
);
