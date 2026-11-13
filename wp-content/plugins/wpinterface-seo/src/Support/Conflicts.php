<?php
/**
 * Other SEO plugin detection.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Detects active SEO plugins that print the same tags we do.
 */
final class Conflicts {

	/**
	 * Active conflicting plugins: slug => name.
	 *
	 * Must run after all plugins are loaded (`plugins_loaded` or later).
	 *
	 * @return array<string, string>
	 */
	public static function active(): array {
		$checks = array(
			'yoast'      => array( 'Yoast SEO', defined( 'WPSEO_VERSION' ) ),
			'rankmath'   => array( 'Rank Math', defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ),
			'aioseo'     => array( 'All in One SEO', defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ),
			'seopress'   => array( 'SEOPress', defined( 'SEOPRESS_VERSION' ) ),
			'tsf'        => array( 'The SEO Framework', defined( 'THE_SEO_FRAMEWORK_VERSION' ) ),
			'slimseo'    => array( 'Slim SEO', defined( 'SLIM_SEO_VER' ) ),
			'squirrly'   => array( 'Squirrly SEO', defined( 'SQ_VERSION' ) ),
			'smartcrawl' => array( 'SmartCrawl', defined( 'SMARTCRAWL_VERSION' ) ),
		);

		$active = array();

		foreach ( $checks as $slug => $check ) {
			if ( $check[1] ) {
				$active[ $slug ] = $check[0];
			}
		}

		/**
		 * Filters the detected conflicting SEO plugins.
		 *
		 * @param array<string, string> $active Slug => name.
		 */
		return (array) apply_filters( 'wpiseo_conflicting_plugins', $active );
	}
}
