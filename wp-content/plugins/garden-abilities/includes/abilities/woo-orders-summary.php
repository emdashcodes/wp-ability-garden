<?php
/**
 * Ability: WooCommerce Orders Summary
 *
 * Retrieves a summary of WooCommerce orders including counts by status,
 * sales metrics, and recent orders. Provides a quick business pulse for
 * AI assistants monitoring store performance.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/woo-orders-summary ability.
 *
 * @return array|WP_Error Output data matching the output_schema, or error if WooCommerce not active.
 */
function garden_abilities_woo_orders_summary_callback() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error(
			'woocommerce_not_active',
			__( 'WooCommerce is not installed or active on this site.', 'garden-abilities' )
		);
	}

	// Get order counts by status using WooCommerce's built-in function.
	$order_counts = array();
	$order_statuses = wc_get_order_statuses();

	foreach ( $order_statuses as $status_slug => $status_label ) {
		// Remove 'wc-' prefix from status slug.
		$clean_slug = str_replace( 'wc-', '', $status_slug );
		$count = wc_orders_count( $clean_slug );
		$order_counts[] = array(
			'status' => $clean_slug,
			'label'  => $status_label,
			'count'  => absint( $count ),
		);
	}

	// Calculate total orders.
	$total_orders = array_sum( array_column( $order_counts, 'count' ) );

	// Get actionable orders (orders that need attention).
	$actionable_count = 0;
	$actionable_statuses = array( 'pending', 'processing', 'on-hold' );
	foreach ( $order_counts as $status_data ) {
		if ( in_array( $status_data['status'], $actionable_statuses, true ) ) {
			$actionable_count += $status_data['count'];
		}
	}

	// Get sales data for the current period (default: last 7 days).
	$date_start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
	$date_end = gmdate( 'Y-m-d' );

	// Query for orders in the date range.
	$args = array(
		'status'       => array( 'completed', 'processing', 'on-hold' ),
		'date_created' => $date_start . '...' . $date_end,
		'limit'        => -1,
		'return'       => 'ids',
	);

	$order_ids = wc_get_orders( $args );

	// Calculate sales metrics.
	$period_total_sales = 0;
	$period_total_items = 0;
	$period_order_count = count( $order_ids );

	foreach ( $order_ids as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$period_total_sales += floatval( $order->get_total() );
			$period_total_items += absint( $order->get_item_count() );
		}
	}

	// Calculate average order value.
	$average_order_value = $period_order_count > 0 ? $period_total_sales / $period_order_count : 0;

	// Get recent orders (last 5).
	$recent_args = array(
		'limit'   => 5,
		'orderby' => 'date',
		'order'   => 'DESC',
	);

	$recent_orders_data = array();
	$recent_orders = wc_get_orders( $recent_args );

	foreach ( $recent_orders as $order ) {
		$recent_orders_data[] = array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'status'       => $order->get_status(),
			'total'        => floatval( $order->get_total() ),
			'currency'     => $order->get_currency(),
			'item_count'   => absint( $order->get_item_count() ),
			'customer'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
		);
	}

	// Get currency settings.
	$currency = get_woocommerce_currency();
	$currency_symbol = get_woocommerce_currency_symbol();

	return array(
		'summary'        => array(
			'total_orders'     => $total_orders,
			'actionable_count' => $actionable_count,
			'currency'         => $currency,
			'currency_symbol'  => $currency_symbol,
		),
		'orders_by_status' => $order_counts,
		'period_metrics'   => array(
			'period_start'        => $date_start,
			'period_end'          => $date_end,
			'period_days'         => 7,
			'orders_count'        => $period_order_count,
			'total_sales'         => round( $period_total_sales, 2 ),
			'total_items_sold'    => $period_total_items,
			'average_order_value' => round( $average_order_value, 2 ),
		),
		'recent_orders'    => $recent_orders_data,
	);
}

/**
 * Register the garden-abilities/woo-orders-summary ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_woo_orders_summary_register' );

/**
 * Registers the woo-orders-summary ability with the Abilities API.
 */
