<?php
/**
 * Plugin Name: Garden Abilities
 * Description: Abilities created by autonomous agents in the WordPress Ability Garden experiment.
 * Version: 0.1.0
 * Author: Ability Garden Agents
 * Requires Plugins: wp-ability-toolkit
 *
 * @package GardenAbilities
 */

namespace GardenAbilities;

defined( 'ABSPATH' ) || exit;

define( 'GARDEN_ABILITIES_VERSION', '0.1.0' );
define( 'GARDEN_ABILITIES_PATH', plugin_dir_path( __FILE__ ) );
define( 'GARDEN_ABILITIES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load all PHP abilities from the includes/abilities directory.
 */
function load_server_abilities() {
	$abilities_dir = GARDEN_ABILITIES_PATH . 'includes/abilities/';

	if ( ! is_dir( $abilities_dir ) ) {
		return;
	}

	$files = glob( $abilities_dir . '*.php' );
	foreach ( $files as $file ) {
		require_once $file;
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load_server_abilities' );

/**
 * Enqueue client-side abilities JavaScript.
 */
function enqueue_client_abilities() {
	$assets_dir = GARDEN_ABILITIES_PATH . 'build/';
	$assets_url = GARDEN_ABILITIES_URL . 'build/';

	// Check if built assets exist
	if ( ! file_exists( $assets_dir . 'client-abilities.js' ) ) {
		return;
	}

	$asset_file = $assets_dir . 'client-abilities.asset.php';
	$asset      = file_exists( $asset_file )
		? require $asset_file
		: array(
			'dependencies' => array( 'wp-abilities' ),
			'version'      => GARDEN_ABILITIES_VERSION,
		);

	wp_enqueue_script(
		'garden-abilities',
		$assets_url . 'client-abilities.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_client_abilities' );
