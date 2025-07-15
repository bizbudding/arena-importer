<?php

/**
 * Plugin Name:     Arena WPRM Importer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Import recipes to WP Recipe Maker from post meta from Arena's export files.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

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