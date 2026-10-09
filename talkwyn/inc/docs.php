<?php
/**
 * Docs: "doc" post type under /docs/, doc_category taxonomy, navigation, TOC, feedback.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'doc',
			array(
				'labels'       => array(
					'name'          => __( 'Docs', 'talkwyn' ),
					'singular_name' => __( 'Doc', 'talkwyn' ),
					'add_new_item'  => __( 'Add new doc', 'talkwyn' ),
					'edit_item'     => __( 'Edit doc', 'talkwyn' ),
					'all_items'     => __( 'All docs', 'talkwyn' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-book-alt',
				'has_archive'  => 'docs',
				'rewrite'      => array(
					'slug'       => 'docs',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions', 'custom-fields', 'author' ),
			)
		);
		register_taxonomy(
			'doc_category',
			'doc',
			array(
				'labels'            => array(
					'name'          => __( 'Doc categories', 'talkwyn' ),
					'singular_name' => __( 'Doc category', 'talkwyn' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'         => 'docs/category',
					'with_front'   => false,
					'hierarchical' => true,
				),
			)
		);
		register_post_meta(
			'doc',
			'_tw_helpful_yes',
			array(
				'type'   => 'integer',
				'single' => true,
			)
		);
		register_post_meta(
			'doc',
			'_tw_helpful_no',
			array(
				'type'   => 'integer',
				'single' => true,
			)
		);
	}
);

/**
 * Docs grouped by category, ordered by menu_order.
 *
 * @return array<int, array{term: WP_Term|null, docs: WP_Post[]}>
 */
