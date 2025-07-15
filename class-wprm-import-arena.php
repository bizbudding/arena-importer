<?php

/**
 * Arena Recipe Importer for WP Recipe Maker.
 *
 * @author Mike Hemberger @JiveDig
 *
 * @version 0.1.0
 */
class WPRM_Import_Arena extends WPRM_Import {
	/**
	 * Get the UID of this import source.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_uid() {
		// This should return a uid (no spaces) representing the import source.
		// For example "wp-ultimate-recipe", "easyrecipe", ...
		return 'arena-tempest';
	}

	/**
	 * Wether or not this importer requires a manual search for recipes.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function requires_search() {
		// Set to true when you need to search through the post content (or somewhere else) to actually find recipes.
		// When set to true the "search_recipes" function is required.
		// Usually false is fine as you can find recipes as a custom post type or in a custom table.
		return false;
	}

	/**
	 * Get the name of this import source.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_name() {
		// Display name for this importer.
		return 'Arena | Tempest';
	}

	/**
	 * Get HTML for the import settings.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_settings_html() {
		// Any HTML can be added here if input is required for doing the import.
		// Take a look at the WP Ultimate Recipe importer for an example.
		// Most importers will just need ''.
		return '';
	}

	/**
	 * Get the meta query.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_meta_query() {
		return [
			// [
			// 	'key'     => 'recipe_nutrition',
			// 	'value'   => '{}',
			// 	'compare' => '!=',
			// ],
			// [
			// 	'key'     => 'recipe_instructions_html',
			// 	'value'   => '<![CDATA[',
			// 	'compare' => 'LIKE',
			// ],
			[
				'key'     => 'recipe_ingredients',
				'value'   => '',
				'compare' => '!=',
			],
			[
				'key'     => '_recipe_imported',
				'compare' => 'NOT EXISTS',
			],
		];
	}

	/**
	 * Get the total number of recipes to import.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_recipe_count() {
		$loop = new WP_Query( [
			'fields'         => 'ids',
			'post_type'      => 'post',
			'posts_per_page' => 15000,
			'meta_query'     => $this->get_meta_query(),
		] );

		return $loop->found_posts;
	}

	/**
	 * Search for recipes to import.
	 *
	 * @since 0.1.0
	 *
	 * @param int $page Page of recipes to import.
	 *
	 * @return array
	 */
	public function search_recipes( $page = 0 ) {
		// Only needed if "search_required" returns true.
		// Function will be called with increased $page number until finished is set to true.
		// Will need a custom way of storing the recipes.
		// Take a look at the Easy Recipe importer for an example.
		return [
			'finished' => true,
			'recipes'  => 0,
		];
	}

	/**
	 * Get a list of recipes that are available to import.
	 *
	 * $recipes[ $post_id ] = [
	 * 	'name' => $post_title,
	 * 	'url'  => get_edit_post_link( $post_id ),
	 * ];
	 *
	 * @since 0.1.0
	 *
	 * @param int $page Page of recipes to get.
	 *
	 * @return array
	 */
	public function get_recipes( $page = 0 ) {
		// Return an array of recipes to be imported with name and edit URL.
		// If not the same number of recipes as in "get_recipe_count" are returned pagination will be used.
		$loop = new WP_Query( [
			'post_type'      => 'post',
			'posts_per_page' => 250,
			'paged'          => $page,
			'meta_query'     => $this->get_meta_query(),
		] );

		// Build the recipes array.
		$recipes = [];
		foreach( $loop->posts as $post ) {
			$recipes[ $post->ID ] = [
				'name' => $post->post_title,
				'url'  => get_edit_post_link( $post->ID ),
			];
		}

		return $recipes;
	}

