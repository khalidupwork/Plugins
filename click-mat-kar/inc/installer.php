<?php
/**
 * Appearance > Click Mat Kar: one-click setup.
 * Safe to run repeatedly: it creates what is missing and never overwrites existing content.
 *
 * @package ClickMatKar
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'cmk_admin_menu' );
function cmk_admin_menu() {
	add_theme_page(
		__( 'Click Mat Kar', 'click-mat-kar' ),
		__( 'Click Mat Kar', 'click-mat-kar' ),
		'manage_options',
		'click-mat-kar',
		'cmk_admin_page'
	);
}

add_action( 'admin_post_cmk_setup', 'cmk_handle_setup' );
function cmk_handle_setup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Mana kiya tha.', 'click-mat-kar' ), 403 );
	}
	check_admin_referer( 'cmk_setup' );

	$log = cmk_run_setup();
	set_transient( 'cmk_setup_log', $log, 5 * MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'themes.php?page=click-mat-kar&done=1' ) );
	exit;
}

/**
 * Pages the site needs. Content is only used when the page does not exist yet.
 */
function cmk_required_pages() {
	return array(
		'home'           => array( 'title' => __( 'Home', 'click-mat-kar' ), 'content' => '' ),
		'about'          => array(
			'title'   => __( 'WTF is this?', 'click-mat-kar' ),
			'content' => "<!-- wp:paragraph -->\n<p>Click Mat Kar is a tiny playground for fake shopping, ridiculous choices and screenshots you immediately send to a friend. No productivity. No life-changing promises. Just fun.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Everything here is pretend. The money is fake, the products are fake, the judgement is very real.</p>\n<!-- /wp:paragraph -->",
		),
		'result'         => array( 'title' => __( 'Your Result', 'click-mat-kar' ), 'content' => '' ),
		'privacy-policy' => array(
			'title'   => __( 'Privacy Policy', 'click-mat-kar' ),
			'content' => "<!-- wp:paragraph -->\n<p>We do not ask you to sign up, log in or pay. Game choices are kept in your browser and inside the share links you create. We may use privacy-friendly analytics to count page views and game events (for example: game started, result shared). We never sell personal data.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><em>Update this page with your final analytics/hosting details before public launch.</em></p>\n<!-- /wp:paragraph -->",
		),
		'terms'          => array(
			'title'   => __( 'Terms', 'click-mat-kar' ),
			'content' => "<!-- wp:paragraph -->\n<p>Click Mat Kar is entertainment. All money, products, prices and scores are fictional. Nothing here is financial, relationship or life advice. Brands or product types mentioned are used for parody only. Be nice when you share results.</p>\n<!-- /wp:paragraph -->",
		),
	);
}

/**
 * Do the work. Returns a list of human-readable log lines.
 */
