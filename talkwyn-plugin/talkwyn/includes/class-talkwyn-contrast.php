<?php
/**
 * Smart Contrast: picks readable text for the owner's colour (WCAG 2 math).
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Colour contrast helpers. Pure functions, unit tested.
 */
class Talkwyn_Contrast {

	const INK   = '#1A0F12';
	const WHITE = '#FFFFFF';

	/**
	 * Hex colour to RGB.
	 *
	 * @param string $hex #RGB or #RRGGBB.
	 * @return int[]|null
	 */
	public static function rgb( $hex ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return null;
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Relative luminance (0 to 1).
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$rgb = self::rgb( $hex );
		if ( null === $rgb ) {
			return 0.0;
		}
		$channels = array();
		foreach ( $rgb as $value ) {
			$c          = $value / 255;
			$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Contrast ratio between two colours (1 to 21).
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	public static function ratio( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Best text colour on a background: Ink or white.
	 *
	 * @param string $background Background colour.
	 * @return string
	 */
	public static function text_on( $background ) {
		return self::ratio( $background, self::WHITE ) >= self::ratio( $background, self::INK ) ? self::WHITE : self::INK;
	}

	/**
	 * Whether text on this colour meets WCAG AA for normal text (4.5:1).
	 *
	 * @param string $background Background colour.
	 * @return bool
	 */
	public static function is_readable( $background ) {
		return self::ratio( $background, self::text_on( $background ) ) >= 4.5;
	}
}
