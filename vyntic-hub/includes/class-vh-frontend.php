<?php
/**
 * Public pages: the [vyntic_plugins] grid and each plugin's own page at
 * /plugins/{slug}/, with structured data for search engines. Works inside any
 * theme (and Elementor) because it only adds to the normal page content.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Frontend {

	public static function init() {
		add_shortcode( 'vyntic_plugins', array( __CLASS__, 'shortcode_grid' ) );
		add_filter( 'the_content', array( __CLASS__, 'single_content' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'vh-front', VH_URL . 'assets/css/front.css', array(), VH_VERSION );
		if ( is_singular( VH_Plugins::TYPE ) ) {
			wp_enqueue_style( 'vh-front' );
		}
	}

	private static function icon_html( $item, $class = 'vh-p-icon' ) {
		if ( ! empty( $item['icon'] ) ) {
			return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $item['icon'] ) . '" alt="' . esc_attr( $item['name'] ) . '" width="72" height="72" loading="lazy">';
		}
		return '<span class="' . esc_attr( $class ) . ' vh-p-icon-ph" aria-hidden="true">' . esc_html( strtoupper( substr( $item['name'], 0, 1 ) ) ) . '</span>';
	}

	/**
	 * [vyntic_plugins] grid of all published plugins.
	 */
	public static function shortcode_grid() {
		wp_enqueue_style( 'vh-front' );
		$items = VH_API::catalog();
		if ( ! $items ) {
			return '<p class="vh-p-empty">' . esc_html__( 'Plugins are coming soon.', 'vyntic-hub' ) . '</p>';
		}
		$html = '<div class="vh-p-grid">';
		foreach ( $items as $item ) {
			$html .= '<article class="vh-p-card">'
				. '<a class="vh-p-card-link" href="' . esc_url( $item['url'] ) . '">'
				. self::icon_html( $item )
				. '<h3>' . esc_html( $item['name'] ) . '</h3></a>'
				. '<p>' . esc_html( $item['short_description'] ) . '</p>'
				. '<div class="vh-p-card-foot"><span class="vh-p-ver">v' . esc_html( $item['version'] ) . '</span>'
				. '<a class="vh-p-more" href="' . esc_url( $item['url'] ) . '">' . esc_html__( 'View plugin', 'vyntic-hub' ) . ' &rarr;</a></div>'
				. '</article>';
		}
		return $html . '</div>';
	}

	/**
	 * Adds the hero box (icon, version, download) and changelog around the
	 * description on a plugin's own page.
	 */
	public static function single_content( $content ) {
		if ( ! is_singular( VH_Plugins::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		$item = $post ? VH_Plugins::item( $post ) : null;
		if ( ! $item ) {
			return $content;
		}

		$meta = array(
			__( 'Version', 'vyntic-hub' )      => $item['version'],
			__( 'Updated', 'vyntic-hub' )      => mysql2date( get_option( 'date_format' ), $item['updated'] ),
			__( 'WordPress', 'vyntic-hub' )    => $item['requires'] ? $item['requires'] . '+' : '',
			__( 'PHP', 'vyntic-hub' )          => $item['requires_php'] ? $item['requires_php'] . '+' : '',
			__( 'Tested up to', 'vyntic-hub' ) => $item['tested'],
		);
		if ( VH_Settings::get( 'show_installs' ) ) {
			$count = VH_Sites::active_count( $item['slug'] );
			if ( $count ) {
				$meta[ __( 'Active on', 'vyntic-hub' ) ] = sprintf( /* translators: %s: number */ _n( '%s site', '%s sites', $count, 'vyntic-hub' ), number_format_i18n( $count ) );
			}
		}

		$hero = '<div class="vh-p-hero">' . self::icon_html( $item, 'vh-p-hero-icon' )
			. '<div class="vh-p-hero-text"><p class="vh-p-lead">' . esc_html( $item['short_description'] ) . '</p>'
			. '<p class="vh-p-by">' . esc_html__( 'by', 'vyntic-hub' ) . ' ' . esc_html( VH_Settings::get( 'brand' ) ) . '</p></div>';
		if ( VH_Settings::get( 'show_download' ) ) {
			$hero .= '<a class="vh-p-download" href="' . esc_url( $item['package'] ) . '" rel="nofollow">' . esc_html__( 'Download', 'vyntic-hub' ) . ' <span>v' . esc_html( $item['version'] ) . ' &middot; ' . esc_html( size_format( $item['size'] ) ) . '</span></a>';
		}
		$hero .= '</div><dl class="vh-p-meta">';
		foreach ( $meta as $label => $value ) {
			if ( '' !== (string) $value ) {
				$hero .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
		}
		$hero .= '</dl>';

		$changelog = '';
		foreach ( array_slice( VH_Plugins::releases( $post->ID ), 0, 10 ) as $release ) {
			if ( '' === trim( $release['changelog'] ) ) {
				continue;
			}
			$changelog .= '<details class="vh-p-release"' . ( '' === $changelog ? ' open' : '' ) . '><summary><strong>' . esc_html( $release['version'] ) . '</strong> <span>' . esc_html( mysql2date( get_option( 'date_format' ), $release['date'] ) ) . '</span></summary>'
				. wpautop( esc_html( $release['changelog'] ) ) . '</details>';
		}
		if ( $changelog ) {
			$changelog = '<section class="vh-p-changelog"><h2>' . esc_html__( 'Changelog', 'vyntic-hub' ) . '</h2>' . $changelog . '</section>';
		}

		$install = '<section class="vh-p-install"><h2>' . esc_html__( 'Installation', 'vyntic-hub' ) . '</h2><ol>'
			. '<li>' . esc_html__( 'Download the zip file.', 'vyntic-hub' ) . '</li>'
			. '<li>' . esc_html__( 'In WordPress go to Plugins, Add New, Upload Plugin and choose the zip.', 'vyntic-hub' ) . '</li>'
			. '<li>' . esc_html__( 'Activate it. Future updates arrive automatically in your dashboard.', 'vyntic-hub' ) . '</li></ol></section>';

		return $hero . '<div class="vh-p-body">' . $content . '</div>' . $install . $changelog;
	}

	/**
	 * Structured data (SoftwareApplication) and a meta description when no SEO
	 * plugin provides one.
	 */
	public static function head() {
		if ( ! is_singular( VH_Plugins::TYPE ) ) {
			return;
		}
		$post = get_queried_object();
		$item = $post instanceof WP_Post ? VH_Plugins::item( $post ) : null;
		if ( ! $item ) {
			return;
		}
		$schema = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'SoftwareApplication',
			'name'                => $item['name'],
			'description'         => $item['short_description'],
			'url'                 => $item['url'],
			'applicationCategory' => 'DeveloperApplication',
			'operatingSystem'     => 'WordPress',
			'softwareVersion'     => $item['version'],
			'dateModified'        => mysql2date( 'c', $item['updated'] ),
			'downloadUrl'         => $item['package'],
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
			),
			'author'              => array(
				'@type' => 'Organization',
				'name'  => VH_Settings::get( 'brand' ),
				'url'   => home_url( '/' ),
			),
		);
		if ( $item['icon'] ) {
			$schema['image'] = $item['icon'];
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

		$has_seo_plugin = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\\Load' );
		if ( ! $has_seo_plugin && $item['short_description'] ) {
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( $item['short_description'], 30, '' ) ) . '">' . "\n";
		}
	}
}
