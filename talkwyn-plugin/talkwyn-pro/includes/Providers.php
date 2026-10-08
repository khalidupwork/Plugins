<?php
/**
 * Paid AI providers: OpenAI, Anthropic Claude, Mistral, DeepSeek.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers providers with the free plugin.
 */
final class Providers {

	/**
	 * OpenAI-compatible endpoints.
	 *
	 * @var array<string, array{chat:string,models:string}>
	 */
	const OPENAI_COMPATIBLE = array(
		'openai'   => array(
			'chat'   => 'https://api.openai.com/v1/chat/completions',
			'models' => 'https://api.openai.com/v1/models',
		),
		'mistral'  => array(
			'chat'   => 'https://api.mistral.ai/v1/chat/completions',
			'models' => 'https://api.mistral.ai/v1/models',
		),
		'deepseek' => array(
			'chat'   => 'https://api.deepseek.com/chat/completions',
			'models' => 'https://api.deepseek.com/models',
		),
		'groq'       => array(
			'chat'   => 'https://api.groq.com/openai/v1/chat/completions',
			'models' => '',
		),
		'openrouter' => array(
			'chat'   => 'https://openrouter.ai/api/v1/chat/completions',
			'models' => '',
		),
	);

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'talkwyn_providers', array( self::class, 'register' ) );
	}

	/**
	 * Add paid providers.
	 *
	 * @param array $providers Providers.
	 * @return array
	 */
	public static function register( array $providers ): array {
		$paid = array(
			'openai'    => array( 'OpenAI', 'https://platform.openai.com/api-keys' ),
			'anthropic' => array( 'Anthropic Claude', 'https://console.anthropic.com/settings/keys' ),
			'mistral'   => array( 'Mistral', 'https://console.mistral.ai/api-keys' ),
			'deepseek'  => array( 'DeepSeek', 'https://platform.deepseek.com/api_keys' ),
		);
		foreach ( $paid as $id => $info ) {
			$providers[ $id ] = array(
				'label'       => $info[0],
				'fields'      => array( $id . '_key', $id . '_model' ),
				'key_field'   => $id . '_key',
				'model_field' => $id . '_model',
				'signup'      => $info[1],
				'free'        => false,
				'ready'       => static function ( $s ) use ( $id ) {
					return ! empty( $s[ $id . '_key' ] ) && ! empty( $s[ $id . '_model' ] );
				},
				'call'        => static function ( $messages, $s ) use ( $id ) {
					return self::call( $id, $messages, $s );
				},
				'models'      => static function ( $s ) use ( $id ) {
					return self::models( $id, $s );
				},
			);
		}
		return $providers;
	}

	/**
	 * Call a paid provider.
	 *
	 * @param string $id       Provider.
	 * @param array  $messages Messages.
	 * @param array  $s        Settings.
	 * @return string
	 * @throws \Exception On failure.
	 */
	public static function call( string $id, array $messages, array $s ): string {
		if ( 'anthropic' === $id ) {
			return self::anthropic( $messages, $s );
		}
		if ( 'openai' === $id ) {
			// Current OpenAI models take max_completion_tokens and their default temperature.
			$res  = wp_remote_post(
				self::OPENAI_COMPATIBLE['openai']['chat'],
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . trim( (string) $s['openai_key'] ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'                 => trim( (string) $s['openai_model'] ),
							'messages'              => $messages,
							'max_completion_tokens' => 700,
						)
					),
				)
			);
			$data = \Talkwyn_Providers::json( $res );
			return trim( (string) ( $data['choices'][0]['message']['content'] ?? '' ) );
		}
		return \Talkwyn_Providers::openai_compatible( self::OPENAI_COMPATIBLE[ $id ]['chat'], (string) $s[ $id . '_key' ], (string) $s[ $id . '_model' ], $messages );
	}

	/**
	 * Anthropic Messages API body.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @param bool  $stream   Stream.
	 * @return array
	 */
	public static function anthropic_body( array $messages, array $s, bool $stream = false ): array {
		$system = '';
		$turns  = array();
		foreach ( $messages as $m ) {
			if ( 'system' === $m['role'] ) {
				$system .= $m['content'] . "\n";
				continue;
			}
			$role = 'assistant' === $m['role'] ? 'assistant' : 'user';
			// The API needs alternating turns; merge repeats.
			if ( $turns && end( $turns )['role'] === $role ) {
				$turns[ count( $turns ) - 1 ]['content'] .= "\n\n" . $m['content'];
			} else {
				$turns[] = array(
					'role'    => $role,
					'content' => (string) $m['content'],
				);
			}
		}
		if ( $turns && 'user' !== $turns[0]['role'] ) {
			array_unshift(
				$turns,
				array(
					'role'    => 'user',
					'content' => '(conversation start)',
				)
			);
		}
		$body = array(
			'model'      => trim( (string) $s['anthropic_model'] ),
			'max_tokens' => 700,
			'system'     => trim( $system ),
			'messages'   => $turns,
		);
		if ( $stream ) {
			$body['stream'] = true;
		}
		return $body;
	}

	/**
	 * Anthropic Messages API.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return string
	 * @throws \Exception On failure.
	 */
	private static function anthropic( array $messages, array $s ): string {
		$res  = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 20,
				'headers' => self::anthropic_headers( (string) $s['anthropic_key'] ),
				'body'    => wp_json_encode( self::anthropic_body( $messages, $s ) ),
			)
		);
		$data = \Talkwyn_Providers::json( $res );
		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) $block['text'];
			}
		}
		return trim( $text );
	}

	/**
	 * Anthropic headers.
	 *
	 * @param string $key API key.
	 * @return array
	 */
	public static function anthropic_headers( string $key ): array {
		return array(
			'x-api-key'         => trim( $key ),
			'anthropic-version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		);
	}

	/**
	 * Model lists.
	 *
	 * @param string $id Provider.
	 * @param array  $s  Settings.
	 * @return string[]
	 */
	public static function models( string $id, array $s ): array {
		$key = trim( (string) ( $s[ $id . '_key' ] ?? '' ) );
		if ( '' === $key ) {
			return array();
		}
		if ( 'anthropic' === $id ) {
			$res = wp_remote_get(
				'https://api.anthropic.com/v1/models?limit=100',
				array(
					'timeout' => 12,
					'headers' => self::anthropic_headers( $key ),
				)
			);
		} else {
			$res = wp_remote_get(
				self::OPENAI_COMPATIBLE[ $id ]['models'],
				array(
					'timeout' => 12,
					'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				)
			);
		}
		try {
			$data = \Talkwyn_Providers::json( $res );
		} catch ( \Exception $e ) {
			return array();
		}
		$out = array();
		foreach ( (array) ( $data['data'] ?? array() ) as $m ) {
			$model = (string) ( $m['id'] ?? '' );
			if ( '' === $model ) {
				continue;
			}
			if ( 'openai' === $id && ! preg_match( '/^(gpt|o\d|chatgpt)/i', $model ) ) {
				continue;
			}
			if ( preg_match( '/embed|whisper|tts|dall-e|image|audio|realtime|moderation|transcribe|search/i', $model ) ) {
				continue;
			}
			$out[] = $model;
		}
		return $out;
	}
}
