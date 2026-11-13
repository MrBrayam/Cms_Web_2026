<?php
/**
 * Redirect importers.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Reads other plugins' tables during a one-off import.

/**
 * Reads redirects from Redirection, Rank Math, Yoast SEO Premium, Slim SEO
 * and SEOPress Pro. Formats are documented in docs/research/importer-storage-formats.md.
 */
final class Importer {

	/**
	 * Rules.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Rules.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Sources with their labels and number of redirects found.
	 *
	 * @return list<array{id: string, label: string, count: int}>
	 */
	public function sources(): array {
		$labels = array(
			'redirection' => 'Redirection',
			'rankmath'    => 'Rank Math',
			'yoast'       => 'Yoast SEO Premium',
			'slimseo'     => 'Slim SEO',
			'seopress'    => 'SEOPress Pro',
		);
		$out    = array();

		foreach ( $labels as $id => $label ) {
			$count = count( $this->read( $id ) );

			if ( $count > 0 ) {
				$out[] = array(
					'id'    => $id,
					'label' => $label,
					'count' => $count,
				);
			}
		}

		return $out;
	}

	/**
	 * Imports all redirects from a source.
	 *
	 * @param string $source Source ID.
	 * @return array{created: int, skipped: int}
	 */
	public function import( string $source ): array {
		$created = 0;
		$skipped = 0;

		foreach ( $this->read( $source ) as $rule ) {
			$rule['origin'] = 'import';

			if ( is_wp_error( $this->repository->create( $rule ) ) ) {
				++$skipped;
			} else {
				++$created;
			}
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
		);
	}

	/**
	 * Rules from a source, in our format.
	 *
	 * @param string $source Source ID.
	 * @return list<array<string, mixed>>
	 */
	public function read( string $source ): array {
		switch ( $source ) {
			case 'redirection':
				return $this->from_redirection();
			case 'rankmath':
				return $this->from_rank_math();
			case 'yoast':
				return $this->from_yoast();
			case 'slimseo':
				return $this->from_slim_seo();
			case 'seopress':
				return $this->from_seopress();
		}

		return array();
	}

	/**
	 * Redirection plugin: URL redirects and error responses (410).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function from_redirection(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'redirection_items';

		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT url, regex, action_type, action_code, action_data FROM %i WHERE status = 'enabled' AND match_type = 'url' AND action_type IN ('url', 'error')", $table ), ARRAY_A );
		$rules = array();

		foreach ( (array) $rows as $row ) {
			$status = (int) $row['action_code'];

			if ( 'error' === $row['action_type'] && ! in_array( $status, array( 410, 451 ), true ) ) {
				continue;
			}

			$rules[] = array(
				'source'     => (string) $row['url'],
				'target'     => 'url' === $row['action_type'] ? (string) $row['action_data'] : '',
				'status'     => $status,
				'match_type' => '1' === (string) $row['regex'] ? 'regex' : 'exact',
			);
		}

		return $rules;
	}

	/**
	 * Rank Math: each source pattern becomes one rule.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function from_rank_math(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'rank_math_redirections';

		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT sources, url_to, header_code FROM %i WHERE status = 'active'", $table ), ARRAY_A );
		$rules = array();

		foreach ( (array) $rows as $row ) {
			$sources = maybe_unserialize( (string) $row['sources'] );

			foreach ( is_array( $sources ) ? $sources : array() as $source ) {
				$pattern = (string) ( $source['pattern'] ?? '' );

				if ( '' === $pattern ) {
					continue;
				}

				$rules[] = array(
					'source'     => $this->comparison_to_source( $pattern, (string) ( $source['comparison'] ?? 'exact' ) ),
					'target'     => (string) $row['url_to'],
					'status'     => (int) $row['header_code'],
					'match_type' => 'exact' === ( $source['comparison'] ?? 'exact' ) ? 'exact' : 'regex',
				);
			}
		}

		return $rules;
	}

	/**
	 * Yoast SEO Premium: `wpseo-premium-redirects-base`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function from_yoast(): array {
		$redirects = get_option( 'wpseo-premium-redirects-base', array() );
		$rules     = array();

		foreach ( is_array( $redirects ) ? $redirects : array() as $redirect ) {
			if ( ! is_array( $redirect ) || empty( $redirect['origin'] ) ) {
				continue;
			}

			$regex  = 'regex' === ( $redirect['format'] ?? 'plain' );
			$target = (string) ( $redirect['url'] ?? '' );

			$rules[] = array(
				'source'     => $regex ? (string) $redirect['origin'] : '/' . ltrim( (string) $redirect['origin'], '/' ),
				'target'     => '' !== $target && ! preg_match( '#^https?://#i', $target ) ? '/' . ltrim( $target, '/' ) : $target,
				'status'     => (int) ( $redirect['type'] ?? 301 ),
				'match_type' => $regex ? 'regex' : 'exact',
			);
		}

		return $rules;
	}

	/**
	 * Slim SEO: `ss_redirects` option.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function from_slim_seo(): array {
		$redirects = get_option( 'ss_redirects', array() );
		$rules     = array();

		foreach ( is_array( $redirects ) ? $redirects : array() as $redirect ) {
			if ( ! is_array( $redirect ) || empty( $redirect['from'] ) || empty( $redirect['enable'] ) ) {
				continue;
			}

			$condition = array(
				'exact-match' => 'exact',
				'contain'     => 'contains',
				'start-with'  => 'start',
				'end-with'    => 'end',
				'regex'       => 'regex',
			)[ (string) ( $redirect['condition'] ?? 'exact-match' ) ] ?? 'exact';

			$rules[] = array(
				'source'       => $this->comparison_to_source( (string) $redirect['from'], $condition ),
				'target'       => (string) ( $redirect['to'] ?? '' ),
				'status'       => (int) ( $redirect['type'] ?? 301 ),
				'match_type'   => 'exact' === $condition ? 'exact' : 'regex',
				'ignore_query' => ! empty( $redirect['ignoreParameters'] ),
			);
		}

		return $rules;
	}

	/**
	 * SEOPress Pro redirect manager: `seopress_404` posts.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function from_seopress(): array {
		global $wpdb;

		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = %s AND e.meta_value = 'yes'
				WHERE p.post_type = %s",
				'_seopress_redirections_enabled',
				'seopress_404'
			),
			ARRAY_A
		);
		$rules = array();

		foreach ( (array) $rows as $row ) {
			$id    = (int) $row['ID'];
			$regex = 'yes' === get_post_meta( $id, '_seopress_redirections_enabled_regex', true );

			$rules[] = array(
				'source'     => $regex ? (string) $row['post_title'] : '/' . ltrim( (string) $row['post_title'], '/' ),
				'target'     => (string) get_post_meta( $id, '_seopress_redirections_value', true ),
				'status'     => (int) get_post_meta( $id, '_seopress_redirections_type', true ),
				'match_type' => $regex ? 'regex' : 'exact',
			);
		}

		return $rules;
	}

	/**
	 * Converts contains/starts/ends patterns to a regex source; exact stays a path.
	 *
	 * @param string $pattern    Pattern.
	 * @param string $comparison exact|contains|start|end|regex.
	 */
	private function comparison_to_source( string $pattern, string $comparison ): string {
		switch ( $comparison ) {
			case 'exact':
				return '/' . ltrim( $pattern, '/' );
			case 'contains':
				return preg_quote( $pattern, '~' );
			case 'start':
				return '^/?' . preg_quote( ltrim( $pattern, '/' ), '~' );
			case 'end':
				return preg_quote( $pattern, '~' ) . '/?$';
			default:
				return $pattern;
		}
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Table name.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}
