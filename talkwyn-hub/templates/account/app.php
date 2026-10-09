<?php
/**
 * Customer dashboard layout for My Account (logged in).
 *
 * Override by copying to yourtheme/talkwyn-hub/account/app.php.
 *
 * @package TalkwynHub
 */

use TWH\Account\App;

defined( 'ABSPATH' ) || exit;

$twh_user     = wp_get_current_user();
$twh_endpoint = App::endpoint();
$twh_nav      = App::nav();
$twh_name     = '' !== $twh_user->first_name ? $twh_user->first_name : $twh_user->display_name;
$twh_full     = trim( $twh_user->first_name . ' ' . $twh_user->last_name );
$twh_full     = '' !== $twh_full ? $twh_full : $twh_user->display_name;
$twh_initials = strtoupper( function_exists( 'mb_substr' ) ? mb_substr( (string) $twh_user->first_name, 0, 1 ) . mb_substr( (string) $twh_user->last_name, 0, 1 ) : substr( (string) $twh_user->first_name, 0, 1 ) . substr( (string) $twh_user->last_name, 0, 1 ) );
$twh_initials = '' !== $twh_initials ? $twh_initials : strtoupper( substr( (string) $twh_user->user_email, 0, 2 ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'twh-app-body' ); ?>>
<?php wp_body_open(); ?>
<div class="twh-app">
	<aside class="twh-app__side" aria-label="<?php esc_attr_e( 'Account', 'talkwyn-hub' ); ?>">
		<div class="twh-app__brand">
			<a class="twh-app__logo" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
				<img src="<?php echo esc_url( TWH_URL . 'assets/img/talkwyn-mark.svg' ); ?>" alt="" width="30" height="30">
				<span><?php echo esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></span>
			</a>
			<a class="twh-app__back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo App::icon( 'back', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Back to homepage', 'talkwyn-hub' ); ?></a>
		</div>

		<p class="twh-app__label"><?php esc_html_e( 'Workspace', 'talkwyn-hub' ); ?></p>
		<nav class="twh-app__nav">
			<?php foreach ( $twh_nav as $twh_item ) : ?>
				<?php $twh_on = ! $twh_item['external'] && in_array( $twh_endpoint, $twh_item['endpoints'], true ); ?>
				<a class="twh-app__link<?php echo $twh_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( $twh_item['url'] ); ?>"<?php echo $twh_on ? ' aria-current="page"' : ''; ?>>
					<?php echo App::icon( $twh_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $twh_item['label'] ); ?></span>
					<?php if ( $twh_item['count'] > 0 ) : ?>
						<span class="twh-app__count"><?php echo esc_html( number_format_i18n( $twh_item['count'] ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="twh-app__promo">
			<p class="twh-app__promo-title"><?php esc_html_e( 'Need more sites?', 'talkwyn-hub' ); ?></p>
			<p><?php esc_html_e( 'Each license covers a set number of sites. Buy another to run Talkwyn Pro on more domains.', 'talkwyn-hub' ); ?></p>
			<a class="twh-app__btn twh-app__btn--red twh-app__btn--block" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>"><?php esc_html_e( 'Buy another license', 'talkwyn-hub' ); ?></a>
		</div>

		<div class="twh-app__user">
			<span class="twh-app__avatar" aria-hidden="true"><?php echo esc_html( $twh_initials ); ?></span>
			<span class="twh-app__who"><strong><?php echo esc_html( $twh_full ); ?></strong><span><?php echo esc_html( $twh_user->user_email ); ?></span></span>
			<a class="twh-app__edit" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>" aria-label="<?php esc_attr_e( 'Edit account', 'talkwyn-hub' ); ?>"><?php echo App::icon( 'edit', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
		</div>
	</aside>

	<div class="twh-app__main">
		<header class="twh-app__top">
			<h1 class="twh-app__title"><?php echo esc_html( App::title() ); ?></h1>
			<a class="twh-app__btn twh-app__btn--light" href="<?php echo esc_url( wc_logout_url() ); ?>"><?php echo App::icon( 'logout', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Log out', 'talkwyn-hub' ); ?></a>
		</header>

		<main class="twh-app__content woocommerce" id="main">
			<?php
			if ( function_exists( 'wc_print_notices' ) ) {
				wc_print_notices();
			}
			if ( '' === $twh_endpoint ) {
				$twh_o = App::overview();
				include TWH_DIR . 'templates/account/overview.php';
			} else {
				if ( in_array( $twh_endpoint, array( 'orders', 'view-order', 'edit-address' ), true ) ) {
					echo '<nav class="twh-app__subnav">';
					foreach ( array(
						'orders'       => __( 'Orders and invoices', 'talkwyn-hub' ),
						'edit-address' => __( 'Billing address', 'talkwyn-hub' ),
					) as $twh_ep => $twh_label ) {
						$twh_cur = $twh_ep === $twh_endpoint || ( 'orders' === $twh_ep && 'view-order' === $twh_endpoint );
						echo '<a class="' . ( $twh_cur ? 'is-active' : '' ) . '" href="' . esc_url( wc_get_account_endpoint_url( $twh_ep ) ) . '">' . esc_html( $twh_label ) . '</a>';
					}
					echo '</nav>';
				}
				echo '<div class="woocommerce-MyAccount-content twh-app__panel">';
				do_action( 'woocommerce_account_content' );
				echo '</div>';
			}
			?>
		</main>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
