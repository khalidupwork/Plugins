<?php
/**
 * Talkwyn theme bootstrap.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

define( 'TALKWYN_THEME_VERSION', '1.0.0' );
define( 'TALKWYN_THEME_DIR', get_template_directory() );
define( 'TALKWYN_THEME_URL', get_template_directory_uri() );

foreach ( array( 'logo', 'icons', 'settings', 'setup', 'components', 'home', 'pricing', 'docs', 'seo', 'analytics', 'woocommerce', 'forms', 'installer' ) as $talkwyn_file ) {
	require_once TALKWYN_THEME_DIR . '/inc/' . $talkwyn_file . '.php';
}
