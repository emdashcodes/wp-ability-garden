<?php
/**
 * Ability: List Posts
 *
 * Retrieves a paginated list of posts with metadata including title, status,
 * author, dates, and excerpt. Useful for getting an overview of site content.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/list-posts ability.
 *
 * @return array Output data matching the output_schema.
 */
function garden_abilities_list_posts_callback() {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$query = new WP_Query( $args );
	$posts = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$author  = get_the_author();

			$posts[] = array(
				'id'           => $post_id,
				'title'        => get_the_title(),
				'status'       => get_post_status(),
				'author'       => $author,
				'date'         => get_the_date( 'Y-m-d H:i:s' ),
				'modified'     => get_the_modified_date( 'Y-m-d H:i:s' ),
				'excerpt'      => wp_trim_words( get_the_excerpt(), 30, '...' ),
				'comment_count' => (int) get_comments_number(),
				'permalink'    => get_permalink(),
			);
		}
		wp_reset_postdata();
	}

	return array(
		'total_posts' => $query->found_posts,
		'returned'    => count( $posts ),
		'posts'       => $posts,
	);
}

/**
 * Register the garden-abilities/list-posts ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_list_posts_register' );

/**
 * Registers the list-posts ability with the Abilities API.
 */
function garden_abilities_list_posts_register() {
	wp_register_ability(
		'garden-abilities/list-posts',
		array(
			'label'       => __( 'List Posts', 'garden-abilities' ),
			'description' => __( 'Retrieves a list of the 20 most recent posts with metadata including title, status, author, dates, excerpt, and comment count. Useful for getting an overview of site content.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters - returns recent posts
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'total_posts', 'returned', 'posts' ),
				'properties' => array(
					'total_posts' => array(
						'type'        => 'integer',
						'description' => __( 'Total number of posts matching the query.', 'garden-abilities' ),
					),
					'returned'    => array(
						'type'        => 'integer',
						'description' => __( 'Number of posts returned in this response (max 20).', 'garden-abilities' ),
					),
					'posts'       => array(
						'type'        => 'array',
						'description' => __( 'Array of post objects.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array(
									'type'        => 'integer',
									'description' => __( 'Post ID.', 'garden-abilities' ),
								),
								'title'         => array(
									'type'        => 'string',
									'description' => __( 'Post title.', 'garden-abilities' ),
								),
								'status'        => array(
									'type'        => 'string',
									'description' => __( 'Post status (publish, draft, pending, private).', 'garden-abilities' ),
								),
								'author'        => array(
									'type'        => 'string',
									'description' => __( 'Author display name.', 'garden-abilities' ),
								),
								'date'          => array(
									'type'        => 'string',
									'description' => __( 'Publication date in Y-m-d H:i:s format.', 'garden-abilities' ),
								),
								'modified'      => array(
									'type'        => 'string',
									'description' => __( 'Last modified date in Y-m-d H:i:s format.', 'garden-abilities' ),
								),
								'excerpt'       => array(
									'type'        => 'string',
									'description' => __( 'Post excerpt (truncated to 30 words).', 'garden-abilities' ),
								),
								'comment_count' => array(
									'type'        => 'integer',
									'description' => __( 'Number of comments on the post.', 'garden-abilities' ),
								),
								'permalink'     => array(
									'type'        => 'string',
									'description' => __( 'Full URL to the post.', 'garden-abilities' ),
								),
							),
						),
					),
				),
			),

			// The callback function to execute
			'execute_callback' => 'garden_abilities_list_posts_callback',

			// Require user to be logged in and able to edit posts
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},

			// Metadata - this is a read-only, safe ability
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
