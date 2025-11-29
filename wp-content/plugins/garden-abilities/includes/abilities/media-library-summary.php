<?php
/**
 * Ability: Media Library Summary
 *
 * Retrieves a summary of the WordPress media library including counts by type,
 * recent uploads, storage estimates, and items missing alt text.
 * Useful for content audits and helping AI agents find existing media assets.
 *
 * @package GardenAbilities
 */

/**
 * Execute callback for garden-abilities/media-library-summary ability.
 *
 * @return array Output data matching the output_schema.
 */
function garden_abilities_media_library_summary_callback() {
	global $wpdb;

	// Get total count of attachments.
	$total_count = (int) wp_count_posts( 'attachment' )->inherit;

	// Get counts by MIME type (simplified to main types).
	$mime_counts = $wpdb->get_results(
		"SELECT
			SUBSTRING_INDEX(post_mime_type, '/', 1) as mime_group,
			COUNT(*) as count
		FROM {$wpdb->posts}
		WHERE post_type = 'attachment'
		AND post_status = 'inherit'
		GROUP BY mime_group
		ORDER BY count DESC",
		ARRAY_A
	);

	$by_type = array();
	foreach ( $mime_counts as $row ) {
		$type = ! empty( $row['mime_group'] ) ? $row['mime_group'] : 'other';
		$by_type[ $type ] = (int) $row['count'];
	}

	// Get images without alt text.
	$images_without_alt = $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID)
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
		WHERE p.post_type = 'attachment'
		AND p.post_status = 'inherit'
		AND p.post_mime_type LIKE 'image/%'
		AND (pm.meta_value IS NULL OR pm.meta_value = '')"
	);

	// Get total image count for context.
	$total_images = isset( $by_type['image'] ) ? $by_type['image'] : 0;

	// Estimate storage usage (sum of file sizes from metadata).
	$storage_result = $wpdb->get_var(
		"SELECT SUM(CAST(meta_value AS UNSIGNED))
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_wp_attachment_metadata'"
	);

	// Get actual file sizes for accurate storage estimate.
	$upload_dir    = wp_upload_dir();
	$storage_bytes = 0;

	// Sample-based storage estimate (check first 100 files to estimate).
	$sample_attachments = $wpdb->get_results(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'attachment'
		AND post_status = 'inherit'
		ORDER BY ID DESC
		LIMIT 100",
		ARRAY_A
	);

	$sample_size  = 0;
	$sample_count = 0;
	foreach ( $sample_attachments as $attachment ) {
		$file = get_attached_file( $attachment['ID'] );
		if ( $file && file_exists( $file ) ) {
			$sample_size += filesize( $file );
			++$sample_count;
		}
	}

	// Extrapolate storage from sample.
	if ( $sample_count > 0 && $total_count > 0 ) {
		$avg_file_size = $sample_size / $sample_count;
		$storage_bytes = (int) ( $avg_file_size * $total_count );
	}

	// Format storage for display.
	$storage_formatted = size_format( $storage_bytes, 2 );

	// Get 10 most recent uploads.
	$recent_query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$recent_items = array();
	if ( $recent_query->have_posts() ) {
		while ( $recent_query->have_posts() ) {
			$recent_query->the_post();
			$attachment_id = get_the_ID();
			$mime_type     = get_post_mime_type();
			$metadata      = wp_get_attachment_metadata( $attachment_id );

			$item = array(
				'id'         => $attachment_id,
				'title'      => get_the_title(),
				'filename'   => basename( get_attached_file( $attachment_id ) ),
				'mime_type'  => $mime_type,
				'date'       => get_the_date( 'Y-m-d H:i:s' ),
				'url'        => wp_get_attachment_url( $attachment_id ),
				'author'     => get_the_author(),
			);

			// Add dimensions for images.
			if ( strpos( $mime_type, 'image/' ) === 0 && ! empty( $metadata['width'] ) ) {
				$item['width']  = (int) $metadata['width'];
				$item['height'] = (int) $metadata['height'];
			}

			// Add alt text status for images.
			if ( strpos( $mime_type, 'image/' ) === 0 ) {
				$alt_text       = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$item['has_alt'] = ! empty( $alt_text );
			}

			$recent_items[] = $item;
		}
		wp_reset_postdata();
	}

	// Build issues array for actionable insights.
	$issues = array();

	if ( $total_images > 0 && (int) $images_without_alt > 0 ) {
		$percentage = round( ( (int) $images_without_alt / $total_images ) * 100 );
		$issues[]   = array(
			'type'        => 'accessibility',
			'severity'    => $percentage > 50 ? 'warning' : 'info',
			'message'     => sprintf(
				/* translators: 1: count of images, 2: percentage */
				__( '%1$d images (%2$d%%) are missing alt text, which impacts accessibility and SEO.', 'garden-abilities' ),
				(int) $images_without_alt,
				$percentage
			),
			'count'       => (int) $images_without_alt,
		);
	}

	return array(
		'summary' => array(
			'total_items'        => $total_count,
			'by_type'            => $by_type,
			'storage_estimate'   => $storage_formatted,
			'storage_bytes'      => $storage_bytes,
			'images_without_alt' => (int) $images_without_alt,
			'total_images'       => $total_images,
		),
		'recent_items' => $recent_items,
		'issues'       => $issues,
	);
}

