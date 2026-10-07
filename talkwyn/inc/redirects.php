<?php
/**
 * 301 redirects from theme 1.x URLs to their v3 pages.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Old path => new path.
 *
 * @return array<string, string>
 */
function talkwyn_legacy_redirects(): array {
	return (array) apply_filters(
		'talkwyn_legacy_redirects',
		array(
			'/wordpress-ai-chatbot/'          => '/integrations/wordpress/',
			'/woocommerce-chatbot/'           => '/integrations/woocommerce/',
			'/shopify-ai-chatbot/'            => '/integrations/shopify/',
			'/solutions/clinics/'             => '/industries/healthcare/',
			'/solutions/real-estate/'         => '/industries/real-estate/',
			'/solutions/ecommerce/'           => '/industries/ecommerce/',
			'/solutions/education/'           => '/industries/education/',
			'/solutions/'                     => '/industries/',
			'/compare/ai-engine-alternative/' => '/blog/best-wordpress-chatbot-plugins/',
			'/multilingual-chatbot/urdu/'     => '/multilingual-chatbot/',
		)
	);
}

add_action(
	'template_redirect',
	static function () {
		$path = talkwyn_current_path();
		$map  = talkwyn_legacy_redirects();
		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301, 'Talkwyn' );
			exit;
		}
	},
	0
);
