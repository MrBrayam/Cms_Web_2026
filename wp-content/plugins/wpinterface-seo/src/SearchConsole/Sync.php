<?php
/**
 * Search Console data sync.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\SearchConsole;

use WP_Error;
use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Copies Search Console data into the local tables: site totals and pages per
 * day, tracked keywords per day, and a 28-day snapshot of top queries.
 *
 * The first run backfills `search_console.backfill_days` days; later runs
 * re-fetch the last few days (Google revises recent data). Work is split
 * into time-boxed steps so WP-Cron never runs long.
 */
final class Sync {

	public const HOOK         = 'wpiseo_gsc_sync';
	public const STATE_OPTION = 'wpiseo_gsc_sync';

	private const ROW_LIMIT     = 25000;
	private const WINDOW_DAYS   = 30;
	private const REVISION_DAYS = 3;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * OAuth.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * API client.
	 *
	 * @var Api
	 */
	private Api $api;

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Run start time.
	 *
	 * @var float
	 */
	private float $started = 0.0;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Auth    $auth    OAuth.
	 * @param Api     $api     API client.
	 * @param Store   $store   Storage.
	 */
	public function __construct( Options $options, Auth $auth, Api $api, Store $store ) {
		$this->options = $options;
		$this->auth    = $auth;
		$this->api     = $api;
		$this->store   = $store;
	}

	/**
	 * Sync state.
	 *
	 * @return array<string, mixed>
	 */
	public static function state(): array {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Schedules a run soon (deduplicated).
	 *
	 * @param int $delay Seconds.
	 */
	public static function schedule( int $delay = 5 ): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}

