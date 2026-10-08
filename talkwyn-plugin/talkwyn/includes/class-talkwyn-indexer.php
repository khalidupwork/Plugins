<?php
/**
 * Site scan: turns public content into searchable knowledge chunks.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indexer.
 */
class Talkwyn_Indexer {

	const BATCH = 8;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_talkwyn_index_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_talkwyn_index_batch', array( __CLASS__, 'ajax_batch' ) );
		add_action( 'wp_ajax_talkwyn_clear_index', array( __CLASS__, 'ajax_clear' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 20, 3 );
		add_action( 'trashed_post', array( __CLASS__, 'remove_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'remove_post' ) );
	}

	/**
	 * Admin AJAX guard.
	 *
	 * @return void
	 */
	private static function guard() {
		check_ajax_referer( 'talkwyn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'talkwyn' ) ), 403 );
		}
	}

	/**
	 * Start a full scan: count what will be scanned.
	 *
	 * @return void
	 */
	public static function ajax_start() {
		self::guard();
		$total = 0;
		foreach ( self::post_types() as $type ) {
			$counts = wp_count_posts( $type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		wp_send_json_success(
			array(
				'total' => $total,
				'types' => self::post_types(),
			)
		);
	}

	/**
	 * Scan one batch.
	 *
	 * @return void
	 */
	public static function ajax_batch() {
		self::guard();
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result = self::scan_batch( $offset );
		wp_send_json_success( $result );
	}

	/**
	 * Scan a batch of published posts. The last batch also refreshes site
	 * identity and menus, and removes chunks for posts that are gone.
	 *
	 * @param int $offset Offset.
	 * @return array{processed:int,next_offset:int,done:bool,chunks:int}
	 */
	public static function scan_batch( $offset ) {
		$ids = get_posts(
			array(
				'post_type'        => self::post_types(),
				'post_status'      => 'publish',
				'posts_per_page'   => self::BATCH,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'has_password'     => false,
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $post_id ) {
			self::index_post( (int) $post_id );
		}
		$done = count( $ids ) < self::BATCH;
		if ( $done ) {
			self::index_site_identity();
			self::index_menus();
			self::purge_stale();

			/**
			 * Fires when a full site scan finishes.
			 */
			do_action( 'talkwyn_scan_complete' );
		}
		return array(
			'processed'   => count( $ids ),
			'next_offset' => $offset + count( $ids ),
			'done'        => $done,
			'chunks'      => self::count(),
		);
	}

	/**
	 * Clear site knowledge (posts, identity, menus). Add-on sources are kept.
	 *
	 * @return void
	 */
	public static function ajax_clear() {
		self::guard();
		global $wpdb;
		$t = Talkwyn_DB::tables();
		$wpdb->query( "DELETE FROM {$t['chunks']} WHERE source_key LIKE 'post:%' OR source_key LIKE 'site:%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		do_action( 'talkwyn_index_cleared' );
		wp_send_json_success( array( 'chunks' => self::count() ) );
	}

	/**
	 * Number of chunks.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$t = Talkwyn_DB::tables();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['chunks']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Post types to scan.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$requested = (array) Talkwyn_Settings::get( 'index_post_types', array() );
		$public    = get_post_types( array( 'public' => true ), 'names' );
		$types     = array_values( array_diff( array_intersect( $requested, $public ), array( 'attachment' ) ) );
		if ( ! $types ) {
			$types = array( 'post', 'page' );
			if ( post_type_exists( 'product' ) ) {
				$types[] = 'product';
			}
		}
		return apply_filters( 'talkwyn_index_post_types', $types );
	}

	/**
	 * Whether a post may be in the knowledge base.
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	public static function is_indexable( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		$ok = 'publish' === $post->post_status
			&& '' === (string) $post->post_password
			&& in_array( $post->post_type, self::post_types(), true );

		/**
		 * Filters whether a post can be indexed.
		 *
		 * @param bool    $ok   Indexable.
		 * @param WP_Post $post Post.
		 */
		return (bool) apply_filters( 'talkwyn_is_indexable', $ok, $post );
	}

	/**
	 * Refresh a post when it is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! self::is_indexable( $post ) ) {
			// A password added or a type no longer scanned removes the post.
			self::remove_post( $post_id );
			return;
		}
		if ( Talkwyn_Settings::get( 'auto_index_on_save' ) ) {
			self::index_post( $post_id );
		}
	}

	/**
	 * Remove a post as soon as it stops being public.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && $post instanceof WP_Post ) {
			self::remove_post( $post->ID );
		}
	}

	/**
	 * Delete a post's chunks.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function remove_post( $post_id ) {
		self::remove_source( 'post:' . absint( $post_id ) );

		/**
		 * Fires after a post was removed from the knowledge base.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'talkwyn_post_removed', absint( $post_id ) );
	}

	/**
	 * Delete all chunks of a source.
	 *
	 * @param string $source_key Source key.
	 * @return void
	 */
	public static function remove_source( $source_key ) {
		global $wpdb;
		$t = Talkwyn_DB::tables();
		$wpdb->delete( $t['chunks'], array( 'source_key' => $source_key ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Remove chunks of posts that were deleted, unpublished or protected while
	 * nobody was watching (for example by a direct database change).
	 *
	 * @return int Rows removed.
	 */
	public static function purge_stale() {
		global $wpdb;
		$t      = Talkwyn_DB::tables();
		$types  = self::post_types();
		$holder = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql    = "DELETE c FROM {$t['chunks']} c LEFT JOIN {$wpdb->posts} p ON p.ID = c.source_id
			WHERE c.source_key LIKE 'post:%%' AND ( p.ID IS NULL OR p.post_status <> 'publish' OR p.post_password <> '' OR p.post_type NOT IN ({$holder}) )";
		return (int) $wpdb->query( $wpdb->prepare( $sql, $types ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Index one post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Chunks stored.
	 */
	public static function index_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! self::is_indexable( $post ) ) {
			self::remove_source( 'post:' . absint( $post_id ) );
			return 0;
		}

		$title = get_the_title( $post );
		$parts = array( $title );
		if ( $post->post_excerpt ) {
			$parts[] = $post->post_excerpt;
		}
		$parts[] = self::clean_html( $post->post_content );

		$elementor = get_post_meta( $post_id, '_elementor_data', true );
		if ( $elementor ) {
			$decoded = is_string( $elementor ) ? json_decode( $elementor, true ) : $elementor;
			if ( is_array( $decoded ) ) {
				$parts[] = self::elementor_text( $decoded );
			}
		}
		if ( Talkwyn_Settings::get( 'index_custom_fields' ) ) {
			$parts[] = self::custom_fields( $post_id );
		}
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$parts[] = self::product_text( $post_id );
		}

		/**
		 * Filters the text stored for a post.
		 *
		 * @param string  $text Text.
		 * @param WP_Post $post Post.
		 */
		$content = Talkwyn_Text::normalize( (string) apply_filters( 'talkwyn_index_post_content', implode( "\n", array_filter( $parts ) ), $post ) );
		$key     = 'post:' . $post_id;
		self::remove_source( $key );
		if ( '' === $content ) {
			return 0;
		}
		$count = self::store_chunks( $key, $post->post_type, $post_id, get_permalink( $post ), $title, $content, $post->post_modified_gmt, self::post_language( $post_id ) );

		/**
		 * Fires after a post was indexed.
		 *
		 * @param int    $post_id Post ID.
		 * @param string $content Indexed text.
		 */
		do_action( 'talkwyn_post_indexed', $post_id, $content );
		return $count;
	}

	/**
	 * WooCommerce product facts.
	 *
	 * @param int $post_id Product ID.
	 * @return string
	 */
	private static function product_text( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return '';
		}
		$lines = array(
			'Price: ' . trim( wp_strip_all_tags( html_entity_decode( $product->get_price_html(), ENT_QUOTES, 'UTF-8' ) ) ),
			'Stock: ' . ( $product->is_in_stock() ? 'In stock' : 'Out of stock' ),
		);
		if ( $product->get_sku() ) {
			$lines[] = 'SKU: ' . $product->get_sku();
		}
		foreach ( $product->get_attributes() as $attr ) {
			if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
				$options = $attr->is_taxonomy() ? wc_get_product_terms( $post_id, $attr->get_name(), array( 'fields' => 'names' ) ) : $attr->get_options();
				$lines[] = wc_attribute_label( $attr->get_name() ) . ': ' . implode( ', ', array_map( 'strval', (array) $options ) );
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Text from Elementor data.
	 *
	 * @param array $nodes Elementor elements.
	 * @return string
	 */
	public static function elementor_text( array $nodes ) {
		$texts     = array();
		$text_keys = array( 'title', 'text', 'description', 'content', 'editor', 'heading', 'subheading', 'subtitle', 'label', 'caption', 'html', 'message', 'name', 'address', 'phone', 'email', 'button', 'testimonial', 'faq', 'question', 'answer', 'item', 'tab', 'accordion', 'quote', 'price', 'feature' );
		$skip_keys = array( 'color', 'typography', 'margin', 'padding', 'width', 'height', 'background', 'border', 'radius', 'animation', 'motion', 'css', 'class', 'responsive', 'font', 'weight', 'position', 'align', 'icon', 'image', 'media', 'gallery', 'video', 'shape', 'hover', 'transition', 'z_index', 'breakpoint', 'size', 'unit' );

		$walk = static function ( $items ) use ( &$walk, &$texts, $text_keys, $skip_keys ) {
			foreach ( (array) $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				foreach ( (array) ( $item['settings'] ?? array() ) as $key => $value ) {
					$k = strtolower( (string) $key );
					if ( false !== strpos( $k, 'url' ) || false !== strpos( $k, 'link' ) ) {
						$url = is_array( $value ) ? (string) ( $value['url'] ?? '' ) : (string) $value;
						if ( preg_match( '#^(https?://|mailto:|tel:)#i', trim( $url ) ) ) {
							$texts[] = trim( $url );
						}
						continue;
					}
					$skip = false;
					foreach ( $skip_keys as $s ) {
						if ( false !== strpos( $k, $s ) ) {
							$skip = true;
							break;
						}
					}
					if ( $skip ) {
						continue;
					}
					$allowed = false;
					foreach ( $text_keys as $t ) {
						if ( false !== strpos( $k, $t ) ) {
							$allowed = true;
							break;
						}
					}
					if ( is_string( $value ) ) {
						$value = trim( $value );
						$human = strlen( $value ) >= 18 && preg_match( '/\s/u', $value ) && ! preg_match( '/^[#.,:;_\-\d\s%pxemrvhw()]+$/i', $value );
						if ( '' !== $value && ( $allowed || $human ) ) {
							$texts[] = $value;
						}
					} elseif ( is_array( $value ) && $allowed ) {
						array_walk_recursive(
							$value,
							static function ( $v, $sub ) use ( &$texts ) {
								if ( is_string( $v ) && strlen( trim( $v ) ) > 1 && ! in_array( strtolower( (string) $sub ), array( '_id', 'id', 'url', 'unit' ), true ) ) {
									$texts[] = $v;
								}
							}
						);
					}
				}
				if ( ! empty( $item['elements'] ) ) {
					$walk( $item['elements'] );
				}
			}
		};
		$walk( $nodes );
		return self::clean_html( implode( "\n", array_unique( $texts ) ) );
	}

	/**
	 * Allowed custom fields (opt-in). Supports exact keys and prefix wildcards like `spec_*`.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function custom_fields( $post_id ) {
		$allow = self::allowlist( (string) Talkwyn_Settings::get( 'custom_field_allowlist', '' ) );
		if ( ! $allow ) {
			return '';
		}
		$out = array();
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( 0 === strpos( $key, '_' ) || ! self::allowed_key( $key, $allow ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				$value = maybe_unserialize( $value );
				if ( is_scalar( $value ) ) {
					$text = trim( wp_strip_all_tags( (string) $value ) );
					if ( '' !== $text && strlen( $text ) < 5000 ) {
						$out[] = ucwords( str_replace( array( '_', '-' ), ' ', $key ) ) . ': ' . $text;
					}
				}
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * Parse the allow list.
	 *
	 * @param string $raw Comma or line separated keys.
	 * @return string[]
	 */
	public static function allowlist( $raw ) {
		$keys = preg_split( '/[\s,]+/', strtolower( $raw ) );
		return array_values( array_filter( array_map( 'trim', (array) $keys ) ) );
	}

	/**
	 * Whether a meta key matches the allow list.
	 *
	 * @param string   $key   Meta key.
	 * @param string[] $allow Allow list.
	 * @return bool
	 */
	public static function allowed_key( $key, array $allow ) {
		$key = strtolower( (string) $key );
		foreach ( $allow as $rule ) {
			if ( $rule === $key ) {
				return true;
			}
			if ( '*' === substr( $rule, -1 ) && 0 === strpos( $key, substr( $rule, 0, -1 ) ) && strlen( $rule ) > 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Site name, tagline and URL.
	 *
	 * @return void
	 */
	public static function index_site_identity() {
		$key = 'site:identity';
		self::remove_source( $key );
		$content = 'Site name: ' . get_bloginfo( 'name' ) . "\nSite description: " . get_bloginfo( 'description' ) . "\nSite URL: " . home_url( '/' );
		self::store_chunks( $key, 'site', 0, home_url( '/' ), get_bloginfo( 'name' ), $content, current_time( 'mysql', true ), self::site_language() );
	}

	/**
	 * Navigation menus.
	 *
	 * @return void
	 */
	public static function index_menus() {
		$key = 'site:menus';
		self::remove_source( $key );
		$lines = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$lines[] = 'Menu: ' . $menu->name;
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				if ( is_object( $item ) ) {
					$lines[] = $item->title . ' ' . $item->url;
				}
			}
		}
		if ( $lines ) {
			self::store_chunks( $key, 'menu', 0, home_url( '/' ), __( 'Website navigation', 'talkwyn' ), implode( "\n", $lines ), current_time( 'mysql', true ), self::site_language() );
		}
	}

	/**
	 * Site language code.
	 *
	 * @return string
	 */
	public static function site_language() {
		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
			return Talkwyn_I18n::short_code( ICL_LANGUAGE_CODE );
		}
		if ( function_exists( 'pll_default_language' ) && pll_default_language( 'slug' ) ) {
			return Talkwyn_I18n::short_code( pll_default_language( 'slug' ) );
		}
		return Talkwyn_I18n::short_code( get_locale() );
	}

	/**
	 * Post language (Polylang, WPML or the site locale).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function post_language( $post_id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id, 'slug' );
			if ( $lang ) {
				return Talkwyn_I18n::short_code( $lang );
			}
		}
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return Talkwyn_I18n::short_code( $details['language_code'] );
		}
		return self::site_language();
	}

	/**
	 * Store text as chunks. Public so add-ons can add their own sources.
	 *
	 * @param string $source_key  Unique source key, e.g. post:12 or file:3.
	 * @param string $type        Source type.
	 * @param int    $source_id   Source ID.
	 * @param string $url         URL shown as the source link.
	 * @param string $title       Title.
	 * @param string $content     Plain text.
	 * @param string $modified    GMT date.
	 * @param string $lang        Language code.
	 * @return int Chunks stored.
	 */
	public static function store_chunks( $source_key, $type, $source_id, $url, $title, $content, $modified = '', $lang = '' ) {
		global $wpdb;
		$t     = Talkwyn_DB::tables();
		$count = 0;
		foreach ( Talkwyn_Text::chunk( $content, 1400, 220 ) as $chunk ) {
			$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$t['chunks'],
				array(
					'source_key'   => $source_key,
					'source_type'  => sanitize_key( $type ),
					'source_id'    => absint( $source_id ),
					'source_url'   => esc_url_raw( (string) $url ),
					'source_lang'  => sanitize_key( $lang ),
					'title'        => sanitize_text_field( (string) $title ),
					'chunk_text'   => $chunk,
					'checksum'     => md5( $chunk ),
					'modified_gmt' => $modified ? $modified : current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( $ok ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * HTML to plain text, keeping useful links.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function clean_html( $html ) {
		$html  = do_shortcode( (string) $html );
		$html  = preg_replace( '#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $html );
		$html  = preg_replace( '#</(p|div|li|h[1-6]|tr|section|article|br)>#i', "$0\n", $html );
		$links = array();
		if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', (string) $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$href  = html_entity_decode( trim( $match[1] ), ENT_QUOTES, 'UTF-8' );
				$label = trim( wp_strip_all_tags( $match[2] ) );
				if ( preg_match( '#^(https?://|mailto:|tel:)#i', $href ) ) {
					$links[] = trim( ( '' !== $label ? $label . ': ' : '' ) . $href );
				}
			}
		}
		$text = wp_strip_all_tags( $html );
		if ( $links ) {
			$text .= "\n" . implode( "\n", array_unique( $links ) );
		}
		return $text;
	}
}
