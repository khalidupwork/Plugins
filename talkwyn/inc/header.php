<?php
/**
 * Site header (mega menu) and footer, rendered as dynamic blocks:
 * talkwyn/site-header and talkwyn/site-footer, used by parts/header.html and parts/footer.html.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * The 12 industries, 12 languages and 12 comparison pages. One list for the mega menu,
 * the footer and the homepage, so they never drift apart.
 *
 * @return array<string, array<int, array<int, string>>> Each item: icon, label, path, native name (languages).
 */
function talkwyn_site_lists(): array {
	return array(
		'industries' => array(
			array( 'shopping-bag', __( 'Ecommerce', 'talkwyn' ), '/industries/ecommerce/' ),
			array( 'building-2', __( 'Real estate', 'talkwyn' ), '/industries/real-estate/' ),
			array( 'stethoscope', __( 'Healthcare and clinics', 'talkwyn' ), '/industries/healthcare/' ),
			array( 'graduation-cap', __( 'Schools and education', 'talkwyn' ), '/industries/education/' ),
			array( 'house', __( 'Small business', 'talkwyn' ), '/industries/small-business/' ),
			array( 'calendar', __( 'Hotels and travel', 'talkwyn' ), '/industries/hotels/' ),
			array( 'store', __( 'Restaurants and cafes', 'talkwyn' ), '/industries/restaurants/' ),
			array( 'scale', __( 'Law firms', 'talkwyn' ), '/industries/law-firms/' ),
			array( 'house', __( 'Home services', 'talkwyn' ), '/industries/home-services/' ),
			array( 'heart-pulse', __( 'Gyms and fitness', 'talkwyn' ), '/industries/fitness/' ),
			array( 'gauge', __( 'Automotive', 'talkwyn' ), '/industries/automotive/' ),
			array( 'sparkles', __( 'Salons and spas', 'talkwyn' ), '/industries/beauty-salons/' ),
		),
		'languages'  => array(
			array( 'languages', __( 'English', 'talkwyn' ), '/multilingual-chatbot/english/', 'English', 'en' ),
			array( 'languages', __( 'Spanish', 'talkwyn' ), '/multilingual-chatbot/spanish/', 'Español', 'es' ),
			array( 'languages', __( 'French', 'talkwyn' ), '/multilingual-chatbot/french/', 'Français', 'fr' ),
			array( 'languages', __( 'German', 'talkwyn' ), '/multilingual-chatbot/german/', 'Deutsch', 'de' ),
			array( 'languages', __( 'Portuguese', 'talkwyn' ), '/multilingual-chatbot/portuguese/', 'Português', 'pt' ),
			array( 'languages', __( 'Italian', 'talkwyn' ), '/multilingual-chatbot/italian/', 'Italiano', 'it' ),
			array( 'languages', __( 'Arabic', 'talkwyn' ), '/multilingual-chatbot/arabic/', 'العربية', 'ar' ),
			array( 'languages', __( 'Hindi', 'talkwyn' ), '/multilingual-chatbot/hindi/', 'हिन्दी', 'hi' ),
			array( 'languages', __( 'Chinese', 'talkwyn' ), '/multilingual-chatbot/chinese/', '中文', 'zh' ),
			array( 'languages', __( 'Japanese', 'talkwyn' ), '/multilingual-chatbot/japanese/', '日本語', 'ja' ),
			array( 'languages', __( 'Korean', 'talkwyn' ), '/multilingual-chatbot/korean/', '한국어', 'ko' ),
			array( 'languages', __( 'Turkish', 'talkwyn' ), '/multilingual-chatbot/turkish/', 'Türkçe', 'tr' ),
		),
		'compare'    => array(
			array( 'scale', __( 'Tidio alternative', 'talkwyn' ), '/compare/tidio-alternative/' ),
			array( 'scale', __( 'Chatbase alternative', 'talkwyn' ), '/compare/chatbase-alternative/' ),
			array( 'scale', __( 'Intercom alternative', 'talkwyn' ), '/compare/intercom-alternative/' ),
			array( 'scale', __( 'Crisp alternative', 'talkwyn' ), '/compare/crisp-alternative/' ),
			array( 'scale', __( 'tawk.to alternative', 'talkwyn' ), '/compare/tawk-to-alternative/' ),
			array( 'scale', __( 'LiveChat alternative', 'talkwyn' ), '/compare/livechat-alternative/' ),
			array( 'scale', __( 'Zendesk alternative', 'talkwyn' ), '/compare/zendesk-alternative/' ),
			array( 'scale', __( 'Freshchat alternative', 'talkwyn' ), '/compare/freshchat-alternative/' ),
			array( 'scale', __( 'HubSpot chatbot alternative', 'talkwyn' ), '/compare/hubspot-chatbot-alternative/' ),
			array( 'scale', __( 'Botpress alternative', 'talkwyn' ), '/compare/botpress-alternative/' ),
			array( 'scale', __( 'ManyChat alternative', 'talkwyn' ), '/compare/manychat-alternative/' ),
			array( 'scale', __( 'Olark alternative', 'talkwyn' ), '/compare/olark-alternative/' ),
		),
	);
}