	/**
	 * Get recipe with the specified ID in the import format.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $id        ID of the recipe we want to import.
	 * @param array $post_data POST data, including any fields from `get_settings_html()`, passed along when submitting the form.
	 *
	 * @return array
	 */
	public function get_recipe( $id, $post_data ) {
		// Get the recipe data in WPRM format for a specific ID, corresponding to the ID in the "get_recipes" array.
		// $post_data will contain any input fields set in the "get_settings_html" function.
		// Include any fields to backup in "import_backup".
		$recipe = [
			'import_id'     => 0, // Important! If set to 0 will create the WPRM recipe as a new post. If set to an ID it will update to post with that ID to become a WPRM post type.
			'import_backup' => [
				'example_recipe_id' => $id,
			],
		];

		// <recipe_instructions_html><![CDATA[<p>Preheat oven to 400°. Lightly grease a 13x9-inch casserole with 1 teaspoon extra virgin olive oil. </p><ol><li>Place zucchini in colander; sprinkle with salt. Let stand 10 minutes, then squeeze out all moisture.</li><li>In a large bowl, combine zucchini with eggs, Parmesan and half of mozzarella. Press into prepared pan. Bake 20 minutes until golden brown.</li><li>Meanwhile, in a large saute pan with evoo, saute onions, mushrooms and garlic. Sprinkle with salt and pepper to taste. </li><li>Spread tomato sauce over zucchini crust, top with mushrooms and cheese. </li><li>Bake until cheese is melted, about 20 minutes more.&nbsp;</li></ol>]]></recipe_instructions_html>
		// <recipe_prep_time_minutes>10</recipe_prep_time_minutes>
		// <recipe_cook_time_minutes>40</recipe_cook_time_minutes>
		// <recipe_total_time_minutes>60</recipe_total_time_minutes>
		// <recipe_yield_description>4</recipe_yield_description>
		// <recipe_exclusive_content_type>free</recipe_exclusive_content_type>
		// <recipe_ingredients><![CDATA[["1 tablespoon and 1 teaspoon extra virgin olive oil","4 cups shredded unpeeled zucchini from about 2 large zucchini","½ teaspoon salt","2 large eggs","½&nbsp;cup grated Parmesan cheese","1 ½ cups shredded part-skim mozzarella cheese, divided","1 large onion, chopped","1 pound mushrooms, chopped","2 cloves garlic, minced","1 (15 ounces) jar Italian tomato sauce",""]]]></recipe_ingredients>
		// <recipe_nutrition>{"calories":"290 kcal","carbohydrateContent":"20 g","fatContent":"16 g","fiberContent":"5 g","proteinContent":"20 g","saturatedFatContent":"7 g","sodiumContent":"880 mg","sugarContent":"10 g"}</recipe_nutrition>
		// <recipe_categories>["Main"]</recipe_categories>
		// <recipe_cuisine>["American"]</recipe_cuisine>
		// <recipe_cooking_method>["Baked"]</recipe_cooking_method>
		// <recipe_suitable_for_diet>["Gluten Free"]</recipe_suitable_for_diet>

		// Get recipe times.
		$servings   = get_post_meta( $id, 'recipe_yield_description', true );
		$prep_time  = (int) get_post_meta( $id, 'recipe_prep_time_minutes', true );
		$cook_time  = (int) get_post_meta( $id, 'recipe_cook_time_minutes', true );
		$total_time = (int) get_post_meta( $id, 'recipe_total_time_minutes', true );
		$total_time = $total_time ?: ( $prep_time + $cook_time );

		// Get and set all the WPRM recipe fields.
		$recipe['name']          = get_the_title( $id );
		$recipe['summary']       = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
		$recipe['image_id']      = get_post_thumbnail_id( $id );
		$recipe['author_name']   = get_the_author_meta( 'display_name', get_post_field( 'post_author', $id ) );
		$recipe['servings_unit'] = 'Servings';
		$recipe['servings']      = $servings;
		$recipe['prep_time']     = $prep_time;
		$recipe['cook_time']     = $cook_time;
		$recipe['total_time']    = $total_time;
		$recipe['notes']         = '';

		// Set recipe options.
		$recipe['author_display']        = 'default'; // default, disabled, post_author, custom.
		$recipe['ingredient_links_type'] = 'global';  // global, custom.

		// Optionally update the GLOBAL ingredient links (Premium only).
		// Warning, this changes the link for ALL recipes using that ingredient.
		// $recipe['global_ingredient_links'] = [
		// 	// Term ID or name of the ingredient to update.
		// 	1 => [
		// 		'url' => '',
		// 		'nofollow' => 'default', // default, follow, nofollow.
		// 	],
		// ];

		// Get raw meta.
		$instructions = get_post_meta( $id, 'recipe_instructions_html', true );
		$ingredients  = get_post_meta( $id, 'recipe_ingredients', true );
		$courses      = get_post_meta( $id, 'recipe_categories', true );
		$cuisines     = get_post_meta( $id, 'recipe_cuisine', true );
		$nutrition    = get_post_meta( $id, 'recipe_nutrition', true );

		// Parse raw meta.
		$instructions = strip_tags( $instructions, '<h1><h2><h3><h4><h5><h6><p><li><figure><img>' );
		$instructions = preg_replace( '/^<!\[CDATA\[(.*?)\]\]>$/s', '$1', $instructions );
		$ingredients  = preg_replace( '/^<!\[CDATA\[(.*?)\]\]>$/s', '$1', $ingredients );
		$ingredients  = strip_tags( $ingredients );
		$ingredients  = json_decode( (string) $ingredients, true );
		$courses      = json_decode( (string) $courses, true );
		$cuisines     = json_decode( (string) $cuisines, true );
		$nutrition    = json_decode( (string) $nutrition, true );

		// Clean up.
		$ingredients  = array_filter( (array) $ingredients );
		$courses      = array_filter( (array) $courses );
		$cuisines     = array_filter( (array) $cuisines );
		$nutrition    = array_filter( (array) $nutrition );

		// Set any recipe tags (custom ones need to be created on the WP Recipe Maker > Manage page first).
		// Use ID of existing terms
		// ...or name of new terms.
		$recipe['tags'] = [
			'course'  => $courses,
			'cuisine' => $cuisines,
			// 'suitablefordiet' => [], // Must be turned on in the WPRM settings.
		];

		// Ingredients.
		$recipe['ingredients'] = [
			[
				'name'        => '',
				'ingredients' => $this->parse_ingredients( $ingredients ),
			],
		];

		// Instructions.
		$recipe['instructions'] = $this->parse_instructions( $instructions );

		// Nutrition Facts.
		$recipe['nutrition'] = [
			'serving_size'        => '',
			'calories'            => $nutrition['calories'] ?? '',
			'carbohydrates'       => $nutrition['carbohydrateContent'] ?? '',
			'protein'             => $nutrition['proteinContent'] ?? '',
			'fat'                 => $nutrition['fatContent'] ?? '',
			'saturated_fat'       => $nutrition['saturatedFatContent'] ?? '',
			'polyunsaturated_fat' => '',
			'monounsaturated_fat' => '',
			'trans_fat'           => '',
			'cholesterol'         => $nutrition['cholesterolContent'] ?? '',
			'sodium'              => $nutrition['sodiumContent'] ?? '',
			'potassium'           => '',
			'fiber'               => $nutrition['fiberContent'] ?? '',
			'sugar'               => $nutrition['sugarContent'] ?? '',
			'vitamin_a'           => '',
			'vitamin_c'           => '',
			'calcium'             => '',
			'iron'                => '',
		];

		return $recipe;
	}

