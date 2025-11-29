<?php
/**
 * Register ability category: {{LABEL}}
 *
 * {{DESCRIPTION}}
 */

/**
 * Hook: wp_abilities_api_categories_init
 * Categories must be registered BEFORE abilities that use them.
 * This hook fires before 'wp_abilities_api_init'.
 */
add_action( 'wp_abilities_api_categories_init', '{{REGISTER_FUNCTION}}' );

function {{REGISTER_FUNCTION}}() {
	wp_register_ability_category( '{{CATEGORY_NAME}}', array(
		'label'       => __( '{{LABEL}}', '{{NAMESPACE}}' ),
		'description' => __( '{{DESCRIPTION}}', '{{NAMESPACE}}' ),
	) );
}