/**
 * Mega menu data (site-plan.md section 3).
 *
 * @return array<int, array<string, mixed>>
 */
function talkwyn_menu(): array {
	$status = static function ( string $slug ): string {
		$all = talkwyn_integrations();
		return $all[ $slug ]['status'] ?? 'planned';
	};
	$menu   = array(
		array(
			'id'      => 'product',
			'label'   => __( 'Product', 'talkwyn' ),
			'links'   => array(
				array( 'layers', __( 'Features overview', 'talkwyn' ), __( 'Everything Talkwyn does, in one place', 'talkwyn' ), '/features/' ),
				array( 'scan-search', __( 'Instant answers', 'talkwyn' ), __( 'Replies from your own pages and products', 'talkwyn' ), '/features/knowledge-base/' ),
				array( 'user-check', __( 'Lead generation', 'talkwyn' ), __( 'Turn questions into follow-ups', 'talkwyn' ), '/features/lead-generation/' ),
				array( 'languages', __( 'Multilingual replies', 'talkwyn' ), __( 'Answers in the visitor\'s language', 'talkwyn' ), '/multilingual-chatbot/' ),
				array( 'bar-chart-3', __( 'Analytics and inbox', 'talkwyn' ), __( 'See what visitors ask, all in one inbox', 'talkwyn' ), '/features/analytics/', 'pro' ),
				array( 'play', __( 'Live demo', 'talkwyn' ), __( 'Ask a sample clinic chatbot anything', 'talkwyn' ), '/#live-demo' ),
			),
			'feature' => array(
				'type'  => 'demo',
				'title' => __( 'Try the live demo', 'talkwyn' ),
				'text'  => __( 'Ask about prices, hours, or insurance. Try it in Arabic.', 'talkwyn' ),
				'url'   => '/#live-demo',
			),
		),
		array(
			'id'      => 'integrations',
			'label'   => __( 'Integrations', 'talkwyn' ),
			'links'   => array(
				array( 'plug', __( 'WordPress plugin', 'talkwyn' ), __( 'One-click install from your dashboard', 'talkwyn' ), '/integrations/wordpress/', $status( 'wordpress' ) ),
				array( 'store', __( 'WooCommerce', 'talkwyn' ), __( 'Answers about products, stock, and shipping', 'talkwyn' ), '/integrations/woocommerce/', $status( 'woocommerce' ) ),
				array( 'layout-template', __( 'Elementor', 'talkwyn' ), __( 'Reads your Elementor pages out of the box', 'talkwyn' ), '/integrations/elementor/', $status( 'elementor' ) ),
				array( 'shopping-bag', __( 'Shopify', 'talkwyn' ), __( 'Product answers and lead capture', 'talkwyn' ), '/integrations/shopify/', $status( 'shopify' ) ),
				array( 'code-xml', __( 'Any website (embed code)', 'talkwyn' ), __( 'One line of code for any site', 'talkwyn' ), '/integrations/website-embed/', $status( 'website_embed' ) ),
				array( 'smartphone', __( 'WhatsApp lead alerts', 'talkwyn' ), __( 'New leads straight to your phone', 'talkwyn' ), '/integrations/whatsapp/', $status( 'whatsapp' ) ),
				array( 'vote', __( 'Wix, Webflow, Squarespace', 'talkwyn' ), __( 'Vote for the next platform', 'talkwyn' ), '/integrations/#planned', 'planned' ),
			),
			'feature' => array(
				'type'  => 'integrations',
				'title' => __( 'See all integrations and vote for the next one', 'talkwyn' ),
				'text'  => __( 'Tell us where your website lives. We email you the day it ships.', 'talkwyn' ),
				'url'   => '/integrations/',
			),
		),
		array(
			'id'      => 'industries',
			'label'   => __( 'Industries', 'talkwyn' ),
			'compact' => true,
			'links'   => array_merge(
				array_map(
					static function ( $i ) {
						return array( $i[0], $i[1], '', $i[2] );
					},
					talkwyn_site_lists()['industries']
				),
				array( array( 'briefcase', __( 'Agencies (white label)', 'talkwyn' ), '', '/agencies/' ) )
			),
			'feature' => array(
				'type'  => 'template',
				'title' => __( 'Free real estate chatbot template', 'talkwyn' ),
				'text'  => __( '25 questions, example answers, and a lead flow you can copy.', 'talkwyn' ),
				'url'   => '/blog/real-estate-chatbot-template/',
			),
		),
		array(
			'id'      => 'languages',
			'label'   => __( 'Languages', 'talkwyn' ),
			'compact' => true,
			'links'   => array_merge(
				array_map(
					static function ( $i ) {
						return array( $i[0], $i[1], $i[3], $i[2] );
					},
					talkwyn_site_lists()['languages']
				),
				array( array( 'globe', __( 'All languages', 'talkwyn' ), __( 'How it works', 'talkwyn' ), '/multilingual-chatbot/' ) )
			),
			'feature' => array(
				'type'  => 'languages',
				'title' => __( 'One chatbot, every language', 'talkwyn' ),
				'text'  => __( 'It replies in the language each visitor writes in.', 'talkwyn' ),
				'url'   => '/multilingual-chatbot/',
			),
		),
		array(
			'id'      => 'resources',
			'label'   => __( 'Resources', 'talkwyn' ),
			'links'   => array(
				array( 'newspaper', __( 'Blog', 'talkwyn' ), __( 'Guides for chatbots that bring leads', 'talkwyn' ), '/blog/' ),
				array( 'book-open', __( 'Docs', 'talkwyn' ), __( 'Setup and how-to guides', 'talkwyn' ), '/docs/' ),
				array( 'key-round', __( 'Free AI keys guide', 'talkwyn' ), __( 'Groq, Gemini, OpenRouter, and Cloudflare', 'talkwyn' ), '/blog/free-ai-api-keys/' ),
				array( 'scale', __( 'Compare', 'talkwyn' ), __( 'Talkwyn next to other chat tools', 'talkwyn' ), '/compare/' ),
				array( 'history', __( 'Changelog', 'talkwyn' ), __( 'What is new in each version', 'talkwyn' ), '/changelog/' ),
				array( 'handshake', __( 'Partners program', 'talkwyn' ), __( 'Earn on every paid plan you refer', 'talkwyn' ), '/partners/' ),
			),
			'feature' => array(
				'type' => 'post',
				'url'  => '/blog/',
			),
		),
		array(
			'id'    => 'pricing',
			'label' => __( 'Pricing', 'talkwyn' ),
			'url'   => '/pricing/',
		),
	);
	return (array) apply_filters( 'talkwyn_menu', $menu );
}

