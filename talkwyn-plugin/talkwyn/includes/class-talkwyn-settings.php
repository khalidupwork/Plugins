<?php
/**
 * Settings: defaults, reading and sanitizing.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings store (option `talkwyn_settings`).
 */
class Talkwyn_Settings {

	const OPTION = 'talkwyn_settings';

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$defaults = array(
			// Widget.
			'enabled'                  => 1,
			'bot_name'                 => 'Talkwyn',
			'welcome_message'          => 'Hi! Ask me anything about this website.',
			'placeholder'              => 'Type your message',
			'brand_color'              => '#D7263D',
			'position'                 => 'right',
			'launcher_label'           => 'Ask us',
			'launcher_icon'            => 'talkwyn',
			'avatar_type'              => 'talkwyn',
			'avatar_url'               => '',
			'full_screen_enabled'      => 1,
			'mobile_full_screen'       => 1,
			'show_minimize_button'     => 1,
			'show_reset_button'        => 1,
			'show_sound_button'        => 1,
			'sound_default'            => 0,
			'show_timestamps'          => 1,
			'show_message_tools'       => 1,
			'feedback_enabled'         => 1,
			'chat_width'               => 400,
			'chat_height'              => 640,
			'show_sources'             => 1,
			'suggested_questions'      => "What do you offer?\nHow can I contact you?",
			'hidden_page_ids'          => array(),
			'hidden_url_paths'         => '',
			'show_badge'               => 0,
			'badge_ref'                => '',
			'show_to'                  => 'all',
			'devices'                  => 'all',
			'widget_menu'              => 1,
			'transcript_enabled'       => 1,
			'language_menu'            => 1,

			// Answers.
			'system_prompt'            => 'You are a friendly website assistant for sales and support. Answer clearly and naturally. Use the supplied website knowledge only when it is relevant to the question, understand follow-up questions from the conversation, and ask a short clarifying question when needed. Never invent business information.',
			'fallback_message'         => 'I could not find that on this website. I can keep helping, or the team can follow up with you.',
			'max_history'              => 8,
			'max_context_chunks'       => 6,
			'multilingual_enabled'     => 1,

			// Providers.
			'provider_order'           => 'groq,openrouter,gemini,cloudflare',
			'groq_key'                 => '',
			'groq_model'               => 'qwen/qwen3.8-27b',
			'gemini_key'               => '',
			'gemini_model'             => 'gemini-3.8-flash',
			'openrouter_key'           => '',
			'openrouter_model'         => 'openrouter/free',
			'cloudflare_account_id'    => '',
			'cloudflare_token'         => '',
			'cloudflare_model'         => '@cf/google/gemma-4-26b-a4b-it',

			// Leads.
			'lead_enabled'             => 1,
			'lead_after_messages'      => 3,
			'lead_ask_first'           => 1,
			'smart_lead_intent'        => 1,
			'lead_prompt'              => 'Would you like the team to contact you about this?',
			'lead_yes_label'           => 'Yes, contact me',
			'lead_no_label'            => 'Not now',
			'lead_decline_reply'       => 'No problem. I can keep helping you here.',
			'lead_form_intro'          => 'Sure. Share your details below and the team will follow up.',
			'lead_require_name'        => 1,
			'lead_require_email'       => 1,
			'lead_require_phone'       => 0,
			'lead_title'               => 'Get a reply from the team',
			'lead_button'              => 'Send my details',
			'lead_success'             => 'Thanks! The team has your details and will follow up.',
			'lead_consent_enabled'     => 1,
			'lead_consent_text'        => 'I agree that my details can be used to reply to my request.',
			'lead_rate_limit_per_hour' => 5,
			'notification_email'       => '',
			'handoff_enabled'          => 0,
			'handoff_label'            => 'Talk to a person',
			'handoff_url'              => '',
			'turnstile_site_key'       => '',
			'turnstile_secret'         => '',

			// Knowledge.
			'auto_index_on_save'       => 1,
			'index_post_types'         => array( 'post', 'page', 'product' ),
			'index_custom_fields'      => 0,
			'custom_field_allowlist'   => '',

			// Privacy and protection.
			'logs_enabled'             => 1,
			'retention_days'           => 30,
			'rate_limit_per_hour'      => 60,
			'trusted_proxy'            => 0,
			'privacy_notice'           => 'Chats are stored to improve answers. Do not share sensitive information.',
			'delete_data_on_uninstall' => 0,

			// Interface text.
			'online_label'             => 'Online',
			'expand_label'             => 'Full screen',
			'minimize_label'           => 'Minimize',
			'reset_label'              => 'New chat',
			'sound_label'              => 'Sound',
			'close_label'              => 'Close chat',
			'copy_label'               => 'Copy',
			'copied_label'             => 'Copied',
			'helpful_label'            => 'Helpful',
			'not_helpful_label'        => 'Not helpful',
			'typing_label'             => '{bot} is typing',
			'reset_confirm'            => 'Start a new conversation?',
			'sources_label'            => 'Sources',
			'name_label'               => 'Name',
			'email_label'              => 'Email',
			'phone_label'              => 'Phone',
			'sending_label'            => 'Sending',
			'success_title'            => 'Lead saved',
			'reference_label'          => 'Reference #',
			'continue_chat_label'      => 'You can keep chatting below.',
			'generic_error'            => 'Sorry, something went wrong.',
			'connection_error'         => 'Sorry, I could not connect right now.',
			'lead_error'               => 'Please check your details and try again.',
		);