function talkwyn_docs_grouped(): array {
	$docs   = get_posts(
		array(
			'post_type'      => 'doc',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);
	$groups = array();
	$loose  = array();
	foreach ( $docs as $doc ) {
		$terms = get_the_terms( $doc, 'doc_category' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			if ( ! isset( $groups[ $term->term_id ] ) ) {
				$groups[ $term->term_id ] = array(
					'term' => $term,
					'docs' => array(),
				);
			}
			$groups[ $term->term_id ]['docs'][] = $doc;
		} else {
			$loose[] = $doc;
		}
	}
	uasort(
		$groups,
		static function ( $a, $b ) {
			return (int) get_term_meta( $a['term']->term_id, 'order', true ) <=> (int) get_term_meta( $b['term']->term_id, 'order', true );
		}
	);
	$out = array_values( $groups );
	if ( $loose ) {
		$out[] = array(
			'term' => null,
			'docs' => $loose,
		);
	}
	return $out;
}

/**
 * Flat, ordered list of docs (for previous/next).
 *
 * @return WP_Post[]
 */
function talkwyn_docs_flat(): array {
	$flat = array();
	foreach ( talkwyn_docs_grouped() as $group ) {
		foreach ( $group['docs'] as $doc ) {
			$flat[] = $doc;
		}
	}
	return $flat;
}

add_shortcode(
	'tw_docs_nav',
	static function () {
		$current = is_singular( 'doc' ) ? get_queried_object_id() : 0;
		$archive = get_post_type_archive_link( 'doc' );
		$html    = '<nav class="tw-docs__nav" aria-label="' . esc_attr__( 'Docs', 'talkwyn' ) . '"><details class="tw-docs__menu" open data-tw-docs-menu>'
			. '<summary class="tw-docs__toggle">' . talkwyn_icon( 'book-open', 18 ) . '<span>' . esc_html__( 'Browse the docs', 'talkwyn' ) . '</span>' . talkwyn_icon( 'chevron-down', 16 ) . '</summary>'
			. '<a class="tw-docs__home" href="' . esc_url( $archive ? $archive : home_url( '/docs/' ) ) . '">' . talkwyn_icon( 'book-open', 18 ) . '<span>' . esc_html__( 'Talkwyn docs', 'talkwyn' ) . '</span></a>'
			. '<label class="tw-docs__search">' . talkwyn_icon( 'search', 16 ) . '<span class="screen-reader-text">' . esc_html__( 'Search the docs', 'talkwyn' ) . '</span><input type="search" placeholder="' . esc_attr__( 'Search the docs', 'talkwyn' ) . '" data-tw-docs-filter autocomplete="off"></label>';
		foreach ( talkwyn_docs_grouped() as $group ) {
			$html .= '<div class="tw-docs__group"><h2>' . esc_html( $group['term'] ? $group['term']->name : __( 'Guides', 'talkwyn' ) ) . '</h2><ul>';
			foreach ( $group['docs'] as $doc ) {
				$aria  = $doc->ID === $current ? ' aria-current="page"' : '';
				$html .= '<li><a href="' . esc_url( get_permalink( $doc ) ) . '"' . $aria . '>' . talkwyn_icon( 'file-text', 16 ) . '<span>' . esc_html( get_the_title( $doc ) ) . '</span></a></li>';
			}
			$html .= '</ul></div>';
		}
		$html .= '<p class="tw-docs__none" hidden data-tw-docs-none>' . esc_html__( 'No guide matches. Try another word, or ask us.', 'talkwyn' ) . '</p>';
		$html .= '<div class="tw-docs__help"><strong>' . esc_html__( 'Stuck on a step?', 'talkwyn' ) . '</strong><span>' . esc_html__( 'A real person replies by email.', 'talkwyn' ) . '</span>'
			. '<a href="' . esc_url( home_url( '/contact/' ) ) . '">' . talkwyn_icon( 'mail', 16 ) . esc_html__( 'Contact support', 'talkwyn' ) . '</a></div>';
		return $html . '</details></nav>';
	}
);

add_shortcode(
	'tw_docs_index',
	static function () {
		$html   = '';
		$groups = talkwyn_docs_grouped();
		foreach ( $groups as $group ) {
			// With a single group the page title already says what these are, so no group heading.
			$head  = count( $groups ) > 1 ? '<h2>' . esc_html( $group['term'] ? $group['term']->name : __( 'Guides', 'talkwyn' ) ) . '</h2>' : '';
			$html .= '<section class="tw-docs-group">' . $head . '<div class="tw-cards">';
			foreach ( $group['docs'] as $doc ) {
				$html .= '<article class="tw-card"><span class="tw-card__icon">' . talkwyn_icon( 'file-text' ) . '</span><h3>' . esc_html( get_the_title( $doc ) ) . '</h3>';
				if ( has_excerpt( $doc ) ) {
					$html .= '<p>' . esc_html( get_the_excerpt( $doc ) ) . '</p>';
				}
				$html .= '<a class="tw-card__link" href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html__( 'Read the guide', 'talkwyn' ) . ' ' . talkwyn_icon( 'arrow-right', 18 ) . '</a></article>';
			}
			$html .= '</div></section>';
		}
		return '' === $html ? '<p>' . esc_html__( 'Docs are being written. Check back soon.', 'talkwyn' ) . '</p>' : $html;
	}
);

/**
 * Stable anchor id for a heading text.
 *
 * @param string $text Heading text.
 */
function talkwyn_heading_id( string $text ): string {
	$id = sanitize_title( wp_strip_all_tags( $text ) );
	return '' === $id ? 'section' : $id;
}

/**
 * Give H2/H3 headings in docs, posts and pages an id so they can be linked.
 *
 * @param string               $html  Block HTML.
 * @param array<string, mixed> $block Block.
 */
add_filter(
	'render_block_core/heading',
	static function ( $html, $block ) {
		$level = (int) ( $block['attrs']['level'] ?? 2 );
		if ( ! in_array( $level, array( 2, 3 ), true ) || false !== strpos( (string) $html, ' id="' ) || ! is_singular() ) {
			return $html;
		}
		$id = talkwyn_heading_id( (string) $html );
		return (string) preg_replace( '/<h([23])(\s|>)/', '<h$1 id="' . esc_attr( $id ) . '"$2', (string) $html, 1 );
	},
	10,
	2
);

add_shortcode(
	'tw_toc',
	static function () {
		if ( ! is_singular() ) {
			return '';
		}
		$items = array();
		$walk  = static function ( array $blocks ) use ( &$walk, &$items ) {
			foreach ( $blocks as $block ) {
				if ( 'core/heading' === $block['blockName'] ) {
					$level = (int) ( $block['attrs']['level'] ?? 2 );
					if ( in_array( $level, array( 2, 3 ), true ) ) {
						$text = trim( wp_strip_all_tags( (string) $block['innerHTML'] ) );
						if ( preg_match( '/id="([^"]+)"/', (string) $block['innerHTML'], $m ) ) {
							$id = $m[1];
						} else {
							$id = talkwyn_heading_id( (string) $block['innerHTML'] );
						}
						$items[] = array( $level, $text, $id );
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( parse_blocks( (string) get_post_field( 'post_content', get_queried_object_id() ) ) );
		if ( count( $items ) < 2 ) {
			return '<aside class="tw-toc" aria-hidden="true"></aside>';
		}
		$html = '<aside class="tw-toc"><nav aria-label="' . esc_attr__( 'On this page', 'talkwyn' ) . '"><h2>' . esc_html__( 'On this page', 'talkwyn' ) . '</h2><ul>';
		foreach ( $items as $item ) {
			$html .= '<li class="tw-toc__h' . (int) $item[0] . '"><a href="#' . esc_attr( $item[2] ) . '">' . esc_html( $item[1] ) . '</a></li>';
		}
		return $html . '</ul></nav></aside>';
	}
);

/**
 * Reading time and last update under a doc title.
 */
add_shortcode(
	'tw_doc_meta',
	static function () {
		if ( ! is_singular() ) {
			return '';
		}
		$id      = get_queried_object_id();
		$words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) );
		$minutes = max( 1, (int) round( $words / 220 ) );
		return '<p class="tw-docs__meta"><span>' . talkwyn_icon( 'clock', 16 )
			/* translators: %d: minutes */
			. esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'talkwyn' ), $minutes ) ) . '</span><span>' . talkwyn_icon( 'refresh-cw', 16 )
			/* translators: %s: date */
			. esc_html( sprintf( __( 'Updated %s', 'talkwyn' ), get_the_modified_date( '', $id ) ) ) . '</span></p>';
	}
);

