<?php
/**
 * Automatic redirects when URLs change.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Redirects;

use WP_Post;
use WP_Term;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Creates 301s when a published post or term changes URL, and optionally
 * a 410 or redirect when a published post is trashed (removed again if the
 * post is restored).
 */
final class AutoRedirects {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Rules.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Term links captured before an edit.
	 *
	 * @var array<int, string>
	 */
	private array $term_links = array();

	/**
	 * Constructor.
	 *
	 * @param Options    $options    Settings.
	 * @param Repository $repository Rules.
	 */
	public function __construct( Options $options, Repository $repository ) {
		$this->options    = $options;
		$this->repository = $repository;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
		add_action( 'edit_terms', array( $this, 'before_term_edit' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'term_edited' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'post_trashed' ) );
		add_action( 'untrashed_post', array( $this, 'post_untrashed' ) );
	}

	/**
	 * Redirects the old URL of a published post whose URL changed.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   Post after the update.
	 * @param WP_Post $before  Post before the update.
	 */
	public function post_updated( $post_id, $after, $before ): void {
		if (
			! $after instanceof WP_Post
			|| ! $before instanceof WP_Post
			|| ! $this->options->get( 'redirects.auto_post', true )
			|| 'publish' !== $before->post_status
			|| 'publish' !== $after->post_status
			|| ! is_post_type_viewable( $after->post_type )
			|| wp_is_post_revision( $after )
		) {
			return;
		}

		$old = get_permalink( $before );
		$new = get_permalink( $after );

		if ( ! is_string( $old ) || ! is_string( $new ) || untrailingslashit( $old ) === untrailingslashit( $new ) ) {
			return;
		}

		$this->repository->create(
			array(
				'source'  => $old,
				'target'  => $new,
				'status'  => 301,
				'origin'  => 'slug',
				'post_id' => (int) $post_id,
			)
		);
	}

	/**
	 * Remembers a term's link before it is edited.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function before_term_edit( $term_id, $taxonomy ): void {
		$taxonomy_object = get_taxonomy( (string) $taxonomy );

		if ( ! $taxonomy_object || ! $taxonomy_object->public ) {
			return;
		}

		$link = get_term_link( (int) $term_id, (string) $taxonomy );

		if ( is_string( $link ) ) {
			$this->term_links[ (int) $term_id ] = $link;
		}
	}

	/**
	 * Redirects the old term URL after a slug or parent change.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_edited( $term_id, $tt_id, $taxonomy ): void {
		unset( $tt_id );

		$old = $this->term_links[ (int) $term_id ] ?? '';

		if ( '' === $old || ! $this->options->get( 'redirects.auto_term', true ) ) {
			return;
		}

		clean_term_cache( (int) $term_id, (string) $taxonomy );
		$new = get_term_link( (int) $term_id, (string) $taxonomy );

		if ( ! is_string( $new ) || untrailingslashit( $old ) === untrailingslashit( $new ) ) {
			return;
		}

		$this->repository->create(
			array(
				'source' => $old,
				'target' => $new,
				'status' => 301,
				'origin' => 'term',
			)
		);
	}

	/**
	 * Handles a published post going to the trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_trashed( $post_id ): void {
		$post   = get_post( (int) $post_id );
		$action = (string) $this->options->get( 'redirects.on_trash', 'none' );

		if ( ! $post instanceof WP_Post || 'none' === $action || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$url = get_permalink( $post );

		if ( ! is_string( $url ) ) {
			return;
		}

		$rule = array(
			'source'  => $url,
			'origin'  => 'trash',
			'post_id' => (int) $post->ID,
		);

		if ( '410' === $action ) {
			$rule['status'] = 410;
		} else {
			$rule['status'] = 301;
			$rule['target'] = $this->fallback_url( $post );
		}

		$this->repository->create( $rule );
	}

	/**
	 * Removes the trash rule when a post is restored.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_untrashed( $post_id ): void {
		$this->repository->delete_for_post( (int) $post_id, 'trash' );
	}

	/**
	 * Where a trashed post's visitors go: its primary category, the post type
	 * archive, or the home page.
	 *
	 * @param WP_Post $post Post.
	 */
	private function fallback_url( WP_Post $post ): string {
		if ( is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			$term = Meta::primary_term( $post, 'category' );
			$link = $term instanceof WP_Term ? get_term_link( $term ) : '';

			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}

		$archive = get_post_type_archive_link( $post->post_type );

		return is_string( $archive ) ? $archive : home_url( '/' );
	}
}