function cmk_run_setup() {
	$log = array();
	cmk_register_game_cpt();

	// 1. Pages.
	$ids = array();
	foreach ( cmk_required_pages() as $slug => $page ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$ids[ $slug ] = $existing->ID;
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
				$log[] = sprintf( 'Published existing page: %s', $page['title'] );
			} else {
				$log[] = sprintf( 'Kept existing page: %s', $page['title'] );
			}
			continue;
		}
		$ids[ $slug ] = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_content' => $page['content'],
			)
		);
		$log[] = sprintf( 'Created page: %s', $page['title'] );
	}

	// 2. Static front page + privacy page.
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $ids['home'] );
	update_option( 'wp_page_for_privacy_policy', (int) $ids['privacy-policy'] );
	$log[] = 'Assigned static front page and privacy page.';

	// 3. Games: live ones published, the rest as drafts (never fake live pages).
	$order = 0;
	foreach ( cmk_games_catalog() as $slug => $game ) {
		++$order;
		$existing = cmk_get_game_by_slug( $slug );
		if ( $existing ) {
			if ( $game['live'] && 'publish' !== $existing->post_status ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
				$log[] = sprintf( 'Published game: %s', $game['title'] );
			} else {
				$log[] = sprintf( 'Kept game: %s', $game['title'] );
			}
			if ( '' === get_post_meta( $existing->ID, 'cmk_engine', true ) ) {
				update_post_meta( $existing->ID, 'cmk_engine', $game['engine'] );
			}
			continue;
		}
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
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'cmk_engine', $game['engine'] );
			update_post_meta( $post_id, 'cmk_emoji', $game['emoji'] );
			update_post_meta( $post_id, 'cmk_color', $game['color'] );
			update_post_meta( $post_id, 'cmk_hook', $game['hook'] );
			update_post_meta( $post_id, 'cmk_kicker', $game['kicker'] );
			update_post_meta( $post_id, 'cmk_minutes', $game['minutes'] );
			$log[] = sprintf( 'Created game (%s): %s', $game['live'] ? 'live' : 'draft', $game['title'] );
		}
	}

	// 4. Menus (only created if missing, only assigned to empty locations).
	$shop      = cmk_get_game_by_slug( 'shop-like-youre-rich' );
	$locations = get_theme_mod( 'nav_menu_locations', array() );

	$menus = array(
		'primary' => array(
			'name'  => 'CMK Primary',
			'items' => array(
				array( 'Games', get_post_type_archive_link( 'cmk_game' ) ),
				array( 'How it works', home_url( '/#how-it-works' ) ),
				array( 'Results', home_url( '/#results' ) ),
				array( 'WTF is this?', get_permalink( $ids['about'] ) ),
			),
		),
		'footer'  => array(
			'name'  => 'CMK Footer',
			'items' => array(
				array( "Shop Like You're Rich", $shop ? get_permalink( $shop ) : get_post_type_archive_link( 'cmk_game' ) ),
				array( 'All games', get_post_type_archive_link( 'cmk_game' ) ),
				array( 'WTF is this?', get_permalink( $ids['about'] ) ),
				array( 'Privacy', get_permalink( $ids['privacy-policy'] ) ),
				array( 'Terms', get_permalink( $ids['terms'] ) ),
			),
		),
	);

	foreach ( $menus as $location => $menu ) {
		if ( ! empty( $locations[ $location ] ) && wp_get_nav_menu_object( $locations[ $location ] ) ) {
			$log[] = sprintf( 'Kept existing %s menu.', $location );
			continue;
		}
		$menu_obj = wp_get_nav_menu_object( $menu['name'] );
		$menu_id  = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu( $menu['name'] );
		if ( is_wp_error( $menu_id ) ) {
			continue;
		}
		if ( ! $menu_obj ) {
			foreach ( $menu['items'] as $item ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => $item[0],
						'menu-item-url'    => $item[1],
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
			}
		}
		$locations[ $location ] = $menu_id;
		$log[]                  = sprintf( 'Assigned %s menu.', $location );
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	// 5. Pretty permalinks are required for /games/... URLs.
	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		$log[] = 'Switched permalinks to /%postname%/.';
	}
	flush_rewrite_rules();
	$log[] = 'Flushed rewrite rules.';

	update_option( 'cmk_setup_version', CMK_VERSION );
	return $log;
}

function cmk_admin_page() {
	$log = get_transient( 'cmk_setup_log' );
	delete_transient( 'cmk_setup_log' );
	$ran = get_option( 'cmk_setup_version' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Click Mat Kar — setup', 'click-mat-kar' ); ?></h1>
		<p><?php esc_html_e( 'One click creates (or repairs) everything V1 needs: Home, WTF is this?, Result, Privacy and Terms pages, the Shop Like You\'re Rich game, draft roadmap games, menus and permalinks. Existing content is never overwritten.', 'click-mat-kar' ); ?></p>
		<?php if ( $ran ) : ?>
			<p><strong><?php printf( esc_html__( 'Last run with theme version %s.', 'click-mat-kar' ), esc_html( $ran ) ); ?></strong></p>
		<?php endif; ?>
		<?php if ( $log ) : ?>
			<div class="notice notice-success"><p><strong><?php esc_html_e( 'Done. Mana kiya tha, phir bhi kar diya.', 'click-mat-kar' ); ?></strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $log as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cmk_setup">
			<?php wp_nonce_field( 'cmk_setup' ); ?>
			<?php submit_button( $ran ? __( 'Run setup again (safe)', 'click-mat-kar' ) : __( 'Run 1-click setup', 'click-mat-kar' ), 'primary large' ); ?>
		</form>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View site →', 'click-mat-kar' ); ?></a></p>
	</div>
	<?php
}

add_action( 'admin_notices', 'cmk_setup_nag' );
function cmk_setup_nag() {
	if ( get_option( 'cmk_setup_version' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'appearance_page_click-mat-kar' === $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p>%s <a class="button button-primary" href="%s">%s</a></p></div>',
		esc_html__( 'Click Mat Kar is active. Run the 1-click setup to create pages, games and menus.', 'click-mat-kar' ),
		esc_url( admin_url( 'themes.php?page=click-mat-kar' ) ),
		esc_html__( 'Open setup', 'click-mat-kar' )
	);
}