/**
 * Path of the current request, for aria-current.
 */
function talkwyn_current_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '/';
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && '/' !== $home && 0 === strpos( $uri, rtrim( $home, '/' ) ) ) {
		$uri = substr( $uri, strlen( rtrim( $home, '/' ) ) );
	}
	return '/' . trim( $uri, '/' ) . ( '/' === $uri || '' === trim( $uri, '/' ) ? '' : '/' );
}

/**
 * Status pill.
 *
 * @param string $status available|in_development|planned|pro.
 */
function talkwyn_status_pill( string $status ): string {
	if ( 'pro' === $status ) {
		return '<span class="tw-status tw-status--pro">' . esc_html__( 'Pro', 'talkwyn' ) . '</span>';
	}
	return '<span class="tw-status tw-status--' . esc_attr( str_replace( '_', '-', $status ) ) . '">' . esc_html( talkwyn_status_label( $status ) ) . '</span>';
}

/**
 * Featured card of a mega menu panel.
 *
 * @param array<string, string> $f Feature.
 */
function talkwyn_menu_feature( array $f ): string {
	if ( 'post' === $f['type'] ) {
		$posts = get_posts(
			array(
				'numberposts'      => 1,
				'post_status'      => 'publish',
				'suppress_filters' => false,
			)
		);
		if ( ! $posts ) {
			return '<a class="tw-mega__feature" href="' . esc_url( home_url( '/blog/' ) ) . '"><span class="tw-mega__feature-kicker">' . esc_html__( 'Blog', 'talkwyn' ) . '</span><span class="tw-mega__feature-title">' . esc_html__( 'Guides for chatbots that bring leads', 'talkwyn' ) . '</span></a>';
		}
		$post = $posts[0];
		return '<a class="tw-mega__feature tw-mega__feature--post" href="' . esc_url( get_permalink( $post ) ) . '">'
			. '<span class="tw-mega__feature-kicker">' . esc_html__( 'Newest on the blog', 'talkwyn' ) . '</span>'
			. '<span class="tw-mega__feature-title">' . esc_html( get_the_title( $post ) ) . '</span>'
			. '<span class="tw-mega__feature-text">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 16 ) ) . '</span>'
			. '<span class="tw-mega__feature-more">' . esc_html__( 'Read the post', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</span></a>';
	}
	$visual = '';
	switch ( $f['type'] ) {
		case 'demo':
			$visual = '<span class="tw-mini-chat" aria-hidden="true"><span class="tw-mini-chat__msg tw-mini-chat__msg--user">How much is whitening?</span><span class="tw-mini-chat__msg tw-mini-chat__msg--bot">Whitening starts from the price on our treatments page. Want a call back?</span></span>';
			break;
		case 'languages':
			$visual = '<span class="tw-mini-chat tw-mini-chat--switch" aria-hidden="true">'
				. '<span class="tw-mini-chat__msg tw-mini-chat__msg--user" lang="ar" dir="rtl">هل تفتحون يوم السبت؟</span>'
				. '<span class="tw-mini-chat__msg tw-mini-chat__msg--user" lang="es">¿Abren el sábado?</span>'
				. '<span class="tw-mini-chat__msg tw-mini-chat__msg--user" lang="fr">Vous êtes ouverts samedi ?</span>'
				. '</span>';
			break;
		case 'integrations':
			$visual = '<span class="tw-mega__vote" aria-hidden="true">' . talkwyn_status_pill( 'available' ) . talkwyn_status_pill( 'in_development' ) . talkwyn_status_pill( 'planned' ) . '</span>';
			break;
		case 'template':
			$visual = '<span class="tw-mega__checklist" aria-hidden="true"><span>' . talkwyn_icon( 'check', 14 ) . 'Listings</span><span>' . talkwyn_icon( 'check', 14 ) . 'Viewings</span><span>' . talkwyn_icon( 'check', 14 ) . 'Lead flow</span></span>';
			break;
	}
	return '<a class="tw-mega__feature tw-mega__feature--' . esc_attr( $f['type'] ) . '" href="' . esc_url( home_url( $f['url'] ) ) . '">'
		. $visual
		. '<span class="tw-mega__feature-title">' . esc_html( $f['title'] ) . '</span>'
		. '<span class="tw-mega__feature-text">' . esc_html( $f['text'] ) . '</span>'
		. '<span class="tw-mega__feature-more">' . esc_html__( 'Open', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</span></a>';
}

/**
 * One link row in a panel.
 *
 * @param array<int, string> $l       Link: icon, title, description, url, [status].
 * @param string             $current Current path.
 */
function talkwyn_menu_link( array $l, string $current ): string {
	$url  = $l[3];
	$here = ( 0 === strpos( $url, '/' ) && false === strpos( $url, '#' ) && $url === $current ) ? ' aria-current="page"' : '';
	$pill = isset( $l[4] ) && 'available' !== $l[4] ? ' ' . talkwyn_status_pill( $l[4] ) : '';
	return '<li><a class="tw-mega__link" href="' . esc_url( home_url( $url ) ) . '"' . $here . '>'
		. '<span class="tw-mega__icon">' . talkwyn_icon( $l[0], 20 ) . '</span>'
		. '<span class="tw-mega__text"><span class="tw-mega__title">' . esc_html( $l[1] ) . $pill . '</span>'
		. ( '' !== $l[2] ? '<span class="tw-mega__desc">' . esc_html( $l[2] ) . '</span>' : '' ) . '</span></a></li>';
}

/**
 * Header markup.
 */
function talkwyn_header_html(): string {
	$current = talkwyn_current_path();
	$menu    = talkwyn_menu();
	$trial   = esc_url( home_url( '/pricing/#trial' ) );
	$install = esc_url( talkwyn_install_url() );
	$login   = esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) );

	$desktop = '';
	$mobile  = '';
	foreach ( $menu as $item ) {
		if ( empty( $item['links'] ) ) {
			$here     = $item['url'] === $current ? ' aria-current="page"' : '';
			$desktop .= '<li class="tw-mega__item"><a class="tw-mega__trigger" href="' . esc_url( home_url( $item['url'] ) ) . '"' . $here . '>' . esc_html( $item['label'] ) . '</a></li>';
			$mobile  .= '<li><a class="tw-mnav__link" href="' . esc_url( home_url( $item['url'] ) ) . '"' . $here . '>' . esc_html( $item['label'] ) . '</a></li>';
			continue;
		}
		$links  = '';
		$active = false;
		foreach ( $item['links'] as $l ) {
			$links .= talkwyn_menu_link( $l, $current );
			$active = $active || $l[3] === $current;
		}
		$id       = 'tw-mega-' . $item['id'];
		$cols     = ! empty( $item['compact'] ) ? ' tw-mega__links--compact' : ( count( $item['links'] ) > 6 ? ' tw-mega__links--3' : '' );
		$desktop .= '<li class="tw-mega__item' . ( $active ? ' is-current' : '' ) . '">'
			. '<button type="button" class="tw-mega__trigger" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '">' . esc_html( $item['label'] ) . talkwyn_icon( 'chevron-down', 16 ) . '</button>'
			. '<div class="tw-mega__panel" id="' . esc_attr( $id ) . '" hidden><div class="tw-mega__inner">'
			. '<ul class="tw-mega__links' . $cols . '">' . $links . '</ul>'
			. talkwyn_menu_feature( $item['feature'] )
			. '</div></div></li>';
		$mid      = 'tw-mnav-' . $item['id'];
		$mobile  .= '<li class="tw-mnav__group"><button type="button" class="tw-mnav__toggle" aria-expanded="' . ( $active ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $mid ) . '">' . esc_html( $item['label'] ) . talkwyn_icon( 'chevron-down', 20 ) . '</button>'
			. '<ul class="tw-mnav__sub" id="' . esc_attr( $mid ) . '"' . ( $active ? '' : ' hidden' ) . '>' . $links . '</ul></li>';
	}

	return '<div class="tw-hdr" data-tw-header>'
		. '<div class="tw-hdr__bar">'
		. '<a class="tw-logo-link" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . talkwyn_logo_svg( 'full', __( 'Talkwyn home', 'talkwyn' ) ) . '</a>'
		. '<nav class="tw-mega" aria-label="' . esc_attr__( 'Main', 'talkwyn' ) . '"><ul class="tw-mega__list">' . $desktop . '</ul></nav>'
		. '<div class="tw-hdr__actions">'
		. ( is_user_logged_in()
			? '<a class="tw-hdr__login" href="' . $login . '">' . esc_html__( 'My account', 'talkwyn' ) . '</a>'
			: '<a class="tw-hdr__login" href="' . $login . '" data-tw-modal="login" aria-haspopup="dialog">' . esc_html__( 'Log in', 'talkwyn' ) . '</a>' )
		. ( talkwyn_show_trial() ? '<a class="tw-pill tw-pill--red tw-pill--sm" href="' . $trial . '" data-tw-event="trial_click" data-tw-location="header" data-tw-modal="trial" aria-haspopup="dialog">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>' : '' )
		. '<a class="tw-pill tw-pill--ink tw-pill--sm tw-hdr__install" href="' . $install . '" data-tw-event="install_click" data-tw-location="header">' . esc_html__( 'Install free', 'talkwyn' ) . '</a>'
		. '<button type="button" class="tw-hdr__burger" aria-expanded="false" aria-controls="tw-mnav">' . talkwyn_icon( 'menu', 24 ) . '<span class="screen-reader-text">' . esc_html__( 'Open menu', 'talkwyn' ) . '</span></button>'
		. '</div></div>'
		. '<div class="tw-mnav" id="tw-mnav" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Menu', 'talkwyn' ) . '" hidden>'
		. '<div class="tw-mnav__head"><a class="tw-logo-link" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . talkwyn_logo_svg( 'full', __( 'Talkwyn home', 'talkwyn' ) ) . '</a>'
		. '<button type="button" class="tw-mnav__close" data-tw-mnav-close>' . talkwyn_icon( 'x', 24 ) . '<span class="screen-reader-text">' . esc_html__( 'Close menu', 'talkwyn' ) . '</span></button></div>'
		. '<nav class="tw-mnav__body" aria-label="' . esc_attr__( 'Main', 'talkwyn' ) . '"><ul class="tw-mnav__list">' . $mobile . '</ul></nav>'
		. '<div class="tw-mnav__foot">'
		. ( talkwyn_show_trial() ? '<a class="tw-pill tw-pill--red" href="' . $trial . '" data-tw-event="trial_click" data-tw-location="mobile_menu" data-tw-modal="trial" aria-haspopup="dialog">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>' : '' )
		. '<a class="tw-pill tw-pill--ink" href="' . $install . '" data-tw-event="install_click" data-tw-location="mobile_menu">' . esc_html__( 'Install free', 'talkwyn' ) . '</a>'
		. '<a class="tw-mnav__login" href="' . $login . '"' . ( is_user_logged_in() ? '>' . esc_html__( 'My account', 'talkwyn' ) : ' data-tw-modal="login" aria-haspopup="dialog">' . esc_html__( 'Log in', 'talkwyn' ) ) . '</a>'
		. '</div></div>'
		. '</div>';
}

