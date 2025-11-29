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
 * Config for wp-abilities script (bundles @wordpress/abilities)
 * This creates the script that other entries will use as external
 */
const abilitiesConfig = {
	...defaultConfig,
	entry: {
		'wp-abilities/index': path.resolve(__dirname, 'src/abilities-api/index.js'),
	},
	output: {
		path: path.resolve(__dirname, 'build'),
		filename: '[name].js',
	},
	// No externals - bundle @wordpress/abilities and its deps
	externals: {},
	plugins: [
		...pluginsWithoutDep,
		// Don't use DependencyExtractionWebpackPlugin for abilities bundle
		// since we want to bundle everything including @wordpress/abilities
	],
};

/**
 * Config for main entries (externalizes @wordpress/abilities to wp.abilities)
 */
const mainConfig = {
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

// Export both configs for webpack to process
module.exports = [abilitiesConfig, mainConfig];
