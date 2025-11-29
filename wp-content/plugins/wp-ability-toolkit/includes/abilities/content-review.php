<?php
/**
 * Content Review Ability
 *
 * An ability that helps AI agents review and analyze Digital Garden content
 * for potential promotion from seedling to growing or evergreen status.
 *
 * @package WP_Ability_Toolkit
 */

namespace WP_Ability_Toolkit\Abilities;

/**
 * Register the content-review ability
 *
 * This ability provides a structured workflow for content review:
 * 1. Get a list of seedling posts that are candidates for review
 * 2. Analyze a specific post's content, links, and claims
 * 3. Return actionable information for verification
 *
 * @return void
 */
function register_content_review_ability() {
	wp_register_ability(
		'wp-ability-toolkit/content-review',
		array(
			'label'               => __( 'Review Content for Promotion', 'wp-ability-toolkit' ),
			'description'         => __( 'Analyzes Digital Garden content to identify posts ready for promotion from seedling to growing or evergreen status. Returns post content, external links to verify, internal links to check, and verification guidance. Use this when the user wants to review seedling content for quality improvement.', 'wp-ability-toolkit' ),
			'category'            => 'data-retrieval',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'action'  => array(
						'type'        => 'string',
						'enum'        => array( 'list-candidates', 'analyze-post' ),
						'description' => 'Action to perform: "list-candidates" to get seedlings ready for review, or "analyze-post" to analyze a specific post.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to analyze (required when action is "analyze-post").',
					),
					'limit'   => array(
						'type'        => 'integer',
						'default'     => 10,
						'description' => 'Maximum number of candidates to return (for list-candidates action).',
					),
				),
				'required'   => array( 'action' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array(
						'type'        => 'boolean',
						'description' => 'Whether the operation succeeded.',
					),
					'action'     => array(
						'type'        => 'string',
						'description' => 'The action that was performed.',
					),
					'data'       => array(
						'type'        => 'object',
						'description' => 'The result data (varies by action).',
					),
					'guidance'   => array(
						'type'        => 'string',
						'description' => 'Instructions for how to proceed with the review.',
					),
				),
			),
			'execute_callback'    => __NAMESPACE__ . '\\execute_content_review',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
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

/**
 * Execute the content review ability
 *
 * @param array $input Input parameters.
 * @return array|WP_Error Result data.
 */
function execute_content_review( $input ) {
	$action = $input['action'] ?? 'list-candidates';

	switch ( $action ) {
		case 'list-candidates':
			return get_review_candidates( $input );

		case 'analyze-post':
			if ( empty( $input['post_id'] ) ) {
				return new \WP_Error(
					'missing_post_id',
					__( 'Post ID is required for analyze-post action.', 'wp-ability-toolkit' )
				);
			}
			return analyze_post_for_review( $input['post_id'] );

		default:
			return new \WP_Error(
				'invalid_action',
				__( 'Invalid action. Use "list-candidates" or "analyze-post".', 'wp-ability-toolkit' )
			);
	}
}

/**
 * Get seedling posts that are candidates for review
 *
 * @param array $input Input parameters.
 * @return array Result with candidates.
 */
function get_review_candidates( $input ) {
	$limit = min( absint( $input['limit'] ?? 10 ), 50 );

	// Get seedling posts.
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'growth_stage',
					'field'    => 'slug',
					'terms'    => 'seedling',
				),
			),
		)
	);

	$candidates = array();
	foreach ( $posts as $post ) {
		// Get topics.
		$topics      = wp_get_post_terms( $post->ID, 'topic', array( 'fields' => 'names' ) );
		$topic_names = is_wp_error( $topics ) ? array() : $topics;

		// Get backlinks count.
		$backlinks       = get_post_meta( $post->ID, '_garden_backlinks', true );
		$backlinks_count = is_array( $backlinks ) ? count( $backlinks ) : 0;

		// Get word count.
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );

		// Check if it's a session post.
		$is_session_post = strpos( $post->post_title, 'Session' ) === 0;

		// Get age in days.
		$age_days = floor( ( time() - strtotime( $post->post_date ) ) / DAY_IN_SECONDS );

		$candidates[] = array(
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'slug'            => $post->post_name,
			'topics'          => $topic_names,
			'backlinks_count' => $backlinks_count,
			'word_count'      => $word_count,
			'is_session_post' => $is_session_post,
			'age_days'        => $age_days,
			'url'             => get_permalink( $post->ID ),
		);
	}

	// Separate educational content from session posts.
	$educational = array_filter( $candidates, fn( $c ) => ! $c['is_session_post'] );
	$sessions    = array_filter( $candidates, fn( $c ) => $c['is_session_post'] );

	return array(
		'success'  => true,
		'action'   => 'list-candidates',
		'data'     => array(
			'total_seedlings'      => count( $candidates ),
			'educational_content'  => array_values( $educational ),
			'session_posts'        => array_values( $sessions ),
		),
		'guidance' => get_candidate_guidance( count( $educational ), count( $sessions ) ),
	);
}