/**
 * Footer columns (site-plan.md section 4).
 *
 * @return array<string, array<int, array{0: string, 1: string}>>
 */
function talkwyn_footer_columns(): array {
	return array(
		__( 'Product', 'talkwyn' )      => array(
			array( __( 'Features', 'talkwyn' ), '/features/' ),
			array( __( 'Live demo', 'talkwyn' ), '/#live-demo' ),
			array( __( 'Pricing', 'talkwyn' ), '/pricing/' ),
			array( __( 'Changelog', 'talkwyn' ), '/changelog/' ),
		),
		__( 'Integrations', 'talkwyn' ) => array(
			array( __( 'WordPress', 'talkwyn' ), '/integrations/wordpress/' ),
			array( __( 'WooCommerce', 'talkwyn' ), '/integrations/woocommerce/' ),
			array( __( 'Shopify', 'talkwyn' ), '/integrations/shopify/' ),
			array( __( 'Any website', 'talkwyn' ), '/integrations/website-embed/' ),
			array( __( 'All integrations', 'talkwyn' ), '/integrations/' ),
		),
		__( 'Resources', 'talkwyn' )    => array(
			array( __( 'Blog', 'talkwyn' ), '/blog/' ),
			array( __( 'Docs', 'talkwyn' ), '/docs/' ),
			array( __( 'Getting started', 'talkwyn' ), '/docs/getting-started/' ),
			array( __( 'All comparisons', 'talkwyn' ), '/compare/' ),
			array( __( 'All industries', 'talkwyn' ), '/industries/' ),
			array( __( 'All languages', 'talkwyn' ), '/multilingual-chatbot/' ),
		),
		__( 'Company', 'talkwyn' )      => array(
			array( __( 'About', 'talkwyn' ), '/about/' ),
			array( __( 'Partners', 'talkwyn' ), '/partners/' ),
			array( __( 'Agencies', 'talkwyn' ), '/agencies/' ),
			array( __( 'Contact', 'talkwyn' ), '/contact/' ),
			array( __( 'Privacy', 'talkwyn' ), '/privacy/' ),
			array( __( 'Terms', 'talkwyn' ), '/terms/' ),
			array( __( 'Refund policy', 'talkwyn' ), '/refund-policy/' ),
		),
	);
}

