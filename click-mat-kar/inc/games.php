<?php
/**
 * Games: custom post type, meta and the V1 catalog.
 *
 * @package ClickMatKar
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'cmk_register_game_cpt' );
function cmk_register_game_cpt() {
	register_post_type(
		'cmk_game',
		array(
			'labels'       => array(
				'name'          => __( 'Games', 'click-mat-kar' ),
				'singular_name' => __( 'Game', 'click-mat-kar' ),
				'add_new_item'  => __( 'Add new bad idea', 'click-mat-kar' ),
				'edit_item'     => __( 'Edit game', 'click-mat-kar' ),
				'menu_name'     => __( 'Games', 'click-mat-kar' ),
			),
			'public'       => true,
			'has_archive'  => 'games',
			'rewrite'      => array( 'slug' => 'games', 'with_front' => false ),
			'menu_icon'    => 'dashicons-games',
			'menu_position' => 5,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
		)
	);

	$meta = array(
		'cmk_engine'  => 'Game engine key (shop, none).',
		'cmk_emoji'   => 'Card sticker emoji.',
		'cmk_color'   => 'Card color token (pink, lavender, mint, orange, blue, red, lime).',
		'cmk_hook'    => 'One-line card hook.',
		'cmk_kicker'  => 'Small label above card title.',
		'cmk_minutes' => 'Rough play length in minutes.',
	);
	foreach ( $meta as $key => $description ) {
		register_post_meta(
			'cmk_game',
			$key,
			array(
				'type'              => 'string',
				'description'       => $description,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}

add_action( 'after_switch_theme', 'cmk_flush_on_switch' );
function cmk_flush_on_switch() {
	cmk_register_game_cpt();
	flush_rewrite_rules();
}

/**
 * V1 roadmap. Single source of truth for installer, homepage cards and fallbacks.
 * Only `live` entries are published; everything else is created as a Draft.
 */
function cmk_games_catalog() {
	return array(
		'shop-like-youre-rich' => array(
			'title'   => "Shop Like You're Rich",
			'kicker'  => __( 'Play first', 'click-mat-kar' ),
			'hook'    => __( 'Fake money. Real temptation.', 'click-mat-kar' ),
			'roman'   => 'Rs 10 Crore hain. Uda do.',
			'emoji'   => '🛍️',
			'color'   => 'pink',
			'engine'  => 'shop',
			'minutes' => '2',
			'live'    => true,
			'excerpt' => __( 'You have a ridiculous budget and zero chill. Build the most unnecessary cart possible and find out your Financial IQ.', 'click-mat-kar' ),
		),
		'dream-wedding'        => array(
			'title'   => 'Plan a Crazy Wedding',
			'kicker'  => __( 'Shaadi special', 'click-mat-kar' ),
			'hook'    => __( 'Bara budget. Bad decisions.', 'click-mat-kar' ),
			'roman'   => 'Bara budget, badi shaadi.',
			'emoji'   => '💍',
			'color'   => 'lavender',
			'engine'  => 'shop',
			'minutes' => '3',
			'live'    => true,
			'excerpt' => __( 'Seven functions, one budget, zero restraint.', 'click-mat-kar' ),
		),
		'spend-1-billion'      => array(
			'title'   => 'Spend $1 Billion',
			'kicker'  => __( 'Big money', 'click-mat-kar' ),
			'hook'    => __( 'Because why not?', 'click-mat-kar' ),
			'roman'   => 'Bas kharch karo.',
			'emoji'   => '💸',
			'color'   => 'mint',
			'engine'  => 'shop',
			'minutes' => '3',
			'live'    => true,
			'excerpt' => __( 'Try to spend a billion. It is harder than it sounds. Not really.', 'click-mat-kar' ),
		),
		'bad-decisions'        => array(
			'title'   => 'Bad Decisions',
			'kicker'  => __( 'Story mode', 'click-mat-kar' ),
			'hook'    => __( "Make choices you shouldn't.", 'click-mat-kar' ),
			'roman'   => 'Socho mat. Bas karo.',
			'emoji'   => '🎮',
			'color'   => 'orange',
			'engine'  => 'quiz',
			'minutes' => '5',
			'live'    => true,
			'excerpt' => __( 'A branching story where every option is worse than the last.', 'click-mat-kar' ),
		),
		'dream-life'           => array(
			'title'   => 'Dream Lifestyle',
			'kicker'  => __( 'Main character', 'click-mat-kar' ),
			'hook'    => __( 'Ghar, gaadi, travel, sab kuch.', 'click-mat-kar' ),
			'roman'   => 'Ghar, gaadi, travel, sab kuch.',
			'emoji'   => '🏝️',
			'color'   => 'blue',
			'engine'  => 'shop',
			'minutes' => '4',
			'live'    => true,
			'excerpt' => __( 'House. Car. Trip. Delusion.', 'click-mat-kar' ),
		),
		'red-flag-check'       => array(
			'title'   => 'Find Your Red Flags',
			'kicker'  => __( '2 min quiz', 'click-mat-kar' ),
			'hook'    => __( 'Kuch sach kadwa hota hai.', 'click-mat-kar' ),
			'roman'   => 'Kuch sach kadwa hota hai.',
			'emoji'   => '🚩',
			'color'   => 'red',
			'engine'  => 'quiz',
			'minutes' => '2',
			'live'    => true,
			'excerpt' => __( 'A completely unnecessary personality crisis.', 'click-mat-kar' ),
		),
		'whats-your-price'     => array(
			'title'   => "What's Your Price?",
			'kicker'  => __( 'Would you?', 'click-mat-kar' ),
			'hook'    => __( 'Would you, for that much?', 'click-mat-kar' ),
			'roman'   => 'Kitne mein bikoge?',
			'emoji'   => '🏷️',
			'color'   => 'lime',
			'engine'  => 'quiz',
			'minutes' => '2',
			'live'    => true,
			'excerpt' => __( 'Put a price on things you should never do. Then compare with friends.', 'click-mat-kar' ),
		),
	);
}