/**
 * Get guidance for selecting a candidate
 *
 * @param int $educational_count Number of educational posts.
 * @param int $session_count Number of session posts.
 * @return string Guidance text.
 */
function get_candidate_guidance( $educational_count, $session_count ) {
	$guidance = "## Review Candidate Selection\n\n";

	if ( $educational_count > 0 ) {
		$guidance .= "**Priority: Educational Content**\n";
		$guidance .= "Educational posts (non-session posts) should be reviewed first as they provide lasting value to readers.\n\n";
		$guidance .= "**Good candidates have:**\n";
		$guidance .= "- External citations to verify\n";
		$guidance .= "- Multiple backlinks (indicates importance)\n";
		$guidance .= "- Substantial word count (400+ words)\n\n";
	} else {
		$guidance .= "**All educational content is already promoted!**\n";
		$guidance .= sprintf( "There are %d session posts that document agent work. Session posts are typically not promoted to growing/evergreen.\n\n", $session_count );
	}

	$guidance .= "**To analyze a post, use action: 'analyze-post' with the post_id.**";

	return $guidance;
}

/**
 * Analyze a post for review
 *
 * @param int $post_id Post ID to analyze.
 * @return array|WP_Error Analysis result.
 */
function analyze_post_for_review( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || 'publish' !== $post->post_status ) {
		return new \WP_Error(
			'post_not_found',
			__( 'Post not found or not published.', 'wp-ability-toolkit' )
		);
	}

	// Get growth stage.
	$growth_stage = wp_get_post_terms( $post->ID, 'growth_stage', array( 'fields' => 'slugs' ) );
	$current_stage = is_wp_error( $growth_stage ) || empty( $growth_stage ) ? 'untagged' : $growth_stage[0];

	// Get topics.
	$topics      = wp_get_post_terms( $post->ID, 'topic', array( 'fields' => 'names' ) );
	$topic_names = is_wp_error( $topics ) ? array() : $topics;

	// Extract links from content.
	$links = extract_links_from_content( $post->post_content );

	// Get backlinks.
	$backlinks = get_post_meta( $post->ID, '_garden_backlinks', true );
	$backlinks = is_array( $backlinks ) ? $backlinks : array();

	$backlink_posts = array();
	foreach ( $backlinks as $backlink_id ) {
		$backlink_post = get_post( $backlink_id );
		if ( $backlink_post ) {
			$backlink_posts[] = array(
				'id'    => $backlink_id,
				'title' => $backlink_post->post_title,
			);
		}
	}

	// Word count.
	$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );

	// Check if session post.
	$is_session_post = strpos( $post->post_title, 'Session' ) === 0;

	return array(
		'success'  => true,
		'action'   => 'analyze-post',
		'data'     => array(
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'slug'            => $post->post_name,
			'current_stage'   => $current_stage,
			'topics'          => $topic_names,
			'word_count'      => $word_count,
			'is_session_post' => $is_session_post,
			'content'         => $post->post_content,
			'external_links'  => $links['external'],
			'internal_links'  => $links['internal'],
			'backlinks'       => $backlink_posts,
			'url'             => get_permalink( $post->ID ),
		),
		'guidance' => get_review_guidance( $current_stage, $links, $is_session_post ),
	);
}