	/**
	 * Runs (or continues) a sync.
	 *
	 * @return true|WP_Error
	 */
	public function run() {
		$property = (string) ( $this->auth->data()['property'] ?? '' );

		if ( ! $this->auth->is_connected() || '' === $property ) {
			return new WP_Error( 'wpiseo_gsc_not_ready', __( 'Connect Search Console and choose a property first.', 'wpinterface-seo' ) );
		}

		$this->started = microtime( true );
		$state         = self::state();
		$end           = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$backfill      = max( 30, min( 480, (int) $this->options->get( 'search_console.backfill_days', 180 ) ) );
		$first_day     = gmdate( 'Y-m-d', strtotime( $end . ' -' . ( $backfill - 1 ) . ' days' ) );
		$last          = $this->store->last_date( Store::SITE );
		$start         = '' === $last ? $first_day : max( $first_day, gmdate( 'Y-m-d', strtotime( $last . ' -' . self::REVISION_DAYS . ' days' ) ) );

		$this->save_state( array( 'running' => true ) );

		// 1. Site totals per day (one request).
		if ( empty( $state['cursor'] ) ) {
			$rows = $this->api->search_analytics( $property, self::body( $start, $end, array( 'date' ) ) );

			if ( is_wp_error( $rows ) ) {
				return $this->fail( $rows );
			}

			$this->store->upsert( Store::SITE, self::rows( $rows, '' ) );
		}

		// 2. Pages per day, in windows; resumes from the saved cursor.
		$cursor = ! empty( $state['cursor'] ) ? (string) $state['cursor'] : $start;

		while ( $cursor <= $end ) {
			$window_end = min( $end, gmdate( 'Y-m-d', strtotime( $cursor . ' +' . ( self::WINDOW_DAYS - 1 ) . ' days' ) ) );
			$offset     = 0;

			do {
				$rows = $this->api->search_analytics( $property, self::body( $cursor, $window_end, array( 'date', 'page' ), $offset ) );

				if ( is_wp_error( $rows ) ) {
					return $this->fail( $rows );
				}

				$this->store->upsert( Store::PAGE, self::rows( $rows, 'page' ) );
				$offset += self::ROW_LIMIT;
				$full    = count( $rows ) >= self::ROW_LIMIT;
			} while ( $full );

			$cursor = gmdate( 'Y-m-d', strtotime( $window_end . ' +1 day' ) );

			if ( $cursor <= $end && $this->out_of_time() ) {
				$this->save_state( array( 'cursor' => $cursor ) );
				self::schedule( 30 );
				return true;
			}
		}

		// 3. Tracked keywords (full backfill when the list changed).
		$keywords = self::keywords( $this->options );
		$hash     = md5( (string) wp_json_encode( $keywords ) );

		$this->store->prune_keywords( $keywords );

		if ( $keywords ) {
			$from = ( $state['keywords_hash'] ?? '' ) === $hash ? $start : $first_day;
			$body = self::body( $from, $end, array( 'date', 'query' ) );

			$body['dimensionFilterGroups'] = array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'query',
							'operator'   => 'includingRegex',
							'expression' => '^(' . implode( '|', array_map( static fn( string $keyword ): string => preg_quote( $keyword, '/' ), $keywords ) ) . ')$',
						),
					),
				),
			);

			$rows = $this->api->search_analytics( $property, $body );

			if ( is_wp_error( $rows ) ) {
				return $this->fail( $rows );
			}

			$this->store->upsert( Store::KEYWORD, self::rows( $rows, 'query' ) );
		}

		// 4. Top queries snapshot (last 28 days).
		$snapshot_start = gmdate( 'Y-m-d', strtotime( $end . ' -27 days' ) );
		$page_queries   = $this->api->search_analytics( $property, self::body( $snapshot_start, $end, array( 'page', 'query' ) ) );
		$site_queries   = $this->api->search_analytics( $property, array_merge( self::body( $snapshot_start, $end, array( 'query' ) ), array( 'rowLimit' => 100 ) ) );

		if ( is_wp_error( $page_queries ) ) {
			return $this->fail( $page_queries );
		}

		if ( is_wp_error( $site_queries ) ) {
			return $this->fail( $site_queries );
		}

		$this->store->replace_queries( self::snapshot( $page_queries, $site_queries ) );
		$this->store->purge();

		update_option(
			self::STATE_OPTION,
			array(
				'last_success'  => time(),
				'last_date'     => $end,
				'keywords_hash' => $hash,
				'error'         => '',
				'running'       => false,
			),
			false
		);

		return true;
	}

	/**
	 * Tracked keywords (lowercase, unique, at most 100).
	 *
	 * @param Options $options Settings.
	 * @return list<string>
	 */
	public static function keywords( Options $options ): array {
		$keywords = array_map( static fn( $keyword ): string => mb_strtolower( trim( (string) $keyword ) ), (array) $options->get( 'search_console.keywords', array() ) );

		return array_slice( array_values( array_unique( array_filter( $keywords ) ) ), 0, 100 );
	}

	/**
	 * Search Analytics request body.
	 *
	 * @param string   $start      Y-m-d.
	 * @param string   $end        Y-m-d.
	 * @param string[] $dimensions Dimensions.
	 * @param int      $offset     startRow.
	 * @return array<string, mixed>
	 */
	private static function body( string $start, string $end, array $dimensions, int $offset = 0 ): array {
		return array(
			'startDate'  => $start,
			'endDate'    => $end,
			'dimensions' => $dimensions,
			'type'       => 'web',
			'dataState'  => 'all',
			'rowLimit'   => self::ROW_LIMIT,
			'startRow'   => $offset,
		);
	}

	/**
	 * API rows to storage rows.
	 *
	 * @param list<array<string, mixed>> $rows API rows (keys[0] = date).
	 * @param string                     $item '' for site totals, otherwise the second key holds the item.
	 * @return list<array<string, mixed>>
	 */
	private static function rows( array $rows, string $item ): array {
		$out = array();

		foreach ( $rows as $row ) {
			$keys = (array) ( $row['keys'] ?? array() );

			if ( empty( $keys[0] ) ) {
				continue;
			}

			$value = '' === $item ? '' : (string) ( $keys[1] ?? '' );

			$out[] = array(
				'date'        => (string) $keys[0],
				'item'        => 'query' === $item ? mb_strtolower( $value ) : $value,
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		return $out;
	}

	/**
	 * Snapshot rows: top 20 queries per page plus the site's top 100.
	 *
	 * @param list<array<string, mixed>> $page_rows Rows with keys [page, query].
	 * @param list<array<string, mixed>> $site_rows Rows with keys [query].
	 * @return list<array<string, mixed>>
	 */
	private static function snapshot( array $page_rows, array $site_rows ): array {
		$out   = array();
		$count = array();

		foreach ( $page_rows as $row ) {
			$page = (string) ( $row['keys'][0] ?? '' );

			if ( '' === $page || ( $count[ $page ] ?? 0 ) >= 20 ) {
				continue;
			}

			$count[ $page ] = ( $count[ $page ] ?? 0 ) + 1;
			$out[]          = array(
				'page'        => $page,
				'query'       => (string) ( $row['keys'][1] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		foreach ( $site_rows as $row ) {
			$out[] = array(
				'page'        => '',
				'query'       => (string) ( $row['keys'][0] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		return $out;
	}

	/**
	 * Whether this run used its time budget.
	 */
	private function out_of_time(): bool {
		/**
		 * Filters the seconds one sync step may run.
		 *
		 * @param int $seconds Seconds (default 20).
		 */
		return microtime( true ) - $this->started > (int) apply_filters( 'wpiseo_gsc_sync_time_limit', 20 );
	}

	/**
	 * Records an error and stops.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	private function fail( WP_Error $error ): WP_Error {
		$this->save_state(
			array(
				'error'   => $error->get_error_message(),
				'running' => false,
			)
		);

		return $error;
	}

	/**
	 * Merges values into the state.
	 *
	 * @param array<string, mixed> $changes Changes.
	 */
	private function save_state( array $changes ): void {
		update_option( self::STATE_OPTION, array_merge( self::state(), $changes ), false );
	}
}
