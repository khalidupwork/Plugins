<?php
/**
 * Removes every trace of Vyntic Speed Optimizer.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'vso_settings' );
delete_option( 'vso_version' );
delete_option( 'vso_preload_queue' );
delete_option( 'vso_psi_last' );
delete_post_meta_by_key( '_vso_disable' );
delete_post_meta_by_key( '_vso_no_cache' );
delete_post_meta_by_key( '_vso_webp' );

// Deactivation already removed the drop-in, WP_CACHE and the cache folder;
// make sure nothing is left if the plugin files were deleted without deactivating.
$vso_dropin = WP_CONTENT_DIR . '/advanced-cache.php';
if ( is_file( $vso_dropin ) && false !== strpos( (string) file_get_contents( $vso_dropin ), 'Vyntic Speed Optimizer page cache drop-in' ) ) { // phpcs:ignore
	@unlink( $vso_dropin ); // phpcs:ignore
}