/**
 * Numbered step headings in docs ("1. Install the plugin") get a round number badge.
 */
add_filter(
	'render_block_core/heading',
	static function ( $html ) {
		if ( ! is_singular( 'doc' ) ) {
			return $html;
		}
		return (string) preg_replace( '/(<h2[^>]*>)\\s*(\\d{1,2})\\.\\s+/', '$1<span class="tw-step-n" aria-hidden="true">$2</span><span class="screen-reader-text">$2. </span>', (string) $html, 1 );
	},
	20
);

add_shortcode(
	'tw_doc_footer',
	static function () {
		if ( ! is_singular( 'doc' ) ) {
			return '';
		}
		$id   = get_queried_object_id();
		$flat = talkwyn_docs_flat();
		$pos  = null;
		foreach ( $flat as $i => $doc ) {
			if ( $doc->ID === $id ) {
				$pos = $i;
			}
		}
		$html = '<div class="tw-helpful" data-tw-helpful="' . (int) $id . '"><p id="tw-helpful-q">' . esc_html__( 'Was this helpful?', 'talkwyn' ) . '</p>'
			. '<button type="button" class="tw-chip" data-value="yes" aria-describedby="tw-helpful-q">' . talkwyn_icon( 'thumbs-up', 16 ) . ' ' . esc_html__( 'Yes', 'talkwyn' ) . '</button>'
			. '<button type="button" class="tw-chip" data-value="no" aria-describedby="tw-helpful-q">' . talkwyn_icon( 'thumbs-down', 16 ) . ' ' . esc_html__( 'No', 'talkwyn' ) . '</button>'
			. '<span class="tw-small" role="status" data-tw-helpful-status></span></div>';
		if ( null !== $pos ) {
			$html .= '<nav class="tw-doc-pager" aria-label="' . esc_attr__( 'Previous and next guide', 'talkwyn' ) . '">';
			if ( $pos > 0 ) {
				$prev  = $flat[ $pos - 1 ];
				$html .= '<a class="tw-doc-pager__prev" href="' . esc_url( get_permalink( $prev ) ) . '" rel="prev"><small>' . esc_html__( 'Previous', 'talkwyn' ) . '</small>' . esc_html( get_the_title( $prev ) ) . '</a>';
			}
			if ( $pos < count( $flat ) - 1 ) {
				$next  = $flat[ $pos + 1 ];
				$html .= '<a class="tw-doc-pager__next" href="' . esc_url( get_permalink( $next ) ) . '" rel="next"><small>' . esc_html__( 'Next', 'talkwyn' ) . '</small>' . esc_html( get_the_title( $next ) ) . '</a>';
			}
			$html .= '</nav>';
		}
		wp_add_inline_script( 'talkwyn', 'window.twRest=' . wp_json_encode( array( 'url' => esc_url_raw( rest_url( 'talkwyn/v1/doc-feedback' ) ) ) ) . ';', 'before' );
		return $html;
	}
);

/**
 * "Was this helpful?" endpoint. Public; one vote per IP per doc per day.
 */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'talkwyn/v1',
			'/doc-feedback',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'value'   => array(
						'type'     => 'string',
						'enum'     => array( 'yes', 'no' ),
						'required' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$id = (int) $request['post_id'];
					if ( 'doc' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
						return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
					}
					$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
					$lock = 'tw_fb_' . md5( $ip . '|' . $id . '|' . wp_salt() );
					if ( get_transient( $lock ) ) {
						return array( 'ok' => true );
					}
					set_transient( $lock, 1, DAY_IN_SECONDS );
					$key = 'yes' === $request['value'] ? '_tw_helpful_yes' : '_tw_helpful_no';
					update_post_meta( $id, $key, (int) get_post_meta( $id, $key, true ) + 1 );
					return array( 'ok' => true );
				},
			)
		);
	}
);

/**
 * Show feedback counts in the docs list table.
 */
add_filter(
	'manage_doc_posts_columns',
	static function ( $columns ) {
		$columns['tw_helpful'] = __( 'Helpful (yes / no)', 'talkwyn' );
		return $columns;
	}
);
add_action(
	'manage_doc_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'tw_helpful' === $column ) {
			echo esc_html( (int) get_post_meta( $post_id, '_tw_helpful_yes', true ) . ' / ' . (int) get_post_meta( $post_id, '_tw_helpful_no', true ) );
		}
	},
	10,
	2
);

// Blog helpers.

