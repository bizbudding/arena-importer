<?php

/**
 * Plugin Name:     Arena Importer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Import posts via WP All Import Pro and recipes to WP Recipe Maker from Arena's export files.
 * Version:         0.16.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Autoload vendor.
require_once( __DIR__ . '/vendor/autoload.php' );

// Load the CLI class.
require_once( __DIR__ . '/class-arena-cli.php' );

/**
 * Set post type.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	$post_type = get_post_meta( $post_id, 'arena_post_type', true );

	// Bail if no post type is found.
	if ( ! $post_type ) {
		return;
	}

	// Update the post type.
	wp_update_post( [
		'ID'        => $post_id,
		'post_type' => $post_type,
	] );
} );

/**
 * Set post categories.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	$categories = get_post_meta( $post_id, 'arena_categories', true );

	// Bail if no categories are found.
	if ( ! $categories ) {
		return;
	}

	// Explode the categories by comma.
	$categories = explode( '|', $categories );

	// Remove empty values.
	$categories = array_filter( $categories );

	// Trim whitespace.
	$categories = array_map( 'trim', $categories );

	// Get the term ID for each category.
	$term_ids = [];

	foreach ( $categories as $category ) {
		$term = get_term_by( 'slug', $category, 'category' );

		if ( ! $term ) {
			continue;
		}

		$term_ids[] = $term->term_id;
	}

	// Bail if no term IDs are found.
	if ( empty( $term_ids ) ) {
		return;
	}

	// Update the post categories.
	wp_set_post_categories( $post_id, $term_ids );
} );

/**
 * Set post tags.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	$tags = get_post_meta( $post_id, 'arena_tags', true );

	// Bail if no tags are found.
	if ( ! $tags ) {
		return;
	}

	// Explode the tags by comma.
	$tags = explode( '|', $tags );

	// Remove empty values.
	$tags = array_filter( $tags );

	// Trim whitespace.
	$tags = array_map( 'trim', $tags );

	// Get the term ID for each tag.
	$term_ids = [];

	foreach ( $tags as $tag ) {
		$term = get_term_by( 'slug', $tag, 'post_tag' );

		if ( ! $term ) {
			continue;
		}

		$term_ids[] = $term->term_id;
	}

	// Bail if no term IDs are found.
	if ( empty( $term_ids ) ) {
		return;
	}

	// Update the post tags.
	wp_set_post_tags( $post_id, $term_ids );
} );

/**
 * Set modified date.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	$modified_date = get_post_meta( $post_id, 'arena_modified_date', true );

	// Bail if no modified date is found.
	if ( ! $modified_date ) {
		return;
	}

	// Get formatted date.
	$modified_date     = date( 'Y-m-d H:i:s', strtotime( $modified_date ) );
	$modified_date_gmt = get_gmt_from_date( $modified_date );

	// Update the post modified date directly in the database.
	global $wpdb;
	$wpdb->update(
		$wpdb->posts,
		[
			'post_modified'     => $modified_date,
			'post_modified_gmt' => $modified_date_gmt,
		],
		[ 'ID' => $post_id ],
		[ '%s', '%s' ],
		[ '%d' ]
	);

	// Clear the post cache.
	clean_post_cache( $post_id );
} );

/**
 * Set attachment credit from imported post meta.
 *
 * @param int $post_id       The post ID.
 * @param int $attachment_id The attachment ID.
 *
 * @return void
 */
add_action( 'pmxi_gallery_image', function( $post_id, $attachment_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	// Get the post credit.
	$credit = get_post_meta( $post_id, '_media_credit', true );

	// Bail if no credit is found.
	if ( ! $credit ) {
		return;
	}

	// Get the attachment credit.
	$attachment_credit = get_post_meta( $attachment_id, '_media_credit', true );

	// Bail if we already have a credit.
	if ( $attachment_credit && $credit === $attachment_credit ) {
		return;
	}

	// Update the attachment credit.
	update_post_meta( $attachment_id, '_media_credit', $credit );

}, 10, 2 );

