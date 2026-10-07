<?php
/**
 * WP-CLI entry point:  wp eval-file wp-content/themes/talkwyn/setup/setup.php [overwrite]
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

$talkwyn_overwrite = isset( $args ) && in_array( 'overwrite', (array) $args, true );
$talkwyn_report    = talkwyn_run_setup( $talkwyn_overwrite );
foreach ( $talkwyn_report as $talkwyn_key => $talkwyn_items ) {
	echo esc_html( $talkwyn_key . ': ' . count( $talkwyn_items ) ) . "\n";
}
echo esc_html( 'Placeholders (noindex): ' . implode( ', ', $talkwyn_report['placeholders'] ) ) . "\n";