add_shortcode(
	'tw_category_filter',
	static function () {
		$cats  = get_categories(
			array(
				'hide_empty' => true,
				'exclude'    => array( (int) get_option( 'default_category' ) ),
			)
		);
		$blog  = (int) get_option( 'page_for_posts' );
		$all   = $blog ? (string) get_permalink( $blog ) : home_url( '/blog/' );
		$html  = '<nav class="tw-filter" aria-label="' . esc_attr__( 'Filter by category', 'talkwyn' ) . '">';
		$html .= '<a class="tw-chip" href="' . esc_url( $all ) . '"' . ( is_home() ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'All', 'talkwyn' ) . '</a>';
		foreach ( $cats as $cat ) {
			$current = is_category( $cat->term_id ) ? ' aria-current="page"' : '';
			$html   .= '<a class="tw-chip" href="' . esc_url( get_category_link( $cat ) ) . '"' . $current . '>' . esc_html( $cat->name ) . '</a>';
		}
		return $html . '</nav>';
	}
);

add_shortcode(
	'tw_related_posts',
	static function () {
		if ( ! is_singular( 'post' ) ) {
			return '';
		}
		$id      = get_queried_object_id();
		$cats    = wp_get_post_categories( $id );
		$related = get_posts(
			array(
				'post__not_in'   => array( $id ),
				'category__in'   => $cats,
				'posts_per_page' => 3,
			)
		);
		if ( count( $related ) < 3 ) {
			$related = array_merge(
				$related,
				get_posts(
					array(
						'post__not_in'   => array_merge( array( $id ), wp_list_pluck( $related, 'ID' ) ),
						'posts_per_page' => 3 - count( $related ),
					)
				)
			);
		}
		if ( ! $related ) {
			return '';
		}
		$html = '<aside class="tw-related"><h2>' . esc_html__( 'Keep reading', 'talkwyn' ) . '</h2><div class="tw-related__grid">';
		foreach ( $related as $post ) {
			$cats_of = get_the_category( $post->ID );
			$html   .= '<article class="tw-post-card">'
				. ( has_post_thumbnail( $post ) ? '<div class="tw-post-card__img">' . get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => '' ) ) . '</div>' : '' )
				. '<div class="tw-post-card__body">'
				. ( $cats_of ? '<span class="tw-post-card__cat">' . esc_html( $cats_of[0]->name ) . '</span>' : '' )
				. '<h3><a class="tw-post-card__link" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>'
				. '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ) . '</p>'
				. '<span class="tw-post-card__more" aria-hidden="true">' . esc_html__( 'Read the article', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</span></div></article>';
		}
		return $html . '</div></aside>';
	}
);

/**
 * [tw_post_meta] Author, date and reading time under a post title.
 */
add_shortcode(
	'tw_post_meta',
	static function () {
		$post = get_post();
		if ( ! $post ) {
			return '';
		}
		$words   = str_word_count( wp_strip_all_tags( (string) $post->post_content ) );
		$minutes = max( 1, (int) round( $words / 220 ) );
		$author  = get_the_author_meta( 'display_name', (int) $post->post_author );
		return '<p class="tw-post-meta"><span class="tw-post-meta__mark" aria-hidden="true">' . talkwyn_logo_svg( 'mark', '' ) . '</span>'
			. '<span>' . esc_html( $author ? $author : __( 'Talkwyn team', 'talkwyn' ) ) . '</span><span aria-hidden="true">·</span>'
			. '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><span aria-hidden="true">·</span>'
			/* translators: %d: minutes */
			. '<span>' . esc_html( sprintf( _n( '%d minute read', '%d minute read', $minutes, 'talkwyn' ), $minutes ) ) . '</span></p>';
	}
);

/**
 * [tw_post_aside] Sticky sidebar for posts: contents (moved in by theme.js) and a trial card.
 */
