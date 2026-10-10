<?php
/**
 * First-run setup wizard: AI key, scan, colours, badge and updates, go live.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setup wizard.
 */
class Talkwyn_Onboarding {

	const SLUG = 'talkwyn-setup';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_talkwyn_wizard', array( __CLASS__, 'save' ) );
	}

	/**
	 * Hidden page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'options.php', __( 'Talkwyn setup', 'talkwyn' ), __( 'Talkwyn setup', 'talkwyn' ), 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	/**
	 * Open the wizard once after the first activation.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( ! get_option( 'talkwyn_do_onboarding' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		delete_option( 'talkwyn_do_onboarding' );
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * Steps.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	private static function steps() {
		return array(
			1 => array( __( 'AI key', 'talkwyn' ), 'providers' ),
			2 => array( __( 'Scan', 'talkwyn' ), 'scan' ),
			3 => array( __( 'Colours', 'talkwyn' ), 'appearance' ),
			4 => array( __( 'Badge and news', 'talkwyn' ), 'star' ),
			5 => array( __( 'Go live', 'talkwyn' ), 'rocket' ),
		);
	}

	/**
	 * URL of a step.
	 *
	 * @param int $step Step.
	 * @return string
	 */
	private static function url( $step ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&step=' . absint( $step ) );
	}

	/**
	 * Page.
	 *
	 * @return void
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$step  = isset( $_GET['step'] ) ? min( 6, max( 1, absint( $_GET['step'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$steps = self::steps();
		echo '<div class="wrap twa twa-wizard">';
		Talkwyn_Admin::hero( __( 'Set up Talkwyn', 'talkwyn' ), __( 'About five minutes. You can change everything later.', 'talkwyn' ), '<a class="twa-btn twa-btn--light" href="' . esc_url( admin_url( 'admin.php?page=talkwyn' ) ) . '">' . esc_html__( 'Skip setup', 'talkwyn' ) . '</a>' );
		if ( $step <= 5 ) {
			$pct = round( 100 * ( $step - 1 ) / 4 );
			echo '<ol class="twa-wizard__steps" style="--twa-progress:' . esc_attr( (string) $pct ) . '%">';
			foreach ( $steps as $n => $info ) {
				$class = $n < $step ? 'is-done' : ( $n === $step ? 'is-current' : '' );
				echo '<li class="' . esc_attr( $class ) . '"' . ( $n === $step ? ' aria-current="step"' : '' ) . '><a href="' . esc_url( self::url( $n ) ) . '"><span class="twa-wizard__dot">' . ( $n < $step ? Talkwyn_Admin::icon( 'check' ) : esc_html( (string) $n ) ) . '</span><span class="twa-wizard__name">' . esc_html( $info[0] ) . '</span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			}
			echo '</ol>';
		}
		echo '<section class="twa-card twa-wizard__card">';
		call_user_func( array( __CLASS__, 'step_' . $step ) );
		echo '</section></div>';
	}

	/**
	 * Step heading.
	 *
	 * @param string $icon  Icon.
	 * @param string $title Title.
	 * @param string $text  Intro.
	 * @return void
	 */
	private static function heading( $icon, $title, $text ) {
		echo '<div class="twa-wizard__head"><span class="twa-chip-icon twa-chip-icon--lg">' . Talkwyn_Admin::icon( $icon ) . '</span><h2>' . esc_html( $title ) . '</h2><p class="twa-intro">' . esc_html( $text ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	/**
	 * Wizard form open.
	 *
	 * @param int $step Step.
	 * @return void
	 */
	private static function form_open( $step ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="talkwyn_wizard"><input type="hidden" name="step" value="' . esc_attr( (string) $step ) . '">';
		wp_nonce_field( 'talkwyn_wizard' );
	}

	/**
	 * Navigation.
	 *
	 * @param int    $step       Step.
	 * @param bool   $submit     Next submits the form.
	 * @param string $next_label Label.
	 * @return void
	 */
	private static function nav( $step, $submit = false, $next_label = '' ) {
		$next_label = '' !== $next_label ? $next_label : __( 'Continue', 'talkwyn' );
		echo '<div class="twa-wizard__nav">';
		if ( $step > 1 ) {
			echo '<a class="twa-btn twa-btn--light twa-btn--lg" href="' . esc_url( self::url( $step - 1 ) ) . '">' . esc_html__( 'Back', 'talkwyn' ) . '</a>';
		} else {
			echo '<span></span>';
		}
		if ( $submit ) {
			echo '<button class="twa-btn twa-btn--ink twa-btn--lg">' . esc_html( $next_label ) . Talkwyn_Admin::icon( 'arrow' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		} else {
			echo '<a class="twa-btn twa-btn--ink twa-btn--lg" href="' . esc_url( self::url( $step + 1 ) ) . '">' . esc_html( $next_label ) . Talkwyn_Admin::icon( 'arrow' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';
	}

	/**
	 * Step 1: provider key, with a test button.
	 *
	 * @return void
	 */
	private static function step_1() {
		$s = Talkwyn_Settings::all();
		self::heading( 'providers', __( 'Add a free AI key', 'talkwyn' ), __( 'Talkwyn works with free tiers from these providers. Groq is the quickest to set up. Add more later for automatic fallback.', 'talkwyn' ) );
		self::form_open( 1 );
		echo '<div class="twa-keys">';
		foreach ( Talkwyn_Providers::registry() as $id => $p ) {
			if ( empty( $p['free'] ) ) {
				continue;
			}
			echo '<div class="twa-key-row" data-provider="' . esc_attr( $id ) . '"><div class="twa-key-row__head"><span class="twa-avatar">' . esc_html( strtoupper( substr( (string) $p['label'], 0, 1 ) ) ) . '</span><strong>' . esc_html( $p['label'] ) . '</strong>' . ( ! empty( $p['signup'] ) ? '<a class="twa-link" href="' . esc_url( $p['signup'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a free key', 'talkwyn' ) . '</a>' : '' ) . '</div><div class="twa-key-row__fields">';
			foreach ( (array) $p['fields'] as $field ) {
				if ( ( $p['model_field'] ?? '' ) === $field ) {
					echo '<input type="hidden" name="talkwyn[' . esc_attr( $field ) . ']" value="' . esc_attr( (string) $s[ $field ] ) . '">';
					continue;
				}
				$label = ( $p['key_field'] ?? '' ) === $field ? __( 'API key', 'talkwyn' ) : __( 'Account ID', 'talkwyn' );
				echo '<label class="twa-field"><span>' . esc_html( $label ) . '</span><input type="' . ( ( $p['key_field'] ?? '' ) === $field ? 'password' : 'text' ) . '" name="talkwyn[' . esc_attr( $field ) . ']" value="' . esc_attr( (string) $s[ $field ] ) . '" autocomplete="off" spellcheck="false"></label>';
			}
			echo '<div class="twa-key-row__test"><button type="button" class="twa-btn twa-btn--light twa-btn--sm twa-test">' . esc_html__( 'Test', 'talkwyn' ) . '</button><span class="twa-test-status" aria-live="polite"></span></div></div></div>';
		}
		echo '</div>';
		self::nav( 1, true, __( 'Save and continue', 'talkwyn' ) );
		echo '</form>';
	}

	/**
	 * Step 2: scan.
	 *
	 * @return void
	 */
	private static function step_2() {
		self::heading( 'scan', __( 'Let Talkwyn read your website', 'talkwyn' ), __( 'Talkwyn scans your published pages, posts and products and keeps the text in your own database. It updates by itself when you edit content.', 'talkwyn' ) );
		echo '<div class="twa-scan twa-scan--center"><button type="button" class="twa-btn twa-btn--red twa-btn--lg" id="twa-scan">' . Talkwyn_Admin::icon( 'scan' ) . esc_html__( 'Scan my site', 'talkwyn' ) . '</button><span id="twa-scan-status" aria-live="polite">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		/* translators: %s: number of chunks */
		printf( esc_html__( '%s chunks indexed', 'talkwyn' ), esc_html( number_format_i18n( Talkwyn_Indexer::count() ) ) );
		echo '</span></div><div class="twa-progress" aria-hidden="true"><span></span></div>';
		self::nav( 2 );
	}

	/**
	 * Step 3: colour, name and welcome.
	 *
	 * @return void
	 */
	private static function step_3() {
		$s     = Talkwyn_Settings::all();
		$on    = Talkwyn_Contrast::text_on( (string) $s['brand_color'] );
		$ratio = Talkwyn_Contrast::ratio( (string) $s['brand_color'], $on );
		self::heading( 'appearance', __( 'Make it yours', 'talkwyn' ), __( 'Pick your colour. Smart Contrast chooses readable text for it automatically.', 'talkwyn' ) );
		self::form_open( 3 );
		echo '<div class="twa-field"><label for="twa-brand_color">' . esc_html__( 'Brand colour', 'talkwyn' ) . '</label><div class="twa-colorrow"><input type="color" id="twa-brand_color" class="twa-color" name="talkwyn[brand_color]" value="' . esc_attr( (string) $s['brand_color'] ) . '"><div class="twa-contrast" data-ink="' . esc_attr( Talkwyn_Contrast::INK ) . '"><span class="twa-contrast__sample" style="background:' . esc_attr( (string) $s['brand_color'] ) . ';color:' . esc_attr( $on ) . '">' . esc_html__( 'Visitor message', 'talkwyn' ) . '</span><p class="twa-contrast__warn"' . ( $ratio >= 4.5 ? ' hidden' : '' ) . '>' . esc_html__( 'Text on this colour is hard to read. Pick a darker or lighter shade.', 'talkwyn' ) . '</p></div></div></div>';
		echo '<label class="twa-field"><span>' . esc_html__( 'Assistant name', 'talkwyn' ) . '</span><input type="text" name="talkwyn[bot_name]" value="' . esc_attr( (string) $s['bot_name'] ) . '"></label>';
		echo '<label class="twa-field"><span>' . esc_html__( 'Welcome message', 'talkwyn' ) . '</span><textarea name="talkwyn[welcome_message]" rows="3">' . esc_textarea( (string) $s['welcome_message'] ) . '</textarea></label>';
		self::nav( 3, true, __( 'Save and continue', 'talkwyn' ) );
		echo '</form>';
	}

	/**
	 * Step 4: badge question and the optional email opt-in.
	 *
	 * @return void
	 */
	private static function step_4() {
		$s = Talkwyn_Settings::all();
		self::heading( 'star', __( 'Two quick questions', 'talkwyn' ), __( 'Both are optional and off unless you turn them on.', 'talkwyn' ) );
		self::form_open( 4 );
		echo '<input type="hidden" name="talkwyn_bools[]" value="show_badge">';
		echo '<div class="twa-choice"><label class="twa-toggle"><input type="checkbox" role="switch" name="talkwyn[show_badge]" value="1"' . checked( ! empty( $s['show_badge'] ), true, false ) . '> <span class="twa-toggle__text"><span class="twa-toggle__label">' . esc_html__( 'Show a small "Powered by Talkwyn" badge in the chat', 'talkwyn' ) . '</span><span class="twa-toggle__help">' . esc_html__( 'It helps other site owners find Talkwyn. It links to talkwyn.com and adds "Add chat to your website" to the chat menu. You can switch it off any time.', 'talkwyn' ) . '</span></span></label><div class="twa-badge-demo"><span class="twc"><a class="twc-badge" href="#" tabindex="-1"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M8 4.5h16A5.5 5.5 0 0 1 29.5 10v10A5.5 5.5 0 0 1 24 25.5H13.5L7.5 30v-4.6A5.5 5.5 0 0 1 2.5 20V10A5.5 5.5 0 0 1 8 4.5Z"/></svg><span>' . esc_html__( 'Powered by Talkwyn', 'talkwyn' ) . '</span></a></span></div></div>';
		$user = wp_get_current_user();
		echo '<div class="twa-choice"><label class="twa-toggle"><input type="checkbox" role="switch" name="subscribe" value="1"> <span class="twa-toggle__text"><span class="twa-toggle__label">' . esc_html__( 'Email me product news and tips (about once a month)', 'talkwyn' ) . '</span><span class="twa-toggle__help">' . esc_html__( 'Sends this email address, your site URL and your site language to talkwyn.com. Unsubscribe any time.', 'talkwyn' ) . '</span></span></label><label class="twa-field twa-field--inline"><span class="screen-reader-text">' . esc_html__( 'Email', 'talkwyn' ) . '</span><input type="email" name="subscribe_email" value="' . esc_attr( (string) $user->user_email ) . '"></label></div>';
		self::nav( 4, true, __( 'Save and continue', 'talkwyn' ) );
		echo '</form>';
	}

	/**
	 * Step 5: go live.
	 *
	 * @return void
	 */
	private static function step_5() {
		$s = Talkwyn_Settings::all();
		self::heading( 'rocket', __( 'Go live', 'talkwyn' ), __( 'Turn the chat on and open your site to try it. Leads and conversations appear in the Talkwyn menu.', 'talkwyn' ) );
		self::form_open( 5 );
		echo '<input type="hidden" name="talkwyn_bools[]" value="enabled"><div class="twa-choice"><label class="twa-toggle"><input type="checkbox" role="switch" name="talkwyn[enabled]" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . '> <span class="twa-toggle__text"><span class="twa-toggle__label">' . esc_html__( 'Show the chat on my site', 'talkwyn' ) . '</span></span></label></div>';
		self::nav( 5, true, __( 'Finish', 'talkwyn' ) );
		echo '</form>';
	}

	/**
	 * Done screen.
	 *
	 * @return void
	 */
	private static function step_6() {
		echo '<div class="twa-done"><span class="twa-done__mark">' . Talkwyn_Admin::icon( 'check' ) . '</span><h2>' . esc_html__( 'Your chat is live', 'talkwyn' ) . '</h2><p class="twa-intro">' . esc_html__( 'Open your site and ask a question your pages answer. New leads and conversations show up in Talkwyn.', 'talkwyn' ) . '</p><div class="twa-actions twa-actions--center"><a class="twa-btn twa-btn--red twa-btn--lg" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open my site', 'talkwyn' ) . '</a><a class="twa-btn twa-btn--light twa-btn--lg" href="' . esc_url( admin_url( 'admin.php?page=talkwyn' ) ) . '">' . esc_html__( 'Go to the dashboard', 'talkwyn' ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		if ( ! Talkwyn_Admin::pro_active() ) {
			echo '<p class="twa-done__pro">' . esc_html__( 'Want paid AI models, PDF knowledge and analytics?', 'talkwyn' ) . ' <a href="' . esc_url( Talkwyn_Admin::TRIAL_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Try Talkwyn Pro free for 15 days', 'talkwyn' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Send the opt-in to the Talkwyn Hub. Only runs when the box was ticked.
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	private static function subscribe( $email ) {
		$url = (string) apply_filters( 'talkwyn_hub_url', 'https://talkwyn.com/wp-json/talkwyn-hub/v1/' );
		$res = wp_remote_post(
			trailingslashit( $url ) . 'subscribe',
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'email'    => $email,
						'site_url' => home_url( '/' ),
						'source'   => 'plugin_wizard',
						'locale'   => get_locale(),
						'consent'  => true,
					)
				),
			)
		);
		$ok  = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res );
		if ( $ok ) {
			update_option(
				'talkwyn_subscribed',
				array(
					'email' => $email,
					'time'  => time(),
				),
				false
			);
		}
		return $ok;
	}

	/**
	 * Save a step.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_wizard' );
		$step  = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		$input = isset( $_POST['talkwyn'] ) && is_array( $_POST['talkwyn'] ) ? wp_unslash( $_POST['talkwyn'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$bools = isset( $_POST['talkwyn_bools'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['talkwyn_bools'] ) ) : array();
		Talkwyn_Settings::update( Talkwyn_Settings::sanitize( $input, $bools ) );
		if ( 4 === $step && ! empty( $_POST['subscribe'] ) ) {
			$email = isset( $_POST['subscribe_email'] ) ? sanitize_email( wp_unslash( $_POST['subscribe_email'] ) ) : '';
			if ( is_email( $email ) ) {
				self::subscribe( $email );
			}
		}
		if ( $step >= 5 ) {
			update_option( 'talkwyn_onboarding_done', time(), false );
		}
		wp_safe_redirect( self::url( $step + 1 ) );
		exit;
	}
}
