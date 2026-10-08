<?php
/**
 * Pro widget assets: streaming transport, product cards, proactive messages, away status.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Front end.
 */
final class Frontend {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'talkwyn_enqueue_widget', array( self::class, 'enqueue' ) );
		add_filter( 'talkwyn_widget_config', array( self::class, 'config' ) );
	}

	/**
	 * Enqueue.
	 */
	public static function enqueue(): void {
		wp_enqueue_style( 'talkwyn-pro', TALKWYN_PRO_URL . 'assets/css/pro.css', array( 'talkwyn-widget' ), TALKWYN_PRO_VERSION );
		wp_enqueue_script( 'talkwyn-pro', TALKWYN_PRO_URL . 'assets/js/pro.js', array( 'talkwyn-widget' ), TALKWYN_PRO_VERSION, true );
	}

	/**
	 * Config.
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function config( array $config ): array {
		$config['pro']           = $config['pro'] ?? array();
		$config['pro']['stream'] = Stream::enabled();
		return $config;
	}
}
