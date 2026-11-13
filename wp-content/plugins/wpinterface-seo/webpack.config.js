/**
 * Extends the @wordpress/scripts config with our entry points.
 * Output: assets/build/{entry}.js, {entry}.asset.php, {entry}.css
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
		editor: path.resolve( __dirname, 'assets/src/editor/index.js' ),
		blocks: path.resolve( __dirname, 'assets/src/blocks/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
};
