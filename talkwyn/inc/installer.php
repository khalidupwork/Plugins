<?php
/**
 * Site setup: creates every page from seo-plan.md, the docs, blog categories,
 * front/posts pages, permalinks and Site Settings. Safe to run more than once:
 * existing pages are matched by path and updated.
 *
 * Run with WP-CLI:  wp eval-file wp-content/themes/talkwyn/setup/setup.php
 * or from Appearance → Talkwyn Site Settings → "Create or update site pages".
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run the setup.
 *
 * @param bool $overwrite Overwrite content of existing pages (default false keeps your edits).
 * @return array<string, mixed> Report.
 */
function talkwyn_run_setup( bool $overwrite = false ): array {
	$report = array(
		'created'      => array(),
		'updated'      => array(),
		'kept'         => array(),
		'placeholders' => array(),
	);
	$file   = TALKWYN_THEME_DIR . '/setup/content/pages.json';
	$pages  = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	if ( ! is_array( $pages ) ) {
		return $report;
	}

	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// WordPress sample content ("Hello world!", "Sample Page") would ship placeholder filler. Trash it only while untouched.
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $sample ) {
		$sample_post = get_page_by_path( $sample[0], OBJECT, $sample[1] );
		if ( $sample_post && $sample_post->post_date === $sample_post->post_modified ) {
			wp_trash_post( $sample_post->ID );
		}
	}

	// Home page from the homepage patterns.
	$home_content = '<!-- wp:pattern {"slug":"talkwyn/page-home"} /-->';
	$pattern      = WP_Block_Patterns_Registry::get_instance()->get_registered( 'talkwyn/page-home' );
	if ( $pattern ) {
		$home_content = $pattern['content'];
	}
	$home_id = talkwyn_setup_upsert(
		array(
			'path'      => '/home/',
			'title'     => 'Home',
			'excerpt'   => '',
			'content'   => $home_content,
			'template'  => '',
			'seo_title' => 'Free WordPress Chatbot With AI, in Every Language | Talkwyn',
			'seo_desc'  => 'Talkwyn learns your website in one click, answers visitors in their own language, and turns chats into leads. Free plan, no monthly bill. Try it today.',
			'noindex'   => false,
			'post_type' => 'page',
			'order'     => 0,
			'doc_cat'   => '',
		),
		$overwrite,
		$report
	);
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );

	// Parents first (shorter paths first).
	usort(
		$pages,
		static function ( $a, $b ) {
			return substr_count( $a['path'], '/' ) <=> substr_count( $b['path'], '/' );
		}
	);
	foreach ( $pages as $page ) {
		$id = talkwyn_setup_upsert( $page, $overwrite, $report );
		if ( '/blog/' === $page['path'] ) {
			update_option( 'page_for_posts', $id );
		}
		if ( ! empty( $page['noindex'] ) ) {
			$report['placeholders'][] = $page['path'];
		}
	}

	// Blog categories that match the launch content plan.
	foreach ( array( 'Guides', 'Multilingual', 'WordPress', 'Comparisons', 'Industries' ) as $cat ) {
		if ( ! term_exists( $cat, 'category' ) ) {
			wp_insert_term( $cat, 'category' );
		}
	}

	// Site Settings: keep existing values, fill product IDs from Talkwyn Hub mappings.
	$settings = get_option( TALKWYN_SETTINGS, array() );
	$settings = is_array( $settings ) ? $settings : array();
	foreach ( array( 'personal', 'business', 'agency', 'lifetime' ) as $plan ) {
		if ( empty( $settings[ 'product_' . $plan ] ) ) {
			$found = get_posts(
				array(
					'post_type'      => array( 'product', 'product_variation' ),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_twh_plan_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time setup.
					'meta_value'     => $plan, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one-time setup.
				)
			);
			if ( $found ) {
				$settings[ 'product_' . $plan ] = (int) $found[0];
			}
		}
	}
	update_option( TALKWYN_SETTINGS, array_merge( talkwyn_settings_defaults(), $settings ) );

	// Link checkout's "Terms" and "Privacy Policy" text to the legal pages.
	$terms   = get_page_by_path( 'terms' );
	$privacy = get_page_by_path( 'privacy' );
	if ( $terms ) {
		update_option( 'woocommerce_terms_page_id', $terms->ID );
	}
	if ( $privacy ) {
		update_option( 'wp_page_for_privacy_policy', $privacy->ID );
	}

	flush_rewrite_rules();
	return $report;
}

/**
 * Create or update one page/doc by path.
 *
 * @param array<string, mixed> $page      Page definition.
 * @param bool                 $overwrite Overwrite existing content.
 * @param array<string, mixed> $report    Report (by reference).
 */
