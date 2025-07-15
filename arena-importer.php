<?php

/**
 * Plugin Name:     Arena Importer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Import posts via WP All Import Pro and recipes to WP Recipe Maker from Arena's export files.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Import and/or select author and set the post author.
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
add_action( 'pmxi_saved_post', function( $post_id ) {

	// DISABLED.
	return;

	// Default user ID.
	$user_id = 0;

	// Get the author from the post meta.
	$author_name = get_post_meta( $post_id, 'arena_author', true );

	// Bail if no author name is found.
	if ( ! $author_name ) {
		return;
	}

	// Sanitize the author name for username.
	$username = sanitize_title( $author_name );

	// Check if user already exists.
	$user = get_user_by( 'login', $username );

	// If no user.
	if ( ! $user ) {
		// Get the site domain.
		$site_url = home_url();
		$domain   = parse_url( $site_url, PHP_URL_HOST );

		// Check if it's a mai-cloud.net domain and extract the main domain.
		if ( str_ends_with( $domain, '.mai-cloud.net' ) ) {
			// Extract the subdomain (jamiegeller from jamiegeller.mai-cloud.net).
			$subdomain = str_replace( '.mai-cloud.net', '', $domain );
			$domain    = $subdomain . '.com';
		} else {
			// Handle other subdomains by getting the main domain.
			$domain_parts = explode( '.', $domain );
			if ( count( $domain_parts ) > 2 ) {
				$domain = $domain_parts[ count( $domain_parts ) - 2 ] . '.' . $domain_parts[ count( $domain_parts ) - 1 ];
			}
		}

		// Build the email.
		$email = sprintf( '%s@%s', sanitize_key( $username ), $domain );

		// Create new user.
		$new_user_id = wp_insert_user( [
			'user_login'    => $username,
			'user_pass'     => wp_generate_password(),
			'user_email'    => $email,
			'user_nicename' => sanitize_title( $author_name ),
			'display_name'  => $author_name,
			'role'          => 'author',
		] );

		// If user was created.
		if ( ! is_wp_error( $new_user_id ) ) {
			$user_id = $new_user_id;
		}
	} else {
		$user_id = $user->ID;
	}

	// Update the post author.
	wp_update_post( [
		'ID'          => $post_id,
		'post_author' => $user_id,
	] );
} );

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
		'ID'          => $post_id,
		'post_type'   => $post_type,
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
	$categories = explode( ',', $categories );

	// Remove empty values.
	$categories = array_filter( $categories );

	// Trim whitespace.
	$categories = array_map( 'trim', $categories );

	// Get the term ID for each category.
	$term_ids = [];

	foreach ( $categories as $category ) {
		$term = get_term_by( 'name', $category, 'category' );

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
	$tags = explode( ',', $tags );

	// Remove empty values.
	$tags = array_filter( $tags );

	// Trim whitespace.
	$tags = array_map( 'trim', $tags );

	// Get the term ID for each tag.
	$term_ids = [];

	foreach ( $tags as $tag ) {
		$term = get_term_by( 'name', $tag, 'post_tag' );

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
 * Function handles downloading a remote file and inserting it into the WP Media Library.
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
	$file_array = array(
		'name'     => basename( $url ),
		'tmp_name' => $tmp,
	);

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