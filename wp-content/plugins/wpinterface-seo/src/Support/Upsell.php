<?php
/**
 * Pro add-on offers.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small, dismissible pointers to WPInterface SEO Pro, shown on the plugin's
 * own screens to people who manage SEO settings, and only while Pro is not
 * installed. No admin notices, nothing on other screens.
 */
final class Upsell {

	/**
	 * Product page (free and Pro share one page).
	 */
	public const URL = 'https://wpinterface.com/plugins/wpinterface-seo/';

	/**
	 * User meta: IDs of offers the user hid.
	 */
	public const HIDDEN_META = '_wpiseo_hidden_offers';

	/**
	 * Whether offers may be shown to the current user.
	 */
	public static function enabled(): bool {
		$enabled = ! defined( 'WPISEO_PRO_VERSION' ) && current_user_can( Capabilities::MANAGE_SETTINGS );

		/**
		 * Filters whether the plugin shows pointers to WPInterface SEO Pro.
		 * Return false to remove them all, e.g. on client sites.
		 *
		 * @param bool $enabled Whether offers are shown.
		 */
		return (bool) apply_filters( 'wpiseo_show_upsell', $enabled );
	}

	/**
	 * Pro page URL with campaign parameters for one placement.
	 *
	 * @param string $placement Where the link is shown, e.g. "plugins".
	 */
	public static function url( string $placement ): string {
		return add_query_arg(
			array(
				'utm_source'   => 'wpinterface-seo',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'upgrade',
				'utm_content'  => sanitize_key( $placement ),
			),
			self::URL
		);
	}

	/**
	 * Offers the current user hid.
	 *
	 * @return list<string>
	 */
	public static function hidden(): array {
		$hidden = get_user_meta( get_current_user_id(), self::HIDDEN_META, true );

		return is_array( $hidden ) ? array_values( array_map( 'strval', $hidden ) ) : array();
	}

	/**
	 * Hides an offer for the current user.
	 *
	 * @param string $id Offer ID.
	 */
	public static function hide( string $id ): void {
		$hidden = self::hidden();

		if ( ! in_array( $id, $hidden, true ) ) {
			$hidden[] = $id;
			update_user_meta( get_current_user_id(), self::HIDDEN_META, $hidden );
		}
	}
}