add_shortcode(
	'tw_post_aside',
	static function () {
		$days = talkwyn_trial()['days'];
		return '<aside class="tw-post-aside" aria-label="' . esc_attr__( 'Article tools', 'talkwyn' ) . '"><div class="tw-post-aside__sticky">'
			. '<div class="tw-post-aside__toc" data-tw-toc-target></div>'
			. '<div class="tw-post-aside__cta"><span class="tw-post-aside__icon">' . talkwyn_icon( 'sparkles', 20 ) . '</span>'
			. '<p class="tw-post-aside__title">' . esc_html__( 'Try it on your own site', 'talkwyn' ) . '</p>'
			/* translators: %d: trial days */
			. '<p>' . esc_html( sprintf( __( 'Every Pro feature free for %d days. Live in about five minutes.', 'talkwyn' ), $days ) ) . '</p>'
			. '<a class="tw-pill tw-pill--red tw-pill--block" href="' . esc_url( home_url( '/pricing/#trial' ) ) . '" data-tw-event="trial_click" data-tw-location="post_aside" data-tw-modal="trial">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>'
			. '<a class="tw-arrow-link" href="' . esc_url( talkwyn_install_url() ) . '" data-tw-event="install_click" data-tw-location="post_aside">' . esc_html__( 'Or install free', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a></div>'
			. '</div></aside>';
	}
);

/**
 * [tw_not_found] The 404 page body: message, search, and the pages people look for most.
 */
add_shortcode(
	'tw_not_found',
	static function () {
		$links = array(
			array( 'wallet', __( 'Pricing', 'talkwyn' ), __( 'Plans, the free trial, and the free plan.', 'talkwyn' ), '/pricing/' ),
			array( 'rocket', __( 'Setup guide', 'talkwyn' ), __( 'From install to first answer.', 'talkwyn' ), '/docs/getting-started/' ),
			array( 'plug', __( 'WordPress plugin', 'talkwyn' ), __( 'The chatbot that is live today.', 'talkwyn' ), '/integrations/wordpress/' ),
			array( 'building-2', __( 'Industries', 'talkwyn' ), __( 'Twelve kinds of business, with examples.', 'talkwyn' ), '/industries/' ),
			array( 'languages', __( 'Languages', 'talkwyn' ), __( 'Twelve focus languages.', 'talkwyn' ), '/multilingual-chatbot/' ),
			array( 'newspaper', __( 'Blog', 'talkwyn' ), __( 'Guides for chatbots that bring leads.', 'talkwyn' ), '/blog/' ),
		);
		$cards = '';
		foreach ( $links as $l ) {
			$cards .= '<a class="tw-nf__card" href="' . esc_url( home_url( $l[3] ) ) . '"><span class="tw-card__icon">' . talkwyn_icon( $l[0], 20 ) . '</span><span><b>' . esc_html( $l[1] ) . '</b><i>' . esc_html( $l[2] ) . '</i></span>' . talkwyn_icon( 'arrow-right', 16 ) . '</a>';
		}
		return '<section class="tw-nf tw-blush tw-inset"><div class="tw-nf__grid">'
			. '<div class="tw-nf__text"><p class="tw-eyebrow"><span class="tw-dot" aria-hidden="true"></span>' . esc_html__( 'Error 404', 'talkwyn' ) . '</p>'
			. '<h1>' . esc_html__( 'This page did not answer.', 'talkwyn' ) . '</h1>'
			. '<p class="tw-lede">' . esc_html__( 'The link may be old, or the page moved. Search the site, or pick one of the pages people look for most.', 'talkwyn' ) . '</p>'
			. '<form class="tw-nf__search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label class="screen-reader-text" for="tw-nf-s">' . esc_html__( 'Search the site', 'talkwyn' ) . '</label>'
			. '<input id="tw-nf-s" type="search" name="s" placeholder="' . esc_attr__( 'Search the site', 'talkwyn' ) . '"><button class="tw-pill tw-pill--red" type="submit">' . esc_html__( 'Search', 'talkwyn' ) . '</button></form>'
			. '<p class="tw-nf__home"><a class="tw-arrow-link" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the homepage', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a></p></div>'
			. '<div class="tw-nf__chat" aria-hidden="true"><div class="tw-vcard"><div class="tw-msg tw-msg--user"><p>' . esc_html__( 'Where did this page go?', 'talkwyn' ) . '</p></div><div class="tw-msg tw-msg--bot"><p>' . esc_html__( 'I could not find it. Here are the pages most people look for.', 'talkwyn' ) . '</p></div><span class="tw-nf__code">404</span></div></div>'
			. '</div><div class="tw-nf__cards">' . $cards . '</div></section>';
	}
);

/**
 * Scheduled posts link to each other. Until a linked post is published, show the link text
 * without the link, so readers never land on a 404; the link appears on its own once it goes live.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( false === strpos( (string) $content, '/blog/' ) ) {
			return $content;
		}
		static $hidden = null;
		if ( null === $hidden ) {
			$hidden = array();
			foreach ( get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => array( 'future', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) as $id ) {
				$hidden[] = (string) get_post_field( 'post_name', $id );
			}
		}
		if ( ! $hidden ) {
			return $content;
		}
		return (string) preg_replace_callback(
			'#<a\b[^>]*href="[^"]*/blog/([a-z0-9-]+)/?(?:\#[^"]*)?"[^>]*>(.*?)</a>#is',
			static function ( $m ) use ( $hidden ) {
				return in_array( $m[1], $hidden, true ) ? $m[2] : $m[0];
			},
			(string) $content
		);
	},
	20
);

/**
 * Screenshots used in docs and guides (assets/docs). Key => file, alt text, default caption, narrow.
 * Sample chats use made-up businesses and are captioned as examples.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
 */
