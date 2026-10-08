<?php
/**
 * White label: hide "Powered by Talkwyn" and rename the admin menu.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * White label.
 */
final class WhiteLabel {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter(
			'talkwyn_show_powered_by',
			static function ( $show ) {
				return \Talkwyn_Settings::get( 'pro_hide_powered', 1 ) ? false : $show;
			},
			20
		);
		add_filter(
			'talkwyn_admin_menu_title',
			static function ( $title ) {
				$name = trim( (string) \Talkwyn_Settings::get( 'pro_menu_name', '' ) );
				return '' !== $name ? $name : $title;
			}
		);
	}
}
