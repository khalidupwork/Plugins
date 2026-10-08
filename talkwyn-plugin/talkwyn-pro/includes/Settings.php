<?php
/**
 * Pro settings, stored with the free settings (option talkwyn_settings).
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Pro settings registration.
 */
final class Settings {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_settings_defaults', array( self::class, 'defaults' ) );
		add_filter( 'talkwyn_settings_schema', array( self::class, 'schema' ) );
		add_filter( 'talkwyn_sanitize_settings', array( self::class, 'sanitize' ), 10, 2 );
		\Talkwyn_Settings::flush();
	}

	/**
	 * Pro defaults.
	 *
	 * @param array $d Defaults.
	 * @return array
	 */
	public static function defaults( array $d ): array {
		return array_merge(
			$d,
			array(
				'openai_key'          => '',
				'openai_model'        => '',
				'anthropic_key'       => '',
				'anthropic_model'     => '',
				'mistral_key'         => '',
				'mistral_model'       => '',
				'deepseek_key'        => '',
				'deepseek_model'      => '',
				'pro_embed_provider'  => '',
				'pro_embed_model'     => '',
				'pro_semantic_weight' => 60,
				'pro_stream'          => 1,
				'pro_woo_cards'       => 1,
				'pro_woo_orders'      => 1,
				'pro_slack_webhook'   => '',
				'pro_telegram_token'  => '',
				'pro_telegram_chat'   => '',
				'pro_proactive'       => array(),
				'pro_hours_enabled'   => 0,
				'pro_hours'           => self::default_hours(),
				'pro_away_label'      => 'Away. Leave a message.',
				'pro_away_message'    => 'We are away right now. Leave your details and the team will reply when we are back.',
				'pro_away_lead_only'  => 1,
				'pro_hide_powered'    => 1,
				'pro_menu_name'       => '',
				'pro_brand_logo'      => '',
				'pro_menu_item_label' => '',
				'pro_menu_item_url'   => '',
			)
		);
	}

	/**
	 * Monday to Sunday, 09:00 to 17:00 on weekdays.
	 *
	 * @return array
	 */
	public static function default_hours(): array {
		$out = array();
		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) as $day ) {
			$out[ $day ] = array(
				'open'  => in_array( $day, array( 'sat', 'sun' ), true ) ? 0 : 1,
				'from'  => '09:00',
				'until' => '17:00',
			);
		}
		return $out;
	}

	/**
	 * Schema additions.
	 *
	 * @param array $schema Schema.
	 * @return array
	 */
	public static function schema( array $schema ): array {
		$schema['bool']     = array_merge( $schema['bool'], array( 'pro_stream', 'pro_woo_cards', 'pro_woo_orders', 'pro_hours_enabled', 'pro_away_lead_only', 'pro_hide_powered' ) );
		$schema['int']      = array_merge( $schema['int'], array( 'pro_semantic_weight' ) );
		$schema['key']      = array_merge( $schema['key'], array( 'openai_key', 'anthropic_key', 'mistral_key', 'deepseek_key', 'pro_telegram_token', 'pro_telegram_chat' ) );
		$schema['url']      = array_merge( $schema['url'], array( 'pro_slack_webhook', 'pro_brand_logo', 'pro_menu_item_url' ) );
		$schema['textarea'] = array_merge( $schema['textarea'], array( 'pro_away_message' ) );
		return $schema;
	}

	/**
	 * Sanitize structured Pro settings.
	 *
	 * @param array $out   Sanitized values.
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( array $out, array $input ): array {
		if ( isset( $out['pro_semantic_weight'] ) ) {
			$out['pro_semantic_weight'] = min( 100, max( 0, (int) $out['pro_semantic_weight'] ) );
		}
		if ( isset( $input['pro_embed_provider'] ) ) {
			$v                         = sanitize_key( (string) $input['pro_embed_provider'] );
			$out['pro_embed_provider'] = in_array( $v, array( '', 'openai', 'mistral', 'gemini' ), true ) ? $v : '';
		}
		if ( isset( $out['pro_slack_webhook'] ) && '' !== $out['pro_slack_webhook'] && 0 !== strpos( $out['pro_slack_webhook'], 'https://hooks.slack.com/' ) ) {
			$out['pro_slack_webhook'] = '';
		}
		if ( isset( $input['pro_proactive'] ) && is_array( $input['pro_proactive'] ) ) {
			$rules = array();
			foreach ( $input['pro_proactive'] as $rule ) {
				if ( ! is_array( $rule ) || '' === trim( (string) ( $rule['message'] ?? '' ) ) ) {
					continue;
				}
				$trigger = sanitize_key( (string) ( $rule['trigger'] ?? 'time' ) );
				$rules[] = array(
					'message' => sanitize_textarea_field( (string) $rule['message'] ),
					'trigger' => in_array( $trigger, array( 'time', 'scroll', 'exit' ), true ) ? $trigger : 'time',
					'value'   => min( 600, max( 0, absint( $rule['value'] ?? 0 ) ) ),
					'url'     => sanitize_text_field( (string) ( $rule['url'] ?? '' ) ),
					'open'    => empty( $rule['open'] ) ? 0 : 1,
				);
				if ( count( $rules ) >= 10 ) {
					break;
				}
			}
			$out['pro_proactive'] = $rules;
		}
		if ( isset( $input['pro_hours'] ) && is_array( $input['pro_hours'] ) ) {
			$hours = array();
			foreach ( self::default_hours() as $day => $def ) {
				$row           = (array) ( $input['pro_hours'][ $day ] ?? array() );
				$hours[ $day ] = array(
					'open'  => empty( $row['open'] ) ? 0 : 1,
					'from'  => self::time( (string) ( $row['from'] ?? $def['from'] ), $def['from'] ),
					'until' => self::time( (string) ( $row['until'] ?? $def['until'] ), $def['until'] ),
				);
			}
			$out['pro_hours'] = $hours;
		}
		return $out;
	}

	/**
	 * HH:MM.
	 *
	 * @param string $value Value.
	 * @param string $fallback Fallback.
	 */
	private static function time( string $value, string $fallback ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}
}