	/**
	 * Parse the instructions.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html The HTML to parse.
	 *
	 * @return array
	 */
	public function parse_instructions( $html ) {
		$groups         = [];
		$current_group  = [
			'name'         => '',
			'instructions' => [],
		];

		// Match headings, list items, paragraphs, and figures (including nested <img> tags).
		preg_match_all( '#<(h[1-6]|p|li|figure)([^>]*)>(.*?)</\1>|<img\s+([^>]+)>#is', $html, $matches, PREG_SET_ORDER );

		// Loop through matches.
		foreach ( $matches as $match ) {
			// Get the tag.
			$tag = strtolower( $match[1] ?? 'img' );

			// Handle headings.
			if ( preg_match( '#^h[1-6]$#', $tag ) ) {
				$body = trim( html_entity_decode( $match[3] ?? '' ) );
				$body = strip_tags( $body );
				$body = trim( $body );

				// Maybe add the current group to the groups array.
				if ( ! empty( $current_group['instructions'] ) || '' !== $current_group['name'] ) {
					$groups[] = $current_group;
				}

				// Start a new group.
				$current_group = [
					'name'         => $body,
					'instructions' => [],
				];

			}
			// Handle paragraphs, list items, and figures.
			elseif ( in_array( $tag, [ 'p', 'li', 'figure' ], true ) ) {
				$image_id = 0;
				$body     = trim( html_entity_decode( $match[3] ?? '' ) );

				// Match any <img> tags inside the block.
				preg_match_all( '/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $body, $img_matches );

				// Handle any image matches.
				if ( ! empty( $img_matches[1] ) ) {
					foreach ( $img_matches[1] as $image_url ) {
						// Upload image and get ID.
						$image_id = arena_upload_to_media_library( $image_url, 0 );
						break;
					}

					// Remove image tags from the text body.
					$body = preg_replace( '/<img\s+[^>]*>/i', '', $body );
				}

				// Some instructions have the numbers in the text like "1. Preheat oven to 400°."
				// We need to remove the numbers.
				$body = preg_replace( '/^\d+\.\s*/', '', $body );
				$body = strip_tags( $body );
				$body = trim( $body );

				// Add remaining text if any.
				if ( $body || $image_id ) {
					$current_group['instructions'][] = [
						'image' => $image_id,
						'text'  => $body,
					];
				}
			}
			// Handle standalone images.
			elseif ( 'img' === $tag ) {
				$attr = $match[4] ?? '';

				// Get the image URL.
				if ( preg_match( '/src=["\']([^"\']+)["\']/', $attr, $src_match ) ) {
					$image_url = $src_match[1];

					// Upload image and get ID.
					$image_id = arena_upload_to_media_library( $image_url, 0 );

					// Add the image to the current group.
					if ( $image_id ) {
						$current_group['instructions'][] = [
							'image' => $image_id,
							'text'  => '',
						];
					}
				}
			}
		}

		// Append the final group.
		if ( ! empty( $current_group['instructions'] ) || '' !== $current_group['name'] ) {
			$groups[] = $current_group;
		}

		return $groups;
	}

	/**
	 * Parse the ingredients.
	 *
	 * @since 0.1.0
	 *
	 * @param array $array The array to parse.
	 *
	 * @return array
	 */
	public function parse_ingredients( $array ) {
		$ingredients = [];

		foreach ( $array as $item ) {
			$ingredients[] = [
				'raw' => $item,
			];
		}

		return $ingredients;
	}

	/**
	 * Replace the original recipe with the newly imported WPRM one.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $id        ID of the recipe we want replace.
	 * @param mixed $wprm_id   ID of the WPRM recipe to replace with.
	 * @param array $post_data POST data, including any fields from `get_settings_html()`, passed along when submitting the form.
	 *
	 * @return void
	 */
	public function replace_recipe( $id, $wprm_id, $post_data ) {
		// The recipe with ID $id has been imported and we now have a WPRM recipe with ID $wprm_id (can be the same ID).
		// $post_data will contain any input fields set in the "get_settings_html" function.
		// Use this function to do anything after the import, like replacing shortcodes.

		// The recipe with ID $id has been imported and we now have a WPRM recipe with ID $wprm_id (can be the same ID).
		// $post_data will contain any input fields set in the "get_settings_html" function.
		// Use this function to do anything after the import, like replacing shortcodes.

		// Mark as migrated so it isn't re-imported.
		update_post_meta( $id, '_recipe_imported', 1 );

		// Set parent post that contains recipe.
		update_post_meta( $wprm_id, 'wprm_parent_post_id', $id );

		// Add the WPRM shortcode.
		$post = get_post( $id );

		// Bail if we don't have a post.
		if ( ! $post ) {
			return;
		}

		// Get the post content.
		$content = $post->post_content;

		// Bail if we already have a WPRM recipe.
		if ( str_contains( $content, '[wprm-recipe id="' . $id . '"]' ) ) {
			return;
		}

		// Add the WPRM block.
		$content .= $this->get_recipe_block( $wprm_id );

		// Update the post content.
		wp_update_post( [
			'ID'          => $id,
			'post_content' => $content,
		] );

		// Get the diet.
		$diet = get_post_meta( $id, 'recipe_suitable_for_diet', true );
		$diet = json_decode( (string) $diet, true );
		$diet = array_filter( (array) $diet );

		// If we have diet, append as post tags.
		if ( $diet ) {
			wp_set_post_tags( $id, $diet, true );
		}

		// Mark as checked. This skips manually having to check each recipe.
		update_post_meta( $wprm_id, 'wprm_import_source', $this->get_uid() . '-checked' );
	}

	/**
	 * Get the recipe block.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id The ID of the recipe.
	 *
	 * @return string
	 */
	public function get_recipe_block( $id ) {
		$html  = '';
		$html .= '<!-- wp:wp-recipe-maker/recipe {"id":' . $id . '} -->';
		$html .= '[wprm-recipe id="' . $id . '"]';
		$html .= '<!-- /wp:wp-recipe-maker/recipe -->';

		return $html;
	}
}