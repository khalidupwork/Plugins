<?php
/**
 * Streaming replies with Server-Sent Events (POST talkwyn/v1/stream).
 *
 * Providers with a streaming API (Groq, OpenRouter, OpenAI, Mistral, DeepSeek,
 * Anthropic) stream token by token. Others answer in one piece. If streaming
 * fails before any text arrives, the next provider is tried; the widget falls
 * back to the normal endpoint if the stream cannot be opened at all.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * SSE endpoint.
 */
final class Stream {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'talkwyn_rest_routes', array( self::class, 'route' ) );
	}

	/**
	 * Register the route.
	 *
	 * @param string $ns Namespace.
	 */
	public static function route( string $ns ): void {
		register_rest_route(
			$ns,
			'/stream',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Whether streaming is on and the server can stream.
	 */
	public static function enabled(): bool {
		return (bool) \Talkwyn_Settings::get( 'pro_stream', 1 ) && function_exists( 'curl_init' );
	}

	/**
	 * Handle a streamed turn.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|void
	 */
	public static function handle( \WP_REST_Request $req ) {
		$session = \Talkwyn_REST::guard( $req );
		if ( $session instanceof \WP_REST_Response ) {
			return $session;
		}
		$s = \Talkwyn_Settings::all();
		if ( ! \Talkwyn_Rate_Limiter::allow_chat( $session, absint( $s['rate_limit_per_hour'] ), ! empty( $s['trusted_proxy'] ) ) ) {
			return \Talkwyn_REST::error( 'talkwyn_rate', __( 'Too many messages. Please try again later.', 'talkwyn-pro' ), 429 );
		}
		$message = sanitize_textarea_field( (string) $req->get_param( 'message' ) );
		if ( '' === trim( $message ) || \Talkwyn_Text::len( $message ) > 4000 ) {
			return \Talkwyn_REST::error( 'talkwyn_message', __( 'Please type a message.', 'talkwyn-pro' ), 400 );
		}
		list( $page_url, $page_lang ) = \Talkwyn_REST::page( $req );

		self::open();
		$ctx = \Talkwyn_Chat::prepare( $message, $session, $page_url, $page_lang );
		if ( isset( $ctx['preset'] ) ) {
			$gen = $ctx['preset'];
		} elseif ( 'social_local' === $ctx['intent'] ) {
			$gen = array(
				'text'     => \Talkwyn_Conversation::social_answer( $message, $ctx['site_name'], $page_lang ),
				'provider' => 'local-conversation',
				'error'    => '',
				'ms'       => 0,
			);
		} else {
			$gen = self::generate( $ctx['messages'], $s );
		}
		$data = \Talkwyn_Chat::finish( $ctx, $gen );
		self::send( 'done', $data );
		exit;
	}

	/**
	 * Start the event stream.
	 */
	private static function open(): void {
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 90 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Accel-Buffering: no' );
		header( 'X-Robots-Tag: noindex' );
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- padding for proxies.
		flush();
	}

	/**
	 * Send one event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Data.
	 */
	private static function send( string $event, array $data ): void {
		echo 'event: ' . $event . "\n" . 'data: ' . wp_json_encode( $data ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON event stream.
		flush();
	}

	/**
	 * Try providers in order, streaming where possible.
	 *
	 * @param array $messages Messages.
	 * @param array $s        Settings.
	 * @return array
	 */
	public static function generate( array $messages, array $s ): array {
		$registry = \Talkwyn_Providers::registry();
		$order    = \Talkwyn_Providers::order( $s, $registry );
		$last     = sanitize_key( (string) get_transient( \Talkwyn_Providers::LAST_GOOD ) );
		if ( $last && in_array( $last, $order, true ) ) {
			$order = array_values( array_unique( array_merge( array( $last ), $order ) ) );
		}
		$errors  = array();
		$started = microtime( true );
		foreach ( $order as $id ) {
			$acc  = '';
			$sent = 0.0;
			try {
				$spec = self::spec( $id, $messages, $s );
				if ( $spec ) {
					$on = static function ( string $piece ) use ( &$acc, &$sent ): void {
						$acc  .= $piece;
						$clean = self::visible( $acc );
						if ( '' !== $clean && microtime( true ) - $sent > 0.08 ) {
							$sent = microtime( true );
							self::send( 'delta', array( 'html' => \Talkwyn_Markdown::render( $clean ) ) );
						}
					};
					self::curl_stream( $spec, $on );
					$text = trim( self::visible( $acc ) );
				} else {
					$text = trim( (string) call_user_func( $registry[ $id ]['call'], $messages, $s ) );
				}
			} catch ( \Exception $e ) {
				$errors[] = $id . ': ' . $e->getMessage();
				$text     = trim( self::visible( $acc ) );
				if ( '' === $text ) {
					continue;
				}
			}
			if ( '' !== $text ) {
				set_transient( \Talkwyn_Providers::LAST_GOOD, $id, 6 * HOUR_IN_SECONDS );
				return array(
					'text'     => $text,
					'provider' => $id,
					'error'    => implode( ' | ', $errors ),
					'ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
				);
			}
			$errors[] = $id . ': empty reply';
		}
		return array(
			'text'     => '',
			'provider' => '',
			'error'    => $errors ? implode( ' | ', $errors ) : 'No AI provider has an API key yet.',
			'ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
		);
	}

	/**
	 * Hide reasoning blocks some models stream first.
	 *
	 * @param string $text Text so far.
	 */
	public static function visible( string $text ): string {
		return ltrim( (string) preg_replace( '#<think>.*?(</think>|$)#s', '', $text ) );
	}

	/**
	 * Request spec for a streaming provider, or null when it cannot stream.
	 *
	 * @param string $id       Provider.
	 * @param array  $messages Messages.
	 * @param array  $s        Settings.
	 * @return array|null
	 */
	public static function spec( string $id, array $messages, array $s ): ?array {
		/**
		 * Filters the streaming request for a provider. Return an array with url,
		 * headers, body and format (openai|anthropic) to stream a custom provider.
		 *
		 * @param array|null $spec     Null to use the built-in spec.
		 * @param string     $id       Provider ID.
		 * @param array      $messages Messages.
		 * @param array      $s        Settings.
		 */
		$custom = apply_filters( 'talkwyn_pro_stream_spec', null, $id, $messages, $s );
		if ( is_array( $custom ) ) {
			return $custom;
		}
		if ( 'anthropic' === $id ) {
			return array(
				'url'     => 'https://api.anthropic.com/v1/messages',
				'headers' => Providers::anthropic_headers( (string) $s['anthropic_key'] ),
				'body'    => Providers::anthropic_body( $messages, $s, true ),
				'format'  => 'anthropic',
			);
		}
		if ( ! isset( Providers::OPENAI_COMPATIBLE[ $id ] ) ) {
			return null;
		}
		$body    = array(
			'model'       => trim( (string) $s[ $id . '_model' ] ),
			'messages'    => $messages,
			'temperature' => 0.35,
			'max_tokens'  => 600,
			'stream'      => true,
		);
		$headers = array(
			'Authorization' => 'Bearer ' . trim( (string) $s[ $id . '_key' ] ),
			'Content-Type'  => 'application/json',
		);
		if ( 'groq' === $id && 0 === strpos( $body['model'], 'qwen/qwen3' ) ) {
			$body['reasoning_effort']      = 'none';
			$body['max_completion_tokens'] = 600;
			unset( $body['max_tokens'] );
		}
		if ( 'openrouter' === $id ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = wp_strip_all_tags( get_bloginfo( 'name' ) );
		}
		if ( 'openai' === $id ) {
			$body['max_completion_tokens'] = 600;
			unset( $body['max_tokens'], $body['temperature'] );
		}
		return array(
			'url'     => Providers::OPENAI_COMPATIBLE[ $id ]['chat'],
			'headers' => $headers,
			'body'    => $body,
			'format'  => 'openai',
		);
	}

	/**
	 * Parse one SSE data line into text. Pure, unit tested.
	 *
	 * @param string $line   Line.
	 * @param string $format openai|anthropic.
	 * @return string|null Text piece, or null for none.
	 */
	public static function parse_line( string $line, string $format ): ?string {
		$line = trim( $line );
		if ( 0 !== strpos( $line, 'data:' ) ) {
			return null;
		}
		$json = trim( substr( $line, 5 ) );
		if ( '' === $json || '[DONE]' === $json ) {
			return null;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( 'anthropic' === $format ) {
			return 'content_block_delta' === ( $data['type'] ?? '' ) ? (string) ( $data['delta']['text'] ?? '' ) : null;
		}
		$piece = $data['choices'][0]['delta']['content'] ?? null;
		return is_string( $piece ) ? $piece : null;
	}

	/**
	 * Stream a request with cURL.
	 *
	 * @param array    $spec Request spec.
	 * @param callable $on   Called with each text piece.
	 * @throws \Exception On HTTP or network errors.
	 */
	private static function curl_stream( array $spec, callable $on ): void {
		$buffer  = '';
		$raw     = '';
		$headers = array();
		foreach ( $spec['headers'] as $k => $v ) {
			$headers[] = $k . ': ' . $v;
		}
		$ch = curl_init( $spec['url'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => wp_json_encode( $spec['body'] ),
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_CONNECTTIMEOUT => 8,
				CURLOPT_TIMEOUT        => 45,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_WRITEFUNCTION  => static function ( $ch, $chunk ) use ( &$buffer, &$raw, $on, $spec ) {
					$raw    .= strlen( $raw ) < 4000 ? $chunk : '';
					$buffer .= $chunk;
					while ( false !== ( $pos = strpos( $buffer, "\n" ) ) ) {
						$line   = substr( $buffer, 0, $pos );
						$buffer = substr( $buffer, $pos + 1 );
						$piece  = self::parse_line( $line, $spec['format'] );
						if ( null !== $piece && '' !== $piece ) {
							$on( $piece );
						}
					}
					return strlen( $chunk );
				},
			)
		);
		if ( file_exists( ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) && ! ini_get( 'curl.cainfo' ) ) {
			curl_setopt( $ch, CURLOPT_CAINFO, ABSPATH . WPINC . '/certificates/ca-bundle.crt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
		$ok   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$err  = curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		if ( false === $ok ) {
			throw new \Exception( '' !== $err ? $err : 'Stream failed' );
		}
		if ( $code < 200 || $code > 299 ) {
			$data = json_decode( $raw, true );
			$msg  = is_array( $data ) ? (string) ( $data['error']['message'] ?? ( $data['message'] ?? '' ) ) : '';
			throw new \Exception( 'HTTP ' . $code . ( '' !== $msg ? ': ' . $msg : '' ) );
		}
	}
}
