<?php
/**
 * Talkwyn Site Settings: one place for prices, counts, links and analytics.
 *
 * Every {{...}} placeholder in the site copy resolves through talkwyn_value(),
 * exposed as [tw_value key="..."] and as the "talkwyn/value" block binding.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

const TALKWYN_SETTINGS = 'talkwyn_site_settings';

/**
 * Default settings.
 *
 * @return array<string, mixed>
 */
function talkwyn_settings_defaults(): array {
	return array(
		'price_source'       => 'woocommerce',
		'product_personal'   => 0,
		'product_business'   => 0,
		'product_agency'     => 0,
		'product_lifetime'   => 0,
		'price_personal'     => '',
		'price_business'     => '',
		'price_agency'       => '',
		'price_lifetime'     => '',
		'show_lifetime'      => 0,
		'founding_enabled'   => 1,
		'founding_seats'     => 100,
		'founding_remaining' => 100,
		'refund_days'        => 14,
		'wporg_url'          => '',
		'free_zip_url'       => '',
		'waitlist_action'    => '',
		'contact_email'      => '',
		'social_x'           => '',
		'social_linkedin'    => '',
		'social_facebook'    => '',
		'social_youtube'     => '',
		'social_instagram'   => '',
		'social_github'      => '',
		'analytics'          => 'none',
		'ga4_id'             => '',
		'plausible_domain'   => '',
		'plausible_src'      => 'https://plausible.io/js/script.js',
		'demo_shortcode'     => '',
		'tidio_checked_on'   => '',
		'tidio_price_note'   => '',
	);
}

/**
 * All settings.
 *
 * @return array<string, mixed>
 */
function talkwyn_settings(): array {
	static $cache = null;
	if ( null === $cache ) {
		$stored = get_option( TALKWYN_SETTINGS, array() );
		$cache  = array_merge( talkwyn_settings_defaults(), is_array( $stored ) ? $stored : array() );
	}
	return $cache;
}

/**
 * One setting.
 *
 * @param string $key Key.
 * @return mixed
 */
function talkwyn_setting( string $key ) {
	$all = talkwyn_settings();
	return $all[ $key ] ?? null;
}

/**
 * Plan price as plain text (e.g. "$99"), from WooCommerce when configured.
 *
 * @param string $plan personal|business|agency|lifetime.
 */
function talkwyn_plan_price( string $plan ): string {
	$product_id = (int) talkwyn_setting( 'product_' . $plan );
	if ( 'woocommerce' === talkwyn_setting( 'price_source' ) && $product_id && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$price = (float) $product->get_price();
			$text  = wp_strip_all_tags( html_entity_decode( wc_price( $price, array( 'decimals' => floor( $price ) === $price ? 0 : 2 ) ) ) );
			return trim( $text );
		}
	}
	return (string) talkwyn_setting( 'price_' . $plan );
}

/**
 * URL that "Install free" buttons point to.
 */
function talkwyn_install_url(): string {
	$wporg = (string) talkwyn_setting( 'wporg_url' );
	return '' !== $wporg ? $wporg : home_url( '/download/' );
}

/**
 * Add-to-cart URL that lands on checkout.
 *
 * @param string $plan Plan.
 */
function talkwyn_plan_checkout_url( string $plan ): string {
	$id = (int) talkwyn_setting( 'product_' . $plan );
	if ( ! $id || ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return home_url( '/pricing/' );
	}
	$product = wc_get_product( $id );
	if ( ! $product ) {
		return home_url( '/pricing/' );
	}
	$args = array( 'add-to-cart' => $product->get_parent_id() ? $product->get_parent_id() : $id );
	if ( $product->get_parent_id() ) {
		$args['variation_id'] = $id;
		foreach ( $product->get_variation_attributes() as $name => $value ) {
			$args[ $name ] = $value;
		}
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), wc_get_checkout_url() );
}

/**
 * Resolve a placeholder key to a display value.
 *
 * @param string $key Key.
 */
function talkwyn_value( string $key ): string {
	switch ( $key ) {
		case 'price_personal':
		case 'price_business':
		case 'price_agency':
		case 'price_lifetime':
			$price = talkwyn_plan_price( substr( $key, 6 ) );
			return '' !== $price ? $price : __( 'See pricing', 'talkwyn' );
		case 'founding_seats':
		case 'founding_remaining':
		case 'refund_days':
			return number_format_i18n( (int) talkwyn_setting( $key ) );
		case 'install_url':
			return talkwyn_install_url();
		case 'year':
			return gmdate( 'Y' );
		case 'contact_email':
			$email = (string) talkwyn_setting( 'contact_email' );
			return '' !== $email ? $email : (string) get_option( 'admin_email' );
	}
	return '';
}

