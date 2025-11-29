<?php
/**
 * Ability: Site Health Info
 *
 * Retrieves comprehensive debug information from the WordPress Site Health Info tab,
 * including WordPress configuration, PHP settings, server environment, database info,
 * and active plugins/themes. Useful for AI assistants to troubleshoot issues and
 * understand the site's technical environment.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/site-health-info ability.
 *
 * @return array Output data matching the output_schema.
 */
function garden_abilities_site_health_info_callback() {
	// Require necessary admin files for debug data.
	// The order matters - some files depend on others.
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'got_url_rewrite' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	if ( ! class_exists( 'WP_Debug_Data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
	}

	// Get all debug data from WordPress.
	$debug_data = WP_Debug_Data::debug_data();

	// Extract and format the key sections.
	$result = array(
		'wordpress'          => garden_abilities_format_debug_section( $debug_data, 'wp-core' ),
		'directories_sizes'  => garden_abilities_format_debug_section( $debug_data, 'wp-paths-sizes' ),
		'active_theme'       => garden_abilities_format_debug_section( $debug_data, 'wp-active-theme' ),
		'inactive_themes'    => garden_abilities_format_themes_section( $debug_data, 'wp-themes-inactive' ),
		'must_use_plugins'   => garden_abilities_format_plugins_section( $debug_data, 'wp-mu-plugins' ),
		'active_plugins'     => garden_abilities_format_plugins_section( $debug_data, 'wp-plugins-active' ),
		'inactive_plugins'   => garden_abilities_format_plugins_section( $debug_data, 'wp-plugins-inactive' ),
		'media_handling'     => garden_abilities_format_debug_section( $debug_data, 'wp-media' ),
		'server'             => garden_abilities_format_debug_section( $debug_data, 'wp-server' ),
		'database'           => garden_abilities_format_debug_section( $debug_data, 'wp-database' ),
		'constants'          => garden_abilities_format_debug_section( $debug_data, 'wp-constants' ),
		'filesystem'         => garden_abilities_format_debug_section( $debug_data, 'wp-filesystem' ),
	);

	// Add summary for quick reference.
	$result['summary'] = array(
		'wordpress_version'    => isset( $result['wordpress']['version'] ) ? $result['wordpress']['version'] : 'unknown',
		'php_version'          => isset( $result['server']['php_version'] ) ? $result['server']['php_version'] : 'unknown',
		'database_version'     => isset( $result['database']['server_version'] ) ? $result['database']['server_version'] : 'unknown',
		'active_theme'         => isset( $result['active_theme']['name'] ) ? $result['active_theme']['name'] : 'unknown',
		'active_plugins_count' => is_array( $result['active_plugins'] ) ? count( $result['active_plugins'] ) : 0,
		'environment_type'     => isset( $result['wordpress']['environment_type'] ) ? $result['wordpress']['environment_type'] : 'unknown',
		'is_multisite'         => isset( $result['wordpress']['multisite'] ) && 'Yes' === $result['wordpress']['multisite'],
		'https_status'         => isset( $result['wordpress']['https_status'] ) ? $result['wordpress']['https_status'] : 'unknown',
	);

	return $result;
}

/**
 * Format a debug data section into a simple key-value array.
 *
 * @param array  $debug_data Full debug data array.
 * @param string $section_key The section key to extract.
 * @return array Formatted section data.
 */
function garden_abilities_format_debug_section( $debug_data, $section_key ) {
	if ( ! isset( $debug_data[ $section_key ]['fields'] ) ) {
		return array();
	}

	$result = array();
	foreach ( $debug_data[ $section_key ]['fields'] as $field_key => $field ) {
		// Skip fields without values.
		if ( ! isset( $field['value'] ) ) {
			continue;
		}

		// Convert debug field value to string/simple format.
		$value = $field['value'];

		// Handle boolean-like values.
		if ( is_bool( $value ) ) {
			$value = $value ? 'Yes' : 'No';
		}

		// Use the debug key as the result key (convert to snake_case for consistency).
		$key            = sanitize_key( str_replace( '-', '_', $field_key ) );
		$result[ $key ] = $value;
	}

	return $result;
}

/**
 * Format plugins section into a list of plugin info.
 *
 * @param array  $debug_data Full debug data array.
 * @param string $section_key The section key to extract.
 * @return array Array of plugin info.
 */
function garden_abilities_format_plugins_section( $debug_data, $section_key ) {
	if ( ! isset( $debug_data[ $section_key ]['fields'] ) ) {
		return array();
	}

	$plugins = array();
	foreach ( $debug_data[ $section_key ]['fields'] as $plugin_key => $plugin ) {
		if ( ! isset( $plugin['value'] ) ) {
			continue;
		}

		// Plugin value format is typically "Version X.X.X by Author | Auto-updates enabled"
		$value      = $plugin['value'];
		$label      = isset( $plugin['label'] ) ? $plugin['label'] : $plugin_key;
		$is_private = isset( $plugin['private'] ) && $plugin['private'];

		$plugins[] = array(
			'name'       => sanitize_text_field( $label ),
			'details'    => sanitize_text_field( $value ),
			'is_private' => $is_private,
		);
	}

	return $plugins;
}

/**
 * Format themes section into a list of theme info.
 *
 * @param array  $debug_data Full debug data array.
 * @param string $section_key The section key to extract.
 * @return array Array of theme info.
 */
function garden_abilities_format_themes_section( $debug_data, $section_key ) {
	if ( ! isset( $debug_data[ $section_key ]['fields'] ) ) {
		return array();
	}

	$themes = array();
	foreach ( $debug_data[ $section_key ]['fields'] as $theme_key => $theme ) {
		if ( ! isset( $theme['value'] ) ) {
			continue;
		}

		$themes[] = array(
			'name'    => isset( $theme['label'] ) ? sanitize_text_field( $theme['label'] ) : $theme_key,
			'details' => sanitize_text_field( $theme['value'] ),
		);
	}

	return $themes;
}

/**
 * Register the garden-abilities/site-health-info ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_site_health_info_register' );

/**
 * Registers the site-health-info ability with the Abilities API.
 */
function garden_abilities_site_health_info_register() {
	wp_register_ability(
		'garden-abilities/site-health-info',
		array(
			'label'       => __( 'Site Health Info', 'garden-abilities' ),
			'description' => __( 'Retrieves comprehensive debug information from the Site Health Info tab including WordPress version, PHP configuration, server environment, database details, installed plugins and themes, and directory sizes. Use this to understand the technical environment when troubleshooting issues or verifying system requirements.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current debug info.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'summary', 'wordpress', 'server', 'database' ),
				'properties' => array(
					'summary'            => array(
						'type'        => 'object',
						'description' => __( 'Quick reference summary of key system information.', 'garden-abilities' ),
						'properties'  => array(
							'wordpress_version'    => array(
								'type'        => 'string',
								'description' => __( 'WordPress version number.', 'garden-abilities' ),
							),
							'php_version'          => array(
								'type'        => 'string',
								'description' => __( 'PHP version running on the server.', 'garden-abilities' ),
							),
							'database_version'     => array(
								'type'        => 'string',
								'description' => __( 'Database server version.', 'garden-abilities' ),
							),
							'active_theme'         => array(
								'type'        => 'string',
								'description' => __( 'Name of the currently active theme.', 'garden-abilities' ),
							),
							'active_plugins_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of active plugins.', 'garden-abilities' ),
							),
							'environment_type'     => array(
								'type'        => 'string',
								'description' => __( 'Environment type (local, development, staging, production).', 'garden-abilities' ),
							),
							'is_multisite'         => array(
								'type'        => 'boolean',
								'description' => __( 'Whether this is a multisite installation.', 'garden-abilities' ),
							),
							'https_status'         => array(
								'type'        => 'string',
								'description' => __( 'Whether HTTPS is being used.', 'garden-abilities' ),
							),
						),
					),
					'wordpress'          => array(
						'type'        => 'object',
						'description' => __( 'WordPress core configuration including version, URLs, permalinks, and settings.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'directories_sizes'  => array(
						'type'        => 'object',
						'description' => __( 'Directory paths and sizes for WordPress installation, uploads, themes, and plugins.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'active_theme'       => array(
						'type'        => 'object',
						'description' => __( 'Details about the currently active theme.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'inactive_themes'    => array(
						'type'        => 'array',
						'description' => __( 'List of installed but inactive themes.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'    => array(
									'type'        => 'string',
									'description' => __( 'Theme name.', 'garden-abilities' ),
								),
								'details' => array(
									'type'        => 'string',
									'description' => __( 'Theme version and other details.', 'garden-abilities' ),
								),
							),
						),
					),
					'must_use_plugins'   => array(
						'type'        => 'array',
						'description' => __( 'Must-use plugins installed in wp-content/mu-plugins.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'       => array(
									'type'        => 'string',
									'description' => __( 'Plugin name.', 'garden-abilities' ),
								),
								'details'    => array(
									'type'        => 'string',
									'description' => __( 'Plugin version and author.', 'garden-abilities' ),
								),
								'is_private' => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this is a private/custom plugin.', 'garden-abilities' ),
								),
							),
						),
					),
					'active_plugins'     => array(
						'type'        => 'array',
						'description' => __( 'List of active plugins with version and author info.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'       => array(
									'type'        => 'string',
									'description' => __( 'Plugin name.', 'garden-abilities' ),
								),
								'details'    => array(
									'type'        => 'string',
									'description' => __( 'Plugin version and author.', 'garden-abilities' ),
								),
								'is_private' => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this is a private/custom plugin.', 'garden-abilities' ),
								),
							),
						),
					),
					'inactive_plugins'   => array(
						'type'        => 'array',
						'description' => __( 'List of installed but inactive plugins.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'       => array(
									'type'        => 'string',
									'description' => __( 'Plugin name.', 'garden-abilities' ),
								),
								'details'    => array(
									'type'        => 'string',
									'description' => __( 'Plugin version and author.', 'garden-abilities' ),
								),
								'is_private' => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this is a private/custom plugin.', 'garden-abilities' ),
								),
							),
						),
					),
					'media_handling'     => array(
						'type'        => 'object',
						'description' => __( 'Media handling capabilities and settings.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'server'             => array(
						'type'        => 'object',
						'description' => __( 'Server environment including PHP version, memory limits, and extensions.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'database'           => array(
						'type'        => 'object',
						'description' => __( 'Database server version, charset, collation, and table information.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'constants'          => array(
						'type'        => 'object',
						'description' => __( 'WordPress constants like WP_DEBUG, WP_CACHE, etc.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
					'filesystem'         => array(
						'type'        => 'object',
						'description' => __( 'Filesystem permissions and ownership.', 'garden-abilities' ),
						'additionalProperties' => true,
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_site_health_info_callback',

			// Require user to have view_site_health_checks capability.
			'permission_callback' => function () {
				return current_user_can( 'view_site_health_checks' );
			},

			// Metadata - this is a read-only, safe ability.
			'meta' => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
