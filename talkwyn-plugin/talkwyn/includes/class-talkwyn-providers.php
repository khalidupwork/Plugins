<?php
/**
 * AI providers with automatic fallback.
 *
 * Free providers: Groq, OpenRouter, Google Gemini, Cloudflare Workers AI.
 * Add-ons register more through the `talkwyn_providers` filter.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider registry and calls.
 */
class Talkwyn_Providers {

	const LAST_GOOD  = 'talkwyn_last_good_provider';
	const TIME_LIMIT = 22;

	/**
	 * Registered providers.
	 *
	 * Each entry: label, fields (setting keys), key_field, model_field, signup (URL),
	 * free (bool), ready (callable $settings => bool), call (callable $messages, $settings => string),
	 * models (callable $settings => string[]).
	 *
	 * @return array<string, array>
	 */
	public static function registry() {
		$providers = array(
			'groq'       => array(
				'label'       => 'Groq',
				'fields'      => array( 'groq_key', 'groq_model' ),
				'key_field'   => 'groq_key',
				'model_field' => 'groq_model',
				'signup'      => 'https://console.groq.com/keys',
				'free'        => true,
				'ready'       => static function ( $s ) {
					return ! empty( $s['groq_key'] );
				},
				'call'        => array( __CLASS__, 'call_groq' ),
				'models'      => array( __CLASS__, 'models_groq' ),
			),
			'openrouter' => array(
				'label'       => 'OpenRouter',
				'fields'      => array( 'openrouter_key', 'openrouter_model' ),
				'key_field'   => 'openrouter_key',
				'model_field' => 'openrouter_model',
				'signup'      => 'https://openrouter.ai/settings/keys',
				'free'        => true,
				'ready'       => static function ( $s ) {
					return ! empty( $s['openrouter_key'] );
				},
				'call'        => array( __CLASS__, 'call_openrouter' ),
				'models'      => array( __CLASS__, 'models_openrouter' ),
			),
			'gemini'     => array(
				'label'       => 'Google Gemini',
				'fields'      => array( 'gemini_key', 'gemini_model' ),
				'key_field'   => 'gemini_key',
				'model_field' => 'gemini_model',
				'signup'      => 'https://aistudio.google.com/app/apikey',
				'free'        => true,
				'ready'       => static function ( $s ) {
					return ! empty( $s['gemini_key'] );
				},
				'call'        => array( __CLASS__, 'call_gemini' ),
				'models'      => array( __CLASS__, 'models_gemini' ),
			),
			'cloudflare' => array(
				'label'       => 'Cloudflare Workers AI',
				'fields'      => array( 'cloudflare_account_id', 'cloudflare_token', 'cloudflare_model' ),
				'key_field'   => 'cloudflare_token',
				'model_field' => 'cloudflare_model',
				'signup'      => 'https://dash.cloudflare.com/?to=/:account/ai/workers-ai',
				'free'        => true,
				'ready'       => static function ( $s ) {
					return ! empty( $s['cloudflare_token'] ) && ! empty( $s['cloudflare_account_id'] );
				},
				'call'        => array( __CLASS__, 'call_cloudflare' ),
				'models'      => array( __CLASS__, 'models_cloudflare' ),
			),
		);

		/**
		 * Filters the AI providers.
		 *
		 * @param array $providers Providers keyed by id.
		 */
		return (array) apply_filters( 'talkwyn_providers', $providers );
	}