/**
 * [tw_value key="price_business"].
 *
 * @param array<string, string>|string $atts Attributes.
 */
function talkwyn_value_shortcode( $atts ): string {
	$atts = shortcode_atts(
		array(
			'key'      => '',
			'suffix'   => '',
			'fallback' => '',
		),
		$atts,
		'tw_value'
	);
	$key  = sanitize_key( $atts['key'] );
	// An unset price reads naturally instead of "See pricing per year".
	if ( 0 === strpos( $key, 'price_' ) && '' === talkwyn_plan_price( substr( $key, 6 ) ) && '' !== $atts['fallback'] ) {
		return esc_html( $atts['fallback'] );
	}
	return esc_html( talkwyn_value( $key ) . $atts['suffix'] );
}
add_shortcode( 'tw_value', 'talkwyn_value_shortcode' );

/**
 * Block binding source "talkwyn/value" (WP 6.5+): {"key":"price_business"}.
 */
add_action(
	'init',
	static function () {
		if ( function_exists( 'register_block_bindings_source' ) ) {
			register_block_bindings_source(
				'talkwyn/value',
				array(
					'label'              => __( 'Talkwyn Site Settings', 'talkwyn' ),
					'get_value_callback' => static function ( array $args ) {
						return talkwyn_value( sanitize_key( (string) ( $args['key'] ?? '' ) ) );
					},
				)
			);
		}
	}
);

/**
 * Settings screen.
 */
add_action(
	'admin_menu',
	static function () {
		add_theme_page( __( 'Talkwyn Site Settings', 'talkwyn' ), __( 'Talkwyn Site Settings', 'talkwyn' ), 'edit_theme_options', 'talkwyn-site-settings', 'talkwyn_settings_render' );
	}
);

/**
 * Field definitions for the settings form.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function talkwyn_settings_fields(): array {
	return array(
		__( 'Prices', 'talkwyn' )             => array(
			array(
				'price_source',
				__( 'Price source', 'talkwyn' ),
				'select',
				array(
					'woocommerce' => __( 'WooCommerce products (recommended)', 'talkwyn' ),
					'manual'      => __( 'Manual prices below', 'talkwyn' ),
				),
			),
			array( 'product_personal', __( 'Personal product or variation ID', 'talkwyn' ), 'number' ),
			array( 'product_business', __( 'Business product or variation ID', 'talkwyn' ), 'number' ),
			array( 'product_agency', __( 'Agency product or variation ID', 'talkwyn' ), 'number' ),
			array( 'product_lifetime', __( 'Lifetime product or variation ID', 'talkwyn' ), 'number' ),
			array( 'price_personal', __( 'Manual price, Personal (e.g. $49)', 'talkwyn' ), 'text' ),
			array( 'price_business', __( 'Manual price, Business', 'talkwyn' ), 'text' ),
			array( 'price_agency', __( 'Manual price, Agency', 'talkwyn' ), 'text' ),
			array( 'price_lifetime', __( 'Manual price, Lifetime', 'talkwyn' ), 'text' ),
			array( 'show_lifetime', __( 'Show the Lifetime plan', 'talkwyn' ), 'checkbox' ),
		),
		__( 'Founding customers', 'talkwyn' ) => array(
			array( 'founding_enabled', __( 'Show founding customer sections', 'talkwyn' ), 'checkbox' ),
			array( 'founding_seats', __( 'Founding seats total', 'talkwyn' ), 'number' ),
			array( 'founding_remaining', __( 'Founding seats remaining', 'talkwyn' ), 'number' ),
		),
		__( 'Policies and links', 'talkwyn' ) => array(
			array( 'refund_days', __( 'Refund window (days)', 'talkwyn' ), 'number' ),
			array( 'wporg_url', __( 'WordPress.org plugin URL (empty until the listing is live)', 'talkwyn' ), 'url' ),
			array( 'free_zip_url', __( 'Direct ZIP download of the free plugin', 'talkwyn' ), 'url' ),
			array( 'waitlist_action', __( 'Shopify waitlist form target URL (empty = store sign-ups here and email you)', 'talkwyn' ), 'url' ),
			array( 'contact_email', __( 'Contact form recipient (empty = site admin email)', 'talkwyn' ), 'email' ),
			array( 'demo_shortcode', __( 'Live demo shortcode (when the Talkwyn plugin runs on this site), e.g. [talkwyn_chat mode="inline" profile="demo-clinic"]', 'talkwyn' ), 'text' ),
		),
		__( 'Comparisons', 'talkwyn' )        => array(
			array( 'tidio_price_note', __( 'Tidio pricing summary from tidio.com/pricing', 'talkwyn' ), 'text' ),
			array( 'tidio_checked_on', __( 'Tidio pricing checked on (date)', 'talkwyn' ), 'date' ),
		),
		__( 'Social profiles', 'talkwyn' )    => array(
			array( 'social_x', 'X (Twitter)', 'url' ),
			array( 'social_linkedin', 'LinkedIn', 'url' ),
			array( 'social_facebook', 'Facebook', 'url' ),
			array( 'social_youtube', 'YouTube', 'url' ),
			array( 'social_instagram', 'Instagram', 'url' ),
			array( 'social_github', 'GitHub', 'url' ),
		),
		__( 'Analytics', 'talkwyn' )          => array(
			array(
				'analytics',
				__( 'Provider', 'talkwyn' ),
				'select',
				array(
					'none'      => __( 'None', 'talkwyn' ),
					'ga4'       => 'Google Analytics 4',
					'plausible' => 'Plausible',
				),
			),
			array( 'ga4_id', __( 'GA4 measurement ID (G-XXXXXXX)', 'talkwyn' ), 'text' ),
			array( 'plausible_domain', __( 'Plausible domain (e.g. talkwyn.com)', 'talkwyn' ), 'text' ),
			array( 'plausible_src', __( 'Plausible script URL', 'talkwyn' ), 'url' ),
		),
	);
}

/**
 * Render the settings page.
 */
