<?php
/**
 * Redirect rule storage.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

use WP_Error;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table; results are cached where they are read on the front end.

/**
 * CRUD for `{prefix}wpiseo_redirects` and the per-request lookup.
 *
 * Rows are arrays: id, source, match_type (exact|regex), target, status,
 * ignore_query, enabled, hits, last_hit, origin, post_id, created, updated.
 */
final class Repository {

	public const STATUSES    = array( 301, 302, 307, 308, 410, 451 );
	public const MATCH_TYPES = array( 'exact', 'regex' );
	public const ORIGINS     = array( 'manual', 'slug', 'term', 'trash', 'import', '404' );

	private const CACHE_GROUP  = 'wpiseo_redirects';
	private const COUNT_OPTION = 'wpiseo_redirect_counts';

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpiseo_redirects';
	}

	/**
	 * Finds the rule for a request, exact rules first, then regex.
	 *
	 * Costs nothing on sites without enabled rules (counts are autoloaded).
	 *
	 * @param array{path: string, query: string} $request Parsed request.
	 * @return array<string, mixed>|null Rule, with `resolved_target` for regex matches.
	 */
	public function match( array $request ): ?array {
		$counts = $this->counts();

		if ( $counts['exact'] > 0 ) {
			$rule = $this->match_exact( $request );

			if ( null !== $rule ) {
				return $rule;
			}
		}

		if ( $counts['regex'] > 0 ) {
			return $this->match_regex( $request );
		}

		return null;
	}

	/**
	 * One rule.
	 *
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );

		return is_array( $row ) ? self::cast( $row ) : null;
	}

	/**
	 * Paginated list for the admin.
	 *
	 * @param array<string, mixed> $args page, per_page, search, status, match_type, origin, orderby, order.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$search     = trim( (string) ( $args['search'] ?? '' ) );
		$like       = '%' . $wpdb->esc_like( $search ) . '%';
		$status     = (int) ( $args['status'] ?? 0 );
		$match_type = in_array( $args['match_type'] ?? '', self::MATCH_TYPES, true ) ? (string) $args['match_type'] : '';
		$origin     = in_array( $args['origin'] ?? '', self::ORIGINS, true ) ? (string) $args['origin'] : '';
		$columns    = array( 'source', 'hits', 'last_hit', 'created', 'status' );
		$orderby    = in_array( $args['orderby'] ?? '', $columns, true ) ? $args['orderby'] : 'created';
		$order      = 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';
		$per_page   = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$offset     = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;

		// Each filter is skipped when its value is empty.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE ( %s = '' OR source LIKE %s OR target LIKE %s ) AND ( %d = 0 OR status = %d ) AND ( %s = '' OR match_type = %s ) AND ( %s = '' OR origin = %s )",
				self::table(),
				$search,
				$like,
				$like,
				$status,
				$status,
				$match_type,
				$match_type,
				$origin,
				$origin
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// Column and direction are whitelisted above.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM %i WHERE ( %s = '' OR source LIKE %s OR target LIKE %s ) AND ( %d = 0 OR status = %d ) AND ( %s = '' OR match_type = %s ) AND ( %s = '' OR origin = %s ) ORDER BY " . sanitize_sql_orderby( "{$orderby} {$order}" ) . ', id DESC LIMIT %d OFFSET %d',
				self::table(),
				$search,
				$like,
				$like,
				$status,
				$status,
				$match_type,
				$match_type,
				$origin,
				$origin,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items' => array_map( array( self::class, 'cast' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Validates and inserts a rule. Fixes chains (rules pointing at the new
	 * source now point at the new target) and refuses loops.
	 *
	 * @param array<string, mixed> $data source, target, status, match_type, ignore_query, enabled, origin, post_id.
	 * @return int|WP_Error New rule ID.
	 */
	public function create( array $data ) {
		$rule = $this->validate( $data );

		if ( is_wp_error( $rule ) ) {
			return $rule;
		}

		global $wpdb;

		if ( 'exact' === $rule['match_type'] ) {
			$existing = $this->find_by_source( $rule['source'] );

			if ( null !== $existing ) {
				if ( 'manual' === $rule['origin'] || 'import' === $rule['origin'] ) {
					return new WP_Error( 'wpiseo_redirect_exists', __( 'A redirect for this URL already exists.', 'wpinterface-seo' ), array( 'status' => 409 ) );
				}

				// Automatic rules replace older automatic or manual rules for the same URL.
				$this->delete( array( (int) $existing['id'] ) );
			}

			$loop = $this->resolve_loops( $rule );
			if ( is_wp_error( $loop ) ) {
				return $loop;
			}
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			self::table(),
			array_merge(
				$rule,
				array(
					'source_hash' => Normalizer::hash( $rule['source'] ),
					'created'     => $now,
					'updated'     => $now,
				)
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'wpiseo_redirect_db', __( 'The redirect could not be saved.', 'wpinterface-seo' ), array( 'status' => 500 ) );
		}

		$id = (int) $wpdb->insert_id;

		if ( 'exact' === $rule['match_type'] && '' !== $rule['target'] ) {
			$this->repoint_chains( $rule['source'], $rule['target'] );
		}

		$this->changed();

		return $id;
	}

	/**
	 * Updates a rule.
	 *
	 * @param int                  $id   Rule ID.
	 * @param array<string, mixed> $data Fields to change.
	 * @return true|WP_Error
	 */
	public function update( int $id, array $data ) {
		$current = $this->get( $id );

		if ( null === $current ) {
			return new WP_Error( 'wpiseo_redirect_not_found', __( 'Redirect not found.', 'wpinterface-seo' ), array( 'status' => 404 ) );
		}

		$rule = $this->validate( array_merge( $current, $data ) );

		if ( is_wp_error( $rule ) ) {
			return $rule;
		}

		if ( 'exact' === $rule['match_type'] ) {
			$existing = $this->find_by_source( $rule['source'] );

			if ( null !== $existing && (int) $existing['id'] !== $id ) {
				return new WP_Error( 'wpiseo_redirect_exists', __( 'A redirect for this URL already exists.', 'wpinterface-seo' ), array( 'status' => 409 ) );
			}
		}

		global $wpdb;

		$wpdb->update(
			self::table(),
			array_merge(
				$rule,
				array(
					'source_hash' => Normalizer::hash( $rule['source'] ),
					'updated'     => current_time( 'mysql', true ),
				)
			),
			array( 'id' => $id )
		);

		$this->changed();

		return true;
	}

	/**
	 * Deletes rules.
	 *
	 * @param int[] $ids Rule IDs.
	 * @return int Rows deleted.
	 */
	public function delete( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above.
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( self::table() ), $ids ) ) );

		$this->changed();

		return $deleted;
	}

	/**
	 * Enables or disables rules.
	 *
	 * @param int[] $ids     Rule IDs.
	 * @param bool  $enabled New state.
	 */
	public function set_enabled( array $ids, bool $enabled ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built above.
		$updated = (int) $wpdb->query( $wpdb->prepare( "UPDATE %i SET enabled = %d WHERE id IN ({$placeholders})", array_merge( array( self::table(), $enabled ? 1 : 0 ), $ids ) ) );

		$this->changed();

		return $updated;
	}

	/**
	 * Deletes automatic rules created for a post (e.g. when it is restored).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $origin  Origin.
	 */
	public function delete_for_post( int $post_id, string $origin ): void {
		global $wpdb;

		$wpdb->delete(
			self::table(),
			array(
				'post_id' => $post_id,
				'origin'  => $origin,
			)
		);

		$this->changed();
	}

	/**
	 * Records a hit.
	 *
	 * @param int $id Rule ID.
	 */
	public function hit( int $id ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', self::table(), current_time( 'mysql', true ), $id ) );
	}

	/**
	 * All rules (export).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', self::table() ), ARRAY_A );

		return array_map( array( self::class, 'cast' ), (array) $rows );
	}

	/**
	 * Validates and normalises rule data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function validate( array $data ) {
		$match_type = (string) ( $data['match_type'] ?? 'exact' );
		$match_type = in_array( $match_type, self::MATCH_TYPES, true ) ? $match_type : 'exact';
		$status     = (int) ( $data['status'] ?? 301 );
		$status     = in_array( $status, self::STATUSES, true ) ? $status : 301;
		$raw_source = trim( (string) ( $data['source'] ?? '' ) );
		$target     = trim( sanitize_text_field( (string) ( $data['target'] ?? '' ) ) );
		$ignore     = ! isset( $data['ignore_query'] ) || (bool) $data['ignore_query'];
		$origin     = (string) ( $data['origin'] ?? 'manual' );
		$origin     = in_array( $origin, self::ORIGINS, true ) ? $origin : 'manual';

		if ( '' === $raw_source ) {
			return new WP_Error( 'wpiseo_redirect_source', __( 'Enter the URL to redirect from.', 'wpinterface-seo' ), array( 'status' => 400 ) );
		}

		if ( 'regex' === $match_type ) {
			$source = $raw_source;

			if ( ! self::valid_regex( $source ) ) {
				return new WP_Error( 'wpiseo_redirect_regex', __( 'The regular expression is not valid.', 'wpinterface-seo' ), array( 'status' => 400 ) );
			}
		} else {
			$parsed = Normalizer::parse( $raw_source );
			$source = Normalizer::key( $parsed, $ignore );

			if ( ! Normalizer::is_internal( $raw_source ) ) {
				return new WP_Error( 'wpiseo_redirect_external_source', __( 'You can only redirect URLs of this site.', 'wpinterface-seo' ), array( 'status' => 400 ) );
			}
		}

		if ( $status < 400 ) {
			if ( '' === $target ) {
				return new WP_Error( 'wpiseo_redirect_target', __( 'Enter the URL to redirect to.', 'wpinterface-seo' ), array( 'status' => 400 ) );
			}

			$url = Normalizer::target_url( $target );

			if ( ! wp_http_validate_url( $url ) && ! preg_match( '#^https?://[^\s]+$#i', $url ) ) {
				return new WP_Error( 'wpiseo_redirect_target_invalid', __( 'The target is not a valid URL.', 'wpinterface-seo' ), array( 'status' => 400 ) );
			}

			if ( 'exact' === $match_type && Normalizer::is_internal( $url ) && Normalizer::key( Normalizer::parse( $url ), $ignore ) === $source ) {
				return new WP_Error( 'wpiseo_redirect_self', __( 'A URL cannot redirect to itself.', 'wpinterface-seo' ), array( 'status' => 400 ) );
			}
		} else {
			$target = '';
		}

		return array(
			'source'       => mb_substr( $source, 0, 2000 ),
			'match_type'   => $match_type,
			'target'       => mb_substr( $target, 0, 2000 ),
			'status'       => $status,
			'ignore_query' => $ignore ? 1 : 0,
			'enabled'      => isset( $data['enabled'] ) && ! $data['enabled'] ? 0 : 1,
			'origin'       => $origin,
			'post_id'      => absint( $data['post_id'] ?? 0 ),
		);
	}

	/**
	 * Whether a pattern compiles (used before saving regex rules).
	 *
	 * @param string $pattern Pattern without delimiters.
	 */
	public static function valid_regex( string $pattern ): bool {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid user patterns must not raise warnings.
		return false !== @preg_match( self::delimit( $pattern ), '' );
	}

	/**
	 * Adds delimiters and flags to a user pattern.
	 *
	 * @param string $pattern Pattern.
	 */
	public static function delimit( string $pattern ): string {
		return '~' . str_replace( '~', '\~', $pattern ) . '~iu';
	}

	/**
	 * Exact match by hash (two candidates: with and without the query string).
	 *
	 * @param array{path: string, query: string} $request Parsed request.
	 * @return array<string, mixed>|null
	 */
	private function match_exact( array $request ): ?array {
		global $wpdb;

		$keys = array_unique( array( Normalizer::key( $request, true ), Normalizer::key( $request, false ) ) );
		$hash = Normalizer::hash( implode( '|', $keys ) );

		$cache_key = $hash . ':' . wp_cache_get_last_changed( self::CACHE_GROUP );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$hashes       = array_map( array( Normalizer::class, 'hash' ), $keys );
		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE source_hash IN ({$placeholders}) AND match_type = 'exact' AND enabled = 1 ORDER BY ignore_query ASC", array_merge( array( self::table() ), $hashes ) ), ARRAY_A );

		$match = null;

		foreach ( (array) $rows as $row ) {
			$row = self::cast( $row );

			// A rule that keeps the query only matches that exact query.
			$expected = $row['ignore_query'] ? $request['path'] : Normalizer::key( $request, false );

			if ( $expected === $row['source'] ) {
				$match = $row;
				break;
			}
		}

		wp_cache_set( $cache_key, null === $match ? 0 : $match, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $match;
	}

	/**
	 * Regex match, rules in creation order. Capture groups ($1…) are expanded in the target.
	 *
	 * @param array{path: string, query: string} $request Parsed request.
	 * @return array<string, mixed>|null
	 */
	private function match_regex( array $request ): ?array {
		global $wpdb;

		$cache_key = 'regex:' . wp_cache_get_last_changed( self::CACHE_GROUP );
		$rules     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( ! is_array( $rules ) ) {
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE match_type = 'regex' AND enabled = 1 ORDER BY id ASC", self::table() ), ARRAY_A );
			$rules = array_map( array( self::class, 'cast' ), (array) $rows );
			wp_cache_set( $cache_key, $rules, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		$subject = Normalizer::full( $request );

		foreach ( $rules as $rule ) {
			$pattern          = self::delimit( (string) $rule['source'] );
			$subject_for_rule = $rule['ignore_query'] ? $request['path'] : $subject;

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Stored patterns are validated on save; stay silent if PCRE limits are hit.
			if ( 1 === @preg_match( $pattern, $subject_for_rule ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$resolved                = '' !== $rule['target'] ? @preg_replace( $pattern, (string) $rule['target'], $subject_for_rule ) : '';
				$rule['resolved_target'] = is_string( $resolved ) ? $resolved : (string) $rule['target'];

				return $rule;
			}
		}

		return null;
	}

	/**
	 * Rule with this exact source.
	 *
	 * @param string $source Normalised source.
	 * @return array<string, mixed>|null
	 */
	private function find_by_source( string $source ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE source_hash = %s AND match_type = 'exact' LIMIT 1", self::table(), Normalizer::hash( $source ) ), ARRAY_A );

		return is_array( $row ) ? self::cast( $row ) : null;
	}

	/**
	 * Prevents A→B when B→A exists: automatic rules remove the opposite rule
	 * (the URL is live again), manual rules are refused.
	 *
	 * @param array<string, mixed> $rule Validated rule.
	 * @return true|WP_Error
	 */
	private function resolve_loops( array $rule ) {
		if ( '' === $rule['target'] ) {
			return true;
		}

		$target_url = Normalizer::target_url( (string) $rule['target'] );

		if ( ! Normalizer::is_internal( $target_url ) ) {
			return true;
		}

		$opposite = $this->find_by_source( Normalizer::key( Normalizer::parse( $target_url ), true ) );

		if ( null === $opposite ) {
			return true;
		}

		if ( in_array( $rule['origin'], array( 'slug', 'term', 'trash' ), true ) ) {
			$this->delete( array( (int) $opposite['id'] ) );
			return true;
		}

		$opposite_target = Normalizer::target_url( (string) $opposite['target'] );

		if ( Normalizer::is_internal( $opposite_target ) && Normalizer::key( Normalizer::parse( $opposite_target ), true ) === $rule['source'] ) {
			return new WP_Error( 'wpiseo_redirect_loop', __( 'This would create a redirect loop with an existing redirect.', 'wpinterface-seo' ), array( 'status' => 409 ) );
		}

		return true;
	}

	/**
	 * Points rules whose target is $source at $target instead (avoids chains).
	 *
	 * @param string $source Normalised source of the new rule.
	 * @param string $target Target of the new rule.
	 */
	private function repoint_chains( string $source, string $target ): void {
		global $wpdb;

		$segment = '/' === $source ? '/' : (string) substr( $source, (int) strrpos( $source, '/' ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT id, target FROM %i WHERE target LIKE %s', self::table(), '%' . $wpdb->esc_like( $segment ) . '%' ), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$url = Normalizer::target_url( (string) $row['target'] );

			if ( Normalizer::is_internal( $url ) && Normalizer::key( Normalizer::parse( $url ), true ) === $source ) {
				$wpdb->update(
					self::table(),
					array(
						'target'  => $target,
						'updated' => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $row['id'] )
				);
			}
		}
	}

	/**
	 * Enabled rule counts (autoloaded so the front end can skip lookups).
	 *
	 * @return array{exact: int, regex: int}
	 */
	public function counts(): array {
		$counts = get_option( self::COUNT_OPTION );

		if ( ! is_array( $counts ) ) {
			$counts = $this->recount();
		}

		return array(
			'exact' => (int) ( $counts['exact'] ?? 0 ),
			'regex' => (int) ( $counts['regex'] ?? 0 ),
		);
	}

	/**
	 * Visits redirected by the enabled rules, all time.
	 */
	public function hits(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(hits) FROM %i WHERE enabled = 1', self::table() ) );
	}

	/**
	 * Recomputes and stores the counts.
	 *
	 * @return array{exact: int, regex: int}
	 */
	private function recount(): array {
		global $wpdb;

		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT match_type, COUNT(*) AS total FROM %i WHERE enabled = 1 GROUP BY match_type', self::table() ), ARRAY_A );
		$counts = array(
			'exact' => 0,
			'regex' => 0,
		);

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['match_type'] ] ) ) {
				$counts[ $row['match_type'] ] = (int) $row['total'];
			}
		}

		update_option( self::COUNT_OPTION, $counts, true );

		return $counts;
	}

	/**
	 * Invalidates caches after a change.
	 */
	private function changed(): void {
		wp_cache_set_last_changed( self::CACHE_GROUP );
		$this->recount();

		/**
		 * Fires after redirect rules changed.
		 */
		do_action( 'wpiseo_redirects_changed' );
	}

	/**
	 * Casts DB strings to proper types.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	public static function cast( array $row ): array {
		foreach ( array( 'id', 'status', 'hits', 'post_id' ) as $key ) {
			if ( isset( $row[ $key ] ) ) {
				$row[ $key ] = (int) $row[ $key ];
			}
		}

		foreach ( array( 'ignore_query', 'enabled' ) as $key ) {
			if ( isset( $row[ $key ] ) ) {
				$row[ $key ] = (bool) (int) $row[ $key ];
			}
		}

		unset( $row['source_hash'] );

		return $row;
	}
}
