<?php
/**
 * Request context.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Context;

use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable description of "what is being rendered": the page type, its
 * queried object and pagination.
 *
 * Every output feature (titles, canonical, robots, schema, breadcrumbs)
 * reads this instead of calling conditional tags, so the same code can
 * render for the current request, for an arbitrary post in the editor
 * preview, or from WP-CLI.
 */
final class Context {

	public const FRONT_PAGE        = 'front_page';
	public const HOME              = 'home';
	public const SINGULAR          = 'singular';
	public const TERM              = 'term';
	public const POST_TYPE_ARCHIVE = 'post_type_archive';
	public const AUTHOR            = 'author';
	public const DATE              = 'date';
	public const SEARCH            = 'search';
	public const NOT_FOUND         = 'not_found';
	public const OTHER             = 'other';

	/**
	 * One of the type constants.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Queried object.
	 *
	 * @var WP_Post|WP_Term|WP_User|WP_Post_Type|null
	 */
	private $object;

	/**
	 * Post type, taxonomy or empty string.
	 *
	 * @var string
	 */
	private string $subtype;

	/**
	 * Current page number (1-based). For singular posts this is the `<!--nextpage-->` page.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * Total number of pages.
	 *
	 * @var int
	 */
	private int $max_pages;

	/**
	 * Search phrase for search results.
	 *
	 * @var string
	 */
	private string $search_query;

	/**
	 * Date archive parts.
	 *
	 * @var array{year: int, month: int, day: int}
	 */
	private array $date;

	/**
	 * Use the named constructors.
	 *
	 * @param string                                    $type         Type constant.
	 * @param WP_Post|WP_Term|WP_User|WP_Post_Type|null $queried      Queried object.
	 * @param string                                    $subtype      Post type or taxonomy.
	 * @param int                                       $page         Current page.
	 * @param int                                       $max_pages    Total pages.
	 * @param string                                    $search_query Search phrase.
	 * @param array{year: int, month: int, day: int}    $date         Date parts.
	 */
	private function __construct(
		string $type,
		$queried = null,
		string $subtype = '',
		int $page = 1,
		int $max_pages = 1,
		string $search_query = '',
		array $date = array(
			'year'  => 0,
			'month' => 0,
			'day'   => 0,
		)
	) {
		$this->type         = $type;
		$this->object       = $queried;
		$this->subtype      = $subtype;
		$this->page         = max( 1, $page );
		$this->max_pages    = max( 1, $max_pages, $this->page );
		$this->search_query = $search_query;
		$this->date         = $date;
	}

	/**
	 * Context for a post, page or custom post type entry.
	 *
	 * Detects the static front page and the posts page.
	 *
	 * @param WP_Post|int $post Post or ID.
	 * @param int         $page Current `<!--nextpage-->` page.
	 */
	public static function for_post( $post, int $page = 1 ): self {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return self::other();
		}

		$type = self::SINGULAR;