function talkwyn_settings_render(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$values = talkwyn_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Talkwyn Site Settings', 'talkwyn' ); ?></h1>
		<p><?php esc_html_e( 'These values fill every placeholder on the site. Use [tw_value key="price_business"] in any text, or bind a paragraph or heading to the "Talkwyn Site Settings" source.', 'talkwyn' ); ?></p>
		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'talkwyn' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="talkwyn_save_settings">
			<?php wp_nonce_field( 'talkwyn_save_settings' ); ?>
			<?php foreach ( talkwyn_settings_fields() as $section => $fields ) : ?>
				<h2><?php echo esc_html( $section ); ?></h2>
				<table class="form-table" role="presentation">
				<?php foreach ( $fields as $field ) : ?>
					<?php list( $key, $label, $type ) = $field; ?>
					<tr>
						<th scope="row"><label for="tw-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
						<?php if ( 'select' === $type ) : ?>
							<select id="tw-<?php echo esc_attr( $key ); ?>" name="tw[<?php echo esc_attr( $key ); ?>]">
								<?php foreach ( $field[3] as $opt => $opt_label ) : ?>
									<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $values[ $key ], $opt ); ?>><?php echo esc_html( $opt_label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'checkbox' === $type ) : ?>
							<input id="tw-<?php echo esc_attr( $key ); ?>" type="checkbox" name="tw[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( (int) $values[ $key ], 1 ); ?>>
						<?php else : ?>
							<input id="tw-<?php echo esc_attr( $key ); ?>" class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="tw[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $values[ $key ] ); ?>">
							<?php if ( 0 === strpos( $key, 'product_' ) && (int) $values[ $key ] ) : ?>
								<span class="description"><?php echo esc_html( talkwyn_plan_price( substr( $key, 8 ) ) ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
		<?php do_action( 'talkwyn_settings_after_form' ); ?>
	</div>
	<?php
}

/**
 * Save handler.
 */
add_action(
	'admin_post_talkwyn_save_settings',
	static function () {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'talkwyn_save_settings' );
		$raw   = isset( $_POST['tw'] ) && is_array( $_POST['tw'] ) ? wp_unslash( $_POST['tw'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$clean = array();
		foreach ( talkwyn_settings_fields() as $fields ) {
			foreach ( $fields as $field ) {
				list( $key, , $type ) = $field;
				$value                = $raw[ $key ] ?? '';
				switch ( $type ) {
					case 'checkbox':
						$clean[ $key ] = empty( $value ) ? 0 : 1;
						break;
					case 'number':
						$clean[ $key ] = absint( $value );
						break;
					case 'url':
						$clean[ $key ] = esc_url_raw( (string) $value );
						break;
					case 'email':
						$clean[ $key ] = sanitize_email( (string) $value );
						break;
					case 'select':
						$clean[ $key ] = array_key_exists( (string) $value, $field[3] ) ? (string) $value : (string) array_key_first( $field[3] );
						break;
					default:
						$clean[ $key ] = sanitize_text_field( (string) $value );
				}
			}
		}
		update_option( TALKWYN_SETTINGS, $clean );
		wp_safe_redirect( admin_url( 'themes.php?page=talkwyn-site-settings&updated=1' ) );
		exit;
	}
);
