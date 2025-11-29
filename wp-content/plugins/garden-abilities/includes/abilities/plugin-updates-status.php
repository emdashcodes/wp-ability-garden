<?php
/**
 * Ability: Plugin Updates Status
 *
 * Retrieves the current status of plugin and theme updates, including
 * available updates, auto-update settings, and WordPress core version.
 * Useful for AI assistants to proactively monitor and alert about updates.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/plugin-updates-status ability.
 *
 * @return array Output data matching the output_schema.
 */
function garden_abilities_plugin_updates_status_callback() {
	// Require necessary admin files for update functionality.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'get_plugin_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	if ( ! function_exists( 'wp_get_themes' ) ) {
		require_once ABSPATH . 'wp-includes/theme.php';
	}

	// Force a refresh of update data.
	wp_update_plugins();
	wp_update_themes();

	// Get WordPress core info.
	global $wp_version;
	$core_updates     = get_core_updates();
	$core_update_info = array(
		'current_version' => $wp_version,
		'update_available' => false,
		'latest_version'   => $wp_version,
		'update_type'      => 'none',
	);

	if ( ! empty( $core_updates ) && is_array( $core_updates ) ) {
		$update = $core_updates[0];
		if ( isset( $update->response ) && 'upgrade' === $update->response ) {
			$core_update_info['update_available'] = true;
			$core_update_info['latest_version']   = isset( $update->version ) ? $update->version : '';

			// Determine update type (major, minor, security).
			if ( isset( $update->version ) ) {
				$current_parts = explode( '.', $wp_version );
				$new_parts     = explode( '.', $update->version );

				if ( $current_parts[0] !== $new_parts[0] ) {
					$core_update_info['update_type'] = 'major';
				} elseif ( isset( $current_parts[1] ) && isset( $new_parts[1] ) && $current_parts[1] !== $new_parts[1] ) {
					$core_update_info['update_type'] = 'minor';
				} else {
					$core_update_info['update_type'] = 'security';
				}
			}
		}
	}

	// Get all plugins and their update status.
	$all_plugins     = get_plugins();
	$plugin_updates  = get_plugin_updates();
	$active_plugins  = get_option( 'active_plugins', array() );
	$auto_updates    = get_site_option( 'auto_update_plugins', array() );

	$plugins_with_updates    = array();
	$plugins_up_to_date      = array();
	$total_plugins           = count( $all_plugins );
	$active_count            = 0;
	$updates_available_count = 0;
	$auto_updates_enabled    = 0;

	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		$is_active      = in_array( $plugin_file, $active_plugins, true );
		$has_update     = isset( $plugin_updates[ $plugin_file ] );
		$auto_update_on = in_array( $plugin_file, $auto_updates, true );

		if ( $is_active ) {
			$active_count++;
		}
		if ( $auto_update_on ) {
			$auto_updates_enabled++;
		}

		$plugin_info = array(
			'name'             => sanitize_text_field( $plugin_data['Name'] ),
			'slug'             => dirname( $plugin_file ),
			'current_version'  => sanitize_text_field( $plugin_data['Version'] ),
			'is_active'        => $is_active,
			'auto_update'      => $auto_update_on,
		);

		if ( $has_update ) {
			$updates_available_count++;
			$update_data = $plugin_updates[ $plugin_file ];

			$plugin_info['new_version'] = isset( $update_data->update->new_version )
				? sanitize_text_field( $update_data->update->new_version )
				: '';
			$plugin_info['tested']      = isset( $update_data->update->tested )
				? sanitize_text_field( $update_data->update->tested )
				: '';
			$plugin_info['requires_php'] = isset( $update_data->update->requires_php )
				? sanitize_text_field( $update_data->update->requires_php )
				: '';

			$plugins_with_updates[] = $plugin_info;
		} else {
			// Only include basic info for up-to-date plugins to keep response manageable.
			$plugins_up_to_date[] = array(
				'name'            => $plugin_info['name'],
				'current_version' => $plugin_info['current_version'],
				'is_active'       => $plugin_info['is_active'],
				'auto_update'     => $plugin_info['auto_update'],
			);
		}
	}

	// Get theme updates.
	$all_themes           = wp_get_themes();
	$theme_updates        = get_theme_updates();
	$current_theme        = wp_get_theme();
	$themes_with_updates  = array();
	$total_themes         = count( $all_themes );
	$theme_updates_count  = count( $theme_updates );

	foreach ( $theme_updates as $theme_slug => $theme_update ) {
		$theme = wp_get_theme( $theme_slug );
		$themes_with_updates[] = array(
			'name'            => sanitize_text_field( $theme->get( 'Name' ) ),
			'slug'            => $theme_slug,
			'current_version' => sanitize_text_field( $theme->get( 'Version' ) ),
			'new_version'     => isset( $theme_update->update['new_version'] )
				? sanitize_text_field( $theme_update->update['new_version'] )
				: '',
			'is_active'       => ( $current_theme->get_stylesheet() === $theme_slug ),
		);
	}

	// Determine overall status.
	$issues = array();

	if ( $core_update_info['update_available'] ) {
		$issues[] = array(
			'type'    => 'core_update',
			'message' => sprintf(
				'WordPress %s update available (currently %s)',
				$core_update_info['update_type'],
				$core_update_info['latest_version']
			),
			'severity' => 'major' === $core_update_info['update_type'] ? 'warning' : 'critical',
		);
	}

	if ( $updates_available_count > 0 ) {
		// Check if any active plugins need updates.
		$active_with_updates = array_filter( $plugins_with_updates, function( $p ) {
			return $p['is_active'];
		} );

		if ( count( $active_with_updates ) > 0 ) {
			$issues[] = array(
				'type'     => 'active_plugin_updates',
				'message'  => sprintf(
					'%d active plugin(s) have updates available',
					count( $active_with_updates )
				),
				'severity' => 'warning',
			);
		}
	}

	if ( $theme_updates_count > 0 ) {
		$issues[] = array(
			'type'     => 'theme_updates',
			'message'  => sprintf( '%d theme(s) have updates available', $theme_updates_count ),
			'severity' => 'info',
		);
	}

	// Check for plugins without auto-updates enabled.
	$without_auto_update = $total_plugins - $auto_updates_enabled;
	if ( $without_auto_update > 0 && $total_plugins > 0 ) {
		$auto_update_percentage = round( ( $auto_updates_enabled / $total_plugins ) * 100 );
		if ( $auto_update_percentage < 50 ) {
			$issues[] = array(
				'type'     => 'auto_updates_low',
				'message'  => sprintf(
					'Only %d%% of plugins have auto-updates enabled (%d of %d)',
					$auto_update_percentage,
					$auto_updates_enabled,
					$total_plugins
				),
				'severity' => 'info',
			);
		}
	}

	// Determine overall status.
	$has_critical = false;
	$has_warning  = false;
	foreach ( $issues as $issue ) {
		if ( 'critical' === $issue['severity'] ) {
			$has_critical = true;
		} elseif ( 'warning' === $issue['severity'] ) {
			$has_warning = true;
		}
	}

	if ( $has_critical ) {
		$overall_status = 'critical';
	} elseif ( $has_warning ) {
		$overall_status = 'needs_attention';
	} else {
		$overall_status = 'good';
	}

	return array(
		'overall_status' => $overall_status,
		'summary'        => array(
			'total_plugins'            => $total_plugins,
			'active_plugins'           => $active_count,
			'plugins_with_updates'     => $updates_available_count,
			'plugins_auto_update_on'   => $auto_updates_enabled,
			'total_themes'             => $total_themes,
			'themes_with_updates'      => $theme_updates_count,
		),
		'wordpress_core' => $core_update_info,
		'plugin_updates' => $plugins_with_updates,
		'theme_updates'  => $themes_with_updates,
		'issues'         => $issues,
		'checked_at'     => current_time( 'c' ),
	);
}

