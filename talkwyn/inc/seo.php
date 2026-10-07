<?php
/**
 * SEO fallback: titles, meta descriptions, robots, Open Graph, JSON-LD, breadcrumbs, sitemap.
 *
 * When Rank Math (recommended) or Yoast is active, the theme stops printing titles,
 * metas, canonicals, Open Graph, Organization/WebSite/Article/Breadcrumb schema, so
 * nothing is duplicated. It keeps FAQPage, SoftwareApplication and pricing Product
 * schema because those come from theme content the plugin cannot see.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a dedicated SEO plugin handles the head.
 */
function talkwyn_seo_plugin_active(): bool {
	return (bool) apply_filters( 'talkwyn_seo_plugin_active', defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) );
}

/**
 * Register SEO post meta (editable in the block editor sidebar via the meta box below).
 */
add_action(
	'init',
	static function () {
		foreach ( array( 'page', 'post', 'doc' ) as $type ) {
			foreach ( array( '_tw_seo_title', '_tw_seo_desc', '_tw_og_image' ) as $key ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => 'sanitize_text_field',
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
			register_post_meta(
				$type,
				'_tw_noindex',
				array(
					'type'          => 'boolean',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
		add_post_type_support( 'page', 'excerpt' );
	}
);

/**
 * Meta box for SEO fields (shown in the block editor's meta box area).
 */
add_action(
	'add_meta_boxes',
	static function () {
		foreach ( array( 'page', 'post', 'doc' ) as $type ) {
			add_meta_box( 'talkwyn-seo', __( 'Talkwyn SEO', 'talkwyn' ), 'talkwyn_seo_metabox', $type, 'normal', 'low' );
		}
	}
);

/**
 * Meta box markup.
 *
 * @param WP_Post $post Post.
 */
function talkwyn_seo_metabox( $post ): void {
	wp_nonce_field( 'talkwyn_seo', 'talkwyn_seo_nonce' );
	if ( talkwyn_seo_plugin_active() ) {
		echo '<p>' . esc_html__( 'An SEO plugin is active. Edit titles and descriptions there; only the noindex flag below is used by the theme (for sitemaps).', 'talkwyn' ) . '</p>';
	}
	$title = (string) get_post_meta( $post->ID, '_tw_seo_title', true );
	$desc  = (string) get_post_meta( $post->ID, '_tw_seo_desc', true );
	$noidx = (bool) get_post_meta( $post->ID, '_tw_noindex', true );
	?>
	<p><label for="tw-seo-title"><strong><?php esc_html_e( 'Title tag (50 to 60 characters)', 'talkwyn' ); ?></strong></label><br>
	<input id="tw-seo-title" name="tw_seo_title" type="text" class="widefat" maxlength="70" value="<?php echo esc_attr( $title ); ?>"></p>
	<p><label for="tw-seo-desc"><strong><?php esc_html_e( 'Meta description (140 to 160 characters)', 'talkwyn' ); ?></strong></label><br>
	<textarea id="tw-seo-desc" name="tw_seo_desc" class="widefat" rows="3" maxlength="200"><?php echo esc_textarea( $desc ); ?></textarea></p>
	<p><label><input name="tw_noindex" type="checkbox" value="1" <?php checked( $noidx ); ?>> <?php esc_html_e( 'Hide from search engines (noindex). Use for placeholder pages until they have real content.', 'talkwyn' ); ?></label></p>
	<?php
}

add_action(
	'save_post',
	static function ( $post_id ) {
		if ( ! isset( $_POST['talkwyn_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['talkwyn_seo_nonce'] ) ), 'talkwyn_seo' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_tw_seo_title', sanitize_text_field( wp_unslash( $_POST['tw_seo_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_tw_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['tw_seo_desc'] ?? '' ) ) );
		update_post_meta( $post_id, '_tw_noindex', empty( $_POST['tw_noindex'] ) ? 0 : 1 );
	}
);

/**
 * The queried singular post id, or 0.
 */
function talkwyn_seo_post_id(): int {
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return (int) get_option( 'page_for_posts' );
	}
	return 0;
}

/**
 * Whether the current request should be noindex.
 */
function talkwyn_is_noindex(): bool {
	$id = talkwyn_seo_post_id();
	if ( $id && get_post_meta( $id, '_tw_noindex', true ) ) {
		return true;
	}
	if ( is_search() || is_tag() || is_author() || is_date() || is_attachment() || is_404() ) {
		return true;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	if ( is_category() && (int) get_queried_object()->count < 3 ) {
		return true; // Thin category archive.
	}
	return false;
}

/**
 * Meta description for the current request.
 */
function talkwyn_meta_description(): string {
	$id = talkwyn_seo_post_id();
	if ( $id ) {
		$desc = (string) get_post_meta( $id, '_tw_seo_desc', true );
		if ( '' === $desc && has_excerpt( $id ) ) {
			$desc = (string) get_the_excerpt( $id );
		}
		if ( '' === $desc ) {
			$desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ), 28, '' );
		}
		return trim( $desc );
	}
	if ( is_category() ) {
		return wp_strip_all_tags( (string) category_description() );
	}
	if ( is_post_type_archive( 'doc' ) ) {
		return __( 'Step-by-step guides for installing Talkwyn, connecting AI providers, and getting the most out of your WordPress chatbot.', 'talkwyn' );
	}
	return (string) get_bloginfo( 'description' );
}

if ( ! talkwyn_seo_plugin_active() ) {
	add_filter(
		'pre_get_document_title',
		static function ( $title ) {
			$id = talkwyn_seo_post_id();
			if ( $id ) {
				$custom = (string) get_post_meta( $id, '_tw_seo_title', true );
				if ( '' !== $custom ) {
					return $custom;
				}
			}
			if ( is_post_type_archive( 'doc' ) ) {
				return __( 'Talkwyn Docs: Setup Guides and How-Tos', 'talkwyn' );
			}
			return $title;
		},
		20
	);
	add_filter(
		'document_title_separator',
		static function () {
			return '|';
		}
	);

	add_filter(
		'wp_robots',
		static function ( $robots ) {
			if ( talkwyn_is_noindex() ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
				unset( $robots['max-image-preview'] );
			} else {
				$robots['max-image-preview'] = 'large';
			}
			return $robots;
		}
	);

	add_action( 'wp_head', 'talkwyn_seo_head', 2 );
}

/**
 * Description, canonical (non-singular), Open Graph and Twitter tags.
 */
function talkwyn_seo_head(): void {
	$desc  = talkwyn_meta_description();
	$id    = talkwyn_seo_post_id();
	$title = wp_get_document_title();
	$url   = $id ? (string) get_permalink( $id ) : talkwyn_current_url();
	if ( is_front_page() ) {
		$url = home_url( '/' );
	}
	if ( '' !== $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	if ( ! is_singular() && ! talkwyn_is_noindex() ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}

	$image = array( TALKWYN_THEME_URL . '/assets/brand/social/og-image-1200x630.png', 1200, 630 );
	if ( $id && get_post_meta( $id, '_tw_og_image', true ) ) {
		$image = array( (string) get_post_meta( $id, '_tw_og_image', true ), 1200, 630 );
	} elseif ( $id && has_post_thumbnail( $id ) ) {
		$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $id ), 'large' );
		if ( $src ) {
			$image = array( $src[0], (int) $src[1], (int) $src[2] );
		}
	}
	$tags = array(
		'og:site_name'    => 'Talkwyn',
		'og:locale'       => str_replace( '-', '_', get_bloginfo( 'language' ) ),
		'og:type'         => is_singular( array( 'post', 'doc' ) ) ? 'article' : 'website',
		'og:title'        => $title,
		'og:description'  => $desc,
		'og:url'          => $url,
		'og:image'        => $image[0],
		'og:image:width'  => (string) $image[1],
		'og:image:height' => (string) $image[2],
		'og:image:alt'    => __( 'Talkwyn, the AI chatbot that answers every visitor in their language', 'talkwyn' ),
	);
	foreach ( $tags as $property => $content ) {
		if ( '' !== $content ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
		}
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	$x = (string) talkwyn_setting( 'social_x' );
	if ( '' !== $x ) {
		$handle = '@' . basename( untrailingslashit( (string) wp_parse_url( $x, PHP_URL_PATH ) ) );
		printf( '<meta name="twitter:site" content="%s">' . "\n", esc_attr( $handle ) );
	}
}

/**
 * Current URL without query string.
 */
function talkwyn_current_url(): string {
	global $wp;
	return home_url( trailingslashit( (string) ( $wp->request ?? '' ) ) );
}

// Breadcrumbs.

/**
 * Breadcrumb trail for the current request.
 *
 * @return array<int, array{0: string, 1: string}> [label, url] pairs; the last one is current.
 */
function talkwyn_breadcrumb_trail(): array {
	if ( is_front_page() ) {
		return array();
	}
	$trail      = array( array( __( 'Home', 'talkwyn' ), home_url( '/' ) ) );
	$blog       = (int) get_option( 'page_for_posts' );
	$blog_crumb = $blog ? array( get_the_title( $blog ), (string) get_permalink( $blog ) ) : array( __( 'Blog', 'talkwyn' ), home_url( '/blog/' ) );

	if ( is_singular( 'doc' ) ) {
		$trail[] = array( __( 'Docs', 'talkwyn' ), (string) get_post_type_archive_link( 'doc' ) );
		$post    = get_queried_object();
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$trail[] = array( get_the_title( $ancestor ), (string) get_permalink( $ancestor ) );
		}
		$trail[] = array( get_the_title( $post ), (string) get_permalink( $post ) );
	} elseif ( is_post_type_archive( 'doc' ) ) {
		$trail[] = array( __( 'Docs', 'talkwyn' ), (string) get_post_type_archive_link( 'doc' ) );
	} elseif ( is_tax( 'doc_category' ) ) {
		$trail[] = array( __( 'Docs', 'talkwyn' ), (string) get_post_type_archive_link( 'doc' ) );
		$trail[] = array( single_term_title( '', false ), talkwyn_current_url() );
	} elseif ( is_page() ) {
		$post = get_queried_object();
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$trail[] = array( get_the_title( $ancestor ), (string) get_permalink( $ancestor ) );
		}
		$trail[] = array( get_the_title( $post ), (string) get_permalink( $post ) );
	} elseif ( is_singular( 'post' ) ) {
		$trail[] = $blog_crumb;
		$cats    = get_the_category();
		if ( $cats ) {
			$trail[] = array( $cats[0]->name, (string) get_category_link( $cats[0] ) );
		}
		$trail[] = array( get_the_title(), (string) get_permalink() );
	} elseif ( is_home() ) {
		$trail[] = $blog_crumb;
	} elseif ( is_category() ) {
		$trail[] = $blog_crumb;
		$trail[] = array( single_cat_title( '', false ), (string) get_category_link( get_queried_object_id() ) );
	} elseif ( is_search() ) {
		$trail[] = array( __( 'Search results', 'talkwyn' ), talkwyn_current_url() );
	} elseif ( is_archive() ) {
		$trail[] = $blog_crumb;
		$trail[] = array( wp_strip_all_tags( get_the_archive_title() ), talkwyn_current_url() );
	} elseif ( is_404() ) {
		$trail[] = array( __( 'Page not found', 'talkwyn' ), talkwyn_current_url() );
	}
	return $trail;
}

add_shortcode(
	'tw_breadcrumbs',
	static function () {
		$trail = talkwyn_breadcrumb_trail();
		if ( count( $trail ) < 2 ) {
			return '';
		}
		$items = '';
		$last  = count( $trail ) - 1;
		foreach ( $trail as $i => $crumb ) {
			$items .= $i === $last
				? '<li><span aria-current="page">' . esc_html( $crumb[0] ) . '</span></li>'
				: '<li><a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a></li>';
		}
		return '<nav class="tw-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'talkwyn' ) . '"><ol>' . $items . '</ol></nav>';
	}
);

// JSON-LD.

/**
 * FAQ items collected while rendering [tw_faq] so the FAQPage schema always
 * matches the visible content.
 *
 * @param array<int, array{0: string, 1: string}>|null $add Items to add.
 * @return array<int, array{0: string, 1: string}>
 */
function talkwyn_faq_registry( ?array $add = null ): array {
	static $items = array();
	if ( null !== $add ) {
		$items = array_merge( $items, $add );
	}
	return $items;
}

/**
 * Numeric price for schema (from WooCommerce or the manual price text).
 *
 * @param string $plan Plan.
 */
function talkwyn_plan_price_number( string $plan ): ?string {
	$id = (int) talkwyn_setting( 'product_' . $plan );
	if ( 'woocommerce' === talkwyn_setting( 'price_source' ) && $id && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $id );
		return $product ? (string) wc_format_decimal( $product->get_price(), 2 ) : null;
	}
	$raw = (string) talkwyn_setting( 'price_' . $plan );
	return preg_match( '/(\d+(?:[.,]\d{1,2})?)/', $raw, $m ) ? str_replace( ',', '.', $m[1] ) : null;
}

/**
 * Currency code for schema.
 */
function talkwyn_currency(): string {
	return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
}

/**
 * Offers for SoftwareApplication / Product schema.
 *
 * @return array<int, array<string, mixed>>
 */
function talkwyn_schema_offers(): array {
	$offers = array(
		array(
			'@type'         => 'Offer',
			'name'          => 'Free',
			'price'         => '0',
			'priceCurrency' => talkwyn_currency(),
			'url'           => talkwyn_install_url(),
		),
	);
	$plans  = array(
		'personal' => 'Pro Personal (1 site, yearly)',
		'business' => 'Pro Business (5 sites, yearly)',
		'agency'   => 'Pro Agency (unlimited sites, yearly)',
	);
	if ( talkwyn_setting( 'show_lifetime' ) ) {
		$plans['lifetime'] = 'Lifetime';
	}
	foreach ( $plans as $plan => $name ) {
		$price = talkwyn_plan_price_number( $plan );
		if ( null !== $price ) {
			$offers[] = array(
				'@type'         => 'Offer',
				'name'          => $name,
				'price'         => $price,
				'priceCurrency' => talkwyn_currency(),
				'url'           => home_url( '/pricing/' ),
				'availability'  => 'https://schema.org/InStock',
			);
		}
	}
	return $offers;
}

add_action(
	'wp_footer',
	static function () {
		$graph   = array();
		$plugin  = talkwyn_seo_plugin_active();
		$org_id  = home_url( '/#organization' );
		$logo    = TALKWYN_THEME_URL . '/assets/brand/logo/talkwyn-logo.png';
		$same_as = array_values(
			array_filter(
				array_map(
					static function ( $k ) {
						return (string) talkwyn_setting( $k );
					},
					array( 'social_x', 'social_linkedin', 'social_facebook', 'social_youtube', 'social_instagram', 'social_github', 'wporg_url' )
				)
			)
		);

		if ( is_front_page() ) {
			if ( ! $plugin ) {
				$graph[] = array(
					'@type'  => 'Organization',
					'@id'    => $org_id,
					'name'   => 'Talkwyn',
					'url'    => home_url( '/' ),
					'logo'   => $logo,
					'sameAs' => $same_as,
				);
				$graph[] = array(
					'@type'           => 'WebSite',
					'@id'             => home_url( '/#website' ),
					'name'            => 'Talkwyn',
					'url'             => home_url( '/' ),
					'publisher'       => array( '@id' => $org_id ),
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => home_url( '/?s={search_term_string}' ),
						'query-input' => 'required name=search_term_string',
					),
				);
			}
			$graph[] = array(
				'@type'               => 'SoftwareApplication',
				'name'                => 'Talkwyn',
				'applicationCategory' => 'BusinessApplication',
				'operatingSystem'     => 'WordPress',
				'description'         => talkwyn_meta_description(),
				'url'                 => home_url( '/' ),
				'image'               => TALKWYN_THEME_URL . '/assets/brand/icons/talkwyn-app-icon-1024.png',
				'offers'              => talkwyn_schema_offers(),
				'publisher'           => array(
					'@type' => 'Organization',
					'name'  => 'Talkwyn',
					'url'   => home_url( '/' ),
				),
			);
		}

		if ( is_page( 'pricing' ) ) {
			$graph[] = array(
				'@type'       => 'Product',
				'name'        => 'Talkwyn Pro',
				'description' => talkwyn_meta_description(),
				'image'       => TALKWYN_THEME_URL . '/assets/brand/icons/talkwyn-app-icon-1024.png',
				'brand'       => array(
					'@type' => 'Brand',
					'name'  => 'Talkwyn',
				),
				'offers'      => talkwyn_schema_offers(),
			);
		}

		$faq = talkwyn_faq_registry();
		if ( $faq ) {
			$graph[] = array(
				'@type'      => 'FAQPage',
				'mainEntity' => array_map(
					static function ( $item ) {
						return array(
							'@type'          => 'Question',
							'name'           => wp_strip_all_tags( $item[0] ),
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => wp_strip_all_tags( $item[1] ),
							),
						);
					},
					$faq
				),
			);
		}

		if ( ! $plugin ) {
			if ( is_singular( array( 'post', 'doc' ) ) ) {
				$post    = get_queried_object();
				$graph[] = array(
					'@type'            => 'Article',
					'headline'         => get_the_title( $post ),
					'description'      => talkwyn_meta_description(),
					'datePublished'    => get_post_time( 'c', true, $post ),
					'dateModified'     => get_post_modified_time( 'c', true, $post ),
					'author'           => array(
						'@type' => 'Person',
						'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
					),
					'publisher'        => array(
						'@type' => 'Organization',
						'name'  => 'Talkwyn',
						'logo'  => array(
							'@type' => 'ImageObject',
							'url'   => $logo,
						),
					),
					'mainEntityOfPage' => get_permalink( $post ),
					'image'            => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'large' ) : TALKWYN_THEME_URL . '/assets/brand/social/og-image-1200x630.png',
				);
			}
			$trail = talkwyn_breadcrumb_trail();
			if ( count( $trail ) > 1 ) {
				$items = array();
				foreach ( $trail as $i => $crumb ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $crumb[0],
						'item'     => $crumb[1],
					);
				}
				$graph[] = array(
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $items,
				);
			}
		}

		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			) . "</script>\n";
		}
	},
	20
);

// Sitemaps and robots.txt.

add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap generation only.
			'relation' => 'OR',
			array(
				'key'     => '_tw_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_tw_noindex',
				'value'   => '1',
				'compare' => '!=',
			),
		);
		$exclude            = array_filter(
			array(
				(int) get_option( 'woocommerce_cart_page_id' ),
				(int) get_option( 'woocommerce_checkout_page_id' ),
				(int) get_option( 'woocommerce_myaccount_page_id' ),
				(int) get_option( 'woocommerce_shop_page_id' ),
			)
		);
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $exclude );
		}
		return $args;
	}
);
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);
add_filter(
	'wp_sitemaps_taxonomies',
	static function ( $taxonomies ) {
		unset( $taxonomies['post_tag'], $taxonomies['post_format'], $taxonomies['product_tag'], $taxonomies['product_cat'] );
		return $taxonomies;
	}
);
add_filter(
	'wp_sitemaps_post_types',
	static function ( $types ) {
		unset( $types['attachment'], $types['product'] );
		return $types;
	}
);
add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		return $output . "Disallow: /cart/\nDisallow: /checkout/\nDisallow: /my-account/\nDisallow: /?s=\nDisallow: /search/\n";
	},
	10,
	2
);