		/**
		 * Filters the default settings. Add-ons add their own keys here.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'talkwyn_settings_defaults', $defaults );
	}

	/**
	 * Field types used for sanitizing.
	 *
	 * @return array<string, string[]>
	 */
	public static function schema() {
		$schema = array(
			'bool'     => array( 'enabled', 'full_screen_enabled', 'mobile_full_screen', 'show_minimize_button', 'show_reset_button', 'show_sound_button', 'sound_default', 'show_timestamps', 'show_message_tools', 'feedback_enabled', 'show_sources', 'show_badge', 'widget_menu', 'transcript_enabled', 'language_menu', 'multilingual_enabled', 'lead_enabled', 'lead_ask_first', 'smart_lead_intent', 'lead_require_name', 'lead_require_email', 'lead_require_phone', 'lead_consent_enabled', 'handoff_enabled', 'auto_index_on_save', 'index_custom_fields', 'logs_enabled', 'trusted_proxy', 'delete_data_on_uninstall' ),
			'int'      => array( 'chat_width', 'chat_height', 'max_history', 'max_context_chunks', 'lead_after_messages', 'lead_rate_limit_per_hour', 'retention_days', 'rate_limit_per_hour' ),
			'textarea' => array( 'welcome_message', 'suggested_questions', 'hidden_url_paths', 'system_prompt', 'fallback_message', 'lead_success', 'privacy_notice', 'custom_field_allowlist', 'lead_consent_text', 'lead_decline_reply', 'lead_form_intro' ),
			'email'    => array( 'notification_email' ),
			'url'      => array( 'avatar_url', 'handoff_url' ),
			'color'    => array( 'brand_color' ),
			'key'      => array( 'groq_key', 'gemini_key', 'openrouter_key', 'cloudflare_token', 'cloudflare_account_id', 'turnstile_secret', 'turnstile_site_key' ),
			'array'    => array( 'index_post_types', 'hidden_page_ids' ),
		);

		/**
		 * Filters the settings schema. Add-ons register their keys by type.
		 *
		 * @param array $schema Keys grouped by type.
		 */
		return apply_filters( 'talkwyn_settings_schema', $schema );
	}

	/**
	 * Integer ranges.
	 *
	 * @return array<string, int[]>
	 */
	public static function ranges() {
		return array(
			'chat_width'               => array( 320, 720 ),
			'chat_height'              => array( 480, 900 ),
			'max_history'              => array( 0, 20 ),
			'max_context_chunks'       => array( 2, 12 ),
			'lead_after_messages'      => array( 1, 20 ),
			'lead_rate_limit_per_hour' => array( 1, 100 ),
			'retention_days'           => array( 1, 3650 ),
			'rate_limit_per_hour'      => array( 5, 2000 ),
		);
	}

