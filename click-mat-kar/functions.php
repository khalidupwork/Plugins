<?php
/**
 * Click Mat Kar theme bootstrap.
 *
 * @package ClickMatKar
 */

defined( 'ABSPATH' ) || exit;

define( 'CMK_VERSION', '1.0.0-alpha.2' );
define( 'CMK_DIR', get_template_directory() );
define( 'CMK_URI', get_template_directory_uri() );

require CMK_DIR . '/inc/setup.php';
require CMK_DIR . '/inc/games.php';
require CMK_DIR . '/inc/template-tags.php';
require CMK_DIR . '/inc/installer.php';