		if ( 'page' === get_option( 'show_on_front' ) ) {
			if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
				$type = self::FRONT_PAGE;
			} elseif ( (int) get_option( 'page_for_posts' ) === $post->ID ) {
				$type = self::HOME;
			}
		}

		$max_pages = self::HOME === $type ? 1 : substr_count( $post->post_content, '<!--nextpage-->' ) + 1;

		return new self( $type, $post, $post->post_type, $page, $max_pages );
	}

	/**
	 * Context for a term archive.
	 *
	 * @param WP_Term|int $term     Term or term ID.
	 * @param string      $taxonomy Taxonomy, needed only when passing an ID that may be ambiguous.
	 * @param int         $page     Current page.
	 * @param int         $max      Total pages.
	 */
	public static function for_term( $term, string $taxonomy = '', int $page = 1, int $max = 1 ): self {
		$term = get_term( $term, $taxonomy );

		if ( ! $term instanceof WP_Term ) {
			return self::other();
		}

		return new self( self::TERM, $term, $term->taxonomy, $page, $max );
	}

	/**
	 * Context for an author archive.
	 *
	 * @param WP_User|int $user User or ID.
	 * @param int         $page Current page.
	 * @param int         $max  Total pages.
	 */
	public static function for_user( $user, int $page = 1, int $max = 1 ): self {
		$user = $user instanceof WP_User ? $user : get_userdata( (int) $user );

		if ( ! $user instanceof WP_User ) {
			return self::other();
		}

		return new self( self::AUTHOR, $user, '', $page, $max );
	}

	/**
	 * Context for a post type archive.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $page      Current page.
	 * @param int    $max       Total pages.
	 */
	public static function for_post_type_archive( string $post_type, int $page = 1, int $max = 1 ): self {
		$object = get_post_type_object( $post_type );

		if ( ! $object instanceof WP_Post_Type ) {
			return self::other();
		}

		return new self( self::POST_TYPE_ARCHIVE, $object, $post_type, $page, $max );
	}

	/**
	 * Context for search results.
	 *
	 * @param string $search_query Search phrase.
	 * @param int    $page         Current page.
	 * @param int    $max          Total pages.
	 */
	public static function for_search( string $search_query, int $page = 1, int $max = 1 ): self {
		return new self( self::SEARCH, null, '', $page, $max, $search_query );
	}

	/**
	 * Context for a date archive.
	 *
	 * @param int $year  Year.
	 * @param int $month Month (0 for a yearly archive).
	 * @param int $day   Day (0 for a monthly or yearly archive).
	 * @param int $page  Current page.
	 * @param int $max   Total pages.
	 */
	public static function for_date( int $year, int $month = 0, int $day = 0, int $page = 1, int $max = 1 ): self {
		return new self(
			self::DATE,
			null,
			'',
			$page,
			$max,
			'',
			array(
				'year'  => $year,
				'month' => $month,
				'day'   => $day,
			)
		);
	}

	/**
	 * Context for the front page when it lists latest posts.
	 *
	 * @param int $page Current page.
	 * @param int $max  Total pages.
	 */
	public static function for_front_page_posts( int $page = 1, int $max = 1 ): self {
		return new self( self::FRONT_PAGE, null, 'post', $page, $max );
	}

	/**
	 * Context for a 404 response.
	 */
	public static function not_found(): self {
		return new self( self::NOT_FOUND );
	}

	/**
	 * Fallback context (feeds, embeds, admin, early calls).
	 */
	public static function other(): self {
		return new self( self::OTHER );
	}

	/**
	 * Resolves the context of a query (normally the main query).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function from_query( WP_Query $query ): self {
		$paged = max( 1, (int) $query->get( 'paged' ) );
		$max   = max( 1, (int) $query->max_num_pages );

		if ( $query->is_404() ) {
			return self::not_found();
		}

		if ( $query->is_search() ) {
			return self::for_search( (string) $query->get( 's' ), $paged, $max );
		}

		if ( $query->is_front_page() ) {
			$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;

			if ( $front_id ) {
				// A static front page paginates with the `page` query var.
				return self::for_post( $front_id, max( 1, (int) $query->get( 'page' ), $paged ) );
			}

			return self::for_front_page_posts( $paged, $max );
		}

		if ( $query->is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$post       = $posts_page ? get_post( $posts_page ) : null;

			return new self( self::HOME, $post instanceof WP_Post ? $post : null, 'post', $paged, $max );
		}

		$queried = $query->get_queried_object();

		if ( $query->is_singular() && $queried instanceof WP_Post ) {
			return self::for_post( $queried, max( 1, (int) $query->get( 'page' ) ) );
		}

		if ( ( $query->is_category() || $query->is_tag() || $query->is_tax() ) && $queried instanceof WP_Term ) {
			return self::for_term( $queried, '', $paged, $max );
		}

		if ( $query->is_post_type_archive() && $queried instanceof WP_Post_Type ) {
			return self::for_post_type_archive( $queried->name, $paged, $max );
		}

		if ( $query->is_author() && $queried instanceof WP_User ) {
			return self::for_user( $queried, $paged, $max );
		}

		if ( $query->is_date() ) {
			return self::for_date(
				(int) $query->get( 'year' ),
				(int) $query->get( 'monthnum' ),
				(int) $query->get( 'day' ),
				$paged,
				$max
			);
		}

		return self::other();
	}

	/**
	 * Context type constant.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Whether the context is one of the given types.
	 *
	 * @param string ...$types Type constants.
	 */
	public function is( string ...$types ): bool {
		return in_array( $this->type, $types, true );
	}

	/**
	 * Queried object.
	 *
	 * @return WP_Post|WP_Term|WP_User|WP_Post_Type|null
	 */
	public function queried_object() {
		return $this->object;
	}

	/**
	 * Queried post (singular, static front page, posts page).
	 */
	public function post(): ?WP_Post {
		return $this->object instanceof WP_Post ? $this->object : null;
	}

	/**
	 * Queried term.
	 */
	public function term(): ?WP_Term {
		return $this->object instanceof WP_Term ? $this->object : null;
	}

	/**
	 * Queried user (author archive).
	 */
	public function user(): ?WP_User {
		return $this->object instanceof WP_User ? $this->object : null;
	}

	/**
	 * Queried post type object (post type archive).
	 */
	public function post_type_object(): ?WP_Post_Type {
		return $this->object instanceof WP_Post_Type ? $this->object : null;
	}

	/**
	 * ID of the queried post, term or user; 0 otherwise.
	 */
	public function object_id(): int {
		if ( $this->object instanceof WP_Post || $this->object instanceof WP_User ) {
			return (int) $this->object->ID;
		}

		if ( $this->object instanceof WP_Term ) {
			return (int) $this->object->term_id;
		}

		return 0;
	}

	/**
	 * Post type or taxonomy name.
	 */
	public function subtype(): string {
		return $this->subtype;
	}

	/**
	 * Current page number (1-based).
	 */
	public function page(): int {
		return $this->page;
	}

	/**
	 * Total pages.
	 */
	public function max_pages(): int {
		return $this->max_pages;
	}

	/**
	 * Whether this is page 2 or later.
	 */
	public function is_paged(): bool {
		return $this->page > 1;
	}

	/**
	 * Search phrase.
	 */
	public function search_query(): string {
		return $this->search_query;
	}

	/**
	 * Date archive parts.
	 *
	 * @return array{year: int, month: int, day: int}
	 */
	public function date(): array {
		return $this->date;
	}

	/**
	 * Copy with different pagination.
	 *
	 * @param int $page      Current page.
	 * @param int $max_pages Total pages.
	 */
	public function with_page( int $page, int $max_pages ): self {
		$clone            = clone $this;
		$clone->page      = max( 1, $page );
		$clone->max_pages = max( 1, $max_pages, $clone->page );

		return $clone;
	}
}
