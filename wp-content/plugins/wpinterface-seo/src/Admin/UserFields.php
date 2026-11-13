<?php
/**
 * Social profile fields on the user profile screen.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Admin;

use WP_User;
use WPInterface\SEO\Meta\Fields;
use WPInterface\SEO\Meta\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Lets authors add social profile URLs (used for schema `sameAs` and `twitter:creator`).
 */
final class UserFields {

	private const NONCE = 'wpiseo_user_fields';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	/**
	 * Network labels.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'facebook'  => 'Facebook',
			'x'         => 'X (Twitter)',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'youtube'   => 'YouTube',
			'pinterest' => 'Pinterest',
			'tiktok'    => 'TikTok',
			'threads'   => 'Threads',
			'mastodon'  => 'Mastodon',
			'bluesky'   => 'Bluesky',
			'github'    => 'GitHub',
			'wikipedia' => 'Wikipedia',
		);
	}

	/**
	 * Renders the profile section.
	 *
	 * @param WP_User $user User being edited.
	 */
	public function render( $user ): void {
		if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$profiles = Meta::user( (int) $user->ID, 'social_profiles' );
		$profiles = is_array( $profiles ) ? $profiles : array();

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<h2><?php esc_html_e( 'SEO: social profiles', 'wpinterface-seo' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Shown to search engines as profiles of this author. Use full URLs; for X you can also enter @username.', 'wpinterface-seo' ); ?></p>
		<table class="form-table" role="presentation">
			<?php foreach ( self::labels() as $network => $label ) : ?>
				<tr>
					<th scope="row"><label for="wpiseo-profile-<?php echo esc_attr( $network ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" class="regular-text code" id="wpiseo-profile-<?php echo esc_attr( $network ); ?>" name="wpiseo_profiles[<?php echo esc_attr( $network ); ?>]" value="<?php echo esc_attr( (string) ( $profiles[ $network ] ?? '' ) ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Saves the profile URLs.
	 *
	 * @param int $user_id User ID.
	 */
	public function save( $user_id ): void {
		if (
			! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['wpiseo_profiles'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE )
			|| ! current_user_can( 'edit_user', (int) $user_id )
			|| ! is_array( $_POST['wpiseo_profiles'] )
		) {
			return;
		}

		// Sanitized per network by the field's spec as it is read.
		$profiles = Fields::sanitize( Fields::user()['social_profiles'], wp_unslash( $_POST['wpiseo_profiles'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fields::sanitize().

		Meta::write( 'user', (int) $user_id, 'social_profiles', $profiles );
	}
}
