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
		$html    = '<nav class="tw-docs__nav" aria-label="' . esc_attr__( 'Docs', 'talkwyn' ) . '">';
		foreach ( talkwyn_docs_grouped() as $group ) {
			$html .= '<h2>' . esc_html( $group['term'] ? $group['term']->name : __( 'Guides', 'talkwyn' ) ) . '</h2><ul>';
			foreach ( $group['docs'] as $doc ) {
				$aria  = $doc->ID === $current ? ' aria-current="page"' : '';
				$html .= '<li><a href="' . esc_url( get_permalink( $doc ) ) . '"' . $aria . '>' . esc_html( get_the_title( $doc ) ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		return $html . '</nav>';
	}
);

add_shortcode(
	'tw_docs_index',
	static function () {
		$html = '';
		foreach ( talkwyn_docs_grouped() as $group ) {
			$html .= '<section class="tw-docs-group"><h2>' . esc_html( $group['term'] ? $group['term']->name : __( 'Guides', 'talkwyn' ) ) . '</h2><div class="tw-cards">';
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
				'posts_per_page' => 2,
			)
		);
		if ( ! $related ) {
			return '';
		}
		$html = '<aside class="tw-related"><h2>' . esc_html__( 'Keep reading', 'talkwyn' ) . '</h2><div class="tw-cards">';
		foreach ( $related as $post ) {
			$html .= '<article class="tw-card"><h3>' . esc_html( get_the_title( $post ) ) . '</h3><p>' . esc_html( get_the_excerpt( $post ) ) . '</p><a class="tw-card__link" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Read the article', 'talkwyn' ) . ' ' . talkwyn_icon( 'arrow-right', 18 ) . '</a></article>';
		}
		return $html . '</div></aside>';
	}
);