/**
 * Set attachment arena_source_url as post meta.
 *
 * @param int $post_id       The post ID.
 * @param int $attachment_id The attachment ID.
 *
 * @return void
 */
add_action( 'pmxi_gallery_image', function( $post_id, $attachment_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	// Get the post source url.
	$source_url = get_post_meta( $post_id, 'arena_source_url', true );

	// Bail if no source url is found.
	if ( ! $source_url ) {
		return;
	}

	// Get the attachment source url.
	$attachment_source_url = get_post_meta( $attachment_id, 'arena_source_url', true );

	// Bail if we already have a source url.
	if ( $attachment_source_url && $source_url === $attachment_source_url ) {
		return;
	}

	// Update the attachment source url.
	update_post_meta( $attachment_id, 'arena_source_url', $source_url );

}, 10, 2 );

/**
 * Fallback for _media_credit and arena_source_url,
 * typically when re-running since pmxi_gallery_image doesn't seem to run on existing attachments.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	// Bail if not a post.
	if ( ! get_post( $post_id ) ) {
		return;
	}

	// Bail if not an arena item.
	if ( 'arena_item' !== get_post_type( $post_id ) ) {
		return;
	}

	// Get the data.
	$credit     = sanitize_text_field( get_post_meta( $post_id, '_media_credit', true ) );
	$source_url = sanitize_text_field( get_post_meta( $post_id, 'arena_source_url', true ) );
	$source_id  = sanitize_text_field( get_post_meta( $post_id, 'arena_source_id', true ) );
	$alt        = sanitize_text_field( get_post_meta( $post_id, 'arena_alt', true ) );
	$caption    = wp_kses_post( get_post_meta( $post_id, 'arena_caption', true ) );

	// Bail if no credit or source url is found.
	if ( ! ( $credit || $source_url || $source_id || $caption ) ) {
		return;
	}

	// Get the attachment ID.
	$attachment_id = get_post_thumbnail_id( $post_id );

	// Bail if no attachment ID is found.
	if ( ! $attachment_id ) {
		return;
	}

	// Get the attachment.
	$attachment = get_post( $attachment_id );

	// Bail if no attachment is found.
	if ( ! $attachment ) {
		return;
	}

	// Get the attachment data.
	$attachment_credit     = get_post_meta( $attachment_id, '_media_credit', true );
	$attachment_source_url = get_post_meta( $attachment_id, 'arena_source_url', true );
	$attachment_source_id  = get_post_meta( $attachment_id, 'arena_source_id', true );
	$attachment_alt        = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	$attachment_caption    = $attachment->post_excerpt;

	// Update the attachment credit.
	if ( $credit && $credit !== $attachment_credit ) {
		update_post_meta( $attachment_id, '_media_credit', $credit );
	}

	// Update the attachment source url.
	if ( $source_url && $source_url !== $attachment_source_url ) {
		update_post_meta( $attachment_id, 'arena_source_url', $source_url );
	}

	// Update the attachment source id.
	if ( $source_id && $source_id !== $attachment_source_id ) {
		update_post_meta( $attachment_id, 'arena_source_id', $source_id );
	}

	// Update the attachment alt.
	if ( $alt && $alt !== $attachment_alt ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	}

	// Update the attachment caption.
	if ( $caption && $caption !== $attachment_caption ) {
		wp_update_post( [
			'ID'           => $attachment_id,
			'post_excerpt' => $caption,
		] );
	}
});

/**
 * Set user avatar.
 *
 * @param int $user_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $user_id ) {
	// Bail if not a user.
	if ( ! get_user_by( 'id', $user_id ) ) {
		return;
	}

	// Get the image URL from the user meta.
	$image_url = get_user_meta( $user_id, 'arena_image_url', true );

	// Bail if no image URL is found.
	if ( ! $image_url ) {
		return;
	}

	// Check for existing user meta.
	$existing_id = get_user_meta( $user_id, 'arena_image_id', true );

	// Bail if we already have an ID.
	if ( $existing_id && get_post( $existing_id ) ) {
		arena_update_user_avatar( $existing_id, $user_id );

		return;
	}

	// Upload the image to the media library.
	$image_id = arena_upload_to_media_library( $image_url, $user_id );

	// Bail if there's an error.
	if ( is_wp_error( $image_id ) ) {
		return;
	}

	// Update the user meta.
	update_user_meta( $user_id, 'arena_image_id', $image_id );

	// Update the user avatar.
	arena_update_user_avatar( $image_id, $user_id );

	return;
} );

/**
 * Delete unused recipe meta.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {
	$keys = [
		'recipe_instructions_html',
		'recipe_prep_time_minutes',
		'recipe_cook_time_minutes',
		'recipe_total_time_minutes',
		'recipe_yield_description',
		'recipe_exclusive_content_type',
		'recipe_ingredients',
		'recipe_nutrition',
		'recipe_categories',
		'recipe_cuisine',
		'recipe_cooking_method',
		'recipe_suitable_for_diet',
	];

	foreach ( $keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );

		// Bail if we have a value.
		if ( $value  && '{}' !== $value && '[]' !== $value ) {
			continue;
		}

		// Delete the meta.
		delete_post_meta( $post_id, $key );
	}
});

/**
 * Force WP All Import to save attachments into uploads/{Y}/{m}
 * based on the record's post_date (e.g., 2024-07-15 10:30:00).
 *
 * Applies only to images downloaded by WP All Import.
 *
 * @link https://www.wpallimport.com/documentation/code-snippets/#save-imported-images-to-a-folder-based-on-the-post-date
 *
 * @param array $uploads           Contains information related to the WordPress uploads path & URL.
 * @param array $articleData       Contains a list of data related to the post/user/taxonomy being imported.
 * @param array $current_xml_node  Contains a list of nodes within the current import record.
 * @param int   $import_id         Contains the ID of the import.
 *
 * @return array
 */
