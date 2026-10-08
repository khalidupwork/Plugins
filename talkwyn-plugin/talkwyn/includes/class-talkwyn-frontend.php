<?php
/**
 * Chat widget: floating launcher, inline shortcode and shared markup.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front end.
 */
class Talkwyn_Frontend {

	/**
	 * Whether an inline chat was printed on this page.
	 *
	 * @var bool
	 */
	private static $inline_rendered = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating' ) );
		add_shortcode( 'talkwyn_chat', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'legacy_shortcode' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'popout' ), 1 );
	}

	/**
	 * Languages a visitor can pick in the widget menu.
	 *
	 * @return array<string, array{native:string,en:string,rtl:bool}>
	 */
	public static function languages() {
		$list = array(
			'en' => array( 'English', 'English', false ),
			'es' => array( 'Español', 'Spanish', false ),
			'fr' => array( 'Français', 'French', false ),
			'de' => array( 'Deutsch', 'German', false ),
			'pt' => array( 'Português', 'Portuguese', false ),
			'it' => array( 'Italiano', 'Italian', false ),
			'nl' => array( 'Nederlands', 'Dutch', false ),
			'tr' => array( 'Türkçe', 'Turkish', false ),
			'pl' => array( 'Polski', 'Polish', false ),
			'ru' => array( 'Русский', 'Russian', false ),
			'ar' => array( 'العربية', 'Arabic', true ),
			'ur' => array( 'اردو', 'Urdu', true ),
			'hi' => array( 'हिन्दी', 'Hindi', false ),
			'zh' => array( '中文', 'Chinese', false ),
			'ja' => array( '日本語', 'Japanese', false ),
			'ko' => array( '한국어', 'Korean', false ),
		);
		$out  = array();
		foreach ( $list as $code => $row ) {
			$out[ $code ] = array(
				'native' => $row[0],
				'en'     => $row[1],
				'rtl'    => $row[2],
			);
		}
		return (array) apply_filters( 'talkwyn_languages', $out );
	}

	/**
	 * Talkwyn link with the owner's referral code and UTM tags.
	 *
	 * @param string $source utm_source.
	 * @return string
	 */
	public static function talkwyn_link( $source ) {
		$args = array();
		$ref  = sanitize_key( (string) Talkwyn_Settings::get( 'badge_ref', '' ) );
		if ( '' !== $ref ) {
			$args['ref'] = $ref;
		}
		$args['utm_source'] = $source;
		$args['utm_medium'] = 'widget';
		return add_query_arg( $args, 'https://talkwyn.com/' );
	}

	/**
	 * Whether the badge shows (owner opted in, add-ons can hide it).
	 *
	 * @return bool
	 */
	public static function show_badge() {
		return (bool) apply_filters( 'talkwyn_show_badge', (bool) Talkwyn_Settings::get( 'show_badge', 0 ) );
	}

	/**
	 * Widget menu items.
	 *
	 * @return array[] Each: id, label, and url for links.
	 */
	public static function menu_items() {
		$s     = Talkwyn_Settings::all();
		$items = array(
			array(
				'id'    => 'name',
				'label' => __( 'Change name', 'talkwyn' ),
			),
		);
		if ( ! empty( $s['transcript_enabled'] ) && ! empty( $s['logs_enabled'] ) ) {
			$items[] = array(
				'id'    => 'transcript',
				'label' => __( 'Email transcript', 'talkwyn' ),
			);
		}
		if ( ! empty( $s['show_sound_button'] ) ) {
			$items[] = array(
				'id'    => 'sound',
				'label' => __( 'Sound', 'talkwyn' ),
			);
		}
		if ( ! empty( $s['language_menu'] ) ) {
			$items[] = array(
				'id'    => 'language',
				'label' => __( 'Language', 'talkwyn' ),
			);
		}
		$items[] = array(
			'id'    => 'popout',
			'label' => __( 'Pop out', 'talkwyn' ),
		);
		if ( ! empty( $s['show_reset_button'] ) ) {
			$items[] = array(
				'id'    => 'reset',
				'label' => Talkwyn_I18n::get( 'reset_label' ),
			);
		}
		if ( self::show_badge() ) {
			$items[] = array(
				'id'    => 'add_chat',
				'label' => __( 'Add chat to your website', 'talkwyn' ),
				'url'   => self::talkwyn_link( 'widget_menu' ),
			);
		}

		/**
		 * Filters the widget menu. Each item: id, label, optional url.
		 *
		 * @param array $items Items.
		 */
		return array_values( (array) apply_filters( 'talkwyn_widget_menu', $items ) );
	}

	/**
	 * Pop-out window: the chat on its own page (?talkwyn_popout=1).
	 *
	 * @return void
	 */
	public static function popout() {
		if ( empty( $_GET['talkwyn_popout'] ) || ! Talkwyn_Settings::get( 'enabled' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		show_admin_bar( false );
		nocache_headers();
		wp_register_style( 'talkwyn-widget', TALKWYN_URL . 'assets/css/widget.css', array(), TALKWYN_VERSION );
		wp_register_script( 'talkwyn-widget', TALKWYN_URL . 'assets/js/widget.js', array(), TALKWYN_VERSION, true );
		self::enqueue();
		$bot = Talkwyn_I18n::get( 'bot_name' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $bot . ' | ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) ); ?></title>
		<?php wp_print_styles(); ?>
<style>html,body{margin:0;height:100%;background:#F7F3F3}.twc--inline{max-width:760px;height:100%;margin:0 auto}.twc--inline .twc-panel{height:100vh;height:100dvh;border-radius:0;border-top:0;border-bottom:0}</style>
</head>
<body class="talkwyn-popout">
		<?php echo self::markup( 'inline', 0, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup(). ?>
		<?php wp_print_scripts(); ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Keep [nabia_ai_chatbot] working after moving from Nabia AI Chatbot.
	 *
	 * @return void
	 */
	public static function legacy_shortcode() {
		if ( ! shortcode_exists( 'nabia_ai_chatbot' ) ) {
			add_shortcode( 'nabia_ai_chatbot', array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * Register assets; enqueue them where the widget shows.
	 *
	 * @return void
	 */
	public static function register() {
		wp_register_style( 'talkwyn-widget', TALKWYN_URL . 'assets/css/widget.css', array(), TALKWYN_VERSION );
		wp_register_script( 'talkwyn-widget', TALKWYN_URL . 'assets/js/widget.js', array(), TALKWYN_VERSION, true );
		if ( self::should_show() ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueue assets and the widget configuration once.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( wp_script_is( 'talkwyn-widget', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'talkwyn-widget' );
		wp_enqueue_script( 'talkwyn-widget' );
		wp_add_inline_script( 'talkwyn-widget', 'window.TalkwynConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );
		if ( Talkwyn_Settings::get( 'lead_enabled' ) && '' !== (string) Talkwyn_Settings::get( 'turnstile_site_key' ) && '' !== (string) Talkwyn_Settings::get( 'turnstile_secret' ) ) {
			wp_enqueue_script( 'talkwyn-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, array( 'in_footer' => true, 'strategy' => 'async' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		/**
		 * Fires after the widget assets were enqueued. Add-ons enqueue theirs here.
		 */
		do_action( 'talkwyn_enqueue_widget' );
	}

	/**
	 * Configuration passed to widget.js.
	 *
	 * @return array
	 */
	public static function config() {
		$s     = Talkwyn_Settings::all();
		$t     = array( 'Talkwyn_I18n', 'get' );
		$brand = sanitize_hex_color( (string) $s['brand_color'] );
		$brand = $brand ? $brand : '#D7263D';
		$bot   = call_user_func( $t, 'bot_name' );
		$lines = preg_split( '/\r\n|\r|\n/', call_user_func( $t, 'suggested_questions' ) );

		$config = array(
			'rest'         => esc_url_raw( rest_url( Talkwyn_REST::NS . '/' ) ),
			'version'      => TALKWYN_VERSION,
			'brand'        => $brand,
			'onBrand'      => Talkwyn_Contrast::text_on( $brand ),
			'position'     => 'left' === $s['position'] ? 'left' : 'right',
			'width'        => min( 720, max( 320, absint( $s['chat_width'] ) ) ),
			'height'       => min( 900, max( 480, absint( $s['chat_height'] ) ) ),
			'pageLanguage' => Talkwyn_I18n::page_language(),
			'turnstile'    => ( '' !== (string) $s['turnstile_site_key'] && '' !== (string) $s['turnstile_secret'] ) ? (string) $s['turnstile_site_key'] : '',
			'badge'        => self::show_badge(),
			'badgeUrl'     => self::talkwyn_link( 'badge' ),
			'menu'         => ! empty( $s['widget_menu'] ) ? self::menu_items() : array(),
			'languages'    => self::languages(),
			'popoutUrl'    => add_query_arg( 'talkwyn_popout', '1', home_url( '/' ) ),
			'devices'      => (string) $s['devices'],
			'flags'        => array(
				'fullScreen'   => ! empty( $s['full_screen_enabled'] ),
				'mobileFull'   => ! empty( $s['mobile_full_screen'] ),
				'sound'        => ! empty( $s['show_sound_button'] ),
				'soundDefault' => ! empty( $s['sound_default'] ),
				'timestamps'   => ! empty( $s['show_timestamps'] ),
				'copy'         => ! empty( $s['show_message_tools'] ),
				'feedback'     => ! empty( $s['feedback_enabled'] ) && ! empty( $s['logs_enabled'] ),
				'lead'         => ! empty( $s['lead_enabled'] ),
				'leadAskFirst' => ! empty( $s['lead_ask_first'] ),
				'requireName'  => ! empty( $s['lead_require_name'] ),
				'requireEmail' => ! empty( $s['lead_require_email'] ),
				'requirePhone' => ! empty( $s['lead_require_phone'] ),
				'consent'      => ! empty( $s['lead_consent_enabled'] ),
				'handoff'      => ! empty( $s['handoff_enabled'] ) && '' !== (string) $s['handoff_url'],
			),
			'handoffUrl'   => esc_url_raw( (string) $s['handoff_url'] ),
			'suggestions'  => array_slice( array_values( array_filter( array_map( 'trim', (array) $lines ) ) ), 0, 4 ),
			'text'         => array(
				'botName'        => $bot,
				'welcome'        => call_user_func( $t, 'welcome_message' ),
				'placeholder'    => call_user_func( $t, 'placeholder' ),
				'online'         => call_user_func( $t, 'online_label' ),
				'typing'         => str_replace( '{bot}', $bot, call_user_func( $t, 'typing_label' ) ),
				'leadPrompt'     => call_user_func( $t, 'lead_prompt' ),
				'leadYes'        => call_user_func( $t, 'lead_yes_label' ),
				'leadNo'         => call_user_func( $t, 'lead_no_label' ),
				'leadDecline'    => call_user_func( $t, 'lead_decline_reply' ),
				'leadIntro'      => call_user_func( $t, 'lead_form_intro' ),
				'leadTitle'      => call_user_func( $t, 'lead_title' ),
				'leadButton'     => call_user_func( $t, 'lead_button' ),
				'leadSuccess'    => call_user_func( $t, 'lead_success' ),
				'consent'        => call_user_func( $t, 'lead_consent_text' ),
				'privacy'        => call_user_func( $t, 'privacy_notice' ),
				'handoff'        => call_user_func( $t, 'handoff_label' ),
				'sources'        => call_user_func( $t, 'sources_label' ),
				'name'           => call_user_func( $t, 'name_label' ),
				'email'          => call_user_func( $t, 'email_label' ),
				'phone'          => call_user_func( $t, 'phone_label' ),
				'sending'        => call_user_func( $t, 'sending_label' ),
				'leadSaved'      => call_user_func( $t, 'success_title' ),
				'reference'      => call_user_func( $t, 'reference_label' ),
				'continue'       => call_user_func( $t, 'continue_chat_label' ),
				'error'          => call_user_func( $t, 'generic_error' ),
				'connection'     => call_user_func( $t, 'connection_error' ),
				'leadError'      => call_user_func( $t, 'lead_error' ),
				'copy'           => call_user_func( $t, 'copy_label' ),
				'copied'         => call_user_func( $t, 'copied_label' ),
				'helpful'        => call_user_func( $t, 'helpful_label' ),
				'notHelpful'     => call_user_func( $t, 'not_helpful_label' ),
				'resetConfirm'   => call_user_func( $t, 'reset_confirm' ),
				'send'           => __( 'Send message', 'talkwyn' ),
				'message'        => __( 'Message', 'talkwyn' ),
				'poweredBy'      => __( 'Powered by Talkwyn', 'talkwyn' ),
				'menu'           => __( 'Chat menu', 'talkwyn' ),
				'yourName'       => __( 'Your name', 'talkwyn' ),
				'save'           => __( 'Save', 'talkwyn' ),
				'cancel'         => __( 'Cancel', 'talkwyn' ),
				'nameSaved'      => __( 'Thanks, %s. Nice to meet you.', 'talkwyn' ),
				'transcriptTitle' => __( 'Email this chat to me', 'talkwyn' ),
				'transcriptConsent' => __( 'Send me this chat and let the team contact me about my questions.', 'talkwyn' ),
				'transcriptSend' => __( 'Send transcript', 'talkwyn' ),
				'autoLanguage'   => __( 'Auto (match my messages)', 'talkwyn' ),
				'languageSet'    => __( 'Replies will be in %s.', 'talkwyn' ),
				'soundOn'        => __( 'Sound on', 'talkwyn' ),
				'soundOff'       => __( 'Sound off', 'talkwyn' ),
				'websiteField'   => __( 'Leave this field empty', 'talkwyn' ),
				'securityCheck'  => __( 'Please complete the security check.', 'talkwyn' ),
				'chatWindow'     => __( 'Chat window', 'talkwyn' ),
				'openChat'       => __( 'Open chat', 'talkwyn' ),
			),
		);

		/**
		 * Filters the widget configuration. Talkwyn Pro adds proactive messages,
		 * business hours and streaming here.
		 *
		 * @param array $config Configuration.
		 */
		return (array) apply_filters( 'talkwyn_widget_config', $config );
	}

	/**
	 * Print the floating widget.
	 *
	 * @return void
	 */
	public static function render_floating() {
		if ( self::$inline_rendered || ! self::should_show() ) {
			return;
		}
		echo self::markup( 'floating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}

	/**
	 * [talkwyn_chat mode="inline"] shortcode.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'mode'   => 'inline',
				'height' => '',
			),
			$atts,
			'talkwyn_chat'
		);
		if ( ! Talkwyn_Settings::get( 'enabled' ) || is_admin() ) {
			return '';
		}
		self::enqueue();
		$mode = 'floating' === $atts['mode'] ? 'floating' : 'inline';
		if ( 'inline' === $mode ) {
			self::$inline_rendered = true;
		}
		return self::markup( $mode, absint( $atts['height'] ) );
	}

	/**
	 * Whether the floating widget shows on this request.
	 *
	 * @return bool
	 */
	public static function should_show() {
		$s = Talkwyn_Settings::all();
		if ( is_admin() || empty( $s['enabled'] ) || is_feed() || is_embed() ) {
			return false;
		}
		if ( ( 'logged_in' === $s['show_to'] && ! is_user_logged_in() ) || ( 'logged_out' === $s['show_to'] && is_user_logged_in() ) ) {
			return false;
		}
		$hidden = array_map( 'absint', (array) $s['hidden_page_ids'] );
		if ( $hidden && is_singular() && in_array( (int) get_queried_object_id(), $hidden, true ) ) {
			return false;
		}
		$paths = trim( (string) $s['hidden_url_paths'] );
		if ( '' !== $paths ) {
			$uri     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$current = untrailingslashit( '/' . ltrim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' ) );
			$current = '' === $current ? '/' : $current;
			foreach ( preg_split( '/\r\n|\r|\n/', $paths ) as $path ) {
				$path = trim( $path );
				if ( '' === $path ) {
					continue;
				}
				$path = untrailingslashit( '/' . ltrim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' ) );
				$path = '' === $path ? '/' : $path;
				if ( $current === $path || ( '/' !== $path && 0 === strpos( $current . '/', $path . '/' ) ) ) {
					return false;
				}
			}
		}

		/**
		 * Filters whether the floating widget shows.
		 *
		 * @param bool $show Show.
		 */
		return (bool) apply_filters( 'talkwyn_show_widget', true );
	}

	/**
	 * Launcher and header icons.
	 *
	 * @param string $icon Icon name.
	 * @return string SVG.
	 */
	public static function icon( $icon ) {
		$open = '<svg class="twc-icon" viewBox="0 0 32 32" aria-hidden="true" focusable="false">';
		switch ( $icon ) {
			case 'chat_dots':
				$inner = '<path class="twc-stroke" d="M6.5 7.5h19A3.5 3.5 0 0 1 29 11v9a3.5 3.5 0 0 1-3.5 3.5H15l-6.5 4v-4h-2A3.5 3.5 0 0 1 3 20v-9a3.5 3.5 0 0 1 3.5-3.5Z"/><circle cx="11" cy="15.5" r="1.4"/><circle cx="16" cy="15.5" r="1.4"/><circle cx="21" cy="15.5" r="1.4"/>';
				break;
			case 'headset':
				$inner = '<path class="twc-stroke" d="M6 17v-2a10 10 0 0 1 20 0v2"/><path class="twc-stroke" d="M6 17h3v8H7.5A2.5 2.5 0 0 1 5 22.5v-3A2.5 2.5 0 0 1 7.5 17H9M26 17h-3v8h1.5a2.5 2.5 0 0 0 2.5-2.5v-3a2.5 2.5 0 0 0-2.5-2.5H23M23 25c0 2-2 3-5 3h-2"/>';
				break;
			case 'question':
				$inner = '<path class="twc-stroke" d="M7 7h18a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H15l-6 4v-4H7a3 3 0 0 1-3-3V10a3 3 0 0 1 3-3Z"/><path class="twc-stroke" d="M12.8 13a3.3 3.3 0 1 1 5.9 2c-1.45 1.55-2.7 1.65-2.7 3.5"/><circle cx="16" cy="21.2" r="1.1"/>';
				break;
			case 'spark_chat':
				$inner = '<path class="twc-stroke" d="M7.5 6.5h17A3.5 3.5 0 0 1 28 10v9a3.5 3.5 0 0 1-3.5 3.5H15l-6 4v-4H7.5A3.5 3.5 0 0 1 4 19v-9a3.5 3.5 0 0 1 3.5-3.5Z"/><path d="M18.6 10.2c.25 1.55 1.25 2.55 2.8 2.8-1.55.25-2.55 1.25-2.8 2.8-.25-1.55-1.25-2.55-2.8-2.8 1.55-.25 2.55-1.25 2.8-2.8Z"/><circle cx="11" cy="14.5" r="1.15"/>';
				break;
			case 'talkwyn':
			default:
				// The Talkwyn mark: a bubble with three rising dots.
				$inner = '<path class="twc-stroke" d="M8 4.5h16A5.5 5.5 0 0 1 29.5 10v10A5.5 5.5 0 0 1 24 25.5H13.5L7.5 30v-4.6A5.5 5.5 0 0 1 2.5 20V10A5.5 5.5 0 0 1 8 4.5Z"/><circle cx="10.2" cy="18.6" r="2"/><circle cx="16" cy="15.3" r="2"/><circle cx="21.8" cy="11.5" r="2.4"/>';
		}
		return $open . $inner . '</svg>';
	}

	/**
	 * Assistant avatar.
	 *
	 * @param array $s Settings.
	 * @return string HTML.
	 */
	private static function avatar( array $s ) {
		$type = (string) $s['avatar_type'];
		if ( 'custom' === $type && ! empty( $s['avatar_url'] ) ) {
			return '<img src="' . esc_url( $s['avatar_url'] ) . '" alt="" width="36" height="36" loading="lazy">';
		}
		if ( 'initials' === $type ) {
			$name    = trim( Talkwyn_I18n::get( 'bot_name' ) );
			$letters = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
			return '<span class="twc-avatar__text">' . esc_html( '' !== $letters ? $letters : 'T' ) . '</span>';
		}
		if ( 'headset' === $type || 'chat' === $type ) {
			return self::icon( 'chat' === $type ? 'chat_dots' : 'headset' );
		}
		return self::icon( 'talkwyn' );
	}

	/**
	 * Widget markup.
	 *
	 * @param string $mode   floating|inline.
	 * @param int    $height Inline height in px.
	 * @param bool   $popout Rendered in the pop-out window.
	 * @return string
	 */
	public static function markup( $mode, $height = 0, $popout = false ) {
		$s        = Talkwyn_Settings::all();
		$label    = trim( Talkwyn_I18n::get( 'launcher_label' ) );
		$bot      = Talkwyn_I18n::get( 'bot_name' );
		$tool     = static function ( $class, $key, $svg, $extra = '' ) {
			$text = Talkwyn_I18n::get( $key );
			return '<button type="button" class="twc-head-btn ' . esc_attr( $class ) . '" aria-label="' . esc_attr( $text ) . '" title="' . esc_attr( $text ) . '"' . $extra . '>' . $svg . '</button>';
		};
		$svg      = static function ( $paths ) {
			return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
		};
		$style    = $height ? ' style="--twc-inline-height:' . absint( $height ) . 'px"' : '';
		$tools    = '';
		$menu     = ! empty( $s['widget_menu'] );
		if ( $menu ) {
			$tools .= '<button type="button" class="twc-head-btn twc-menu-btn" aria-haspopup="menu" aria-expanded="false" aria-label="' . esc_attr__( 'Chat menu', 'talkwyn' ) . '" title="' . esc_attr__( 'Chat menu', 'talkwyn' ) . '">' . $svg( '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>' ) . '</button>';
		}
		if ( ! $menu && ! empty( $s['show_reset_button'] ) ) {
			$tools .= $tool( 'twc-reset', 'reset_label', $svg( '<path d="M4 4v6h6"/><path d="M5.5 15a7.5 7.5 0 1 0 .8-7.7L4 10"/>' ) );
		}
		if ( ! $menu && ! empty( $s['show_sound_button'] ) ) {
			$tools .= $tool( 'twc-sound', 'sound_label', $svg( '<g class="twc-on"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15 9.5a4 4 0 0 1 0 5"/><path d="M17.5 7a7.5 7.5 0 0 1 0 10"/></g><g class="twc-off"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="m16 10 5 5"/><path d="m21 10-5 5"/></g>' ), ' aria-pressed="false"' );
		}
		if ( 'floating' === $mode && ! empty( $s['full_screen_enabled'] ) ) {
			$tools .= $tool( 'twc-expand', 'expand_label', $svg( '<g class="twc-on"><path d="M8 3H3v5"/><path d="M16 3h5v5"/><path d="M8 21H3v-5"/><path d="M16 21h5v-5"/></g><g class="twc-off"><path d="M8 8H3V3"/><path d="M16 8h5V3"/><path d="M8 16H3v5"/><path d="M16 16h5v5"/></g>' ), ' aria-pressed="false"' );
		}
		if ( 'floating' === $mode && ! empty( $s['show_minimize_button'] ) ) {
			$tools .= $tool( 'twc-minimize', 'minimize_label', $svg( '<path d="M5 12h14"/>' ) );
		}
		if ( 'floating' === $mode ) {
			$tools .= $tool( 'twc-close', 'close_label', $svg( '<path d="m6 6 12 12"/><path d="M18 6 6 18"/>' ) );
		}

		ob_start();
		?>
		<div class="twc twc--<?php echo esc_attr( $mode ); ?><?php echo $popout ? ' twc--popout' : ''; ?>" data-talkwyn-chat="<?php echo esc_attr( $mode ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from absint(). ?>>
			<?php if ( 'floating' === $mode ) : ?>
				<div class="twc-launcher-wrap">
					<?php if ( '' !== $label ) : ?>
						<span class="twc-launcher-label" dir="auto"><?php echo esc_html( $label ); ?></span>
					<?php endif; ?>
					<button class="twc-launcher" type="button" aria-expanded="false" aria-label="<?php echo esc_attr( '' !== $label ? $label : __( 'Open chat', 'talkwyn' ) ); ?>">
						<?php echo self::icon( (string) $s['launcher_icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</button>
				</div>
			<?php endif; ?>
			<section class="twc-panel" role="dialog" aria-label="<?php echo esc_attr( $bot ); ?>"<?php echo 'floating' === $mode ? ' hidden' : ''; ?>>
				<header class="twc-header">
					<div class="twc-avatar"><?php echo self::avatar( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar(). ?></div>
					<div class="twc-identity">
						<strong class="twc-name" dir="auto"><?php echo esc_html( $bot ); ?></strong>
						<span class="twc-status" dir="auto"><?php echo esc_html( Talkwyn_I18n::get( 'online_label' ) ); ?></span>
					</div>
					<div class="twc-tools"><?php echo $tools; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
				</header>
				<div class="twc-menu" role="menu" aria-label="<?php esc_attr_e( 'Chat menu', 'talkwyn' ); ?>" hidden></div>
				<div class="twc-messages" role="log" aria-live="polite" aria-relevant="additions"></div>
				<div class="twc-suggestions"></div>
				<form class="twc-compose" novalidate>
					<label class="twc-sr" for="twc-input-<?php echo esc_attr( $mode ); ?>"><?php esc_html_e( 'Message', 'talkwyn' ); ?></label>
					<textarea id="twc-input-<?php echo esc_attr( $mode ); ?>" rows="1" maxlength="4000" dir="auto" placeholder="<?php echo esc_attr( Talkwyn_I18n::get( 'placeholder' ) ); ?>"></textarea>
					<button type="submit" class="twc-send" aria-label="<?php esc_attr_e( 'Send message', 'talkwyn' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h13"/><path d="m13 6 6 6-6 6"/></svg></button>
				</form>
				<div class="twc-foot" dir="auto"></div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