/**
 * Extract links from post content
 *
 * @param string $content Post content.
 * @return array Array with 'external' and 'internal' links.
 */
function extract_links_from_content( $content ) {
	$external = array();
	$internal = array();

	// Match all href attributes.
	preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]*)<\/a>/i', $content, $matches, PREG_SET_ORDER );

	$site_url = home_url();

	foreach ( $matches as $match ) {
		$url  = $match[1];
		$text = wp_strip_all_tags( $match[2] );

		// Skip anchors and empty URLs.
		if ( empty( $url ) || '#' === $url[0] ) {
			continue;
		}

		// Check if internal or external.
		if ( strpos( $url, $site_url ) === 0 || strpos( $url, '/' ) === 0 ) {
			// Internal link - extract slug.
			$slug = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
			$internal[] = array(
				'url'  => $url,
				'text' => $text,
				'slug' => $slug,
			);
		} else {
			// External link.
			$external[] = array(
				'url'  => $url,
				'text' => $text,
			);
		}
	}

	return array(
		'external' => $external,
		'internal' => $internal,
	);
}

/**
 * Get review guidance based on post analysis
 *
 * @param string $current_stage Current growth stage.
 * @param array  $links Extracted links.
 * @param bool   $is_session_post Whether this is a session post.
 * @return string Guidance text.
 */
function get_review_guidance( $current_stage, $links, $is_session_post ) {
	$guidance = "## Review Guidance\n\n";

	// Session post warning.
	if ( $is_session_post ) {
		$guidance .= "**Note:** This is a session post documenting agent work.\n";
		$guidance .= "Session posts are typically not promoted - they serve as historical records.\n";
		$guidance .= "Consider reviewing educational content instead.\n\n";
	}

	// Current stage info.
	$guidance .= "**Current Stage:** {$current_stage}\n\n";

	// Promotion path.
	if ( 'seedling' === $current_stage ) {
		$guidance .= "**Promotion Path:** seedling -> growing\n";
		$guidance .= "Requirements: Review content, verify at least one external link, improve if needed.\n\n";
	} elseif ( 'growing' === $current_stage ) {
		$guidance .= "**Promotion Path:** growing -> evergreen\n";
		$guidance .= "Requirements: Verify ALL external links, verify ALL factual claims, ensure comprehensive citations.\n\n";
	} elseif ( 'evergreen' === $current_stage ) {
		$guidance .= "**Already Evergreen** - This content has been fully verified.\n\n";
	}

	// Verification tasks.
	$guidance .= "## Verification Tasks\n\n";

	$external_count = count( $links['external'] );
	$internal_count = count( $links['internal'] );

	$guidance .= "### External Links ({$external_count})\n";
	if ( $external_count > 0 ) {
		$guidance .= "Use WebFetch to verify each URL is accessible and content matches claims.\n";
		$guidance .= "**URL Stability Notes:**\n";
		$guidance .= "- WordPress docs (developer.wordpress.org) - Stable\n";
		$guidance .= "- Anthropic docs - Unstable, verify redirects\n";
		$guidance .= "- GitHub links - Stable\n\n";
	} else {
		$guidance .= "No external links to verify.\n\n";
	}

	$guidance .= "### Internal Links ({$internal_count})\n";
	if ( $internal_count > 0 ) {
		$guidance .= "Verify each internal link points to an existing published post.\n";
		$guidance .= "Use WP-CLI: `wp post list --name=<slug> --field=ID`\n\n";
	} else {
		$guidance .= "No internal links to verify.\n\n";
	}

	$guidance .= "### Factual Claims\n";
	$guidance .= "Use Perplexity MCP to verify any factual claims (dates, statistics, technical details).\n";
	$guidance .= "Use Grep to verify code-related claims against source files.\n\n";

	$guidance .= "## After Review\n";
	$guidance .= "If verified, promote with: `wp post term set <ID> growth_stage <new-stage>`\n";
	$guidance .= "Then rebuild backlinks: `wp garden-backlinks rebuild`\n";

	return $guidance;
}
