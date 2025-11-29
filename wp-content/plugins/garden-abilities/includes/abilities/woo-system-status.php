<?php
/**
 * Ability: WooCommerce System Status
 *
 * Retrieves the WooCommerce system status including environment details,
 * database information, active plugins, theme info, and settings.
 * Essential for AI assistants to diagnose WooCommerce issues and
 * understand the store's technical configuration.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/woo-system-status ability.
 *
 * @return array|WP_Error Output data matching the output_schema, or error if WooCommerce not active.
 */
function garden_abilities_woo_system_status_callback() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error(
			'woocommerce_not_active',
			__( 'WooCommerce is not installed or active on this site.', 'garden-abilities' )
		);
	}

	// Get the system status data from WooCommerce.
	$system_status = new WC_REST_System_Status_V2_Controller();

	// Environment data.
	$environment = $system_status->get_environment_info();

	// Database data.
	$database = $system_status->get_database_info();

	// Active plugins.
	$active_plugins = $system_status->get_active_plugins();

	// Inactive plugins.
	$inactive_plugins = $system_status->get_inactive_plugins();

	// Theme info.
	$theme = $system_status->get_theme_info();

	// Settings.
	$settings = $system_status->get_settings();

	// Security.
	$security = $system_status->get_security_info();

	// Build the issues array by checking for problems.
	$issues = array();

	// Check SoapClient.
	if ( empty( $environment['soapclient_enabled'] ) ) {
		$issues[] = array(
			'type'        => 'warning',
			'area'        => 'environment',
			'message'     => __( 'SoapClient is not enabled. Some payment gateways that use SOAP may not work.', 'garden-abilities' ),
			'action_hint' => __( 'Contact your hosting provider to enable the PHP SOAP extension.', 'garden-abilities' ),
		);
	}

	// Check remote connections.
	if ( empty( $environment['remote_post_successful'] ) ) {
		$issues[] = array(
			'type'        => 'error',
			'area'        => 'environment',
			'message'     => __( 'Remote POST requests are failing. WooCommerce cannot communicate with external services.', 'garden-abilities' ),
			'action_hint' => __( 'Check firewall settings or contact your hosting provider about outbound HTTP connections.', 'garden-abilities' ),
		);
	}

	if ( empty( $environment['remote_get_successful'] ) ) {
		$issues[] = array(
			'type'        => 'error',
			'area'        => 'environment',
			'message'     => __( 'Remote GET requests are failing. WooCommerce cannot fetch external resources.', 'garden-abilities' ),
			'action_hint' => __( 'Check firewall settings or contact your hosting provider about outbound HTTP connections.', 'garden-abilities' ),
		);
	}

	// Check WordPress memory.
	if ( isset( $environment['wp_memory_limit'] ) && $environment['wp_memory_limit'] < 67108864 ) {
		$issues[] = array(
			'type'        => 'warning',
			'area'        => 'environment',
			'message'     => __( 'WordPress memory limit is below recommended 64MB for WooCommerce.', 'garden-abilities' ),
			'action_hint' => __( 'Increase WP_MEMORY_LIMIT in wp-config.php or contact your hosting provider.', 'garden-abilities' ),
		);
	}

	// Check PHP version (WooCommerce recommends 7.4+).
	if ( isset( $environment['php_version'] ) && version_compare( $environment['php_version'], '7.4', '<' ) ) {
		$issues[] = array(
			'type'        => 'error',
			'area'        => 'environment',
			'message'     => sprintf(
				/* translators: %s: Current PHP version */
				__( 'PHP version %s is below the recommended 7.4 for WooCommerce.', 'garden-abilities' ),
				$environment['php_version']
			),
			'action_hint' => __( 'Contact your hosting provider to upgrade PHP.', 'garden-abilities' ),
		);
	}

	// Check if HTTPS is enabled.
	if ( ! empty( $security ) && isset( $security['secure_connection'] ) && ! $security['secure_connection'] ) {
		$issues[] = array(
			'type'        => 'error',
			'area'        => 'security',
			'message'     => __( 'Your store is not using HTTPS. This is required for payment processing.', 'garden-abilities' ),
			'action_hint' => __( 'Install an SSL certificate and update your site URLs to use https://', 'garden-abilities' ),
		);
	}

	// Calculate database size.
	$total_database_size = 0;
	if ( ! empty( $database['database_size']['data'] ) ) {
		$total_database_size += floatval( $database['database_size']['data'] );
	}
	if ( ! empty( $database['database_size']['index'] ) ) {
		$total_database_size += floatval( $database['database_size']['index'] );
	}

	// Determine overall status.
	$error_count   = 0;
	$warning_count = 0;
	foreach ( $issues as $issue ) {
		if ( 'error' === $issue['type'] ) {
			$error_count++;
		} elseif ( 'warning' === $issue['type'] ) {
			$warning_count++;
		}
	}

	if ( $error_count > 0 ) {
		$overall_status = 'critical';
	} elseif ( $warning_count > 0 ) {
		$overall_status = 'needs_attention';
	} else {
		$overall_status = 'good';
	}

	return array(
		'overall_status' => $overall_status,
		'summary'        => array(
			'error_count'   => $error_count,
			'warning_count' => $warning_count,
		),
		'issues'         => $issues,
		'environment'    => array(
			'woocommerce_version' => isset( $environment['version'] ) ? sanitize_text_field( $environment['version'] ) : '',
			'wordpress_version'   => isset( $environment['wp_version'] ) ? sanitize_text_field( $environment['wp_version'] ) : '',
			'php_version'         => isset( $environment['php_version'] ) ? sanitize_text_field( $environment['php_version'] ) : '',
			'mysql_version'       => isset( $environment['mysql_version_string'] ) ? sanitize_text_field( $environment['mysql_version_string'] ) : '',
			'server_info'         => isset( $environment['server_info'] ) ? sanitize_text_field( $environment['server_info'] ) : '',
			'php_memory_limit'    => isset( $environment['php_post_max_size'] ) ? size_format( $environment['php_post_max_size'] ) : '',
			'wp_memory_limit'     => isset( $environment['wp_memory_limit'] ) ? size_format( $environment['wp_memory_limit'] ) : '',
			'wp_debug_mode'       => ! empty( $environment['wp_debug_mode'] ),
			'wp_cron_enabled'     => ! empty( $environment['wp_cron'] ),
			'remote_post_works'   => ! empty( $environment['remote_post_successful'] ),
			'remote_get_works'    => ! empty( $environment['remote_get_successful'] ),
		),
		'database'       => array(
			'wc_database_version' => isset( $database['wc_database_version'] ) ? sanitize_text_field( $database['wc_database_version'] ) : '',
			'database_prefix'     => isset( $database['database_prefix'] ) ? sanitize_text_field( $database['database_prefix'] ) : '',
			'total_size_mb'       => round( $total_database_size, 2 ),
		),
		'theme'          => array(
			'name'            => isset( $theme['name'] ) ? sanitize_text_field( $theme['name'] ) : '',
			'version'         => isset( $theme['version'] ) ? sanitize_text_field( $theme['version'] ) : '',
			'is_child_theme'  => ! empty( $theme['is_child_theme'] ),
			'has_wc_support'  => ! empty( $theme['has_woocommerce_support'] ),
		),
		'settings'       => array(
			'currency'            => isset( $settings['currency'] ) ? sanitize_text_field( $settings['currency'] ) : '',
			'currency_position'   => isset( $settings['currency_position'] ) ? sanitize_text_field( $settings['currency_position'] ) : '',
			'thousand_separator'  => isset( $settings['thousand_separator'] ) ? sanitize_text_field( $settings['thousand_separator'] ) : '',
			'decimal_separator'   => isset( $settings['decimal_separator'] ) ? sanitize_text_field( $settings['decimal_separator'] ) : '',
			'number_of_decimals'  => isset( $settings['number_of_decimals'] ) ? absint( $settings['number_of_decimals'] ) : 2,
			'taxes_enabled'       => ! empty( $settings['taxes_enabled'] ),
			'coupons_enabled'     => ! empty( $settings['coupons_enabled'] ),
		),
		'active_plugins_count'   => is_array( $active_plugins ) ? count( $active_plugins ) : 0,
		'inactive_plugins_count' => is_array( $inactive_plugins ) ? count( $inactive_plugins ) : 0,
	);
}