function talkwyn_setup_upsert( array $page, bool $overwrite, array &$report ): int {
	$type     = $page['post_type'];
	$segments = array_values( array_filter( explode( '/', trim( (string) $page['path'], '/' ) ) ) );
	if ( 'doc' === $type ) {
		array_shift( $segments ); // Drop the "docs" archive base.
	}
	$slug   = (string) array_pop( $segments );
	$parent = 0;
	if ( $segments ) {
		$parent_post = get_page_by_path( implode( '/', $segments ), OBJECT, $type );
		$parent      = $parent_post ? (int) $parent_post->ID : 0;
	}
	$existing = get_page_by_path( ( $segments ? implode( '/', $segments ) . '/' : '' ) . $slug, OBJECT, $type );

	$data = array(
		'post_type'    => $type,
		'post_status'  => 'publish',
		'post_title'   => $page['title'],
		'post_name'    => $slug,
		'post_parent'  => $parent,
		'post_excerpt' => $page['excerpt'],
		'post_content' => $page['content'],
		'menu_order'   => (int) $page['order'],
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
		if ( ! $overwrite ) {
			unset( $data['post_content'], $data['post_title'], $data['post_excerpt'] );
			$report['kept'][] = $page['path'];
		} else {
			$report['updated'][] = $page['path'];
		}
		$id = (int) wp_update_post( wp_slash( $data ) );
	} else {
		$id                  = (int) wp_insert_post( wp_slash( $data ) );
		$report['created'][] = $page['path'];
	}
	if ( ! $id ) {
		return 0;
	}
	if ( 'page' === $type ) {
		update_post_meta( $id, '_wp_page_template', $page['template'] ? $page['template'] : 'default' );
	}
	if ( ! $existing || $overwrite ) {
		update_post_meta( $id, '_tw_seo_title', $page['seo_title'] );
		update_post_meta( $id, '_tw_seo_desc', $page['seo_desc'] );
		update_post_meta( $id, '_tw_noindex', $page['noindex'] ? 1 : 0 );
		// The same values for Rank Math, which takes over titles and robots when active.
		if ( '' !== (string) $page['seo_title'] ) {
			update_post_meta( $id, 'rank_math_title', $page['seo_title'] );
		}
		if ( '' !== (string) $page['seo_desc'] ) {
			update_post_meta( $id, 'rank_math_description', $page['seo_desc'] );
		}
		update_post_meta( $id, 'rank_math_robots', $page['noindex'] ? array( 'noindex', 'follow' ) : array( 'index' ) );
	}
	if ( 'doc' === $type && $page['doc_cat'] ) {
		$term = term_exists( $page['doc_cat'], 'doc_category' );
		if ( ! $term ) {
			$term = wp_insert_term( $page['doc_cat'], 'doc_category' );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( (int) $term['term_id'], 'order', 1 );
			}
		}
		if ( ! is_wp_error( $term ) && $term ) {
			wp_set_object_terms( $id, (int) $term['term_id'], 'doc_category' );
		}
	}
	return $id;
}

/**
 * Admin button on the Site Settings screen.
 */
add_action(
	'admin_post_talkwyn_run_setup',
	static function () {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'talkwyn_run_setup' );
		$report = talkwyn_run_setup( ! empty( $_POST['overwrite'] ) );
		set_transient( 'talkwyn_setup_report', $report, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'themes.php?page=talkwyn-site-settings&setup=1' ) );
		exit;
	}
);

add_action(
	'talkwyn_settings_after_form',
	static function () {
		$report = get_transient( 'talkwyn_setup_report' );
		?>
		<hr>
		<h2><?php esc_html_e( 'Site pages', 'talkwyn' ); ?></h2>
		<p><?php esc_html_e( 'Creates every page from the SEO plan (real pages plus noindex placeholders), the docs, blog categories, and sets the homepage and blog page. Existing pages are kept unless you tick "overwrite".', 'talkwyn' ); ?></p>
		<?php if ( is_array( $report ) ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: created, 2: updated, 3: kept, 4: placeholders */
						__( 'Done. Created %1$d, updated %2$d, kept %3$d. Placeholders (noindex): %4$s', 'talkwyn' ),
						count( $report['created'] ),
						count( $report['updated'] ),
						count( $report['kept'] ),
						implode( ', ', $report['placeholders'] )
					)
				);
				?>
			</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="talkwyn_run_setup">
			<?php wp_nonce_field( 'talkwyn_run_setup' ); ?>
			<label><input type="checkbox" name="overwrite" value="1"> <?php esc_html_e( 'Overwrite the content of existing pages', 'talkwyn' ); ?></label>
			<?php submit_button( __( 'Create or update site pages', 'talkwyn' ), 'secondary' ); ?>
		</form>
		<?php
	}
);