add_filter( 'wp_all_import_images_uploads_dir', function( $uploads, $articleData, $current_xml_node, $import_id ) {
	if ( isset( $articleData['post_date'] ) && ! empty( $articleData['post_date'] ) ) {
		$uploads['path'] = $uploads['basedir'] . '/' . date( 'Y/m', strtotime( $articleData['post_date'] ) );
		$uploads['url']  = $uploads['baseurl'] . '/' . date( 'Y/m', strtotime( $articleData['post_date'] ) );

		if ( ! file_exists( $uploads['path'] ) ) {
			mkdir( $uploads['path'], 0755, true );
		}
	}
	return $uploads;
}, 10, 4 );

/**
 * Function handles downloading a remote file and inserting it into the WP Media Library.
 * First checks if the image already exists by filename to avoid duplicates.
 *
 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
 *
 * @param string $url     HTTP URL address of a remote file.
 * @param int    $post_id The post ID the media is associated with.
 *
 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
 */
function arena_upload_to_media_library( $url, $post_id ) {
	// Make sure we have the functions we need.
	if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	// Check if the image already exists by arena_source_url meta field.
	$existing = get_posts([
		'post_type'      => 'attachment',
		'meta_key'       => 'arena_source_url',
		'meta_value'     => $url,
		'posts_per_page' => 1,
		'fields'         => 'ids'
	]);

	// Get the first attachment ID.
	$existing_id = $existing[0] ?? null;

	// If existing, return the ID.
	if ( $existing_id ) {
		return (int) $existing_id;
	}

	// If we get here, the image doesn't exist, so proceed with download and upload.
	// Build a temp url.
	$tmp = download_url( $url );

	// Bail if error.
	if ( is_wp_error( $tmp ) ) {
		// Remove the original image if it exists and return the error.
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		return $tmp;
	}

	// Build the file array.
	$file_array = [
		'name'     => basename( $url ),
		'tmp_name' => $tmp,
	];

	// Add the image to the media library.
	$id = media_handle_sideload( $file_array, $post_id );

	// Bail if error.
	if ( is_wp_error( $id ) ) {
		// Remove the original image if it exists and return the error.
		if ( file_exists( $file_array[ 'tmp_name' ] ) ) {
			@unlink( $file_array[ 'tmp_name' ] );
		}
		return $id;
	}

	// Remove the original image if it exists.
	if ( file_exists( $file_array[ 'tmp_name' ] ) ) {
		@unlink( $file_array[ 'tmp_name' ] );
	}

	// Double-check that the media was added successfully.
	if ( ! $id || ! get_post( $id ) ) {
		return new WP_Error( 'media_handle_failed', 'Failed to add media to the library.' );
	}

	return $id;
}

