<?php
/**
 * Taxonomy sitemap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Sitemaps;

use WP_Term;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Lists non-empty, indexable terms of one taxonomy.
 */
final class TaxonomyProvider implements Provider {

	/**
	 * Taxonomy.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * URLs per file.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Whether the taxonomy is noindex by default.
	 *
	 * @var bool
	 */
	private bool $noindex_default;

	/**
	 * Excluded term IDs.
	 *
	 * @var list<int>
	 */
	private array $exclude;

	/**
	 * Cached total.
	 *
	 * @var int|null
	 */
	private ?int $total = null;

	/**
	 * Cached term ID => last post modification (GMT, MySQL format).
	 *
	 * @var array<int, string>|null
	 */
	private ?array $modified = null;

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy        Taxonomy.
	 * @param int    $per_page        URLs per file.
	 * @param bool   $noindex_default Whether the taxonomy defaults to noindex.
	 * @param int[]  $exclude         Excluded term IDs.
	 */
	public function __construct( string $taxonomy, int $per_page, bool $noindex_default, array $exclude ) {
		$this->taxonomy        = $taxonomy;
		$this->per_page        = max( 1, $per_page );
		$this->noindex_default = $noindex_default;
		$this->exclude         = $exclude;
	}

	/**
	 * Provider name.
	 */
	public function name(): string {
		return $this->taxonomy;
	}

	/**
	 * Number of files.
	 */
	public function max_pages(): int {
		return (int) ceil( $this->total() / $this->per_page );
	}

	/**
	 * Entries of one file.
	 *
	 * @param int $page Page number.
	 * @return list<array{loc: string, lastmod?: string, images?: list<string>}>
	 */
	public function entries( int $page ): array {
		$terms = get_terms(
			array_merge(
				$this->query_args(),
				array(
					'number' => $this->per_page,
					'offset' => ( $page - 1 ) * $this->per_page,
				)
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$modified = $this->modified();
		$entries  = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$url = get_term_link( $term );

			if ( ! is_string( $url ) ) {
				continue;
			}

			$canonical = (string) Meta::term( (int) $term->term_id, 'canonical' );
			if ( '' !== $canonical && untrailingslashit( $canonical ) !== untrailingslashit( $url ) ) {
				continue;
			}

			$entry = array( 'loc' => $url );

			if ( isset( $modified[ (int) $term->term_id ] ) ) {
				$entry['lastmod'] = self::w3c( $modified[ (int) $term->term_id ] );
			}

			/**
			 * Filters a term's sitemap entry. Return null to skip the term.
			 *
			 * @param array|null $entry Entry.
			 * @param WP_Term    $term  Term.
			 */
			$entry = apply_filters( 'wpiseo_sitemap_term_entry', $entry, $term );

			if ( is_array( $entry ) && ! empty( $entry['loc'] ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Lastmod of one file: newest post across the listed terms.
	 *
	 * @param int $page Page number.
	 */
	public function lastmod( int $page ): string {
		$ids = get_terms(
			array_merge(
				$this->query_args(),
				array(
					'number' => $this->per_page,
					'offset' => ( $page - 1 ) * $this->per_page,
					'fields' => 'ids',
				)
			)
		);

		if ( ! is_array( $ids ) ) {
			return '';
		}

		$latest = '';

		foreach ( $ids as $id ) {
			$date = $this->modified()[ (int) $id ] ?? '';
			if ( $date > $latest ) {
				$latest = $date;
			}
		}

		return '' !== $latest ? self::w3c( $latest ) : '';
	}

	/**
	 * Number of listable terms.
	 */
	private function total(): int {
		if ( null === $this->total ) {
			$count       = wp_count_terms( $this->query_args() );
			$this->total = is_numeric( $count ) ? (int) $count : 0;
		}

		return $this->total;
	}

	/**
	 * Last modification of published posts per term, in one grouped query.
	 *
	 * @return array<int, string>
	 */
	private function modified(): array {
		if ( null !== $this->modified ) {
			return $this->modified;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate not available through the API; result cached for the request and the sitemap file is cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, MAX(p.post_modified_gmt) AS modified
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				WHERE tt.taxonomy = %s AND p.post_status = 'publish' AND p.post_password = ''
				GROUP BY tt.term_id",
				$this->taxonomy
			)
		);

		$this->modified = array();

		foreach ( (array) $rows as $row ) {
			$this->modified[ (int) $row->term_id ] = (string) $row->modified;
		}

		return $this->modified;
	}

	/**
	 * Base get_terms() arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function query_args(): array {
		$key = Fields::PREFIX . 'noindex';

		$meta_query = $this->noindex_default
			? array(
				array(
					'key'   => $key,
					'value' => 'index',
				),
			)
			: array(
				'relation' => 'OR',
				array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $key,
					'value'   => 'noindex',
					'compare' => '!=',
				),
			);

		$args = array(
			'taxonomy'               => $this->taxonomy,
			'hide_empty'             => true,
			'orderby'                => 'term_id',
			'order'                  => 'ASC',
			'exclude'                => $this->exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A short list of terms the site owner removed from the sitemap.
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to honour per-term noindex.
			'update_term_meta_cache' => true,
		);

		/**
		 * Filters the query used to list a taxonomy in the sitemap.
		 *
		 * @param array  $args     get_terms() arguments.
		 * @param string $taxonomy Taxonomy.
		 */
		return (array) apply_filters( 'wpiseo_sitemap_term_query_args', $args, $this->taxonomy );
	}

	/**
	 * MySQL GMT datetime to W3C.
	 *
	 * @param string $gmt Datetime.
	 */
	private static function w3c( string $gmt ): string {
		$time = strtotime( $gmt . ' UTC' );

		return false === $time ? '' : gmdate( 'c', $time );
	}
}
