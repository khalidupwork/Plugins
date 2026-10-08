<?php
/**
 * WPML and Polylang string translation for the editable widget text.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and translates widget strings.
 */
class Talkwyn_I18n {

	const CONTEXT = 'Talkwyn';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_strings' ), 30 );
	}

	/**
	 * Settings that visitors read.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return apply_filters(
			'talkwyn_translatable_keys',
			array(
				'bot_name', 'welcome_message', 'placeholder', 'launcher_label', 'suggested_questions', 'fallback_message',
				'lead_prompt', 'lead_yes_label', 'lead_no_label', 'lead_decline_reply', 'lead_form_intro', 'lead_title',
				'lead_button', 'lead_success', 'lead_consent_text', 'handoff_label', 'privacy_notice', 'online_label',
				'expand_label', 'minimize_label', 'reset_label', 'sound_label', 'close_label', 'copy_label', 'copied_label',
				'helpful_label', 'not_helpful_label', 'typing_label', 'reset_confirm', 'sources_label', 'name_label',
				'email_label', 'phone_label', 'sending_label', 'success_title', 'reference_label', 'continue_chat_label',
				'generic_error', 'connection_error', 'lead_error',
			)
		);
	}

	/**
	 * Register strings with WPML String Translation and Polylang.
	 *
	 * @return void
	 */
	public static function register_strings() {
		$s = Talkwyn_Settings::all();
		foreach ( self::keys() as $key ) {
			if ( ! isset( $s[ $key ] ) || ! is_scalar( $s[ $key ] ) || '' === trim( (string) $s[ $key ] ) ) {
				continue;
			}
			$value = trim( (string) $s[ $key ] );
			do_action( 'wpml_register_single_string', self::CONTEXT, $key, $value );
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( 'talkwyn_' . $key, $value, self::CONTEXT, true );
			}
		}
	}

	/**
	 * Translated value of a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function get( $key ) {
		return self::translate( $key, (string) Talkwyn_Settings::get( $key, '' ) );
	}

	/**
	 * Translate a value for the current language.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Value.
	 * @return string
	 */
	public static function translate( $key, $value ) {
		$value = (string) apply_filters( 'wpml_translate_single_string', (string) $value, self::CONTEXT, $key );
		if ( function_exists( 'pll__' ) ) {
			$value = (string) pll__( $value );
		}
		return $value;
	}

	/**
	 * Current page language (WPML, Polylang or the site locale).
	 *
	 * @return string
	 */
	public static function page_language() {
		$lang = '';
		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
			$lang = (string) ICL_LANGUAGE_CODE;
		} elseif ( function_exists( 'pll_current_language' ) ) {
			$lang = (string) pll_current_language( 'locale' );
		}
		if ( '' === $lang ) {
			$lang = (string) determine_locale();
		}
		return sanitize_text_field( $lang );
	}

	/**
	 * Two or three letter language code.
	 *
	 * @param string $lang Locale or code.
	 * @return string
	 */
	public static function short_code( $lang ) {
		$lang = strtolower( str_replace( '_', '-', trim( (string) $lang ) ) );
		return preg_match( '/^[a-z]{2,3}/', $lang, $m ) ? $m[0] : '';
	}

	/**
	 * Right-to-left language codes.
	 *
	 * @param string $lang Code.
	 * @return bool
	 */
	public static function is_rtl_code( $lang ) {
		return in_array( self::short_code( $lang ), array( 'ar', 'ur', 'fa', 'he', 'ps', 'sd', 'ug', 'yi', 'dv', 'ckb' ), true );
	}
}