/**
 * Update the user avatar.
 *
 * @param int $image_id The image ID.
 * @param int $user_id  The user ID.
 *
 * @return void
 */
function arena_update_user_avatar( $image_id, $user_id ) {
	if ( ! class_exists( 'Simple_Local_Avatars' ) ) {
		return;
	}

	// Bail if no image ID is found.
	if ( ! $image_id ) {
		return;
	}

	// Bail if no user ID is found.
	if ( ! $user_id ) {
		return;
	}

	// Get the Simple Local Avatars instance.
	global $simple_local_avatars;

	// Get the existing local avatar.
	$local_avatars = $simple_local_avatars->get_user_local_avatar( $user_id );

	// Bail if we already have a local avatar.
	if ( isset( $local_avatars['media_id'] ) ) {
		return;
	}

	// Assign the new user avatar.
	$simple_local_avatars->assign_new_user_avatar( $image_id, $user_id );
}

/**
 * Include custom WPRM importer.
 *
 * @link https://www.billerickson.net/custom-importer-for-wp-recipe-maker/
 *
 * @param array $directories
 *
 * @return array
 */
add_filter( 'wprm_importer_directories', function( $directories ) {
	$directories[] = plugin_dir_path( __FILE__ );

	return $directories;
}, 1 );

/**
 * Get all meta items to map to WPRM taxonomies.
 *
 * @since 0.1.0
 *
 * @return void
 */
// add_action( 'genesis_before_loop', function() {
// 	$query = new WP_Query( [
// 		'post_type'              => 'post',
// 		'posts_per_page'         => 15000,
// 		'offset'                 => 0,
// 		'fields'                 => 'ids',
// 		'no_found_rows'          => true,
// 		'update_post_meta_cache' => false,
// 		'update_post_term_cache' => false,
// 		'meta_query'             => [
// 			[
// 				'key'     => 'recipe_suitable_for_diet',
// 				'value'   => '',
// 				'compare' => '!=',
// 			],
// 			// [
// 			// 	'key'     => '_recipe_imported',
// 			// 	'compare' => 'NOT EXISTS',
// 			// ],
// 		]
// 	] );

// 	$items = [];

// 	if ( $query->have_posts() ) {
// 		while ( $query->have_posts() ) : $query->the_post();
// 			$diet  = get_post_meta( get_the_ID(), 'recipe_suitable_for_diet', true );
// 			$diet  = preg_replace( '/^<!\[CDATA\[(.*?)\]\]>$/s', '$1', $diet );
// 			$diet  = json_decode( (string) $diet, true );
// 			$diet  = array_filter( (array) $diet );
// 			$items = array_merge( $items, $diet );
// 		endwhile;
// 	}
// 	wp_reset_postdata();

// 	$items = array_unique( $items );
// 	$items = array_values( $items );

// 	ray( $items );
// });

/**
 * Mark all recipes as checked.
 *
 * @since 0.1.0
 *
 * @return void
 */
// add_action( 'genesis_before_loop', function() {
// 	$query = new WP_Query( [
// 		'post_type'              => 'wprm_recipe',
// 		'posts_per_page'         => 250,
// 		'offset'                 => 0,
// 		'fields'                 => 'ids',
// 		'no_found_rows'          => true,
// 		'update_post_meta_cache' => false,
// 		'update_post_term_cache' => false,
// 		'meta_query'             => [
// 			[
// 				'key'     => 'wprm_import_source',
// 				'value'   => 'arena-tempest',
// 				'compare' => '=',
// 			],
// 		]
// 	] );