/**
 * Register the garden-abilities/media-library-summary ability.
 */
add_action( 'wp_abilities_api_init', 'garden_abilities_media_library_summary_register' );

/**
 * Registers the media-library-summary ability with the Abilities API.
 */
function garden_abilities_media_library_summary_register() {
	wp_register_ability(
		'garden-abilities/media-library-summary',
		array(
			'label'       => __( 'Media Library Summary', 'garden-abilities' ),
			'description' => __( 'Retrieves a summary of the WordPress media library including counts by type (image, video, audio, etc.), storage estimates, recent uploads, and accessibility issues like missing alt text. Use this to audit media assets or help find existing images for content.', 'garden-abilities' ),
			'category'    => 'site',

			// No input parameters needed.
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),

			// Output schema describing the returned data.
			'output_schema' => array(
				'type'       => 'object',
				'required'   => array( 'summary', 'recent_items', 'issues' ),
				'properties' => array(
					'summary' => array(
						'type'        => 'object',
						'description' => __( 'Overall media library statistics.', 'garden-abilities' ),
						'properties'  => array(
							'total_items' => array(
								'type'        => 'integer',
								'description' => __( 'Total number of media items in the library.', 'garden-abilities' ),
							),
							'by_type' => array(
								'type'        => 'object',
								'description' => __( 'Count of items by MIME type group (image, video, audio, application, etc.).', 'garden-abilities' ),
								'additionalProperties' => array(
									'type' => 'integer',
								),
							),
							'storage_estimate' => array(
								'type'        => 'string',
								'description' => __( 'Human-readable storage estimate (e.g., "15.2 MB").', 'garden-abilities' ),
							),
							'storage_bytes' => array(
								'type'        => 'integer',
								'description' => __( 'Estimated storage in bytes.', 'garden-abilities' ),
							),
							'images_without_alt' => array(
								'type'        => 'integer',
								'description' => __( 'Number of images missing alt text.', 'garden-abilities' ),
							),
							'total_images' => array(
								'type'        => 'integer',
								'description' => __( 'Total number of image files.', 'garden-abilities' ),
							),
						),
					),
					'recent_items' => array(
						'type'        => 'array',
						'description' => __( '10 most recently uploaded media items.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id' => array(
									'type'        => 'integer',
									'description' => __( 'Attachment post ID.', 'garden-abilities' ),
								),
								'title' => array(
									'type'        => 'string',
									'description' => __( 'Attachment title.', 'garden-abilities' ),
								),
								'filename' => array(
									'type'        => 'string',
									'description' => __( 'Original filename.', 'garden-abilities' ),
								),
								'mime_type' => array(
									'type'        => 'string',
									'description' => __( 'Full MIME type (e.g., image/png).', 'garden-abilities' ),
								),
								'date' => array(
									'type'        => 'string',
									'description' => __( 'Upload date in Y-m-d H:i:s format.', 'garden-abilities' ),
								),
								'url' => array(
									'type'        => 'string',
									'description' => __( 'Full URL to the media file.', 'garden-abilities' ),
								),
								'author' => array(
									'type'        => 'string',
									'description' => __( 'Name of user who uploaded the file.', 'garden-abilities' ),
								),
								'width' => array(
									'type'        => 'integer',
									'description' => __( 'Image width in pixels (images only).', 'garden-abilities' ),
								),
								'height' => array(
									'type'        => 'integer',
									'description' => __( 'Image height in pixels (images only).', 'garden-abilities' ),
								),
								'has_alt' => array(
									'type'        => 'boolean',
									'description' => __( 'Whether the image has alt text (images only).', 'garden-abilities' ),
								),
							),
						),
					),
					'issues' => array(
						'type'        => 'array',
						'description' => __( 'Detected issues and recommendations.', 'garden-abilities' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type' => array(
									'type'        => 'string',
									'description' => __( 'Issue category (accessibility, storage, etc.).', 'garden-abilities' ),
								),
								'severity' => array(
									'type'        => 'string',
									'description' => __( 'Issue severity (info, warning, critical).', 'garden-abilities' ),
								),
								'message' => array(
									'type'        => 'string',
									'description' => __( 'Human-readable description of the issue.', 'garden-abilities' ),
								),
								'count' => array(
									'type'        => 'integer',
									'description' => __( 'Number of items affected.', 'garden-abilities' ),
								),
							),
						),
					),
				),
			),

			// The callback function to execute.
			'execute_callback' => 'garden_abilities_media_library_summary_callback',

			// Require user to be logged in and able to upload files.
			'permission_callback' => function() {
				return current_user_can( 'upload_files' );
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
