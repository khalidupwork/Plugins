<?php
/**
 * Software products admin.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Repository\Products;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every admin-post handler calls Admin::guard() (capability + check_admin_referer) before reading input; an id needed to build the nonce action is read with absint() first.

/**
 * Manage software products (e.g. talkwyn-pro, future Shopify app or add-ons).
 */
final class ProductsPage {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_save_product', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_twh_delete_product', array( self::class, 'handle_delete' ) );
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$edit = isset( $_GET['product'] ) ? Products::find( absint( $_GET['product'] ) ) : null;
		$meta = $edit ? (array) $edit['meta'] : array();
		?>
		<div class="wrap twh-wrap">
			<h1><?php esc_html_e( 'Software products', 'talkwyn-hub' ); ?></h1>
			<div class="twh-columns">
				<div class="twh-panel">
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Name', 'talkwyn-hub' ); ?></th>
							<th><?php esc_html_e( 'Slug (sent by the client)', 'talkwyn-hub' ); ?></th>
							<th><?php esc_html_e( 'Latest stable', 'talkwyn-hub' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody>
						<?php foreach ( Products::all() as $p ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
								<td><code><?php echo esc_html( $p['slug'] ); ?></code></td>
								<td><?php echo esc_html( $p['latest_version'] ? $p['latest_version'] : __( 'None', 'talkwyn-hub' ) ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=twh-products&product=' . (int) $p['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'talkwyn-hub' ); ?></a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="twh-inline twh-confirm-form">
										<input type="hidden" name="action" value="twh_delete_product">
										<input type="hidden" name="product" value="<?php echo (int) $p['id']; ?>">
										<?php wp_nonce_field( 'twh_delete_product_' . (int) $p['id'] ); ?>
										<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'talkwyn-hub' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="twh-panel">
					<h2><?php echo $edit ? esc_html__( 'Edit product', 'talkwyn-hub' ) : esc_html__( 'Add product', 'talkwyn-hub' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="twh_save_product">
						<input type="hidden" name="product" value="<?php echo $edit ? (int) $edit['id'] : 0; ?>">
						<?php wp_nonce_field( 'twh_save_product' ); ?>
						<table class="form-table" role="presentation">
							<tr><th><label for="twh-p-name"><?php esc_html_e( 'Name', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-p-name" name="name" class="regular-text" required value="<?php echo esc_attr( $edit ? $edit['name'] : '' ); ?>"></td></tr>
							<tr><th><label for="twh-p-slug"><?php esc_html_e( 'Slug', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-p-slug" name="slug" class="regular-text" required pattern="[a-z0-9-]+" value="<?php echo esc_attr( $edit ? $edit['slug'] : '' ); ?>" <?php disabled( (bool) $edit ); ?>>
								<p class="description"><?php esc_html_e( 'Lowercase, e.g. talkwyn-pro. Cannot be changed later because clients send it.', 'talkwyn-hub' ); ?></p></td></tr>
							<tr><th><label for="twh-p-home"><?php esc_html_e( 'Homepage', 'talkwyn-hub' ); ?></label></th>
								<td><input id="twh-p-home" name="homepage" type="url" class="regular-text" value="<?php echo esc_attr( $edit ? $edit['homepage'] : '' ); ?>"></td></tr>
							<tr><th><?php esc_html_e( 'Icons', 'talkwyn-hub' ); ?></th>
								<td>
									<input name="icon_1x" type="url" class="regular-text" placeholder="<?php esc_attr_e( '128×128 icon URL', 'talkwyn-hub' ); ?>" value="<?php echo esc_attr( (string) ( $meta['icons']['1x'] ?? '' ) ); ?>"><br>
									<input name="icon_2x" type="url" class="regular-text" placeholder="<?php esc_attr_e( '256×256 icon URL', 'talkwyn-hub' ); ?>" value="<?php echo esc_attr( (string) ( $meta['icons']['2x'] ?? '' ) ); ?>">
								</td></tr>
							<tr><th><?php esc_html_e( 'Banners', 'talkwyn-hub' ); ?></th>
								<td>
									<input name="banner_low" type="url" class="regular-text" placeholder="<?php esc_attr_e( '772×250 banner URL', 'talkwyn-hub' ); ?>" value="<?php echo esc_attr( (string) ( $meta['banners']['low'] ?? '' ) ); ?>"><br>
									<input name="banner_high" type="url" class="regular-text" placeholder="<?php esc_attr_e( '1544×500 banner URL', 'talkwyn-hub' ); ?>" value="<?php echo esc_attr( (string) ( $meta['banners']['high'] ?? '' ) ); ?>">
								</td></tr>
							<tr><th><label for="twh-p-desc"><?php esc_html_e( 'Description (Markdown)', 'talkwyn-hub' ); ?></label></th>
								<td><textarea id="twh-p-desc" name="description" rows="5" class="large-text"><?php echo esc_textarea( (string) ( $meta['description'] ?? '' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Shown in the "View details" modal in WordPress.', 'talkwyn-hub' ); ?></p></td></tr>
						</table>
						<?php submit_button( $edit ? __( 'Save product', 'talkwyn-hub' ) : __( 'Add product', 'talkwyn-hub' ) ); ?>
						<?php if ( $edit ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=twh-products' ) ); ?>"><?php esc_html_e( 'Cancel', 'talkwyn-hub' ); ?></a>
						<?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save.
	 */
	public static function handle_save(): void {
		Admin::guard( 'twh_save_product' );
		$id   = absint( $_POST['product'] ?? 0 );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$url  = static function ( string $key ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
			return isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
		};
		$meta = array(
			'icons'       => array_filter(
				array(
					'1x' => $url( 'icon_1x' ),
					'2x' => $url( 'icon_2x' ),
				)
			),
			'banners'     => array_filter(
				array(
					'low'  => $url( 'banner_low' ),
					'high' => $url( 'banner_high' ),
				)
			),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
		);
		if ( '' === $name ) {
			Admin::redirect( 'twh-products', 'invalid' );
		}
		if ( $id ) {
			Products::update(
				$id,
				array(
					'name'     => $name,
					'homepage' => $url( 'homepage' ),
					'meta'     => $meta,
				)
			);
			Admin::redirect( 'twh-products', 'saved', array( 'product' => $id ) );
		}
		$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( '' === $slug ) {
			Admin::redirect( 'twh-products', 'invalid' );
		}
		if ( Products::find_by_slug( $slug ) ) {
			Admin::redirect( 'twh-products', 'slug_exists' );
		}
		Products::create( $slug, $name, $url( 'homepage' ), $meta );
		Admin::redirect( 'twh-products', 'saved' );
	}

	/**
	 * Delete.
	 */
	public static function handle_delete(): void {
		$id = absint( $_POST['product'] ?? 0 );
		Admin::guard( 'twh_delete_product_' . $id );
		Admin::redirect( 'twh-products', Products::delete( $id ) ? 'deleted' : 'delete_blocked' );
	}
}
