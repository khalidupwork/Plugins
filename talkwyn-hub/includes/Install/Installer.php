<?php
/**
 * Activation, deactivation and schema upgrades.
 *
 * @package TalkwynHub
 */

namespace TWH\Install;

use TWH\Repository\Products;
use TWH\Support\SigningKeys;
use TWH\Support\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class Installer {

	public const DB_VERSION_OPTION = 'twh_db_version';

	/**
	 * Upgrade routines keyed by the version they upgrade to.
	 * dbDelta handles additive column/index changes; put data migrations here.
	 *
	 * @var array<string, callable-string>
	 */
	private const MIGRATIONS = array();

	/**
	 * Plugin activation.
	 */
	public static function activate(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			deactivate_plugins( TWH_BASENAME );
			wp_die( esc_html__( 'Talkwyn Hub requires the PHP sodium extension.', 'talkwyn-hub' ) );
		}
		self::maybe_upgrade( true );
		SigningKeys::ensure();
		Storage::ensure_dir();
		self::seed_default_product();

		\TWH\Account\Account::add_endpoints();
		\TWH\Partners\PartnerAccount::endpoint();
		\TWH\Partners\Tracking::rewrite();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'twh_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'twh_daily' );
		}
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'twh_daily' );
		flush_rewrite_rules();
	}

	/**
	 * Run dbDelta and migrations when the stored schema version is older.
	 *
	 * @param bool $force Run dbDelta even if versions match.
	 */
	public static function maybe_upgrade( bool $force = false ): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '0' );
		if ( ! $force && version_compare( $installed, TWH_DB_VERSION, '>=' ) ) {
			return;
		}
		Schema::install();
		foreach ( self::MIGRATIONS as $version => $callback ) {
			if ( version_compare( $installed, $version, '<' ) && is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
		update_option( self::DB_VERSION_OPTION, TWH_DB_VERSION );
		if ( ! $force ) {
			// New endpoints or rewrite rules may ship with a schema change: rebuild once they are registered.
			add_action( 'init', 'flush_rewrite_rules', 999 );
		}
	}

	/**
	 * Create the talkwyn-pro product on first install.
	 */
	private static function seed_default_product(): void {
		if ( null === Products::find_by_slug( 'talkwyn-pro' ) ) {
			Products::create( 'talkwyn-pro', 'Talkwyn Pro', 'https://talkwyn.com' );
		}
	}
}