/**
 * Giant footer wordmark (outlined from brand/logo/talkwyn-wordmark-on-dark.svg).
 */
function talkwyn_footer_wordmark(): string {
	static $svg = null;
	if ( null === $svg ) {
		$file = TALKWYN_THEME_DIR . '/assets/brand/logo/talkwyn-wordmark-on-dark.svg';
		$raw  = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$raw  = (string) preg_replace( '/<title>.*?<\/title>/s', '', $raw );
		$raw  = (string) preg_replace( '/<svg[^>]*viewBox="([^"]+)"[^>]*>/', '<svg class="tw-ftr__word" viewBox="$1" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMax meet">', $raw, 1 );
		$svg  = $raw;
	}
	return $svg;
}

/**
 * Footer explore band: every industry, language and comparison page.
 *
 * @param string $current Current path.
 */
function talkwyn_footer_explore( string $current ): string {
	$lists  = talkwyn_site_lists();
	$groups = array(
		array( __( 'Industries', 'talkwyn' ), $lists['industries'], '/industries/' ),
		array( __( 'Languages', 'talkwyn' ), $lists['languages'], '/multilingual-chatbot/' ),
		array( __( 'Compare', 'talkwyn' ), $lists['compare'], '/compare/' ),
	);
	$out    = '';
	foreach ( $groups as $g ) {
		$items = '';
		foreach ( $g[1] as $l ) {
			$here   = $l[2] === $current ? ' aria-current="page"' : '';
			$items .= '<li><a href="' . esc_url( home_url( $l[2] ) ) . '"' . $here . '>' . esc_html( $l[1] ) . '</a></li>';
		}
		$out .= '<div class="tw-ftr__xgroup"><h2 class="tw-ftr__heading"><a href="' . esc_url( home_url( $g[2] ) ) . '">' . esc_html( $g[0] ) . '</a></h2><ul>' . $items . '</ul></div>';
	}
	return '<nav class="tw-ftr__explore" aria-label="' . esc_attr__( 'Explore', 'talkwyn' ) . '">' . $out . '</nav>';
}

