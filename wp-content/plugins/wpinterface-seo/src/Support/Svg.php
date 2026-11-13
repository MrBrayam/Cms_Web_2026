<?php
/**
 * Inline SVG output.
 *
 * @package WPInterface\SEO
 */

namespace WPInterface\SEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * SVG allow-list for wp_kses(), for the plugin's small inline icons.
 */
final class Svg {

	/**
	 * Tags and attributes the icons use: `wp_kses( $svg, Svg::allowed() )`.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed(): array {
		$shape = array(
			'd'               => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		);

		return array(
			'svg'    => array(
				'xmlns'       => true,
				'class'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'role'        => true,
				'aria-label'  => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path'   => $shape,
			'circle' => $shape,
		);
	}
}
