<?php
/**
 * Ability: Scheduled Actions Status
 *
 * Retrieves the status of WordPress/WooCommerce scheduled actions (Action Scheduler).
 * Shows pending, running, failed, and completed actions with counts and recent items.
 * Use to monitor background jobs, identify stuck or failed tasks, and diagnose cron issues.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/scheduled-actions ability.
 *
 * @return array|WP_Error Output data matching the output_schema, or error if ActionScheduler not available.
 */
function garden_abilities_scheduled_actions_callback() {
	// Check if ActionScheduler is available (loaded by WooCommerce or standalone).
	if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
		return new WP_Error(
			'action_scheduler_not_available',
			__( 'Action Scheduler is not installed or active on this site.', 'garden-abilities' )
		);
	}

	// Get status constants from ActionScheduler_Store.
	$statuses = array(
		'pending'     => ActionScheduler_Store::STATUS_PENDING,
		'running'     => ActionScheduler_Store::STATUS_RUNNING,
		'complete'    => ActionScheduler_Store::STATUS_COMPLETE,
		'failed'      => ActionScheduler_Store::STATUS_FAILED,
		'canceled'    => ActionScheduler_Store::STATUS_CANCELED,
	);

	$store = ActionScheduler::store();

	// Get counts by status.
	$counts = array();
	$total = 0;

	foreach ( $statuses as $key => $status ) {
		$count = $store->query_actions(
			array(
				'status'   => $status,
				'per_page' => 0,
			),
			'count'
		);
		$counts[ $key ] = absint( $count );
		$total += $counts[ $key ];
	}

	// Determine overall health status.
	$overall_status = 'good';
	$issues = array();

	if ( $counts['failed'] > 0 ) {
		$overall_status = 'needs_attention';
		$issues[] = array(
			'severity' => 'warning',
			'message'  => sprintf(
				/* translators: %d is the number of failed actions. */
				__( '%d scheduled action(s) have failed and may need investigation.', 'garden-abilities' ),
				$counts['failed']
			),
		);
	}

	// Check for very old pending actions (stuck for more than 1 hour).
	$one_hour_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
	$stuck_actions = as_get_scheduled_actions(
		array(
			'status'       => ActionScheduler_Store::STATUS_PENDING,
			'date'         => $one_hour_ago,
			'date_compare' => '<=',
			'per_page'     => 1,
		),
		'ids'
	);

	if ( ! empty( $stuck_actions ) ) {
		// Count total stuck actions.
		$stuck_count = count( as_get_scheduled_actions(
			array(
				'status'       => ActionScheduler_Store::STATUS_PENDING,
				'date'         => $one_hour_ago,
				'date_compare' => '<=',
				'per_page'     => 100,
			),
			'ids'
		) );

		$overall_status = 'needs_attention';
		$issues[] = array(
			'severity' => 'warning',
			'message'  => sprintf(
				/* translators: %d is the number of stuck actions. */
				__( '%d pending action(s) are overdue by more than 1 hour. This may indicate cron issues.', 'garden-abilities' ),
				$stuck_count
			),
		);
	}

	// Get recent failed actions (last 10).
	$failed_actions = array();
	if ( $counts['failed'] > 0 ) {
		$failed_raw = as_get_scheduled_actions(
			array(
				'status'   => ActionScheduler_Store::STATUS_FAILED,
				'per_page' => 10,
				'orderby'  => 'modified',
				'order'    => 'DESC',
			),
			OBJECT
		);

		foreach ( $failed_raw as $action_id => $action ) {
			$failed_actions[] = array(
				'id'             => $action_id,
				'hook'           => $action->get_hook(),
				'group'          => $action->get_group(),
				'scheduled_date' => $action->get_schedule()->get_date() ? $action->get_schedule()->get_date()->format( 'Y-m-d H:i:s' ) : null,
			);
		}
	}

	// Get upcoming pending actions (next 10).
	$pending_actions = array();
	if ( $counts['pending'] > 0 ) {
		$pending_raw = as_get_scheduled_actions(
			array(
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 10,
				'orderby'  => 'date',
				'order'    => 'ASC',
			),
			OBJECT
		);

		foreach ( $pending_raw as $action_id => $action ) {
			$schedule = $action->get_schedule();
			$scheduled_date = $schedule->get_date();

			$action_data = array(
				'id'             => $action_id,
				'hook'           => $action->get_hook(),
				'group'          => $action->get_group(),
				'scheduled_date' => $scheduled_date ? $scheduled_date->format( 'Y-m-d H:i:s' ) : null,
			);

			// Add recurrence info only if this is a recurring action.
			if ( $schedule instanceof ActionScheduler_IntervalSchedule ) {
				$action_data['recurrence'] = array(
					'type'     => 'interval',
					'interval' => $schedule->get_recurrence(),
				);
			} elseif ( $schedule instanceof ActionScheduler_CronSchedule ) {
				$action_data['recurrence'] = array(
					'type'    => 'cron',
					'pattern' => $schedule->get_recurrence(),
				);
			}

			$pending_actions[] = $action_data;
		}
	}

	// Get recently completed actions (last 5).
	$completed_actions = array();
	$completed_raw = as_get_scheduled_actions(
		array(
			'status'   => ActionScheduler_Store::STATUS_COMPLETE,
			'per_page' => 5,
			'orderby'  => 'modified',
			'order'    => 'DESC',
		),
		OBJECT
	);

	foreach ( $completed_raw as $action_id => $action ) {
		$completed_actions[] = array(
			'id'             => $action_id,
			'hook'           => $action->get_hook(),
			'group'          => $action->get_group(),
			'scheduled_date' => $action->get_schedule()->get_date() ? $action->get_schedule()->get_date()->format( 'Y-m-d H:i:s' ) : null,
		);
	}

	// Get unique groups.
	$all_groups = array();
	$all_actions = as_get_scheduled_actions(
		array(
			'per_page' => 100,
			'status'   => array(
				ActionScheduler_Store::STATUS_PENDING,
				ActionScheduler_Store::STATUS_RUNNING,
			),
		),
		OBJECT
	);

	foreach ( $all_actions as $action ) {
		$group = $action->get_group();
		if ( ! empty( $group ) && ! isset( $all_groups[ $group ] ) ) {
			$all_groups[ $group ] = 0;
		}
		if ( ! empty( $group ) ) {
			$all_groups[ $group ]++;
		}
	}

	$groups = array();
	foreach ( $all_groups as $group_name => $count ) {
		$groups[] = array(
			'name'          => $group_name,
			'pending_count' => $count,
		);
	}

	return array(
		'overall_status'     => $overall_status,
		'issues'             => $issues,
		'summary'            => array(
			'total'     => $total,
			'pending'   => $counts['pending'],
			'running'   => $counts['running'],
			'complete'  => $counts['complete'],
			'failed'    => $counts['failed'],
			'canceled'  => $counts['canceled'],
		),
		'groups'             => $groups,
		'pending_actions'    => $pending_actions,
		'failed_actions'     => $failed_actions,
		'completed_actions'  => $completed_actions,
	);
}