/**
 * Footer markup.
 */
function talkwyn_footer_html(): string {
	$cols    = '';
	$current = talkwyn_current_path();
	$columns = talkwyn_footer_columns();
	if ( talkwyn_show_trial() ) {
		$first                      = array_key_first( $columns );
		$columns[ $first ][]        = array( __( 'Start free trial', 'talkwyn' ), '/pricing/#trial' );
	}
	foreach ( $columns as $heading => $links ) {
		$items = '';
		foreach ( $links as $l ) {
			$here   = $l[1] === $current ? ' aria-current="page"' : '';
			$items .= '<li><a href="' . esc_url( home_url( $l[1] ) ) . '"' . $here . '>' . esc_html( $l[0] ) . '</a></li>';
		}
		$cols .= '<div class="tw-ftr__col"><h2 class="tw-ftr__heading">' . esc_html( $heading ) . '</h2><ul>' . $items . '</ul></div>';
	}
	return '<div class="tw-ftr"><div class="tw-ftr__band">'
		. '<div class="tw-ftr__top">'
		. '<div class="tw-ftr__brand"><a class="tw-logo-link" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . talkwyn_logo_svg( 'full', __( 'Talkwyn home', 'talkwyn' ) ) . '</a>'
		. '<p>' . esc_html__( 'An AI chatbot for any website that answers visitors in their own language and turns chats into leads.', 'talkwyn' ) . '</p>'
		. '<a class="tw-pill tw-pill--red tw-pill--sm" href="' . esc_url( home_url( '/pricing/#trial' ) ) . '" data-tw-event="trial_click" data-tw-location="footer">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a></div>'
		. '<nav class="tw-ftr__cols" aria-label="' . esc_attr__( 'Footer', 'talkwyn' ) . '">' . $cols . '</nav>'
		. '</div>'
		. talkwyn_footer_explore( $current )
		. '<div class="tw-ftr__bottom">' . do_shortcode( '[tw_social]' )
		/* translators: %s: year */
		. '<p>' . esc_html( sprintf( __( '© %s Talkwyn. All rights reserved.', 'talkwyn' ), gmdate( 'Y' ) ) ) . '</p>'
		. '<p class="tw-ftr__tag">' . esc_html__( 'Made for businesses that speak every language.', 'talkwyn' ) . '</p></div>'
		. talkwyn_footer_wordmark()
		. '</div></div>';
}