function talkwyn_doc_shots(): array {
	return array(
		'plugins'       => array( 'plugins.webp', __( 'The WordPress Plugins screen with Talkwyn installed and active', 'talkwyn' ), __( 'Talkwyn installed and active under Plugins.', 'talkwyn' ), false ),
		'scan'          => array( 'scan.webp', __( 'The Talkwyn Knowledge tab with the Scan entire site button and counts of pages, posts and products read', 'talkwyn' ), __( 'Rescan any time under Talkwyn, Knowledge, and see what was read.', 'talkwyn' ), false ),
		'providers'     => array( 'providers.webp', __( 'Talkwyn AI providers fallback order with Groq first and Google Gemini second, both with a key added', 'talkwyn' ), __( 'Two free providers: Groq first, Gemini as the backup.', 'talkwyn' ), false ),
		'appearance'    => array( 'appearance.webp', __( 'Talkwyn appearance settings with name, welcome message and suggested questions next to a live preview of the chat', 'talkwyn' ), __( 'Appearance settings with a live preview of your chat.', 'talkwyn' ), false ),
		'conversations' => array( 'conversations.webp', __( 'The Talkwyn Conversations tab with sample chats from a bakery, one opened to show the question and the answer', 'talkwyn' ), __( 'Talkwyn, Conversations: every chat, with the answer and the provider that replied.', 'talkwyn' ), false ),
		'lead-email'    => array( 'lead-email.webp', __( 'Example Talkwyn lead email for a sample real estate agency with the visitor name, email, phone, page and question', 'talkwyn' ), __( 'Example: the lead email gives you the visitor\'s details and their question. The full chat is in WordPress under Talkwyn, Conversations.', 'talkwyn' ), false ),
		'woo'           => array( 'woo-attributes.webp', __( 'A WooCommerce product with Size, Color and Material attributes filled in', 'talkwyn' ), __( 'Filled-in product attributes give the chatbot something real to answer from.', 'talkwyn' ), false ),
		'chat-sources'  => array( 'chat-sources.webp', __( 'Sample chat answering an opening hours question, with its source page listed under the answer', 'talkwyn' ), __( 'A first answer, with the page it came from.', 'talkwyn' ), true ),
		'chat-offer'    => array( 'chat-offer.webp', __( 'Sample chat: a visitor asks to book, Talkwyn answers and asks whether the team may contact them', 'talkwyn' ), __( 'The chatbot asks first. The form opens only after Yes.', 'talkwyn' ), true ),
		'chat-delivery' => array( 'chat-delivery.webp', __( 'Sample chat: a visitor asks about Saturday delivery and gets the answer with a source link', 'talkwyn' ), __( 'A chatbot can answer on the spot.', 'talkwyn' ), true ),
		'chat-dental'   => array( 'chat-dental.webp', __( 'Sample dental clinic chat: Saturday opening hours answered with a link to the contact page', 'talkwyn' ), __( 'Example: an hours question answered from the clinic\'s own page.', 'talkwyn' ), true ),
		'chat-hotel'    => array( 'chat-hotel.webp', __( 'Sample hotel chat: check-in time and parking answered with links to two pages', 'talkwyn' ), __( 'Example: arrival questions answered from the hotel\'s own pages.', 'talkwyn' ), true ),
		'chat-spanish'  => array( 'chat-spanish.webp', __( 'Sample store chat in Spanish about shipping to Puerto Rico, with the English shipping page as the source', 'talkwyn' ), __( 'Example: a question in Spanish, an answer in Spanish.', 'talkwyn' ), true ),
		'chat-arabic'   => array( 'chat-arabic.webp', __( 'Sample chat in Arabic shown right to left, with the English product name and the price kept as written', 'talkwyn' ), __( 'Arabic shown right to left, with names and prices kept as written.', 'talkwyn' ), true ),
		'chat-viewing'  => array( 'chat-viewing.webp', __( 'Sample real estate chat: Saturday viewing times answered, then an offer for the team to get in touch', 'talkwyn' ), __( 'Help first, then offer a follow-up.', 'talkwyn' ), true ),
		'chat-night'    => array( 'chat-night.webp', __( 'Sample chat after hours: the chatbot explains the team replies the next working day and offers to pass the request on', 'talkwyn' ), __( 'Answer what you can, then ask before collecting details.', 'talkwyn' ), true ),
	);
}

/**
 * Diagrams drawn in HTML (no image). Key => type (flow, compare, outline) and content.
 *
 * @return array<string, array<string, mixed>>
 */