/**
 * Register the garden-abilities/woo-system-status ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_woo_system_status_register' );

/**
 * Registers the woo-system-status ability with the Abilities API.
 */
function garden_abilities_woo_system_status_register() {
	// Only register if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	wp_register_ability(
		'garden-abilities/woo-system-status',
		array(
			'label'       => __( 'WooCommerce System Status', 'garden-abilities' ),
			'description' => __( 'Retrieves WooCommerce system status including environment details (PHP, MySQL, WordPress versions), database information, theme compatibility, store settings, and detected issues. Use this to diagnose WooCommerce problems, verify server requirements, and understand the store configuration.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current system status.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'overall_status', 'summary', 'issues', 'environment', 'database', 'theme', 'settings' ),
				'properties' => array(
					'overall_status'         => array(
						'type'        => 'string',
						'enum'        => array( 'good', 'needs_attention', 'critical' ),
						'description' => __( 'Overall WooCommerce health: good (no issues), needs_attention (warnings), or critical (errors that may break functionality).', 'garden-abilities' ),
					),
					'summary'                => array(
						'type'        => 'object',
						'description' => __( 'Count of issues by severity.', 'garden-abilities' ),
						'properties'  => array(
							'error_count'   => array(
								'type'        => 'integer',
								'description' => __( 'Number of critical errors that may break WooCommerce functionality.', 'garden-abilities' ),
							),
							'warning_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of warnings that should be addressed.', 'garden-abilities' ),
							),
						),
					),
					'issues'                 => array(
						'type'        => 'array',
						'description' => __( 'List of detected issues with the WooCommerce installation.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type'        => array(
									'type'        => 'string',
									'enum'        => array( 'error', 'warning' ),
									'description' => __( 'Severity: error (critical) or warning (should fix).', 'garden-abilities' ),
								),
								'area'        => array(
									'type'        => 'string',
									'description' => __( 'Area affected: environment, security, database, etc.', 'garden-abilities' ),
								),
								'message'     => array(
									'type'        => 'string',
									'description' => __( 'Description of the issue.', 'garden-abilities' ),
								),
								'action_hint' => array(
									'type'        => 'string',
									'description' => __( 'Suggested action to resolve the issue.', 'garden-abilities' ),
								),
							),
						),
					),
					'environment'            => array(
						'type'        => 'object',
						'description' => __( 'Server and WordPress environment information.', 'garden-abilities' ),
						'properties'  => array(
							'woocommerce_version' => array(
								'type'        => 'string',
								'description' => __( 'WooCommerce plugin version.', 'garden-abilities' ),
							),
							'wordpress_version'   => array(
								'type'        => 'string',
								'description' => __( 'WordPress core version.', 'garden-abilities' ),
							),
							'php_version'         => array(
								'type'        => 'string',
								'description' => __( 'PHP version running on the server.', 'garden-abilities' ),
							),
							'mysql_version'       => array(
								'type'        => 'string',
								'description' => __( 'MySQL/MariaDB version.', 'garden-abilities' ),
							),
							'server_info'         => array(
								'type'        => 'string',
								'description' => __( 'Web server software (Apache, nginx, etc.).', 'garden-abilities' ),
							),
							'php_memory_limit'    => array(
								'type'        => 'string',
								'description' => __( 'PHP memory limit (formatted, e.g., "256 MB").', 'garden-abilities' ),
							),
							'wp_memory_limit'     => array(
								'type'        => 'string',
								'description' => __( 'WordPress memory limit (formatted, e.g., "128 MB").', 'garden-abilities' ),
							),
							'wp_debug_mode'       => array(
								'type'        => 'boolean',
								'description' => __( 'Whether WordPress debug mode is enabled.', 'garden-abilities' ),
							),
							'wp_cron_enabled'     => array(
								'type'        => 'boolean',
								'description' => __( 'Whether WP-Cron is enabled for scheduled tasks.', 'garden-abilities' ),
							),
							'remote_post_works'   => array(
								'type'        => 'boolean',
								'description' => __( 'Whether the server can make outbound POST requests.', 'garden-abilities' ),
							),
							'remote_get_works'    => array(
								'type'        => 'boolean',
								'description' => __( 'Whether the server can make outbound GET requests.', 'garden-abilities' ),
							),
						),
					),
					'database'               => array(
						'type'        => 'object',
						'description' => __( 'Database information.', 'garden-abilities' ),
						'properties'  => array(
							'wc_database_version' => array(
								'type'        => 'string',
								'description' => __( 'WooCommerce database version.', 'garden-abilities' ),
							),
							'database_prefix'     => array(
								'type'        => 'string',
								'description' => __( 'WordPress database table prefix.', 'garden-abilities' ),
							),
							'total_size_mb'       => array(
								'type'        => 'number',
								'description' => __( 'Total database size in megabytes.', 'garden-abilities' ),
							),
						),
					),
					'theme'                  => array(
						'type'        => 'object',
						'description' => __( 'Active theme information.', 'garden-abilities' ),
						'properties'  => array(
							'name'           => array(
								'type'        => 'string',
								'description' => __( 'Active theme name.', 'garden-abilities' ),
							),
							'version'        => array(
								'type'        => 'string',
								'description' => __( 'Theme version.', 'garden-abilities' ),
							),
							'is_child_theme' => array(
								'type'        => 'boolean',
								'description' => __( 'Whether a child theme is in use.', 'garden-abilities' ),
							),
							'has_wc_support' => array(
								'type'        => 'boolean',
								'description' => __( 'Whether the theme declares WooCommerce support.', 'garden-abilities' ),
							),
						),
					),
					'settings'               => array(
						'type'        => 'object',
						'description' => __( 'WooCommerce store settings.', 'garden-abilities' ),
						'properties'  => array(
							'currency'           => array(
								'type'        => 'string',
								'description' => __( 'Store currency code (e.g., USD, EUR).', 'garden-abilities' ),
							),
							'currency_position'  => array(
								'type'        => 'string',
								'description' => __( 'Currency symbol position (left, right, etc.).', 'garden-abilities' ),
							),
							'thousand_separator' => array(
								'type'        => 'string',
								'description' => __( 'Thousand separator character.', 'garden-abilities' ),
							),
							'decimal_separator'  => array(
								'type'        => 'string',
								'description' => __( 'Decimal separator character.', 'garden-abilities' ),
							),
							'number_of_decimals' => array(
								'type'        => 'integer',
								'description' => __( 'Number of decimal places for prices.', 'garden-abilities' ),
							),
							'taxes_enabled'      => array(
								'type'        => 'boolean',
								'description' => __( 'Whether taxes are enabled.', 'garden-abilities' ),
							),
							'coupons_enabled'    => array(
								'type'        => 'boolean',
								'description' => __( 'Whether coupons are enabled.', 'garden-abilities' ),
							),
						),
					),
					'active_plugins_count'   => array(
						'type'        => 'integer',
						'description' => __( 'Number of active WordPress plugins.', 'garden-abilities' ),
					),
					'inactive_plugins_count' => array(
						'type'        => 'integer',
						'description' => __( 'Number of inactive WordPress plugins.', 'garden-abilities' ),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_woo_system_status_callback',

			// Require user to be able to manage WooCommerce settings.
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
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
