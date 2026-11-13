<?php
/**
 * Custom database tables.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Definitions of the plugin's custom tables, created with dbDelta().
 *
 * The list is static so uninstall.php can drop the tables without booting
 * modules. Bump WPISEO_DB_VERSION after changing a definition.
 */
final class Schema {

	/**
	 * Table definitions: unprefixed name => column/index SQL (dbDelta format).
	 *
	 * Format rules for dbDelta(): one column per line, two spaces after PRIMARY KEY,
	 * use KEY not INDEX, lowercase types.
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		return array(
			'wpiseo_redirects'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
source varchar(2000) NOT NULL,
source_hash char(32) NOT NULL,
match_type varchar(10) NOT NULL DEFAULT 'exact',
target varchar(2000) NOT NULL DEFAULT '',
status smallint(3) unsigned NOT NULL DEFAULT 301,
ignore_query tinyint(1) unsigned NOT NULL DEFAULT 1,
enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
hits bigint(20) unsigned NOT NULL DEFAULT 0,
last_hit datetime DEFAULT NULL,
origin varchar(20) NOT NULL DEFAULT 'manual',
post_id bigint(20) unsigned NOT NULL DEFAULT 0,
created datetime NOT NULL,
updated datetime NOT NULL,
PRIMARY KEY  (id),
KEY source_hash (source_hash),
KEY match_type (match_type,enabled),
KEY post_id (post_id)",
			'wpiseo_404_log'     => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
url varchar(2000) NOT NULL,
url_hash char(32) NOT NULL,
referrer varchar(2000) NOT NULL DEFAULT '',
user_agent varchar(255) NOT NULL DEFAULT '',
hits bigint(20) unsigned NOT NULL DEFAULT 1,
first_seen datetime NOT NULL,
last_seen datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY url_hash (url_hash),
KEY last_seen (last_seen)",
			'wpiseo_links'       => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
post_id bigint(20) unsigned NOT NULL,
target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
url varchar(2000) NOT NULL,
type varchar(10) NOT NULL DEFAULT 'internal',
nofollow tinyint(1) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY post_id (post_id),
KEY target_post_id (target_post_id)",
			'wpiseo_gsc_stats'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
stat_date date NOT NULL,
dimension varchar(10) NOT NULL,
item_hash char(32) NOT NULL,
item varchar(2000) NOT NULL DEFAULT '',
clicks int(10) unsigned NOT NULL DEFAULT 0,
impressions int(10) unsigned NOT NULL DEFAULT 0,
position float NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
UNIQUE KEY row_key (dimension,item_hash,stat_date),
KEY stat_date (stat_date)",
			'wpiseo_gsc_queries' => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
page_hash char(32) NOT NULL,
query varchar(255) NOT NULL,
clicks int(10) unsigned NOT NULL DEFAULT 0,
impressions int(10) unsigned NOT NULL DEFAULT 0,
position float NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY page_hash (page_hash,clicks)',
		);
	}

	/**
	 * Prefixed table names.
	 *
	 * @return list<string>
	 */
	public static function table_names(): array {
		global $wpdb;

		return array_map(
			static fn( string $name ): string => $wpdb->prefix . $name,
			array_keys( self::definitions() )
		);
	}

	/**
	 * Creates or updates all tables for the current site.
	 */
	public static function install(): void {
		global $wpdb;

		$definitions = self::definitions();

		if ( array() === $definitions ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( $definitions as $name => $columns ) {
			dbDelta( "CREATE TABLE {$wpdb->prefix}{$name} (\n{$columns}\n) {$charset_collate};" );
		}
	}

	/**
	 * Drops all tables for the current site (uninstall only).
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( self::table_names() as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes the plugin's own tables.
		}
	}
}