/**
 * Register the garden-abilities/scheduled-actions ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_scheduled_actions_register' );

/**
 * Registers the scheduled-actions ability with the Abilities API.
 */
function garden_abilities_scheduled_actions_register() {
	// Only register if ActionScheduler is available.
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return;
	}

	wp_register_ability(
		'garden-abilities/scheduled-actions',
		array(
			'label'       => __( 'Scheduled Actions Status', 'garden-abilities' ),
			'description' => __( 'Retrieves the status of WordPress/WooCommerce scheduled actions (Action Scheduler). Shows pending, running, failed, and completed actions with counts, groups, and details. Use to monitor background jobs, identify stuck or failed tasks, and diagnose cron-related issues.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current scheduled actions status.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'overall_status', 'summary' ),
				'properties' => array(
					'overall_status'     => array(
						'type'        => 'string',
						'enum'        => array( 'good', 'needs_attention' ),
						'description' => __( 'Overall health status of scheduled actions.', 'garden-abilities' ),
					),
					'issues'             => array(
						'type'        => 'array',
						'description' => __( 'List of detected issues requiring attention.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'severity' => array(
									'type'        => 'string',
									'description' => __( 'Issue severity (warning, critical).', 'garden-abilities' ),
								),
								'message'  => array(
									'type'        => 'string',
									'description' => __( 'Human-readable issue description.', 'garden-abilities' ),
								),
							),
						),
					),
					'summary'            => array(
						'type'        => 'object',
						'description' => __( 'Counts of actions by status.', 'garden-abilities' ),
						'properties'  => array(
							'total'    => array(
								'type'        => 'integer',
								'description' => __( 'Total number of scheduled actions.', 'garden-abilities' ),
							),
							'pending'  => array(
								'type'        => 'integer',
								'description' => __( 'Actions waiting to be executed.', 'garden-abilities' ),
							),
							'running'  => array(
								'type'        => 'integer',
								'description' => __( 'Actions currently being executed.', 'garden-abilities' ),
							),
							'complete' => array(
								'type'        => 'integer',
								'description' => __( 'Actions that completed successfully.', 'garden-abilities' ),
							),
							'failed'   => array(
								'type'        => 'integer',
								'description' => __( 'Actions that failed execution.', 'garden-abilities' ),
							),
							'canceled' => array(
								'type'        => 'integer',
								'description' => __( 'Actions that were canceled.', 'garden-abilities' ),
							),
						),
					),
					'groups'             => array(
						'type'        => 'array',
						'description' => __( 'Action groups with pending action counts.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'          => array(
									'type'        => 'string',
									'description' => __( 'Group name (e.g., woocommerce, jetpack).', 'garden-abilities' ),
								),
								'pending_count' => array(
									'type'        => 'integer',
									'description' => __( 'Number of pending actions in this group.', 'garden-abilities' ),
								),
							),
						),
					),
					'pending_actions'    => array(
						'type'        => 'array',
						'description' => __( 'Next 10 pending actions to be executed.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array(
									'type'        => 'integer',
									'description' => __( 'Action ID.', 'garden-abilities' ),
								),
								'hook'           => array(
									'type'        => 'string',
									'description' => __( 'WordPress hook that will be triggered.', 'garden-abilities' ),
								),
								'group'          => array(
									'type'        => 'string',
									'description' => __( 'Action group name.', 'garden-abilities' ),
								),
								'scheduled_date' => array(
									'type'        => 'string',
									'description' => __( 'Scheduled execution date (Y-m-d H:i:s format, UTC).', 'garden-abilities' ),
								),
								'recurrence'     => array(
									'type'        => 'object',
									'description' => __( 'Recurrence information if this is a recurring action.', 'garden-abilities' ),
									'properties'  => array(
										'type'     => array(
											'type'        => 'string',
											'description' => __( 'Recurrence type (interval or cron).', 'garden-abilities' ),
										),
										'interval' => array(
											'type'        => 'integer',
											'description' => __( 'Interval in seconds (for interval type).', 'garden-abilities' ),
										),
										'pattern'  => array(
											'type'        => 'string',
											'description' => __( 'Cron pattern (for cron type).', 'garden-abilities' ),
										),
									),
								),
							),
						),
					),
					'failed_actions'     => array(
						'type'        => 'array',
						'description' => __( 'Last 10 failed actions for investigation.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array(
									'type'        => 'integer',
									'description' => __( 'Action ID.', 'garden-abilities' ),
								),
								'hook'           => array(
									'type'        => 'string',
									'description' => __( 'WordPress hook that was triggered.', 'garden-abilities' ),
								),
								'group'          => array(
									'type'        => 'string',
									'description' => __( 'Action group name.', 'garden-abilities' ),
								),
								'scheduled_date' => array(
									'type'        => 'string',
									'description' => __( 'Originally scheduled date.', 'garden-abilities' ),
								),
							),
						),
					),
					'completed_actions'  => array(
						'type'        => 'array',
						'description' => __( 'Last 5 completed actions.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array(
									'type'        => 'integer',
									'description' => __( 'Action ID.', 'garden-abilities' ),
								),
								'hook'           => array(
									'type'        => 'string',
									'description' => __( 'WordPress hook that was triggered.', 'garden-abilities' ),
								),
								'group'          => array(
									'type'        => 'string',
									'description' => __( 'Action group name.', 'garden-abilities' ),
								),
								'scheduled_date' => array(
									'type'        => 'string',
									'description' => __( 'Originally scheduled date.', 'garden-abilities' ),
								),
							),
						),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_scheduled_actions_callback',

			// Require user to be able to manage options (admin-level).
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
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
