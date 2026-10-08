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
			'talkwyn_show_badge',
			static function ( $show ) {
				return \Talkwyn_Settings::get( 'pro_hide_powered', 1 ) ? false : $show;
			},
			20
		);
		add_filter(
			'talkwyn_admin_logo',
			static function ( $url ) {
				$logo = trim( (string) \Talkwyn_Settings::get( 'pro_brand_logo', '' ) );
				return '' !== $logo ? $logo : $url;
			}
		);
		add_filter( 'talkwyn_widget_menu', array( self::class, 'menu' ) );
		add_filter(
			'talkwyn_admin_menu_title',
			static function ( $title ) {
				$name = trim( (string) \Talkwyn_Settings::get( 'pro_menu_name', '' ) );
				return '' !== $name ? $name : $title;
			}
		);
	}

	/**
	 * Add your own link to the chat menu (for example "Website by Your Agency").
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu( $items ) {
		$label = trim( (string) \Talkwyn_Settings::get( 'pro_menu_item_label', '' ) );
		$url   = trim( (string) \Talkwyn_Settings::get( 'pro_menu_item_url', '' ) );
		if ( '' === $label || '' === $url ) {
			return $items;
		}
		$items   = array_values(
			array_filter(
				(array) $items,
				static function ( $item ) {
					return 'add_chat' !== ( $item['id'] ?? '' );
				}
			)
		);
		$items[] = array(
			'id'    => 'brand_link',
			'label' => $label,
			'url'   => esc_url_raw( $url ),
		);
		return $items;
	}
}