function garden_abilities_woo_orders_summary_register() {
	// Only register if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	wp_register_ability(
		'garden-abilities/woo-orders-summary',
		array(
			'label'       => __( 'WooCommerce Orders Summary', 'garden-abilities' ),
			'description' => __( 'Retrieves a summary of WooCommerce orders including counts by status (pending, processing, completed, etc.), sales metrics for the last 7 days, and recent orders. Use this for a quick business pulse check to understand order volume, identify actionable orders, and monitor sales performance.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns current order summary.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'summary', 'orders_by_status', 'period_metrics', 'recent_orders' ),
				'properties' => array(
					'summary'          => array(
						'type'        => 'object',
						'description' => __( 'High-level order summary.', 'garden-abilities' ),
						'properties'  => array(
							'total_orders'     => array(
								'type'        => 'integer',
								'description' => __( 'Total number of orders across all statuses.', 'garden-abilities' ),
							),
							'actionable_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of orders needing attention (pending, processing, on-hold).', 'garden-abilities' ),
							),
							'currency'         => array(
								'type'        => 'string',
								'description' => __( 'Store currency code (e.g., USD, EUR).', 'garden-abilities' ),
							),
							'currency_symbol'  => array(
								'type'        => 'string',
								'description' => __( 'Store currency symbol (e.g., $, €).', 'garden-abilities' ),
							),
						),
					),
					'orders_by_status' => array(
						'type'        => 'array',
						'description' => __( 'Order counts broken down by status.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'status' => array(
									'type'        => 'string',
									'description' => __( 'Order status slug (e.g., pending, processing, completed).', 'garden-abilities' ),
								),
								'label'  => array(
									'type'        => 'string',
									'description' => __( 'Human-readable status label.', 'garden-abilities' ),
								),
								'count'  => array(
									'type'        => 'integer',
									'description' => __( 'Number of orders with this status.', 'garden-abilities' ),
								),
							),
						),
					),
					'period_metrics'   => array(
						'type'        => 'object',
						'description' => __( 'Sales metrics for the reporting period (last 7 days).', 'garden-abilities' ),
						'properties'  => array(
							'period_start'        => array(
								'type'        => 'string',
								'description' => __( 'Start date of the reporting period (Y-m-d format).', 'garden-abilities' ),
							),
							'period_end'          => array(
								'type'        => 'string',
								'description' => __( 'End date of the reporting period (Y-m-d format).', 'garden-abilities' ),
							),
							'period_days'         => array(
								'type'        => 'integer',
								'description' => __( 'Number of days in the reporting period.', 'garden-abilities' ),
							),
							'orders_count'        => array(
								'type'        => 'integer',
								'description' => __( 'Number of orders placed in the period.', 'garden-abilities' ),
							),
							'total_sales'         => array(
								'type'        => 'number',
								'description' => __( 'Total sales value in the period.', 'garden-abilities' ),
							),
							'total_items_sold'    => array(
								'type'        => 'integer',
								'description' => __( 'Total number of items sold in the period.', 'garden-abilities' ),
							),
							'average_order_value' => array(
								'type'        => 'number',
								'description' => __( 'Average order value in the period.', 'garden-abilities' ),
							),
						),
					),
					'recent_orders'    => array(
						'type'        => 'array',
						'description' => __( 'The 5 most recent orders.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array(
									'type'        => 'integer',
									'description' => __( 'Order ID.', 'garden-abilities' ),
								),
								'number'       => array(
									'type'        => 'string',
									'description' => __( 'Order number (may differ from ID).', 'garden-abilities' ),
								),
								'status'       => array(
									'type'        => 'string',
									'description' => __( 'Order status slug.', 'garden-abilities' ),
								),
								'total'        => array(
									'type'        => 'number',
									'description' => __( 'Order total amount.', 'garden-abilities' ),
								),
								'currency'     => array(
									'type'        => 'string',
									'description' => __( 'Order currency code.', 'garden-abilities' ),
								),
								'item_count'   => array(
									'type'        => 'integer',
									'description' => __( 'Number of items in the order.', 'garden-abilities' ),
								),
								'customer'     => array(
									'type'        => 'string',
									'description' => __( 'Customer name from billing address.', 'garden-abilities' ),
								),
								'date_created' => array(
									'type'        => 'string',
									'description' => __( 'Order creation date (Y-m-d H:i:s format).', 'garden-abilities' ),
								),
							),
						),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_woo_orders_summary_callback',

			// Require user to be able to view WooCommerce reports.
			'permission_callback' => function () {
				return current_user_can( 'view_woocommerce_reports' );
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
