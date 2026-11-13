<?php
/**
 * SEO fields on term edit screens.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Admin;

use WP_Term;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;
use WPInterface\SEO\Modules\Meta\MetaModule;
use WPInterface\SEO\Plugin;
use WPInterface\SEO\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "SEO" section to the edit screen of every public taxonomy.
 */
final class TermFields {

	private const NONCE = 'wpiseo_term_fields';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'hook_taxonomies' ) );
		add_action( 'edited_term', array( $this, 'save' ), 10, 3 );
	}

	/**
	 * Hooks the edit form of each public taxonomy.
	 */
	public function hook_taxonomies(): void {
		foreach ( get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		) as $taxonomy ) {
			add_action( $taxonomy . '_edit_form', array( $this, 'render' ), 20, 2 );
		}
	}

	/**
	 * Renders the SEO section.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Taxonomy.
	 */
	public function render( $term, $taxonomy ): void {
		if ( ! $term instanceof WP_Term || ! current_user_can( Capabilities::EDIT_SEO ) ) {
			return;
		}

		MediaField::enqueue();

		$settings = MetaModule::taxonomy( Plugin::instance()->options(), (string) $taxonomy );
		$value    = static fn( string $key ) => Meta::term( (int) $term->term_id, $key );
		$noindex  = (string) $value( 'noindex' );

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<h2><?php esc_html_e( 'SEO', 'wpinterface-seo' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-title"><?php esc_html_e( 'SEO title', 'wpinterface-seo' ); ?></label></th>
				<td>
					<input type="text" id="wpiseo-term-title" name="wpiseo_term[title]" value="<?php echo esc_attr( (string) $value( 'title' ) ); ?>" placeholder="<?php echo esc_attr( (string) $settings['title'] ); ?>" />
					<p class="description">
						<?php
						printf(
							/* translators: 1: %term_title% variable, 2: %sep% variable. */
							esc_html__( 'Leave empty to use the template from SEO → Search Appearance. Variables such as %1$s and %2$s work here.', 'wpinterface-seo' ),
							'<code>%term_title%</code>',
							'<code>%sep%</code>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-description"><?php esc_html_e( 'Meta description', 'wpinterface-seo' ); ?></label></th>
				<td><textarea id="wpiseo-term-description" name="wpiseo_term[description]" rows="3" placeholder="<?php echo esc_attr( (string) $settings['description'] ); ?>"><?php echo esc_textarea( (string) $value( 'description' ) ); ?></textarea></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-noindex"><?php esc_html_e( 'Search engine indexing', 'wpinterface-seo' ); ?></label></th>
				<td>
					<select id="wpiseo-term-noindex" name="wpiseo_term[noindex]">
						<option value="" <?php selected( $noindex, '' ); ?>>
							<?php
							echo esc_html(
								$settings['noindex']
									? __( 'Default (noindex)', 'wpinterface-seo' )
									: __( 'Default (index)', 'wpinterface-seo' )
							);
							?>
						</option>
						<option value="index" <?php selected( $noindex, 'index' ); ?>><?php esc_html_e( 'Index', 'wpinterface-seo' ); ?></option>
						<option value="noindex" <?php selected( $noindex, 'noindex' ); ?>><?php esc_html_e( 'Noindex', 'wpinterface-seo' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-canonical"><?php esc_html_e( 'Canonical URL', 'wpinterface-seo' ); ?></label></th>
				<td><input type="url" id="wpiseo-term-canonical" name="wpiseo_term[canonical]" value="<?php echo esc_attr( (string) $value( 'canonical' ) ); ?>" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-breadcrumb"><?php esc_html_e( 'Breadcrumb title', 'wpinterface-seo' ); ?></label></th>
				<td><input type="text" id="wpiseo-term-breadcrumb" name="wpiseo_term[breadcrumb_title]" value="<?php echo esc_attr( (string) $value( 'breadcrumb_title' ) ); ?>" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-og-title"><?php esc_html_e( 'Social title', 'wpinterface-seo' ); ?></label></th>
				<td><input type="text" id="wpiseo-term-og-title" name="wpiseo_term[og_title]" value="<?php echo esc_attr( (string) $value( 'og_title' ) ); ?>" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="wpiseo-term-og-description"><?php esc_html_e( 'Social description', 'wpinterface-seo' ); ?></label></th>
				<td><textarea id="wpiseo-term-og-description" name="wpiseo_term[og_description]" rows="2"><?php echo esc_textarea( (string) $value( 'og_description' ) ); ?></textarea></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><?php esc_html_e( 'Social image', 'wpinterface-seo' ); ?></th>
				<td>
					<?php MediaField::render( 'wpiseo_term[og_image]', (int) $value( 'og_image' ) ); ?>
					<p class="description"><?php esc_html_e( 'Used for Facebook, LinkedIn and X. Recommended size: 1200 × 630 pixels.', 'wpinterface-seo' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the fields.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function save( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		unset( $tt_id, $taxonomy );

		if (
			! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['wpiseo_term'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE )
			|| ! current_user_can( 'edit_term', (int) $term_id )
			|| ! current_user_can( Capabilities::EDIT_SEO )
			|| ! is_array( $_POST['wpiseo_term'] )
		) {
			return;
		}

		foreach ( Fields::term() as $key => $spec ) {
			if ( array_key_exists( $key, $_POST['wpiseo_term'] ) ) {
				// Sanitized by the field's spec as it is read.
				$value = Fields::sanitize( $spec, wp_unslash( $_POST['wpiseo_term'][ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fields::sanitize().

				Meta::write( 'term', (int) $term_id, $key, $value );
			}
		}
	}
}
