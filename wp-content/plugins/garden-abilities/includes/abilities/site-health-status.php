<?php
/**
 * Ability: Site Health Status
 *
 * Retrieves the WordPress Site Health status including critical issues,
 * recommended improvements, and passed tests. Useful for AI assistants
 * to diagnose site problems and suggest fixes.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/site-health-status ability.
 *
 * @return array Output data matching the output_schema.
 */
function garden_abilities_site_health_status_callback() {
	// Require necessary admin files for Site Health functionality.
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'get_plugin_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	if ( ! class_exists( 'WP_Site_Health' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	$site_health = WP_Site_Health::get_instance();
	$tests       = $site_health->get_tests();

	$critical_issues     = array();
	$recommendations     = array();
	$passed_tests        = array();
	$good_count          = 0;
	$recommended_count   = 0;
	$critical_count      = 0;

	// Tests to skip - either slow, require special conditions, or problematic in REST context.
	$skip_tests = array(
		'loopback_requests',
		'authorization_header',
		'dotorg_communication',
		'background_updates',
	);

	// Run direct tests (synchronous tests that run immediately).
	if ( ! empty( $tests['direct'] ) ) {
		foreach ( $tests['direct'] as $test_key => $test ) {
			// Skip tests that might be slow or require special conditions.
			if ( in_array( $test_key, $skip_tests, true ) ) {
				continue;
			}

			$callback = false;

			// Determine the callback function.
			if ( is_string( $test['test'] ) ) {
				$method = 'get_test_' . $test['test'];
				if ( method_exists( $site_health, $method ) ) {
					$callback = array( $site_health, $method );
				}
			} elseif ( is_callable( $test['test'] ) ) {
				$callback = $test['test'];
			}

			if ( ! $callback || ! is_callable( $callback ) ) {
				continue;
			}

			// Run the test and capture the result safely.
			try {
				$result = call_user_func( $callback );
			} catch ( \Throwable $e ) {
				// Skip tests that throw errors.
				continue;
			}

			if ( ! is_array( $result ) || empty( $result['status'] ) ) {
				continue;
			}

			$test_info = array(
				'label'       => isset( $result['label'] ) ? sanitize_text_field( $result['label'] ) : $test_key,
				'description' => isset( $result['description'] ) ? wp_strip_all_tags( $result['description'] ) : '',
				'badge'       => isset( $result['badge']['label'] ) ? sanitize_text_field( $result['badge']['label'] ) : '',
			);

			// Add action guidance if available.
			if ( ! empty( $result['actions'] ) ) {
				$test_info['actions'] = wp_strip_all_tags( $result['actions'] );
			}

			// Categorize by status.
			switch ( $result['status'] ) {
				case 'critical':
					$critical_issues[] = $test_info;
					$critical_count++;
					break;
				case 'recommended':
					$recommendations[] = $test_info;
					$recommended_count++;
					break;
				case 'good':
					$passed_tests[] = $test_info;
					$good_count++;
					break;
			}
		}
	}

	// Determine overall status.
	if ( $critical_count > 0 ) {
		$overall_status = 'critical';
	} elseif ( $recommended_count > 0 ) {
		$overall_status = 'should_be_improved';
	} else {
		$overall_status = 'good';
	}

	return array(
		'overall_status'    => $overall_status,
		'summary'           => array(
			'critical_count'    => $critical_count,
			'recommended_count' => $recommended_count,
			'good_count'        => $good_count,
		),
		'critical_issues'   => $critical_issues,
		'recommendations'   => $recommendations,
		'passed_tests'      => $passed_tests,
	);
}

/**
 * Register the garden-abilities/site-health-status ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_site_health_status_register' );

/**
 * Registers the site-health-status ability with the Abilities API.
 */
function garden_abilities_site_health_status_register() {
	wp_register_ability(
		'garden-abilities/site-health-status',
		array(
			'label'       => __( 'Site Health Status', 'garden-abilities' ),
			'description' => __( 'Retrieves the WordPress Site Health status including critical issues that need immediate attention, recommended improvements, and tests that passed. Use this to diagnose site problems, identify security concerns, and get actionable recommendations.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current site health status.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'overall_status', 'summary', 'critical_issues', 'recommendations', 'passed_tests' ),
				'properties' => array(
					'overall_status'    => array(
						'type'        => 'string',
						'enum'        => array( 'good', 'should_be_improved', 'critical' ),
						'description' => __( 'Overall site health status: good (all tests passed), should_be_improved (has recommendations), or critical (has critical issues).', 'garden-abilities' ),
					),
					'summary'           => array(
						'type'        => 'object',
						'description' => __( 'Count of issues by severity.', 'garden-abilities' ),
						'properties'  => array(
							'critical_count'    => array(
								'type'        => 'integer',
								'description' => __( 'Number of critical issues requiring immediate attention.', 'garden-abilities' ),
							),
							'recommended_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of recommended improvements.', 'garden-abilities' ),
							),
							'good_count'        => array(
								'type'        => 'integer',
								'description' => __( 'Number of tests that passed.', 'garden-abilities' ),
							),
						),
					),
					'critical_issues'   => array(
						'type'        => 'array',
						'description' => __( 'Critical issues that may have a high impact on performance or security.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'label'       => array(
									'type'        => 'string',
									'description' => __( 'Short description of the issue.', 'garden-abilities' ),
								),
								'description' => array(
									'type'        => 'string',
									'description' => __( 'Detailed explanation of the issue.', 'garden-abilities' ),
								),
								'badge'       => array(
									'type'        => 'string',
									'description' => __( 'Category badge (e.g., Security, Performance).', 'garden-abilities' ),
								),
								'actions'     => array(
									'type'        => 'string',
									'description' => __( 'Suggested actions to resolve the issue.', 'garden-abilities' ),
								),
							),
						),
					),
					'recommendations'   => array(
						'type'        => 'array',
						'description' => __( 'Recommended improvements that could benefit the site.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'label'       => array(
									'type'        => 'string',
									'description' => __( 'Short description of the recommendation.', 'garden-abilities' ),
								),
								'description' => array(
									'type'        => 'string',
									'description' => __( 'Detailed explanation of the recommendation.', 'garden-abilities' ),
								),
								'badge'       => array(
									'type'        => 'string',
									'description' => __( 'Category badge (e.g., Security, Performance).', 'garden-abilities' ),
								),
								'actions'     => array(
									'type'        => 'string',
									'description' => __( 'Suggested actions to implement the recommendation.', 'garden-abilities' ),
								),
							),
						),
					),
					'passed_tests'      => array(
						'type'        => 'array',
						'description' => __( 'Tests that passed with no issues.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'label'       => array(
									'type'        => 'string',
									'description' => __( 'Short description of what was tested.', 'garden-abilities' ),
								),
								'description' => array(
									'type'        => 'string',
									'description' => __( 'Details about the passing test.', 'garden-abilities' ),
								),
								'badge'       => array(
									'type'        => 'string',
									'description' => __( 'Category badge (e.g., Security, Performance).', 'garden-abilities' ),
								),
							),
						),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_site_health_status_callback',

			// Require user to be administrator to view site health.
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
