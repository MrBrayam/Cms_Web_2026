<?php
/**
 * Local Search Console data.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\SearchConsole;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables, written by the sync and read by reports.

/**
 * `{prefix}wpiseo_gsc_stats` holds daily rows per dimension (site totals,
 * pages, tracked keywords); `{prefix}wpiseo_gsc_queries` holds a snapshot
 * of top queries for the last 28 days. Reports never call Google.
 */
final class Store {

	public const SITE    = 'site';
	public const PAGE    = 'page';
	public const KEYWORD = 'keyword';

	/**
	 * Daily stats table.
	 */
	public static function stats_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpiseo_gsc_stats';
	}

	/**
	 * Query snapshot table.
	 */
	public static function queries_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpiseo_gsc_queries';
	}

	/**
	 * Hash of an item (URL or query) for lookups.
	 *
	 * @param string $item Item.
	 */
	public static function hash( string $item ): string {
		return md5( $item );
	}

	/**
	 * Inserts or updates daily rows.
	 *
	 * @param string                     $dimension site|page|keyword.
	 * @param list<array<string, mixed>> $rows      date, item, clicks, impressions, position.
	 */
	public function upsert( string $dimension, array $rows ): void {
		global $wpdb;

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$params = array( self::stats_table() );

			foreach ( $chunk as $row ) {
				$item = mb_substr( (string) ( $row['item'] ?? '' ), 0, 2000 );
				array_push( $params, (string) $row['date'], $dimension, self::hash( $item ), $item, (int) $row['clicks'], (int) $row['impressions'], (float) $row['position'] );
			}

			// One row of placeholders per record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Bulk upsert into the plugin's own table; values passed as one array.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (stat_date, dimension, item_hash, item, clicks, impressions, position) VALUES ' . implode( ',', array_fill( 0, count( $chunk ), '(%s, %s, %s, %s, %d, %d, %f)' ) ) . ' ON DUPLICATE KEY UPDATE clicks = VALUES(clicks), impressions = VALUES(impressions), position = VALUES(position)', $params ) );
		}
	}

	/**
	 * Replaces the top-queries snapshot.
	 *
	 * @param list<array<string, mixed>> $rows page ('' = whole site), query, clicks, impressions, position.
	 */
	public function replace_queries( array $rows ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::queries_table() ) );

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$params = array( self::queries_table() );

			foreach ( $chunk as $row ) {
				array_push( $params, self::hash( (string) $row['page'] ), mb_substr( (string) $row['query'], 0, 255 ), (int) $row['clicks'], (int) $row['impressions'], (float) $row['position'] );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Bulk insert into the plugin's own table; values passed as one array.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (page_hash, query, clicks, impressions, position) VALUES ' . implode( ',', array_fill( 0, count( $chunk ), '(%s, %s, %d, %d, %f)' ) ), $params ) );
		}
	}

	/**
	 * Totals for one item (or the site) over a date range.
	 *
	 * @param string $dimension site|page|keyword.
	 * @param string $item      Item ('' for the site).
	 * @param string $start     Y-m-d.
	 * @param string $end       Y-m-d.
	 * @return array{clicks: int, impressions: int, ctr: float, position: float}
	 */
	public function totals( string $dimension, string $item, string $start, string $end ): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) AS position FROM %i WHERE dimension = %s AND item_hash = %s AND stat_date BETWEEN %s AND %s',
				self::stats_table(),
				$dimension,
				self::hash( $item ),
				$start,
				$end
			),
			ARRAY_A
		);

		return self::totals_row( is_array( $row ) ? $row : array() );
	}

	/**
	 * Daily series for one item, one row per day of the range (days without
	 * data have zero clicks and a null position).
	 *
	 * @param string $dimension site|page|keyword.
	 * @param string $item      Item.
	 * @param string $start     Y-m-d.
	 * @param string $end       Y-m-d.
	 * @return list<array{date: string, clicks: int, impressions: int, position: float|null}>
	 */
	public function series( string $dimension, string $item, string $start, string $end ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT stat_date, clicks, impressions, position FROM %i WHERE dimension = %s AND item_hash = %s AND stat_date BETWEEN %s AND %s',
				self::stats_table(),
				$dimension,
				self::hash( $item ),
				$start,
				$end
			),
			ARRAY_A
		);

		$by_date = array_column( (array) $rows, null, 'stat_date' );
		$series  = array();
		$day     = strtotime( $start . ' UTC' );
		$last    = strtotime( $end . ' UTC' );

		for ( ; $day <= $last; $day += DAY_IN_SECONDS ) {
			$date = gmdate( 'Y-m-d', $day );
			$row  = $by_date[ $date ] ?? null;

			$series[] = array(
				'date'        => $date,
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => null !== $row && (int) $row['impressions'] > 0 ? round( (float) $row['position'], 1 ) : null,
			);
		}

		return $series;
	}

	/**
	 * A period of $days ending with the last synced day, and the period of
	 * equal length before it.
	 *
	 * @param int $days 7, 28 or 90.
	 * @return array{start: string, end: string, prev_start: string, prev_end: string, days: int}
	 */
	public function range( int $days ): array {
		$days = in_array( $days, array( 7, 28, 90 ), true ) ? $days : 28;
		$end  = $this->last_date( self::SITE );
		$end  = '' !== $end ? $end : gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$start      = gmdate( 'Y-m-d', strtotime( $end . ' -' . ( $days - 1 ) . ' days' ) );
		$prev_end   = gmdate( 'Y-m-d', strtotime( $start . ' -1 day' ) );
		$prev_start = gmdate( 'Y-m-d', strtotime( $prev_end . ' -' . ( $days - 1 ) . ' days' ) );

		return array(
			'start'      => $start,
			'end'        => $end,
			'prev_start' => $prev_start,
			'prev_end'   => $prev_end,
			'days'       => $days,
		);
	}

	/**
	 * Pages ranked by clicks in a range, with the previous period for comparison.
	 *
	 * @param string $start      Y-m-d.
	 * @param string $end        Y-m-d.
	 * @param string $prev_start Y-m-d.
	 * @param string $prev_end   Y-m-d.
	 * @param int    $limit      Rows.
	 * @param string $order      clicks|decline.
	 * @return list<array<string, mixed>>
	 */
	public function pages( string $start, string $end, string $prev_start, string $prev_end, int $limit = 20, string $order = 'clicks' ): array {
		global $wpdb;

		// Aggregates are ordered in an outer query (MySQL cannot order by an expression over aliases).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM ( SELECT item,
					SUM(CASE WHEN stat_date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS clicks,
					SUM(CASE WHEN stat_date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS impressions,
					SUM(CASE WHEN stat_date BETWEEN %s AND %s THEN position * impressions ELSE 0 END) / NULLIF(SUM(CASE WHEN stat_date BETWEEN %s AND %s THEN impressions ELSE 0 END), 0) AS position,
					SUM(CASE WHEN stat_date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS previous_clicks
				FROM %i WHERE dimension = %s AND stat_date BETWEEN %s AND %s
				GROUP BY item_hash, item ) AS ranked
				ORDER BY CASE WHEN %s = 'decline' THEN previous_clicks - clicks ELSE clicks END DESC, impressions DESC LIMIT %d",
				$start,
				$end,
				$start,
				$end,
				$start,
				$end,
				$start,
				$end,
				$prev_start,
				$prev_end,
				self::stats_table(),
				self::PAGE,
				min( $start, $prev_start ),
				max( $end, $prev_end ),
				$order,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $row ): array => array(
				'url'             => (string) $row['item'],
				'clicks'          => (int) $row['clicks'],
				'impressions'     => (int) $row['impressions'],
				'ctr'             => (int) $row['impressions'] > 0 ? round( (int) $row['clicks'] / (int) $row['impressions'] * 100, 1 ) : 0.0,
				'position'        => round( (float) $row['position'], 1 ),
				'previous_clicks' => (int) $row['previous_clicks'],
			),
			(array) $rows
		);
	}

	/**
	 * Pages whose clicks fell by at least $threshold percent.
	 *
	 * @param string $start      Y-m-d.
	 * @param string $end        Y-m-d.
	 * @param string $prev_start Y-m-d.
	 * @param string $prev_end   Y-m-d.
	 * @param int    $threshold  Minimum drop in percent.
	 * @param int    $min_clicks Minimum clicks in the previous period.
	 * @return list<array<string, mixed>>
	 */
	public function decay( string $start, string $end, string $prev_start, string $prev_end, int $threshold, int $min_clicks ): array {
		$pages = $this->pages( $start, $end, $prev_start, $prev_end, 200, 'decline' );

		return array_values(
			array_filter(
				array_map(
					static function ( array $page ): array {
						$page['change'] = $page['previous_clicks'] > 0 ? round( ( $page['clicks'] - $page['previous_clicks'] ) / $page['previous_clicks'] * 100 ) : 0;
						return $page;
					},
					$pages
				),
				static fn( array $page ): bool => $page['previous_clicks'] >= $min_clicks && $page['change'] <= -$threshold
			)
		);
	}

	/**
	 * Top queries of a page ('' = whole site) from the 28-day snapshot.
	 *
	 * @param string $page  Page URL or ''.
	 * @param int    $limit Rows.
	 * @return list<array<string, mixed>>
	 */
	public function queries( string $page, int $limit = 10 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT query, clicks, impressions, position FROM %i WHERE page_hash = %s ORDER BY clicks DESC, impressions DESC LIMIT %d', self::queries_table(), self::hash( $page ), $limit ), ARRAY_A );

		return array_map(
			static fn( array $row ): array => array(
				'query'       => (string) $row['query'],
				'clicks'      => (int) $row['clicks'],
				'impressions' => (int) $row['impressions'],
				'ctr'         => (int) $row['impressions'] > 0 ? round( (int) $row['clicks'] / (int) $row['impressions'] * 100, 1 ) : 0.0,
				'position'    => round( (float) $row['position'], 1 ),
			),
			(array) $rows
		);
	}

	/**
	 * Clicks per URL for a range (list-table column).
	 *
	 * @param string[] $urls  URLs.
	 * @param string   $start Y-m-d.
	 * @param string   $end   Y-m-d.
	 * @return array<string, array{clicks: int, impressions: int}> Keyed by URL hash.
	 */
	public function clicks_for( array $urls, string $start, string $end ): array {
		global $wpdb;

		$hashes = array_values( array_unique( array_map( array( self::class, 'hash' ), $urls ) ) );

		if ( array() === $hashes ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT item_hash, SUM(clicks) AS clicks, SUM(impressions) AS impressions FROM %i WHERE dimension = %s AND stat_date BETWEEN %s AND %s AND item_hash IN ({$placeholders}) GROUP BY item_hash", array_merge( array( self::stats_table(), self::PAGE, $start, $end ), $hashes ) ), ARRAY_A );

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['item_hash'] ] = array(
				'clicks'      => (int) $row['clicks'],
				'impressions' => (int) $row['impressions'],
			);
		}

		return $out;
	}

	/**
	 * Latest date stored for a dimension ('' when empty).
	 *
	 * @param string $dimension Dimension.
	 */
	public function last_date( string $dimension ): string {
		global $wpdb;

		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(stat_date) FROM %i WHERE dimension = %s', self::stats_table(), $dimension ) );
	}

	/**
	 * Deletes rows older than Search Console's 16-month retention.
	 */
	public function purge(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE stat_date < %s', self::stats_table(), gmdate( 'Y-m-d', strtotime( '-16 months' ) ) ) );
	}

	/**
	 * Deletes rows of tracked keywords that are no longer tracked.
	 *
	 * @param string[] $keywords Tracked keywords.
	 */
	public function prune_keywords( array $keywords ): void {
		global $wpdb;

		$keep = array_map( array( self::class, 'hash' ), $keywords );

		if ( array() === $keep ) {
			$wpdb->delete( self::stats_table(), array( 'dimension' => self::KEYWORD ) );
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built above.
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE dimension = %s AND item_hash NOT IN ({$placeholders})", array_merge( array( self::stats_table(), self::KEYWORD ), $keep ) ) );
	}

	/**
	 * Deletes all Search Console data.
	 */
	public function delete_all(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::stats_table() ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::queries_table() ) );
	}

	/**
	 * Typed totals with CTR.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array{clicks: int, impressions: int, ctr: float, position: float}
	 */
	private static function totals_row( array $row ): array {
		$clicks      = (int) ( $row['clicks'] ?? 0 );
		$impressions = (int) ( $row['impressions'] ?? 0 );

		return array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? round( $clicks / $impressions * 100, 1 ) : 0.0,
			'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
		);
	}
}