function talkwyn_doc_diagrams(): array {
	return array(
		're-answer'   => array( 'flow', array( __( 'A buyer asks about a listing', 'talkwyn' ), __( 'The chatbot finds the listing page on your site', 'talkwyn' ), __( 'It answers and links to the listing', 'talkwyn' ) ) ),
		're-leads'    => array( 'flow', array( __( 'Answer the question', 'talkwyn' ), __( 'Ask budget, area and timeline', 'talkwyn' ), __( 'Ask for contact details', 'talkwyn' ), __( 'Email the lead to the agency', 'talkwyn' ) ) ),
		'kinds'       => array( 'compare', __( 'Plugin inside WordPress', 'talkwyn' ), array( __( 'Runs on your own site', 'talkwyn' ), __( 'Chats and leads stay in your WordPress database', 'talkwyn' ) ), __( 'Hosted service', 'talkwyn' ), array( __( 'Runs on the vendor\'s servers', 'talkwyn' ), __( 'Connects to WordPress with a script or plugin', 'talkwyn' ), __( 'Chats are stored by the vendor', 'talkwyn' ) ) ),
		'es-flow'     => array( 'flow', array( __( 'A visitor asks in Spanish', 'talkwyn' ), __( 'The chatbot searches your English pages', 'talkwyn' ), __( 'It replies in Spanish, with a link to the English page', 'talkwyn' ) ) ),
		'hi-flow'     => array( 'flow', array( __( 'A visitor asks about delivery in Hindi', 'talkwyn' ), __( 'The chatbot reads your English shipping page', 'talkwyn' ), __( 'It replies in Hindi, with a link to that page', 'talkwyn' ) ) ),
		'faq-index'   => array( 'flow', array( __( 'FAQ, shipping and product pages', 'talkwyn' ), __( 'One chatbot index', 'talkwyn' ), __( 'A visitor asks in the chat', 'talkwyn' ), __( 'The reply links back to the FAQ page', 'talkwyn' ) ) ),
		'faq-page'    => array( 'outline', __( 'A FAQ page a chatbot can answer from', 'talkwyn' ), array( __( 'Each question is a heading', 'talkwyn' ), __( 'The first sentence answers it directly', 'talkwyn' ), __( 'A link to the full policy page', 'talkwyn' ) ) ),
		'question-log' => array( 'outline', __( 'A one week question log, three columns', 'talkwyn' ), array( __( 'The question, in the customer\'s own words', 'talkwyn' ), __( 'How many times it came up this week', 'talkwyn' ), __( 'The page that answers it, or "missing"', 'talkwyn' ) ) ),
		'shipping'    => array( 'outline', __( 'Shipping and returns page, one heading per question', 'talkwyn' ), array( __( 'Where we ship', 'talkwyn' ), __( 'Costs', 'talkwyn' ), __( 'Delivery times', 'talkwyn' ), __( 'Tracking', 'talkwyn' ), __( 'Returns', 'talkwyn' ), __( 'Refunds', 'talkwyn' ), __( 'How to start a return', 'talkwyn' ) ) ),
		'scan-scope'  => array( 'compare', __( 'Read by the scan', 'talkwyn' ), array( __( 'Published pages and posts', 'talkwyn' ), __( 'WooCommerce products', 'talkwyn' ), __( 'Menus', 'talkwyn' ), __( 'Custom fields, when you turn them on', 'talkwyn' ), __( 'Elementor content', 'talkwyn' ) ), __( 'Never read', 'talkwyn' ), array( __( 'Drafts and private content', 'talkwyn' ), __( 'Password protected pages', 'talkwyn' ), __( 'User accounts and passwords', 'talkwyn' ) ) ),
	);
}

/**
 * One docs visual: a screenshot or an HTML diagram.
 *
 * @param string $key     Visual key.
 * @param string $caption Caption, empty for the screenshot's default.
 */
function talkwyn_doc_shot_html( string $key, string $caption = '' ): string {
	$shots = talkwyn_doc_shots();
	if ( isset( $shots[ $key ] ) ) {
		list( $file, $alt, $default, $narrow ) = $shots[ $key ];
		$path = TALKWYN_THEME_DIR . '/assets/docs/' . $file;
		$size = is_readable( $path ) ? (array) wp_getimagesize( $path ) : array();
		return '<figure class="tw-figure tw-figure--shot' . ( $narrow ? ' tw-figure--narrow' : '' ) . '"><img class="tw-shot-img" src="' . esc_url( TALKWYN_THEME_URL . '/assets/docs/' . $file . '?v=' . TALKWYN_THEME_VERSION ) . '" alt="' . esc_attr( $alt ) . '"'
			. ( isset( $size[0], $size[1] ) ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : '' ) . ' loading="lazy" decoding="async">'
			. '<figcaption>' . esc_html( '' !== $caption ? $caption : $default ) . '</figcaption></figure>';
	}
	$diagrams = talkwyn_doc_diagrams();
	if ( ! isset( $diagrams[ $key ] ) ) {
		return '';
	}
	$d    = $diagrams[ $key ];
	$list = static function ( array $items ): string {
		return '<ul>' . implode( '', array_map( static fn( $i ) => '<li>' . esc_html( $i ) . '</li>', $items ) ) . '</ul>';
	};
	if ( 'flow' === $d[0] ) {
		$body = '<ol class="tw-dflow">';
		foreach ( $d[1] as $n => $step ) {
			$body .= '<li><span class="tw-dflow__n">' . (int) ( $n + 1 ) . '</span><span>' . esc_html( $step ) . '</span></li>';
		}
		$body .= '</ol>';
	} elseif ( 'compare' === $d[0] ) {
		$body = '<div class="tw-dcompare"><div><strong>' . esc_html( $d[1] ) . '</strong>' . $list( $d[2] ) . '</div><div><strong>' . esc_html( $d[3] ) . '</strong>' . $list( $d[4] ) . '</div></div>';
	} else {
		$body = '<div class="tw-doutline"><strong>' . esc_html( $d[1] ) . '</strong>' . $list( $d[2] ) . '</div>';
	}
	return '<figure class="tw-figure tw-figure--diagram">' . $body . ( '' !== $caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
}

// [tw_shot key="scan" caption="..."]: a docs screenshot or diagram.
add_shortcode(
	'tw_shot',
	static function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'     => '',
				'caption' => '',
			),
			$atts,
			'tw_shot'
		);
		return talkwyn_doc_shot_html( sanitize_key( $atts['key'] ), (string) $atts['caption'] );
	}
);