function cmk_get_game_by_slug( $slug ) {
	$posts = get_posts(
		array(
			'post_type'      => 'cmk_game',
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	return $posts ? $posts[0] : null;
}

function cmk_game_engine( $post_id ) {
	$engine = get_post_meta( $post_id, 'cmk_engine', true );
	return $engine ? $engine : 'none';
}

/**
 * Card data for every catalog game, merged with the real post (if any).
 * A card only gets a URL when its game post is published — never a placeholder href.
 */
function cmk_game_cards() {
	$cards = array();
	foreach ( cmk_games_catalog() as $slug => $game ) {
		$post = cmk_get_game_by_slug( $slug );
		$url  = '';
		if ( $post && 'publish' === $post->post_status ) {
			$url = get_permalink( $post );
			foreach ( array( 'emoji', 'color', 'hook', 'kicker' ) as $key ) {
				$value = get_post_meta( $post->ID, 'cmk_' . $key, true );
				if ( '' !== $value ) {
					$game[ $key ] = $value;
				}
			}
			$game['title'] = get_the_title( $post );
		}
		$game['slug']     = $slug;
		$game['url']      = $url;
		$game['playable'] = '' !== $url;
		$cards[ $slug ]   = $game;
	}

	// Games added in wp-admin that are not part of the built-in catalog.
	$extra = get_posts(
		array(
			'post_type'      => 'cmk_game',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'post_name__not_in' => array_keys( $cards ),
			'no_found_rows'  => true,
		)
	);
	foreach ( $extra as $post ) {
		$meta = function ( $key, $fallback ) use ( $post ) {
			$value = get_post_meta( $post->ID, 'cmk_' . $key, true );
			return '' !== $value ? $value : $fallback;
		};
		$cards[ $post->post_name ] = array(
			'title'    => get_the_title( $post ),
			'kicker'   => $meta( 'kicker', __( 'New', 'click-mat-kar' ) ),
			'hook'     => $meta( 'hook', get_the_excerpt( $post ) ),
			'emoji'    => $meta( 'emoji', '🎲' ),
			'color'    => $meta( 'color', 'lavender' ),
			'minutes'  => $meta( 'minutes', '2' ),
			'slug'     => $post->post_name,
			'url'      => get_permalink( $post ),
			'playable' => true,
		);
	}
	return $cards;
}

/**
 * Create or update the catalog games. Publishes games that went live, fills engine/meta,
 * and replaces old "coming soon" kickers. Never touches titles or content you edited.
 *
 * @return string[] Log lines.
 */
function cmk_sync_games() {
	$log   = array();
	$order = 0;
	$stale = array( 'Coming next', 'Coming soon', 'In the lab', 'Cooking' );

	foreach ( cmk_games_catalog() as $slug => $game ) {
		++$order;
		$existing = cmk_get_game_by_slug( $slug );

		if ( ! $existing ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'cmk_game',
					'post_status'  => $game['live'] ? 'publish' : 'draft',
					'post_title'   => $game['title'],
					'post_name'    => $slug,
					'post_excerpt' => $game['excerpt'],
					'menu_order'   => $order,
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			foreach ( array( 'engine', 'emoji', 'color', 'hook', 'kicker', 'minutes' ) as $key ) {
				update_post_meta( $post_id, 'cmk_' . $key, $game[ $key ] );
			}
			$log[] = sprintf( 'Created game (%s): %s', $game['live'] ? 'live' : 'draft', $game['title'] );
			continue;
		}

		$post_id = $existing->ID;
		if ( $game['live'] && 'draft' === $existing->post_status ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			$log[] = sprintf( 'Launched game: %s', $game['title'] );
		} else {
			$log[] = sprintf( 'Kept game: %s', $game['title'] );
		}

		$engine = get_post_meta( $post_id, 'cmk_engine', true );
		if ( ( '' === $engine || 'none' === $engine ) && 'none' !== $game['engine'] ) {
			update_post_meta( $post_id, 'cmk_engine', $game['engine'] );
		}
		if ( in_array( get_post_meta( $post_id, 'cmk_kicker', true ), $stale, true ) ) {
			update_post_meta( $post_id, 'cmk_kicker', $game['kicker'] );
		}
		foreach ( array( 'emoji', 'color', 'hook', 'minutes' ) as $key ) {
			if ( '' === get_post_meta( $post_id, 'cmk_' . $key, true ) ) {
				update_post_meta( $post_id, 'cmk_' . $key, $game[ $key ] );
			}
		}
	}
	return $log;
}

/**
 * Theme updated (uploaded over an existing install): sync games once per version so
 * new launches go live without re-running the full setup.
 */
add_action( 'init', 'cmk_maybe_upgrade', 20 );
function cmk_maybe_upgrade() {
	$ran = get_option( 'cmk_setup_version' );
	if ( ! $ran || CMK_VERSION === get_option( 'cmk_games_version' ) ) {
		return;
	}
	cmk_sync_games();
	update_option( 'cmk_games_version', CMK_VERSION );
}