	/**
	 * Allowed values for select fields.
	 *
	 * @return array<string, string[]>
	 */
	public static function enums() {
		return array(
			'position'      => array( 'right', 'left' ),
			'launcher_icon' => array( 'talkwyn', 'spark_chat', 'chat_dots', 'headset', 'question' ),
			'avatar_type'   => array( 'talkwyn', 'initials', 'headset', 'chat', 'custom' ),
			'show_to'       => array( 'all', 'logged_in', 'logged_out' ),
			'devices'       => array( 'all', 'desktop', 'mobile' ),
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
			if ( '' === self::$cache['notification_email'] ) {
				self::$cache['notification_email'] = (string) get_option( 'admin_email' );
			}
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Save settings (merged into the stored ones).
	 *
	 * @param array $values Already sanitized values.
	 * @return void
	 */
	public static function update( array $values ) {
		$saved = get_option( self::OPTION, array() );
		$saved = array_merge( is_array( $saved ) ? $saved : array(), $values );
		update_option( self::OPTION, $saved, false );
		self::flush();
	}

	/**
	 * Clear the per-request cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize submitted values. Only keys present in $input (or listed as
	 * checkboxes on the submitted form) are returned.
	 *
	 * @param array    $input      Raw input (unslashed).
	 * @param string[] $form_bools Checkbox keys that were on the submitted form.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input, array $form_bools = array() ) {
		$schema = self::schema();
		$ranges = self::ranges();
		$enums  = self::enums();
		$known  = self::defaults();
		$out    = array();

		foreach ( $schema['bool'] as $key ) {
			if ( in_array( $key, $form_bools, true ) || isset( $input[ $key ] ) ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}
		foreach ( $input as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $known ) || in_array( $key, $schema['bool'], true ) ) {
				continue;
			}
			// Structured values are handled by the `talkwyn_sanitize_settings` filter.
			if ( is_array( $value ) && ! in_array( $key, $schema['array'], true ) ) {
				continue;
			}
			if ( in_array( $key, $schema['int'], true ) ) {
				$value = absint( $value );
				if ( isset( $ranges[ $key ] ) ) {
					$value = min( $ranges[ $key ][1], max( $ranges[ $key ][0], $value ) );
				}
				$out[ $key ] = $value;
			} elseif ( in_array( $key, $schema['textarea'], true ) ) {
				$out[ $key ] = sanitize_textarea_field( (string) $value );
			} elseif ( in_array( $key, $schema['email'], true ) ) {
				$out[ $key ] = sanitize_email( (string) $value );
			} elseif ( in_array( $key, $schema['url'], true ) ) {
				$out[ $key ] = esc_url_raw( (string) $value );
			} elseif ( in_array( $key, $schema['color'], true ) ) {
				$color       = sanitize_hex_color( (string) $value );
				$out[ $key ] = $color ? $color : $known[ $key ];
			} elseif ( in_array( $key, $schema['key'], true ) ) {
				$out[ $key ] = trim( sanitize_text_field( (string) $value ) );
			} elseif ( in_array( $key, $schema['array'], true ) ) {
				$list        = array_filter( array_map( 'sanitize_key', (array) $value ) );
				$out[ $key ] = 'hidden_page_ids' === $key ? array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) ) : array_values( array_unique( $list ) );
			} elseif ( isset( $enums[ $key ] ) ) {
				$value = sanitize_key( (string) $value );
				if ( in_array( $value, $enums[ $key ], true ) ) {
					$out[ $key ] = $value;
				}
			} elseif ( 'provider_order' === $key ) {
				$ids         = array_keys( Talkwyn_Providers::registry() );
				$parts       = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', strtolower( (string) $value ) ) ) ) );
				$parts       = array_values( array_unique( array_intersect( $parts, $ids ) ) );
				$out[ $key ] = $parts ? implode( ',', $parts ) : $known[ $key ];
			} else {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		/**
		 * Filters sanitized settings before they are saved.
		 *
		 * @param array $out   Sanitized values.
		 * @param array $input Raw input.
		 */
		return apply_filters( 'talkwyn_sanitize_settings', $out, $input );
	}
}