	/**
	 * Providers in fallback order that have credentials.
	 *
	 * @param array      $s        Settings.
	 * @param array|null $registry Registry (for tests).
	 * @return string[]
	 */
	public static function order( array $s, $registry = null ) {
		$registry = null === $registry ? self::registry() : $registry;
		$order    = array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $s['provider_order'] ?? '' ) ) ) ) );
		// Providers that are configured but missing from the order are tried last.
		$order = array_values( array_unique( array_merge( $order, array_keys( $registry ) ) ) );
		return array_values(
			array_filter(
				$order,
				static function ( $id ) use ( $registry, $s ) {
					return isset( $registry[ $id ] ) && is_callable( $registry[ $id ]['ready'] ) && call_user_func( $registry[ $id ]['ready'], $s );
				}
			)
		);
	}

	/**
	 * Generate a reply, trying providers in order. The provider that worked last
	 * is tried first, so a broken one does not slow every message.
	 *
	 * @param array      $messages Chat messages (role, content).
	 * @param array|null $s        Settings.
	 * @param array|null $registry Registry (for tests).
	 * @return array{text:string,provider:string,error:string,ms:int}
	 */
	public static function generate( array $messages, $s = null, $registry = null ) {
		$s        = null === $s ? Talkwyn_Settings::all() : $s;
		$registry = null === $registry ? self::registry() : $registry;
		$order    = self::order( $s, $registry );
		$last     = sanitize_key( (string) get_transient( self::LAST_GOOD ) );
		if ( $last && in_array( $last, $order, true ) ) {
			$order = array_values( array_unique( array_merge( array( $last ), $order ) ) );
		}

		$errors  = array();
		$started = microtime( true );
		foreach ( $order as $id ) {
			if ( ( microtime( true ) - $started ) > self::TIME_LIMIT ) {
				$errors[] = 'Time limit reached before trying ' . $id . '.';
				break;
			}
			try {
				$text = trim( (string) call_user_func( $registry[ $id ]['call'], $messages, $s ) );
				if ( '' !== $text ) {
					if ( $last !== $id ) {
						set_transient( self::LAST_GOOD, $id, 6 * HOUR_IN_SECONDS );
					}
					return array(
						'text'     => $text,
						'provider' => $id,
						'error'    => implode( ' | ', $errors ),
						'ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
					);
				}
				$errors[] = $id . ': empty reply';
			} catch ( Exception $e ) {
				$errors[] = $id . ': ' . $e->getMessage();
			}
			if ( $last === $id ) {
				delete_transient( self::LAST_GOOD );
			}
		}
		if ( ! $order ) {
			$errors[] = 'No AI provider has an API key yet.';
		}
		return array(
			'text'     => '',
			'provider' => '',
			'error'    => implode( ' | ', $errors ),
			'ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
		);
	}

	/**
	 * Test one provider.
	 *
	 * @param string $id        Provider ID.
	 * @param array  $overrides Unsaved field values.
	 * @return array{success:bool,message:string}
	 */
	public static function test( $id, array $overrides = array() ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $id ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unknown provider.', 'talkwyn' ),
			);
		}
		$s        = array_merge( Talkwyn_Settings::all(), $overrides );
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'This is a connection test. Reply with OK only.',
			),
			array(
				'role'    => 'user',
				'content' => 'Connection test',
			),
		);
		try {
			$text = trim( (string) call_user_func( $registry[ $id ]['call'], $messages, $s ) );
			if ( '' === $text ) {
				return array(
					'success' => false,
					'message' => __( 'No reply came back. Check the key, model, quota and account access.', 'talkwyn' ),
				);
			}
			set_transient( self::LAST_GOOD, $id, 6 * HOUR_IN_SECONDS );
			return array(
				'success' => true,
				/* translators: %s: reply from the AI provider */
				'message' => sprintf( __( 'Connected. Reply: %s', 'talkwyn' ), wp_trim_words( wp_strip_all_tags( $text ), 12 ) ),
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Model list for a provider, cached for 12 hours.
	 *
	 * @param string $id        Provider ID.
	 * @param array  $overrides Unsaved field values.
	 * @param bool   $refresh   Skip the cache.
	 * @return string[]
	 */
	public static function models( $id, array $overrides = array(), $refresh = false ) {
		$registry = self::registry();
		if ( empty( $registry[ $id ]['models'] ) ) {
			return array();
		}
		$s     = array_merge( Talkwyn_Settings::all(), $overrides );
		$key   = 'talkwyn_models_' . $id . '_' . md5( (string) ( $s[ $registry[ $id ]['key_field'] ] ?? '' ) );
		$cache = $refresh ? false : get_transient( $key );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$list = array_values( array_unique( array_filter( array_map( 'strval', (array) call_user_func( $registry[ $id ]['models'], $s ) ) ) ) );
		sort( $list, SORT_NATURAL | SORT_FLAG_CASE );
		set_transient( $key, $list, 12 * HOUR_IN_SECONDS );
		return $list;
	}

	/**
	 * Groq.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return string
	 * @throws Exception On failure.
	 */
	public static function call_groq( array $messages, array $s ) {
		$extra = array();
		if ( 0 === strpos( (string) $s['groq_model'], 'qwen/qwen3' ) ) {
			$extra = array(
				'reasoning_effort'      => 'none',
				'max_completion_tokens' => 600,
			);
		}
		return self::openai_compatible( 'https://api.groq.com/openai/v1/chat/completions', $s['groq_key'], $s['groq_model'], $messages, array(), $extra );
	}

	/**
	 * OpenRouter.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return string
	 * @throws Exception On failure.
	 */
	public static function call_openrouter( array $messages, array $s ) {
		$headers = array(
			'HTTP-Referer' => home_url( '/' ),
			'X-Title'      => wp_strip_all_tags( get_bloginfo( 'name' ) ),
		);
		return self::openai_compatible( 'https://openrouter.ai/api/v1/chat/completions', $s['openrouter_key'], $s['openrouter_model'], $messages, $headers );
	}

	/**
	 * Google Gemini. The key goes in the x-goog-api-key header, never the URL.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return string
	 * @throws Exception On failure.
	 */
	public static function call_gemini( array $messages, array $s ) {
		$model = trim( (string) $s['gemini_model'] );
		if ( '' === $model ) {
			throw new Exception( 'Model is empty.' );
		}
		$system   = '';
		$contents = array();
		foreach ( $messages as $m ) {
			if ( 'system' === $m['role'] ) {
				$system .= $m['content'] . "\n";
				continue;
			}
			$contents[] = array(
				'role'  => 'assistant' === $m['role'] ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			);
		}
		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'temperature'     => 0.35,
				'maxOutputTokens' => 600,
			),
		);
		if ( '' !== $system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => trim( $system ) ) ) );
		}
		$res  = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( preg_replace( '#^models/#', '', $model ) ) . ':generateContent',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => trim( (string) $s['gemini_key'] ),
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$data = self::json( $res );
		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}
		return trim( $text );
	}

	/**
	 * Cloudflare Workers AI.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return string
	 * @throws Exception On failure.
	 */
	public static function call_cloudflare( array $messages, array $s ) {
		$model = trim( (string) $s['cloudflare_model'] );
		if ( '' === $model ) {
			throw new Exception( 'Model is empty.' );
		}
		$url  = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( trim( (string) $s['cloudflare_account_id'] ) ) . '/ai/run/' . str_replace( array( '%2F', '%40' ), array( '/', '@' ), rawurlencode( $model ) );
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( (string) $s['cloudflare_token'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'messages'    => $messages,
						'max_tokens'  => 600,
						'temperature' => 0.35,
					)
				),
			)
		);
		$data = self::json( $res );
		if ( isset( $data['result']['response'] ) ) {
			return is_string( $data['result']['response'] ) ? trim( $data['result']['response'] ) : trim( (string) wp_json_encode( $data['result']['response'] ) );
		}
		if ( isset( $data['result']['choices'][0]['message']['content'] ) ) {
			return trim( (string) $data['result']['choices'][0]['message']['content'] );
		}
		return '';
	}

	/**
	 * OpenAI-compatible chat completion. Public so add-ons can reuse it.
	 *
	 * @param string $url     Endpoint.
	 * @param string $key     API key.
	 * @param string $model   Model.
	 * @param array  $messages Messages.
	 * @param array  $headers Extra headers.
	 * @param array  $extra   Extra body fields.
	 * @return string
	 * @throws Exception On failure.
	 */
	public static function openai_compatible( $url, $key, $model, array $messages, array $headers = array(), array $extra = array() ) {
		if ( '' === trim( (string) $model ) ) {
			throw new Exception( 'Model is empty.' );
		}
		$body = array_merge(
			array(
				'model'       => trim( (string) $model ),
				'messages'    => $messages,
				'temperature' => 0.35,
				'max_tokens'  => 600,
			),
			$extra
		);
		if ( isset( $body['max_completion_tokens'] ) ) {
			unset( $body['max_tokens'] );
		}
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array_merge(
					array(
						'Authorization' => 'Bearer ' . trim( (string) $key ),
						'Content-Type'  => 'application/json',
					),
					$headers
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$data = self::json( $res );
		$text = (string) ( $data['choices'][0]['message']['content'] ?? '' );
		// Some reasoning models wrap thoughts in <think> tags.
		return trim( (string) preg_replace( '#<think>.*?</think>#is', '', $text ) );
	}

	/**
	 * Groq models.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function models_groq( array $s ) {
		$data = self::get_json( 'https://api.groq.com/openai/v1/models', array( 'Authorization' => 'Bearer ' . trim( (string) $s['groq_key'] ) ) );
		$out  = array();
		foreach ( (array) ( $data['data'] ?? array() ) as $m ) {
			$id = (string) ( $m['id'] ?? '' );
			if ( '' !== $id && ! preg_match( '/whisper|tts|guard|embed|distil/i', $id ) && ( ! isset( $m['active'] ) || $m['active'] ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * OpenRouter free models (plus the openrouter/free router).
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function models_openrouter( array $s ) {
		$data = self::get_json( 'https://openrouter.ai/api/v1/models', array( 'Authorization' => 'Bearer ' . trim( (string) $s['openrouter_key'] ) ) );
		$out  = array( 'openrouter/free' );
		foreach ( (array) ( $data['data'] ?? array() ) as $m ) {
			$id   = (string) ( $m['id'] ?? '' );
			$free = ':free' === substr( $id, -5 ) || ( isset( $m['pricing']['prompt'] ) && 0.0 === (float) $m['pricing']['prompt'] && 0.0 === (float) ( $m['pricing']['completion'] ?? 1 ) );
			if ( '' !== $id && $free ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Gemini models that can generate content.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function models_gemini( array $s ) {
		$data = self::get_json( 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200', array( 'x-goog-api-key' => trim( (string) $s['gemini_key'] ) ) );
		$out  = array();
		foreach ( (array) ( $data['models'] ?? array() ) as $m ) {
			if ( in_array( 'generateContent', (array) ( $m['supportedGenerationMethods'] ?? array() ), true ) ) {
				$out[] = preg_replace( '#^models/#', '', (string) $m['name'] );
			}
		}
		return $out;
	}

	/**
	 * Cloudflare text generation models.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function models_cloudflare( array $s ) {
		$url  = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( trim( (string) $s['cloudflare_account_id'] ) ) . '/ai/models/search?task=' . rawurlencode( 'Text Generation' ) . '&per_page=100';
		$data = self::get_json( $url, array( 'Authorization' => 'Bearer ' . trim( (string) $s['cloudflare_token'] ) ) );
		$out  = array();
		foreach ( (array) ( $data['result'] ?? array() ) as $m ) {
			if ( ! empty( $m['name'] ) ) {
				$out[] = (string) $m['name'];
			}
		}
		return $out;
	}

	/**
	 * GET JSON (model lists). Errors return an empty array.
	 *
	 * @param string $url     URL.
	 * @param array  $headers Headers.
	 * @return array
	 */
	private static function get_json( $url, array $headers ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => $headers,
			)
		);
		try {
			return self::json( $res );
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Decode a response or throw a readable error.
	 *
	 * @param array|WP_Error $res Response.
	 * @return array
	 * @throws Exception On HTTP error.
	 */
	public static function json( $res ) {
		if ( is_wp_error( $res ) ) {
			throw new Exception( $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		if ( $code < 200 || $code > 299 ) {
			$msg = '';
			if ( is_array( $data ) ) {
				$msg = (string) ( $data['error']['message'] ?? ( $data['errors'][0]['message'] ?? ( $data['message'] ?? '' ) ) );
				if ( '' === $msg && isset( $data['error'] ) && is_string( $data['error'] ) ) {
					$msg = $data['error'];
				}
			}
			if ( '' === $msg ) {
				$msg = '' !== $body ? substr( wp_strip_all_tags( $body ), 0, 240 ) : 'No response body';
			}
			throw new Exception( 'HTTP ' . $code . ': ' . $msg );
		}
		return is_array( $data ) ? $data : array();
	}
}