// 	if ( $query->have_posts() ) {
// 		while ( $query->have_posts() ) : $query->the_post();
// 			$post_id = get_the_ID();

// 			update_post_meta( $post_id, 'wprm_import_source', 'arena-tempest-checked' );
// 		endwhile;
// 	}
// 	wp_reset_postdata();
// });

// /**
//  * Add recipe block to any recipe that doesn't have it.
//  *
//  * @since 0.1.0
//  *
//  * @return void
//  */
// add_action( 'genesis_before_loop', function() {
// 	/**
// 	 * Prevent post_modified update.
// 	 *
// 	 * @param array $data                An array of slashed, sanitized, and processed post data.
// 	 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
// 	 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as originally passed to wp_insert_post() .
// 	 * @param bool  $update              Whether this is an existing post being updated.
// 	 *
// 	 * @return array
// 	 */
// 	add_filter( 'wp_insert_post_data', function( $data, $postarr, $unsanitized_postarr, $update ) {
// 		if ( $update && ! empty( $postarr['ID'] ) ) {
// 			// Get the existing post.
// 			$existing = get_post( $postarr['ID'] );

// 			// Preserve the current modified dates.
// 			if ( $existing ) {
// 				$data['post_modified']     = $existing->post_modified;
// 				$data['post_modified_gmt'] = $existing->post_modified_gmt;
// 			}
// 		}

// 		return $data;

// 	}, 10, 4 );

// 	$query = new WP_Query( [
// 		'post_type'              => 'wprm_recipe',
// 		'posts_per_page'         => 200,
// 		'offset'                 => 0,
// 		'fields'                 => 'ids',
// 		'no_found_rows'          => true,
// 		'update_post_meta_cache' => false,
// 		'update_post_term_cache' => false,
// 	] );

// 	if ( $query->have_posts() ) {
// 		while ( $query->have_posts() ) : $query->the_post();
// 			$recipe_id = get_the_ID();
// 			$parent_id = get_post_meta( $recipe_id, 'wprm_parent_post_id', true );

// 			if ( ! $parent_id ) {
// 				$wprm_import_backup = get_post_meta( $recipe_id, 'wprm_import_backup', true );
// 				$parent_id          = $wprm_import_backup['example_recipe_id'];
// 			}

// 			printf( '<p>%s</p>', print_r( $parent_id . ' - ' . get_the_title( $parent_id ), true ) );

// 			if ( ! $parent_id ) {
// 				continue;
// 			}

// 			// Get the post.
// 			$post = get_post( $parent_id );

// 			// Bail if we don't have a post.
// 			if ( ! $post ) {
// 				continue;
// 			}

// 			// Get the post content.
// 			$content = $post->post_content;

// 			// printf( '<pre>%s</pre>', esc_html( print_r( $content, true ) ) );

// 			// Bail if we already have a WPRM recipe.
// 			if ( str_contains( $content, '[wprm-recipe id="' . $recipe_id . '"]' ) || str_contains( $content, 'wp:wp-recipe-maker/recipe {"id":' . $recipe_id . '}' ) ) {
// 				printf( '<p>%s</p>', print_r( 'Already has recipe block', true ) );

// 				continue;
// 			}

// 			// Add the WPRM block.
// 			$content .= arena_get_recipe_block( $recipe_id );

// 			// Update the post content.
// 			wp_update_post( [
// 				'ID'           => $parent_id,
// 				'post_content' => $content,
// 			] );

// 			printf( '<p>%s</p>', print_r( 'Updated post content - ' . $parent_id . ' - ' . get_permalink( $parent_id ), true ) );
// 		endwhile;
// 	} else {
// 		printf( '<pre>%s</pre>', print_r( 'No recipes found', true ) );
// 	}
// 	wp_reset_postdata();
// });

