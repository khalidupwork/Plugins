<?php
/**
 * Database schema.
 *
 * @package TalkwynHub
 */

namespace TWH\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Table names and dbDelta definitions.
 */
final class Schema {

	public const TABLES = array( 'products', 'releases', 'licenses', 'activations', 'events', 'partners', 'referral_visits', 'referrals', 'payouts' );

	/**
	 * Full table name.
	 *
	 * @param string $name Short name.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'twh_' . $name;
	}

	/**
	 * Create or update tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'twh_';

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		$sql = array();

		$sql[] = "CREATE TABLE {$p}products (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(100) NOT NULL,
  name varchar(190) NOT NULL,
  latest_version varchar(32) NOT NULL DEFAULT '',
  homepage varchar(255) NOT NULL DEFAULT '',
  meta longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;";

		$sql[] = "CREATE TABLE {$p}releases (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  version varchar(32) NOT NULL,
  channel varchar(10) NOT NULL DEFAULT 'stable',
  zip_path varchar(255) NOT NULL,
  file_size bigint(20) unsigned NOT NULL DEFAULT 0,
  checksum char(64) NOT NULL DEFAULT '',
  changelog longtext NULL,
  requires_wp varchar(20) NOT NULL DEFAULT '',
  requires_php varchar(20) NOT NULL DEFAULT '',
  tested_wp varchar(20) NOT NULL DEFAULT '',
  released_at datetime NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY product_version_channel (product_id,version,channel),
  KEY product_active (product_id,is_active)
) $charset;";

		$sql[] = "CREATE TABLE {$p}licenses (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  plan_slug varchar(64) NOT NULL DEFAULT '',
  key_hash char(64) NOT NULL,
  key_encrypted text NOT NULL,
  key_last4 char(4) NOT NULL,
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  customer_email varchar(190) NOT NULL DEFAULT '',
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
  wc_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  subscription_id bigint(20) unsigned NULL DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  activation_limit int(11) unsigned NOT NULL DEFAULT 1,
  duration_days int(11) unsigned NOT NULL DEFAULT 365,
  features varchar(255) NOT NULL DEFAULT 'pro',
  expires_at datetime NULL DEFAULT NULL,
  reminders_sent varchar(64) NOT NULL DEFAULT '',
  is_trial tinyint(1) NOT NULL DEFAULT 0,
  trial_ends_at datetime NULL DEFAULT NULL,
  trial_emails varchar(64) NOT NULL DEFAULT '',
  converted_at datetime NULL DEFAULT NULL,
  site_domain varchar(255) NOT NULL DEFAULT '',
  partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  notes longtext NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY key_hash (key_hash),
  KEY customer_id (customer_id),
  KEY customer_email (customer_email),
  KEY order_id (order_id),
  KEY order_item_id (order_item_id),
  KEY subscription_id (subscription_id),
  KEY status_expires (status,expires_at),
  KEY key_last4 (key_last4),
  KEY trial (is_trial,status),
  KEY site_domain (site_domain(100)),
  KEY partner_id (partner_id)
) $charset;";

		$sql[] = "CREATE TABLE {$p}activations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  license_id bigint(20) unsigned NOT NULL,
  instance_id varchar(64) NOT NULL,
  site_url varchar(255) NOT NULL DEFAULT '',
  domain_normalized varchar(255) NOT NULL DEFAULT '',
  is_dev_site tinyint(1) NOT NULL DEFAULT 0,
  wp_version varchar(20) NOT NULL DEFAULT '',
  php_version varchar(20) NOT NULL DEFAULT '',
  plugin_version varchar(32) NOT NULL DEFAULT '',
  last_check_at datetime NULL DEFAULT NULL,
  activated_at datetime NOT NULL,
  deactivated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY license_active (license_id,deactivated_at),
  KEY instance_id (instance_id),
  KEY domain_normalized (domain_normalized(100)),
  KEY plugin_version (plugin_version)
) $charset;";

		$sql[] = "CREATE TABLE {$p}events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  license_id bigint(20) unsigned NULL DEFAULT NULL,
  type varchar(32) NOT NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  meta longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY license_id (license_id),
  KEY type_created (type,created_at),
  KEY created_at (created_at)
) $charset;";

		$sql[] = "CREATE TABLE {$p}partners (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  referral_code varchar(40) NOT NULL,
  code_changed tinyint(1) NOT NULL DEFAULT 0,
  commission_rate decimal(5,2) NULL DEFAULT NULL,
  payout_method varchar(20) NOT NULL DEFAULT '',
  payout_details text NULL,
  website varchar(255) NOT NULL DEFAULT '',
  promotion text NULL,
  coupon_code varchar(64) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  UNIQUE KEY referral_code (referral_code),
  KEY status (status),
  KEY coupon_code (coupon_code)
) $charset;";

		$sql[] = "CREATE TABLE {$p}referral_visits (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL,
  landing_url varchar(255) NOT NULL DEFAULT '',
  referrer varchar(255) NOT NULL DEFAULT '',
  ip_hash char(64) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY partner_created (partner_id,created_at),
  KEY ip_hash (ip_hash)
) $charset;";

		$sql[] = "CREATE TABLE {$p}referrals (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL,
  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  license_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(20) NOT NULL DEFAULT 'new',
  plan_slug varchar(64) NOT NULL DEFAULT '',
  amount decimal(12,2) NOT NULL DEFAULT 0,
  commission decimal(12,2) NOT NULL DEFAULT 0,
  currency char(3) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'pending',
  reason varchar(255) NOT NULL DEFAULT '',
  source varchar(20) NOT NULL DEFAULT 'cookie',
  click_to_buy int(11) unsigned NULL DEFAULT NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  payout_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  approved_at datetime NULL DEFAULT NULL,
  paid_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_partner_license (order_id,partner_id,license_id,type),
  KEY partner_status (partner_id,status),
  KEY status_created (status,created_at),
  KEY license_id (license_id)
) $charset;";

		$sql[] = "CREATE TABLE {$p}payouts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  currency char(3) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT '',
  reference varchar(190) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'paid',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY partner_id (partner_id)
) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Drop all tables (uninstall only).
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( self::TABLES as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- table name is internal.
		}
	}
}
