<?php
/**
 * Public REST API for the chat widget (namespace talkwyn/v1).
 *
 * The widget fetches a fresh token from /session when it opens. That response
 * is never cached, so full-page caches cannot serve an expired token.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
class Talkwyn_REST {

	const NS     = 'talkwyn/v1';
	const ACTION = 'talkwyn_chat';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function routes() {
		$public = '__return_true';
		register_rest_route(
			self::NS,
			'/session',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'session' ),
				'permission_callback' => $public,
				'args'                => array( 'session' => array( 'type' => 'string' ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => $public,
				'args'                => array(
					'message' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'lead' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'feedback' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'event' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reset' ),
				'permission_callback' => $public,
			)
		);

		/**
		 * Fires after Talkwyn registered its REST routes.
		 *
		 * @param string $namespace REST namespace.
		 */
		do_action( 'talkwyn_rest_routes', self::NS );
	}

	/**
	 * Uncached response.
	 *
	 * @param array $data   Data.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function response( array $data, $status = 200 ) {
		$res = new WP_REST_Response( $data, $status );
		$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$res->header( 'X-Robots-Tag', 'noindex' );
		return $res;
	}

	/**
	 * Error response.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	public static function error( $code, $message, $status ) {
		return self::response(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Fresh token.
	 *
	 * @return string
	 */
	public static function token() {
		return wp_create_nonce( self::ACTION );
	}

	/**
	 * Check the token and session of a request.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return string|WP_REST_Response Session ID or an error response.
	 */
	public static function guard( WP_REST_Request $req ) {
		if ( ! Talkwyn_Settings::get( 'enabled' ) ) {
			return self::error( 'talkwyn_disabled', __( 'Chat is turned off.', 'talkwyn' ), 403 );
		}
		if ( ! wp_verify_nonce( (string) $req->get_param( 'token' ), self::ACTION ) ) {
			return self::error( 'talkwyn_token', __( 'Session expired. Please try again.', 'talkwyn' ), 403 );
		}
		$session = Talkwyn_History::clean_session( (string) $req->get_param( 'session' ) );
		if ( '' === $session ) {
			return self::error( 'talkwyn_session', __( 'Invalid session.', 'talkwyn' ), 400 );
		}
		return $session;
	}

	/**
	 * GET /session: token, session and saved conversation.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function session( WP_REST_Request $req ) {
		$session = Talkwyn_History::clean_session( (string) $req->get_param( 'session' ) );
		if ( '' === $session ) {
			$session = Talkwyn_History::new_session();
		}
		return self::response(
			array(
				'token'   => self::token(),
				'session' => $session,
				'history' => Talkwyn_Chat::widget_history( $session ),
				'flags'   => Talkwyn_History::flags( $session ),
			)
		);
	}

	/**
	 * POST /chat.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function chat( WP_REST_Request $req ) {
		$session = self::guard( $req );
		if ( $session instanceof WP_REST_Response ) {
			return $session;
		}
		$s = Talkwyn_Settings::all();
		if ( ! Talkwyn_Rate_Limiter::allow_chat( $session, absint( $s['rate_limit_per_hour'] ), ! empty( $s['trusted_proxy'] ) ) ) {
			return self::error( 'talkwyn_rate', __( 'Too many messages. Please try again later.', 'talkwyn' ), 429 );
		}
		$message = sanitize_textarea_field( (string) $req->get_param( 'message' ) );
		if ( '' === trim( $message ) || Talkwyn_Text::len( $message ) > 4000 ) {
			return self::error( 'talkwyn_message', __( 'Please type a message.', 'talkwyn' ), 400 );
		}
		list( $page_url, $page_lang ) = self::page( $req );
		return self::response( Talkwyn_Chat::respond( $message, $session, $page_url, $page_lang ) );
	}

	/**
	 * Page URL (same site only) and language from a request.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return string[]
	 */
	public static function page( WP_REST_Request $req ) {
		$url = esc_url_raw( (string) $req->get_param( 'page_url' ) );
		if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$url = '';
		}
		$lang = sanitize_text_field( (string) $req->get_param( 'page_lang' ) );
		$lang = preg_match( '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $lang ) ? str_replace( '_', '-', $lang ) : '';
		return array( $url, $lang );
	}

	/**
	 * POST /lead.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function lead( WP_REST_Request $req ) {
		$session = self::guard( $req );
		if ( $session instanceof WP_REST_Response ) {
			return $session;
		}
		$s = Talkwyn_Settings::all();
		if ( empty( $s['lead_enabled'] ) ) {
			return self::error( 'talkwyn_leads_off', __( 'Lead capture is turned off.', 'talkwyn' ), 403 );
		}
		$ip = Talkwyn_Rate_Limiter::client_ip( ! empty( $s['trusted_proxy'] ) );
		if ( ! Talkwyn_Rate_Limiter::hit( 'lead', $ip, absint( $s['lead_rate_limit_per_hour'] ) ) ) {
			return self::error( 'talkwyn_rate', __( 'Too many requests. Please try again later.', 'talkwyn' ), 429 );
		}
		$in    = array(
			'name'    => sanitize_text_field( (string) $req->get_param( 'name' ) ),
			'email'   => sanitize_email( (string) $req->get_param( 'email' ) ),
			'phone'   => sanitize_text_field( (string) $req->get_param( 'phone' ) ),
			'consent' => (bool) $req->get_param( 'consent' ),
			'website' => (string) $req->get_param( 'website' ),
		);
		$check = Talkwyn_Leads::validate( $in, $s );
		if ( $check['spam'] ) {
			// Look like success to bots, store nothing.
			return self::response(
				array(
					'message' => Talkwyn_I18n::get( 'lead_success' ),
					'lead_id' => 0,
				)
			);
		}
		if ( ! $check['ok'] ) {
			return self::error( 'talkwyn_lead_invalid', $check['error'], 400 );
		}
		if ( ! Talkwyn_Leads::turnstile_ok( (string) $req->get_param( 'turnstile' ), $ip ) ) {
			return self::error( 'talkwyn_lead_check', __( 'Please complete the security check.', 'talkwyn' ), 400 );
		}
		list( $page_url ) = self::page( $req );
		$id               = Talkwyn_Leads::save(
			array_merge(
				$in,
				array(
					'session_id' => $session,
					'message'    => Talkwyn_Chat::last_question( $session ),
					'page_url'   => $page_url,
				)
			)
		);
		if ( ! $id ) {
			return self::error( 'talkwyn_lead_save', __( 'We could not save your details. Please try again.', 'talkwyn' ), 500 );
		}
		Talkwyn_History::set_flag( $session, 'lead_submitted', $id );
		Talkwyn_History::add(
			$session,
			array(
				array(
					'role'    => 'assistant',
					'content' => Talkwyn_I18n::get( 'lead_success' ),
					'kind'    => 'lead_saved',
					'extra'   => array( 'lead_id' => $id ),
				),
			)
		);
		return self::response(
			array(
				'message' => Talkwyn_I18n::get( 'lead_success' ),
				'lead_id' => $id,
			)
		);
	}

	/**
	 * POST /feedback.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function feedback( WP_REST_Request $req ) {
		$session = self::guard( $req );
		if ( $session instanceof WP_REST_Response ) {
			return $session;
		}
		$id    = absint( $req->get_param( 'message_id' ) );
		$value = sanitize_key( (string) $req->get_param( 'feedback' ) );
		if ( ! Talkwyn_Settings::get( 'feedback_enabled' ) || ! $id || ! in_array( $value, array( 'helpful', 'not_helpful' ), true ) ) {
			return self::error( 'talkwyn_feedback', __( 'Invalid feedback.', 'talkwyn' ), 400 );
		}
		if ( ! Talkwyn_Logs::feedback( $id, $session, $value ) ) {
			return self::error( 'talkwyn_feedback', __( 'Could not save feedback.', 'talkwyn' ), 404 );
		}

		/**
		 * Fires after feedback was saved.
		 *
		 * @param int    $id      Log ID of the reply.
		 * @param string $value   helpful|not_helpful.
		 * @param string $session Session ID.
		 */
		do_action( 'talkwyn_feedback_saved', $id, $value, $session );
		return self::response( array( 'ok' => true ) );
	}

	/**
	 * POST /event: lead offer answers, so the conversation survives page changes.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function event( WP_REST_Request $req ) {
		$session = self::guard( $req );
		if ( $session instanceof WP_REST_Response ) {
			return $session;
		}
		$type = sanitize_key( (string) $req->get_param( 'type' ) );
		if ( 'lead_yes' === $type ) {
			Talkwyn_History::set_flag( $session, 'lead_form', 1 );
			Talkwyn_History::add(
				$session,
				array(
					array(
						'role'    => 'user',
						'content' => Talkwyn_I18n::get( 'lead_yes_label' ),
						'kind'    => 'event',
					),
					array(
						'role'    => 'assistant',
						'content' => Talkwyn_I18n::get( 'lead_form_intro' ),
						'kind'    => 'lead_form',
					),
				)
			);
		} elseif ( 'lead_no' === $type ) {
			Talkwyn_History::set_flag( $session, 'lead_declined', 1 );
			Talkwyn_History::add(
				$session,
				array(
					array(
						'role'    => 'user',
						'content' => Talkwyn_I18n::get( 'lead_no_label' ),
						'kind'    => 'event',
					),
					array(
						'role'    => 'assistant',
						'content' => Talkwyn_I18n::get( 'lead_decline_reply' ),
						'kind'    => 'event',
					),
				)
			);
		} else {
			/**
			 * Fires for other widget events (add-ons).
			 *
			 * @param string          $type    Event type.
			 * @param string          $session Session ID.
			 * @param WP_REST_Request $req     Request.
			 */
			do_action( 'talkwyn_widget_event', $type, $session, $req );
		}
		return self::response( array( 'ok' => true ) );
	}

	/**
	 * POST /reset: forget the conversation and start a new session.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function reset( WP_REST_Request $req ) {
		$session = self::guard( $req );
		if ( $session instanceof WP_REST_Response ) {
			return $session;
		}
		Talkwyn_History::clear( $session );
		return self::response( array( 'session' => Talkwyn_History::new_session() ) );
	}
}
