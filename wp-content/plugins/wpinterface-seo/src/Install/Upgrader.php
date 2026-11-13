<?php
/**
 * Install and upgrade routines.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Install;

use WPInterface\SEO\Options;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Runs migrations when the stored DB version is behind WPISEO_DB_VERSION.
 *
 * Runs on every `plugins_loaded` (cheap: one autoloaded option read) because
 * the activation hook does not fire on plugin updates.
 */
final class Upgrader {

	public const VERSION_OPTION    = 'wpiseo_version';
	public const DB_VERSION_OPTION = 'wpiseo_db_version';
	private const LOCK_OPTION      = 'wpiseo_upgrade_lock';
	private const LOCK_TTL         = 600;

	/**
	 * Upgrades the current site when needed.
	 */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );

		if ( $installed < WPISEO_DB_VERSION && self::acquire_lock() ) {
			try {
				self::upgrade( $installed );
			} finally {
				delete_option( self::LOCK_OPTION );
			}
		}

		if ( get_option( self::VERSION_OPTION ) !== WPISEO_VERSION ) {
			update_option( self::VERSION_OPTION, WPISEO_VERSION, true );
		}
	}

	/**
	 * Runs table updates and every migration newer than `$from`.
	 *
	 * @param int $from Installed DB version (0 on a fresh install).
	 */
	private static function upgrade( int $from ): void {
		Schema::install();

		foreach ( self::migrations() as $version => $callback ) {
			if ( $version > $from && $version <= WPISEO_DB_VERSION ) {
				call_user_func( $callback );
			}
		}

		update_option( self::DB_VERSION_OPTION, WPISEO_DB_VERSION, true );

		/**
		 * Fires after the plugin database was upgraded.
		 *
		 * @param int $from Previous DB version.
		 * @param int $to   New DB version.
		 */
		do_action( 'wpiseo_upgraded', $from, WPISEO_DB_VERSION );
	}

	/**
	 * Migrations keyed by the DB version they upgrade to.
	 *
	 * @return array<int, callable>
	 */
	private static function migrations(): array {
		return array(
			1 => array( self::class, 'migrate_1' ),
			2 => array( self::class, 'migrate_2' ),
			3 => array( self::class, 'migrate_3' ),
			4 => array( self::class, 'migrate_4' ),
		);
	}

	/**
	 * DB version 1: initial install.
	 */
	public static function migrate_1(): void {
		Capabilities::add_to_roles();

		if ( false === get_option( Options::OPTION ) ) {
			add_option( Options::OPTION, array(), '', true );
		}

		if ( false === get_option( Options::LARGE_OPTION ) ) {
			add_option( Options::LARGE_OPTION, array(), '', false );
		}

		update_option( 'wpiseo_flush_rewrite_rules', 1, true );
	}

	/**
	 * DB version 2: authors and contributors may edit SEO fields of their own posts.
	 */
	public static function migrate_2(): void {
		Capabilities::add_to_roles();
	}

	/**
	 * DB version 3: redirect and 404 tables (created by Schema::install() before migrations run).
	 */
	public static function migrate_3(): void {
		update_option( 'wpiseo_flush_rewrite_rules', 1, true );
	}

	/**
	 * DB version 4: internal link and Search Console tables (created by
	 * Schema::install()). Existing content is indexed for links in the background.
	 */
	public static function migrate_4(): void {
		update_option( 'wpiseo_links_rebuild', array( 'last_id' => 0 ), false );
	}

	/**
	 * Prevents concurrent upgrades. Stale locks expire after LOCK_TTL seconds.
	 */
	private static function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );

		if ( $locked_at && time() - $locked_at > self::LOCK_TTL ) {
			update_option( self::LOCK_OPTION, time(), false );

			return true;
		}

		return false;
	}
}