/**
 * Register the garden-abilities/plugin-updates-status ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_plugin_updates_status_register' );

/**
 * Registers the plugin-updates-status ability with the Abilities API.
 */
function garden_abilities_plugin_updates_status_register() {
	wp_register_ability(
		'garden-abilities/plugin-updates-status',
		array(
			'label'       => __( 'Plugin Updates Status', 'garden-abilities' ),
			'description' => __( 'Retrieves the current status of all plugin and theme updates, including which plugins have updates available, auto-update settings, and WordPress core version. Use this to proactively monitor and alert administrators about available updates, ensuring the site stays secure and up-to-date.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current update status.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'overall_status', 'summary', 'wordpress_core', 'plugin_updates', 'theme_updates', 'issues', 'checked_at' ),
				'properties' => array(
					'overall_status' => array(
						'type'        => 'string',
						'enum'        => array( 'good', 'needs_attention', 'critical' ),
						'description' => __( 'Overall update status: good (all up to date), needs_attention (non-critical updates), or critical (security updates pending).', 'garden-abilities' ),
					),
					'summary'        => array(
						'type'        => 'object',
						'description' => __( 'Summary counts for plugins and themes.', 'garden-abilities' ),
						'properties'  => array(
							'total_plugins'            => array(
								'type'        => 'integer',
								'description' => __( 'Total number of installed plugins.', 'garden-abilities' ),
							),
							'active_plugins'           => array(
								'type'        => 'integer',
								'description' => __( 'Number of active plugins.', 'garden-abilities' ),
							),
							'plugins_with_updates'     => array(
								'type'        => 'integer',
								'description' => __( 'Number of plugins with updates available.', 'garden-abilities' ),
							),
							'plugins_auto_update_on'   => array(
								'type'        => 'integer',
								'description' => __( 'Number of plugins with auto-updates enabled.', 'garden-abilities' ),
							),
							'total_themes'             => array(
								'type'        => 'integer',
								'description' => __( 'Total number of installed themes.', 'garden-abilities' ),
							),
							'themes_with_updates'      => array(
								'type'        => 'integer',
								'description' => __( 'Number of themes with updates available.', 'garden-abilities' ),
							),
						),
					),
					'wordpress_core' => array(
						'type'        => 'object',
						'description' => __( 'WordPress core version and update status.', 'garden-abilities' ),
						'properties'  => array(
							'current_version'   => array(
								'type'        => 'string',
								'description' => __( 'Currently installed WordPress version.', 'garden-abilities' ),
							),
							'update_available'  => array(
								'type'        => 'boolean',
								'description' => __( 'Whether a WordPress update is available.', 'garden-abilities' ),
							),
							'latest_version'    => array(
								'type'        => 'string',
								'description' => __( 'Latest available WordPress version.', 'garden-abilities' ),
							),
							'update_type'       => array(
								'type'        => 'string',
								'enum'        => array( 'none', 'major', 'minor', 'security' ),
								'description' => __( 'Type of update: none, major (X.0), minor (X.Y), or security (X.Y.Z).', 'garden-abilities' ),
							),
						),
					),
					'plugin_updates' => array(
						'type'        => 'array',
						'description' => __( 'List of plugins that have updates available.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'            => array(
									'type'        => 'string',
									'description' => __( 'Plugin display name.', 'garden-abilities' ),
								),
								'slug'            => array(
									'type'        => 'string',
									'description' => __( 'Plugin directory/slug.', 'garden-abilities' ),
								),
								'current_version' => array(
									'type'        => 'string',
									'description' => __( 'Currently installed version.', 'garden-abilities' ),
								),
								'new_version'     => array(
									'type'        => 'string',
									'description' => __( 'New version available.', 'garden-abilities' ),
								),
								'is_active'       => array(
									'type'        => 'boolean',
									'description' => __( 'Whether the plugin is currently active.', 'garden-abilities' ),
								),
								'auto_update'     => array(
									'type'        => 'boolean',
									'description' => __( 'Whether auto-updates are enabled.', 'garden-abilities' ),
								),
								'tested'          => array(
									'type'        => 'string',
									'description' => __( 'WordPress version the update is tested up to.', 'garden-abilities' ),
								),
								'requires_php'    => array(
									'type'        => 'string',
									'description' => __( 'Minimum PHP version required.', 'garden-abilities' ),
								),
							),
						),
					),
					'theme_updates'  => array(
						'type'        => 'array',
						'description' => __( 'List of themes that have updates available.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'            => array(
									'type'        => 'string',
									'description' => __( 'Theme display name.', 'garden-abilities' ),
								),
								'slug'            => array(
									'type'        => 'string',
									'description' => __( 'Theme slug/directory.', 'garden-abilities' ),
								),
								'current_version' => array(
									'type'        => 'string',
									'description' => __( 'Currently installed version.', 'garden-abilities' ),
								),
								'new_version'     => array(
									'type'        => 'string',
									'description' => __( 'New version available.', 'garden-abilities' ),
								),
								'is_active'       => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this is the active theme.', 'garden-abilities' ),
								),
							),
						),
					),
					'issues'         => array(
						'type'        => 'array',
						'description' => __( 'Detected issues and recommendations related to updates.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type'     => array(
									'type'        => 'string',
									'description' => __( 'Issue type identifier.', 'garden-abilities' ),
								),
								'message'  => array(
									'type'        => 'string',
									'description' => __( 'Human-readable description of the issue.', 'garden-abilities' ),
								),
								'severity' => array(
									'type'        => 'string',
									'enum'        => array( 'critical', 'warning', 'info' ),
									'description' => __( 'Issue severity level.', 'garden-abilities' ),
								),
							),
						),
					),
					'checked_at'     => array(
						'type'        => 'string',
						'format'      => 'date-time',
						'description' => __( 'ISO 8601 timestamp when the check was performed.', 'garden-abilities' ),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_plugin_updates_status_callback',

			// Require user to have update capabilities.
			'permission_callback' => function () {
				return current_user_can( 'update_plugins' );
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
