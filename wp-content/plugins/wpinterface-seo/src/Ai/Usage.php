<?php
/**
 * Per-user daily AI request cap.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Counts AI requests per user per day (site time) in user meta. The cap
 * protects the site owner's provider budget; core sets no limits.
 */
final class Usage {

	private const META = '_wpiseo_ai_usage';

	/**
	 * Requests made today by a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function today( int $user_id ): int {
		$usage = get_user_meta( $user_id, self::META, true );

		return is_array( $usage ) && ( $usage['date'] ?? '' ) === self::date() ? (int) ( $usage['count'] ?? 0 ) : 0;
	}

	/**
	 * Records one request.
	 *
	 * @param int $user_id User ID.
	 */
	public static function record( int $user_id ): void {
		update_user_meta(
			$user_id,
			self::META,
			array(
				'date'  => self::date(),
				'count' => self::today( $user_id ) + 1,
			)
		);
	}

	/**
	 * Today's date in the site's timezone.
	 */
	private static function date(): string {
		return wp_date( 'Y-m-d' );
	}
}
