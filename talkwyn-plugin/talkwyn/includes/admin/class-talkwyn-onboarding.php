<?php
/**
 * First-run setup wizard: scan, add a key, test, style, go live.
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
	 * @return array<int, string>
	 */
	private static function steps() {
		return array(
			1 => __( 'Scan your site', 'talkwyn' ),
			2 => __( 'Add an AI key', 'talkwyn' ),
			3 => __( 'Test the connection', 'talkwyn' ),
			4 => __( 'Colour and welcome', 'talkwyn' ),
			5 => __( 'Go live', 'talkwyn' ),
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
		$step  = isset( $_GET['step'] ) ? min( 5, max( 1, absint( $_GET['step'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$steps = self::steps();
		echo '<div class="wrap twa twa-wizard"><header class="twa-head"><div class="twa-brand"><img src="' . esc_url( TALKWYN_URL . 'assets/img/talkwyn-mark.svg' ) . '" alt="" width="36" height="36"><div><h1>' . esc_html__( 'Set up Talkwyn', 'talkwyn' ) . '</h1><p>' . esc_html__( 'About five minutes. You can change everything later.', 'talkwyn' ) . '</p></div></div><div class="twa-head__actions"><a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=talkwyn' ) ) . '">' . esc_html__( 'Skip setup', 'talkwyn' ) . '</a></div></header>';
		echo '<ol class="twa-wizard__steps">';
		foreach ( $steps as $n => $label ) {
			$class = $n < $step ? 'is-done' : ( $n === $step ? 'is-current' : '' );
			echo '<li class="' . esc_attr( $class ) . '"' . ( $n === $step ? ' aria-current="step"' : '' ) . '><a href="' . esc_url( self::url( $n ) ) . '"><span>' . esc_html( (string) $n ) . '</span>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ol><section class="twa-card twa-wizard__card">';
		call_user_func( array( __CLASS__, 'step_' . $step ) );
		echo '</section></div>';
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
	 * @param int  $step   Step.
	 * @param bool $submit Next submits the form.
	 * @param string $next_label Label.
	 * @return void
	 */
	private static function nav( $step, $submit = false, $next_label = '' ) {
		$next_label = '' !== $next_label ? $next_label : __( 'Continue', 'talkwyn' );
		echo '<div class="twa-wizard__nav">';
		if ( $step > 1 ) {
			echo '<a class="button button-large" href="' . esc_url( self::url( $step - 1 ) ) . '">' . esc_html__( 'Back', 'talkwyn' ) . '</a>';
		} else {
			echo '<span></span>';
		}
		if ( $submit ) {
			echo '<button class="button button-primary button-large">' . esc_html( $next_label ) . '</button>';
		} else {
			echo '<a class="button button-primary button-large" href="' . esc_url( self::url( $step + 1 ) ) . '">' . esc_html( $next_label ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Step 1: scan.
	 *
	 * @return void
	 */
	private static function step_1() {
		echo '<h2>' . esc_html__( 'Let Talkwyn read your website', 'talkwyn' ) . '</h2><p class="twa-intro">' . esc_html__( 'Talkwyn scans your published pages, posts and products and keeps the text in your own database. It updates by itself when you edit content.', 'talkwyn' ) . '</p>';
		echo '<div class="twa-scan"><button type="button" class="button button-primary button-hero" id="twa-scan">' . esc_html__( 'Scan my site', 'talkwyn' ) . '</button> <span id="twa-scan-status" aria-live="polite">';
		/* translators: %s: number of chunks */
		printf( esc_html__( '%s chunks indexed', 'talkwyn' ), esc_html( number_format_i18n( Talkwyn_Indexer::count() ) ) );
		echo '</span></div><div class="twa-progress" aria-hidden="true"><span></span></div>';
		self::nav( 1 );
	}

	/**
	 * Step 2: key.
	 *
	 * @return void
	 */
	private static function step_2() {
		$s = Talkwyn_Settings::all();
		echo '<h2>' . esc_html__( 'Add a free AI key', 'talkwyn' ) . '</h2><p class="twa-intro">' . esc_html__( 'Talkwyn works with free tiers from these providers. Groq is the quickest to set up. Add more later for automatic fallback.', 'talkwyn' ) . '</p>';
		self::form_open( 2 );
		echo '<div class="twa-keys">';
		foreach ( Talkwyn_Providers::registry() as $id => $p ) {
			if ( empty( $p['free'] ) ) {
				continue;
			}
			echo '<div class="twa-key-row"><div class="twa-key-row__head"><strong>' . esc_html( $p['label'] ) . '</strong>' . ( ! empty( $p['signup'] ) ? ' <a href="' . esc_url( $p['signup'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a free key', 'talkwyn' ) . '</a>' : '' ) . '</div>';
			foreach ( (array) $p['fields'] as $field ) {
				if ( ( $p['model_field'] ?? '' ) === $field ) {
					continue;
				}
				$label = ( $p['key_field'] ?? '' ) === $field ? __( 'API key', 'talkwyn' ) : __( 'Account ID', 'talkwyn' );
				echo '<label class="twa-field"><span>' . esc_html( $label ) . '</span><input type="' . ( ( $p['key_field'] ?? '' ) === $field ? 'password' : 'text' ) . '" name="talkwyn[' . esc_attr( $field ) . ']" value="' . esc_attr( (string) $s[ $field ] ) . '" autocomplete="off" spellcheck="false"></label>';
			}
			echo '</div>';
		}
		echo '</div>';
		self::nav( 2, true, __( 'Save and continue', 'talkwyn' ) );
		echo '</form>';
	}

	/**
	 * Step 3: test.
	 *
	 * @return void
	 */
	private static function step_3() {
		$s     = Talkwyn_Settings::all();
		$ready = Talkwyn_Providers::order( $s );
		$reg   = Talkwyn_Providers::registry();
		echo '<h2>' . esc_html__( 'Test the connection', 'talkwyn' ) . '</h2>';
		if ( ! $ready ) {
			echo '<p class="twa-intro">' . esc_html__( 'No key yet. Go back and add one, or continue and add it later. Until then the chat answers from your pages without AI wording.', 'talkwyn' ) . '</p>';
		} else {
			echo '<p class="twa-intro">' . esc_html__( 'Send a short test message to each provider you added.', 'talkwyn' ) . '</p>';
			foreach ( $ready as $id ) {
				echo '<div class="twa-provider twa-test-row" data-provider="' . esc_attr( $id ) . '"><strong>' . esc_html( $reg[ $id ]['label'] ) . '</strong> <button type="button" class="button twa-test">' . esc_html__( 'Test', 'talkwyn' ) . '</button> <span class="twa-test-status" aria-live="polite"></span></div>';
			}
		}
		self::nav( 3 );
	}

	/**
	 * Step 4: colour and welcome.
	 *
	 * @return void
	 */
	private static function step_4() {
		$s = Talkwyn_Settings::all();
		echo '<h2>' . esc_html__( 'Make it yours', 'talkwyn' ) . '</h2><p class="twa-intro">' . esc_html__( 'Smart Contrast picks readable text for your colour automatically.', 'talkwyn' ) . '</p>';
		self::form_open( 4 );
		echo '<div class="twa-field"><label for="twa-brand_color">' . esc_html__( 'Brand colour', 'talkwyn' ) . '</label><input type="color" id="twa-brand_color" class="twa-color" name="talkwyn[brand_color]" value="' . esc_attr( (string) $s['brand_color'] ) . '"></div>';
		$on    = Talkwyn_Contrast::text_on( (string) $s['brand_color'] );
		$ratio = Talkwyn_Contrast::ratio( (string) $s['brand_color'], $on );
		echo '<div class="twa-contrast" data-ink="' . esc_attr( Talkwyn_Contrast::INK ) . '"><span class="twa-contrast__sample" style="background:' . esc_attr( (string) $s['brand_color'] ) . ';color:' . esc_attr( $on ) . '">' . esc_html__( 'Visitor message', 'talkwyn' ) . '</span><p class="twa-contrast__warn"' . ( $ratio >= 4.5 ? ' hidden' : '' ) . '>' . esc_html__( 'Text on this colour is hard to read. Pick a darker or lighter shade.', 'talkwyn' ) . '</p></div>';
		echo '<label class="twa-field"><span>' . esc_html__( 'Assistant name', 'talkwyn' ) . '</span><input type="text" name="talkwyn[bot_name]" value="' . esc_attr( (string) $s['bot_name'] ) . '"></label>';
		echo '<label class="twa-field"><span>' . esc_html__( 'Welcome message', 'talkwyn' ) . '</span><textarea name="talkwyn[welcome_message]" rows="3">' . esc_textarea( (string) $s['welcome_message'] ) . '</textarea></label>';
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
		echo '<h2>' . esc_html__( 'Go live', 'talkwyn' ) . '</h2><p class="twa-intro">' . esc_html__( 'Turn the chat on and open your site to try it. Leads and conversations appear in the Talkwyn menu.', 'talkwyn' ) . '</p>';
		self::form_open( 5 );
		echo '<input type="hidden" name="talkwyn_bools[]" value="enabled"><label class="twa-toggle twa-toggle--big"><input type="checkbox" name="talkwyn[enabled]" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . '> <span>' . esc_html__( 'Show the chat on my site', 'talkwyn' ) . '</span></label>';
		self::nav( 5, true, __( 'Finish', 'talkwyn' ) );
		echo '</form>';
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
		if ( $step >= 5 ) {
			update_option( 'talkwyn_onboarding_done', time(), false );
			wp_safe_redirect( admin_url( 'admin.php?page=talkwyn&updated=1' ) );
			exit;
		}
		wp_safe_redirect( self::url( $step + 1 ) );
		exit;
	}
}