/**
 * Get the recipe block.
 * This was taken from the WPRM importer class.
 *
 * @param int $id The ID of the recipe.
 *
 * @return string
 */
function arena_get_recipe_block( $id ) {
	$html  = '';
	$html .= '<!-- wp:wp-recipe-maker/recipe {"id":' . $id . '} -->';
	$html .= '[wprm-recipe id="' . $id . '"]';
	$html .= '<!-- /wp:wp-recipe-maker/recipe -->';

	return $html;
}

function arena_write_to_file( $value ) {
	/**
	 * This function for testing & debuggin only.
	 * Do not leave this function working on your site.
	 */
	$file   = dirname( __FILE__ ) . '/__data.txt';
	$handle = fopen( $file, 'a' );
	ob_start();
	if ( is_array( $value ) || is_object( $value ) ) {
		print_r( $value );
	} elseif ( is_bool( $value ) ) {
		var_dump( $value );
	} else {
		echo $value;
	}
	echo "\r\n\r\n";
	fwrite( $handle, ob_get_clean() );
	fclose( $handle );
}

/**
 */
// add_action( 'genesis_before_loop', function() {

// 	if ( ! current_user_can( 'manage_options' ) ) {
// 		return;
// 	}

// 	$query = new WP_Query( [
// 		'post_type'              => 'post',
// 		'posts_per_page'         => 200,
// 		'offset'                 => 0,
// 		'no_found_rows'          => true,
// 		'update_post_meta_cache' => false,
// 		'update_post_term_cache' => false,
// 		'meta_query'             => [
// 			[
// 				'key'     => 'arena_categories',
// 				'compare' => 'EXISTS',
// 			],
// 		],
// 	] );

// 	if ( $query->have_posts() ) {
// 		while ( $query->have_posts() ) : $query->the_post();
// 			$post_id    = get_the_ID();
// 			$categories = get_post_meta( $post_id, 'arena_categories', true );

// 			printf( '<p>%s</p>', print_r( $post_id . ' - ' . $categories, true ) );

// 		endwhile;
// 	}
// 	wp_reset_postdata();
// });

/**
 * Register custom post types.
 *
 * @return void
 */
add_action( 'init', function() {

	register_post_type( 'arena_item', [
		'exclude_from_search' => false,
		'has_archive'         => false,
		'hierarchical'        => false,
		'labels'              => [
			'name'               => _x( 'Arena Items', 'Arena Item general name', 'arena-importer' ),
			'singular_name'      => _x( 'Arena Item', 'Arena Item singular name', 'arena-importer' ),
			'menu_name'          => _x( 'Arena Items', 'Arena Item admin menu', 'arena-importer' ),
			'name_admin_bar'     => _x( 'Arena Item', 'Arena Item add new on admin bar', 'arena-importer' ),
			'add_new'            => _x( 'Add New', 'Arena Item', 'arena-importer' ),
			'add_new_item'       => __( 'Add New Arena Item',  'arena-importer' ),
			'new_item'           => __( 'New Arena Item', 'arena-importer' ),
			'edit_item'          => __( 'Edit Arena Item', 'arena-importer' ),
			'view_item'          => __( 'View Arena Item', 'arena-importer' ),
			'all_items'          => __( 'All Arena Items', 'arena-importer' ),
			'search_items'       => __( 'Search Arena Items', 'arena-importer' ),
			'parent_item_colon'  => __( 'Parent Arena Items:', 'arena-importer' ),
			'not_found'          => __( 'No Arena Items found.', 'arena-importer' ),
			'not_found_in_trash' => __( 'No Arena Items found in Trash.', 'arena-importer' )
		],
		'menu_icon'          => 'dashicons-welcome-widgets-menus',
		'public'             => false,
		'publicly_queryable' => false,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => true,
		'show_in_rest'       => true,
		'show_ui'            => true,
		'rewrite'            => false,
		'supports'           => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
	] );
});