/**
 * End-of-page call to action. Pages can switch it with the post meta _tw_cta:
 * "waitlist" (integration pages that are not live yet; _tw_platform names the platform) or "none".
 */
function talkwyn_cta_band_html(): string {
	$post_id = is_singular() ? (int) get_queried_object_id() : 0;
	$mode    = $post_id ? (string) get_post_meta( $post_id, '_tw_cta', true ) : '';
	if ( 'none' === $mode ) {
		return '';
	}
	if ( 'waitlist' === $mode ) {
		$platform = (string) get_post_meta( $post_id, '_tw_platform', true );
		/* translators: %s: platform name */
		$title = '' !== $platform ? sprintf( __( 'Be first when %s ships.', 'talkwyn' ), $platform ) : __( 'Be first when it ships.', 'talkwyn' );
		return '<section class="tw-cta-band tw-on-ink" aria-labelledby="tw-cta-title"><h2 id="tw-cta-title">' . esc_html( $title ) . '</h2>'
			. '<p>' . esc_html__( 'Join the waitlist and we will email you the day it launches. No spam, one email when it is ready.', 'talkwyn' ) . '</p>'
			. '<div class="tw-actions"><a class="tw-pill tw-pill--red tw-pill--lg" href="#waitlist">' . esc_html__( 'Join the waitlist', 'talkwyn' ) . '</a>'
			. '<a class="tw-pill tw-pill--white tw-pill--lg" href="' . esc_url( home_url( '/integrations/' ) ) . '">' . esc_html__( 'See what works today', 'talkwyn' ) . '</a></div></section>';
	}
	$days = talkwyn_trial()['days'];
	$line = talkwyn_show_trial()
		/* translators: %d: trial days */
		? sprintf( __( 'Start your %d-day trial, scan your site, and see your first answer in about five minutes.', 'talkwyn' ), $days )
		: __( 'Install Talkwyn on another site, or pick the plan that fits your next project.', 'talkwyn' );
	return '<section class="tw-cta-band tw-on-ink" aria-labelledby="tw-cta-title"><h2 id="tw-cta-title">' . esc_html__( 'Let your website do the talking.', 'talkwyn' ) . '</h2>'
		. '<p>' . esc_html( $line ) . '</p>'
		. '<div class="tw-actions"><a class="tw-pill tw-pill--red tw-pill--lg" href="' . esc_url( home_url( '/pricing/#trial' ) ) . '" data-tw-event="trial_click" data-tw-location="cta_band">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>'
		. '<a class="tw-pill tw-pill--white tw-pill--lg" href="' . esc_url( talkwyn_install_url() ) . '" data-tw-event="install_click" data-tw-location="cta_band">' . esc_html__( 'Install free', 'talkwyn' ) . '</a></div>'
		. '<p class="tw-cta-band__link"><a href="' . esc_url( home_url( '/docs/getting-started/' ) ) . '">' . esc_html__( 'Read the setup guide', 'talkwyn' ) . '</a></p></section>';
}

/**
 * Dynamic blocks for the template parts.
 */
add_action(
	'init',
	static function () {
		register_block_type(
			'talkwyn/site-header',
			array(
				'api_version'     => 3,
				'title'           => __( 'Talkwyn header', 'talkwyn' ),
				'category'        => 'theme',
				'render_callback' => 'talkwyn_header_html',
				'supports'        => array( 'html' => false ),
			)
		);
		register_block_type(
			'talkwyn/cta-band',
			array(
				'api_version'     => 3,
				'title'           => __( 'Talkwyn call to action band', 'talkwyn' ),
				'category'        => 'theme',
				'render_callback' => 'talkwyn_cta_band_html',
				'supports'        => array( 'html' => false ),
			)
		);
		register_block_type(
			'talkwyn/site-footer',
			array(
				'api_version'     => 3,
				'title'           => __( 'Talkwyn footer', 'talkwyn' ),
				'category'        => 'theme',
				'render_callback' => 'talkwyn_footer_html',
				'supports'        => array( 'html' => false ),
			)
		);
	}
);