/**
 * Placeholder label (start of the old "Screenshot of ..." text) => visual key, '' to drop it,
 * and an optional caption that replaces the old one.
 *
 * @return array<string, array{0: string, 1?: string}>
 */
function talkwyn_doc_placeholder_map(): array {
	return array(
		'groq console'                                    => array( '' ),
		'cloudflare dashboard'                            => array( '' ),
		'chart placeholder'                               => array( '' ),
		'buyer question goes to the chatbot'              => array( 're-answer' ),
		'real estate lead flow'                           => array( 're-leads' ),
		'two kinds of chatbot'                            => array( 'kinds' ),
		'asks a question in spanish, the chatbot searches' => array( 'es-flow' ),
		'asks about delivery in hindi'                    => array( 'hi-flow' ),
		'faq page, shipping page and product pages feed'  => array( 'faq-index' ),
		'annotated example of a well structured faq'      => array( 'faq-page' ),
		'simple spreadsheet'                              => array( 'question-log' ),
		'outline of a shipping and returns page'          => array( 'shipping' ),
		'what the chatbot scan includes'                  => array( 'scan-scope' ),
		'side by side comparison'                         => array( 'chat-delivery' ),
		'arabic question'                                 => array( 'chat-arabic' ),
		'saturday viewings'                               => array( 'chat-viewing' ),
		'sample dental clinic'                            => array( 'chat-dental' ),
		'ships to puerto rico'                            => array( 'chat-spanish' ),
		'example lead email'                              => array( 'lead-email', __( 'Example: the lead email gives you the visitor\'s details and their question. The full chat is in WordPress under Talkwyn, Conversations.', 'talkwyn' ) ),
		'talkwyn settings screen'                         => array( 'appearance' ),
		'chat window at night'                            => array( 'chat-night' ),
		'sample hotel website'                            => array( 'chat-hotel' ),
		'chat history list'                               => array( 'conversations' ),
		'add new plugin'                                  => array( 'plugins', __( 'Talkwyn installed and active under Plugins.', 'talkwyn' ) ),
		'scan my site'                                    => array( 'scan', __( 'Rescan any time under Talkwyn, Knowledge, and see what was read.', 'talkwyn' ) ),
		'appearance settings'                             => array( 'appearance', __( 'Appearance settings with a live preview of your chat.', 'talkwyn' ) ),
		'ai provider'                                     => array( 'providers' ),
		'opening hours'                                   => array( 'chat-sources' ),
		'asks to book'                                    => array( 'chat-offer' ),
		'woocommerce product edit'                        => array( 'woo' ),
	);
}

/**
 * Pages imported before theme 2.11.0 still hold "Screenshot of ..." placeholder boxes.
 * Show the real screenshot or diagram instead, keeping the page's own caption, and drop
 * the boxes for third-party screens (Groq console, Cloudflare dashboard) and the cost
 * chart that had no data behind it.
 */
add_filter(
	'the_content',
	static function ( $html ) {
		// One FAQ answer said the lead email carries the whole chat; it carries the last question.
		$html = str_replace( 'The details the visitor entered in the short form, plus the conversation.', 'The details the visitor entered in the short form and the question they asked. The full conversation is saved in WordPress under Talkwyn, Conversations.', (string) $html );
		if ( false === strpos( $html, 'tw-shot"' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#<figure class="tw-figure">\s*<div class="tw-shot"[^>]*aria-label="([^"]*)".*?(?:<figcaption>(.*?)</figcaption>)?\s*</figure>#s',
			static function ( $m ) {
				$label = strtolower( html_entity_decode( $m[1], ENT_QUOTES ) );
				foreach ( talkwyn_doc_placeholder_map() as $needle => $to ) {
					if ( false !== strpos( $label, $needle ) ) {
						$caption = $to[1] ?? html_entity_decode( wp_strip_all_tags( (string) ( $m[2] ?? '' ) ), ENT_QUOTES );
						return '' === $to[0] ? '' : talkwyn_doc_shot_html( $to[0], $caption );
					}
				}
				return $m[0];
			},
			(string) $html
		);
	},
	20
);
