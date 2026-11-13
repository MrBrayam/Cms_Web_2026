<?php
/**
 * 404 log storage.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table, write-heavy log.

/**
 * `{prefix}wpiseo_404_log`: one row per URL (hits are counted, not duplicated),
 * no IP addresses stored.
 */
final class NotFoundLog {

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpiseo_404_log';
	}

	/**
	 * Records a 404 hit (insert or increment).
	 *
	 * @param string $url        Path and query, relative to home.
	 * @param string $referrer   Referrer URL.
	 * @param string $user_agent User agent ('' when not logged).
	 */
	public function record( string $url, string $referrer, string $user_agent ): void {
		global $wpdb;

		$url = mb_substr( $url, 0, 2000 );
		$now = current_time( 'mysql', true );

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (url, url_hash, referrer, user_agent, hits, first_seen, last_seen) VALUES (%s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) = \'\', referrer, VALUES(referrer)), user_agent = IF(VALUES(user_agent) = \'\', user_agent, VALUES(user_agent))',
				self::table(),
				$url,
				md5( $url ),
				mb_substr( $referrer, 0, 2000 ),
				mb_substr( $user_agent, 0, 255 ),
				$now,
				$now
			)
		);
	}

	/**
	 * Paginated list.
	 *
	 * @param array<string, mixed> $args page, per_page, search, orderby (hits|last_seen|url), order.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$columns  = array( 'hits', 'last_seen', 'url', 'first_seen' );
		$orderby  = in_array( $args['orderby'] ?? '', $columns, true ) ? $args['orderby'] : 'last_seen';
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;

		// An empty search matches everything ('' = '').
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ( %s = '' OR url LIKE %s )", self::table(), $search, $like ) );

		// Column and direction are whitelisted above.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, url, referrer, user_agent, hits, first_seen, last_seen FROM %i WHERE ( %s = '' OR url LIKE %s ) ORDER BY " . sanitize_sql_orderby( "{$orderby} {$order}" ) . ', id DESC LIMIT %d OFFSET %d', self::table(), $search, $like, $per_page, $offset ), ARRAY_A );

		$items = array_map(
			static function ( array $row ): array {
				$row['id']   = (int) $row['id'];
				$row['hits'] = (int) $row['hits'];
				return $row;
			},
			(array) $rows
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * One entry.
	 *
	 * @param int $id Entry ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Deletes entries; an empty list clears the log.
	 *
	 * @param int[] $ids Entry IDs.
	 */
	public function delete( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return (int) $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( self::table() ), $ids ) ) );
	}

	/**
	 * Deletes the entry for a URL (after a redirect was created for it).
	 *
	 * @param string $url Path and query.
	 */
	public function delete_url( string $url ): void {
		global $wpdb;

		$wpdb->delete( self::table(), array( 'url_hash' => md5( $url ) ) );
	}

	/**
	 * Applies retention: drops entries older than $days and keeps at most $max rows.
	 *
	 * @param int $days Days to keep.
	 * @param int $max  Maximum rows.
	 */
	public function purge( int $days, int $max ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS ) ) );

		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );

		if ( $count > $max ) {
			$cutoff = $wpdb->get_var( $wpdb->prepare( 'SELECT last_seen FROM %i ORDER BY last_seen DESC LIMIT 1 OFFSET %d', self::table(), $max ) );

			if ( is_string( $cutoff ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen <= %s', self::table(), $cutoff ) );
			}
		}
	}

	/**
	 * Missing URLs requested since a time: how many, how many were first
	 * seen in that time, and the most requested ones.
	 *
	 * @param int $since Unix time.
	 * @param int $limit Number of top URLs.
	 * @return array{seen: int, new: int, top: list<array{url: string, hits: int}>}
	 */
	public function recent( int $since, int $limit = 5 ): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', $since );
		$table = self::table();
		$count = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS seen, SUM(first_seen >= %s) AS fresh FROM %i WHERE last_seen >= %s', $since, $table, $since ), ARRAY_A );
		$top   = $limit > 0 ? $wpdb->get_results( $wpdb->prepare( 'SELECT url, hits FROM %i WHERE last_seen >= %s ORDER BY hits DESC LIMIT %d', $table, $since, $limit ), ARRAY_A ) : array();

		return array(
			'seen' => (int) ( $count['seen'] ?? 0 ),
			'new'  => (int) ( $count['fresh'] ?? 0 ),
			'top'  => array_map(
				static fn( array $row ): array => array(
					'url'  => (string) $row['url'],
					'hits' => (int) $row['hits'],
				),
				(array) $top
			),
		);
	}

	/**
	 * Number of logged URLs.
	 */
	public function count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}
}
