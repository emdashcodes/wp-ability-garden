/**
 * External dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const path = require('path');

// Find and remove the default DependencyExtractionWebpackPlugin
// Note: use constructor.name instead of instanceof due to pnpm module resolution
const pluginsWithoutDep = defaultConfig.plugins.filter(
	(plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

/**
 * Main webpack config
 *
 * Uses the pre-built wp-abilities script from the wordpress/abilities-api
 * Composer package (v0.4.0) - see vendor/wordpress/abilities-api/packages/client/build/
 *
 * @wordpress/abilities is externalized to wp.abilities global
 */
module.exports = {
	...defaultConfig,
	entry: {
		'chat-widget/index': path.resolve(__dirname, 'src/chat-widget/index.tsx'),
		'admin/settings': path.resolve(__dirname, 'src/admin/settings.tsx'),
	},
	output: {
		path: path.resolve(__dirname, 'build'),
		filename: '[name].js',
	},
	externals: [
		// Externalize @wordpress/abilities to wp.abilities global
		({ request }, callback) => {
			if (request === '@wordpress/abilities') {
				return callback(null, ['wp', 'abilities']);
			}
			callback();
		},
	],
	plugins: [
		...pluginsWithoutDep,
		new DependencyExtractionWebpackPlugin({
			useDefaults: true,
			requestToExternal: (request) => {
				if (request === '@wordpress/abilities') {
					return ['wp', 'abilities'];
				}
			},
			requestToHandle: (request) => {
				if (request === '@wordpress/abilities') {
					return 'wp-abilities';
				}
			},
		}),
	],
};
