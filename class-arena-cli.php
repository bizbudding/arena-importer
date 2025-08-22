<?php
/**
 * WP-CLI Arena Commands
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'arena', 'WPArena_CLI_Command' );
}

class WPArena_CLI_Command {

	/**
	 * When importing, the _media_credit post meta was saved to the arena_item post type
	 * instead of the attachment that was created during import.
	 * This command migrates the media credits to the correct attachment post type.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list posts that would be updated. No changes will be made.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 100).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview posts that would be updated without making changes.
	 *     wp arena migrate-media-credits --per_page=1 --dry-run
	 *
	 *     # Actually update posts.
	 *     wp arena migrate-media-credits --per_page=10
	 *
	 *     # Process in batches with offset.
	 *     wp arena migrate-media-credits --per_page=50 --offset=1000
	 *
	 * @subcommand migrate-media-credits
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return void
	 */
	public function migrate_media_credits( $args, $assoc_args ) {
		$dry_run  = isset( $assoc_args['dry-run'] );
		$offset   = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 100;

		WP_CLI::log( "Loading posts..." );

		try {
			$query = new WP_Query([
				'post_type'              => 'arena_item',
				'post_status'            => 'any',
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]);

			$processed_imgs = 0;
			$skipped_imgs   = 0;

			if ( $query->have_posts() ) {
				WP_CLI::log( sprintf( "Found %d posts starting from offset %d. Processing...", $query->post_count, $offset ) );

				while ( $query->have_posts() ) : $query->the_post();
					global $post;

					$media_credit = get_post_meta( $post->ID, '_media_credit', true );

					if ( ! $media_credit ) {
						$skipped_imgs++;
						continue;
					}

					// Get post by parent id.
					$attachments = get_posts([
						'post_type'      => 'attachment',
						'post_parent'    => $post->ID,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					]);

					$attachment_id = $attachments[0] ?? false;

					if ( ! $attachment_id ) {
						WP_CLI::warning( sprintf( 'No attachment found for post %d', $post->ID ) );
						$skipped_imgs++;
						continue;
					}

					if ( ! $dry_run ) {
						update_post_meta( $attachment_id, '_media_credit', $media_credit );
						WP_CLI::log( sprintf( 'Updated attachment %d with media credit: %s %s', $attachment_id, $media_credit, get_permalink( $attachment_id ) ) );
					} else {
						WP_CLI::log( sprintf( 'Dry run: Would update attachment %d with media credit: %s %s', $attachment_id, $media_credit, get_permalink( $attachment_id ) ) );
					}
					$processed_imgs++;
				endwhile;
			} else {
				WP_CLI::success( 'No posts found.' );
			}
			wp_reset_postdata();

			WP_CLI::log( '' );
			WP_CLI::log( '=== Summary ===' );
			WP_CLI::log( sprintf( 'Processed images: %d', $processed_imgs ) );
			WP_CLI::log( sprintf( 'Skipped images: %d', $skipped_imgs ) );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Update content images.
	 *
	 * This command scans all posts and updates the content to add the `wp-image-{$image_id}` class to the images.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post_type>]
	 * : The post type to process. Default: 'post'.
	 *
	 * [--post_in=<post_id>]
	 * : The post ID to process.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 100).
	 *
	 * [--dry-run]
	 * : Only list posts that would be updated. No changes will be made.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview posts that would be updated without making changes.
	 *     wp arena update-content-images --per_page=1 --dry-run
	 *
	 *     # Actually update posts.
	 *     wp arena update-content-images --per_page=10
	 *
	 *     # Process in batches with offset.
	 *     wp arena update-content-images --per_page=50 --offset=1000
	 *
	 * @subcommand update-content-images
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return void
	 */
	public function update_content_images( $args, $assoc_args ) {
		$post_type = isset( $assoc_args['post_type'] ) ? $assoc_args['post_type'] : 'post';
		$offset    = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page  = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 100;
		$post_in   = isset( $assoc_args['post_in'] ) ? explode( ',', $assoc_args['post_in'] ) : [];
		$dry_run   = isset( $assoc_args['dry-run'] );

		WP_CLI::log( "Loading posts..." );

		try {
			$query_args = [
				'post_type'              => $post_type,
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			];

			if ( ! empty( $post_in ) ) {
				$query_args['post__in'] = $post_in;
			}

			$query = new WP_Query( $query_args );

			$processed_posts = 0;
			$processed_imgs  = 0;
			$skipped_imgs    = 0;
			$skipped_posts   = 0;

			if ( $query->have_posts() ) {
				WP_CLI::log( sprintf( "Found %d posts starting from offset %d. Processing...", $query->post_count, $offset ) );

				while ( $query->have_posts() ) : $query->the_post();
					$found_imgs = [];
					$processed_blocks = false;
					$post_id    = get_the_ID();
					$html       = get_post_field( 'post_content', $post_id );

					if ( has_blocks( $html ) ) {
						// "blockName" => "core/image"
						// "attrs" => array:2 [▼
						//   "align" => "none"
						//   "className" => "wp-image-857"
						// ]

						// "blockName" => "core/image"
						// "attrs" => array:5 [▼
						//   "id" => 857
						//   "sizeSlug" => "large"
						//   "linkDestination" => "none"
						//   "align" => "none"
						//   "className" => "wp-image-857"
						// ]

						$new_blocks = [];
						$blocks     = parse_blocks( $html );

						foreach ( $blocks as $block ) {
							// If html block.
							if ( 'core/html' === $block['blockName'] ) {
								$inner_html    = $block['innerHTML'] ?? '';
								$inner_content = $block['innerContent'] ?? [];

								// Skip if the html block contains an empty figure tag.
								// This is leftover from converting to blocks prior to this image fix command being run.
								if ( str_contains( $inner_html, '<figure></figure>' )
									&& 1 === count( $inner_content )
									&& str_contains( $inner_content[0], '<figure></figure>' )
								) {
									continue;
								}

								$new_blocks[] = $block;
								continue;
							}

							// Skip if not an image block.
							if ( 'core/image' !== $block['blockName'] ) {
								$new_blocks[] = $block;
								continue;
							}

							// Get block attributes.
							$id    = $block['attrs']['id'] ?? null;
							$size  = $block['attrs']['sizeSlug'] ?? 'large';
							$link  = $block['attrs']['linkDestination'] ?? 'none';
							$align = $block['attrs']['align'] ?? 'none';
							$class = (string) ($block['attrs']['className'] ?? '');

							// Skip if we already have an image ID.
							if ( $id ) {
								$new_blocks[] = $block;
								continue;
							}

							// Get inner content.
							$inner_content = $block['innerContent'][0] ?? '';
							$inner_content = $inner_content ? $inner_content : $block['innerHTML'] ?? '';

							// Skip if no inner content.
							if ( ! $inner_content ) {
								$new_blocks[] = $block;
								continue;
							}

							// Set up tag processor.
							$tags = new WP_HTML_Tag_Processor( $inner_content );

							// Loop through img tags.
							while ( $tags->next_tag( [ 'tag_name' => 'figure' ] ) ) {
								$tags->add_class( 'size-' . $size );
							}

							// Get the updated HTML.
							$inner_content = $tags->get_updated_html();

							// Set up tag processor.
							$tags = new WP_HTML_Tag_Processor( $inner_content );

							// Flag to track if we processed an image
							$processed_image = false;

							// Loop through img tags.
							while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
								$src = (string) $tags->get_attribute( 'src' );

								// Skip if no src.
								if ( empty( $src ) ) {
									continue;
								}

								// Get the image ID.
								$image_id = attachment_url_to_postid( $src );

								// Skip if no image ID.
								if ( ! $image_id ) {
									continue;
								}

								// Update src so it's not the full size.
								$new_src = wp_get_attachment_image_url( $image_id, $size );

								// If url, set the src attribute and update the srcset attribute.
								if ( $new_src ) {
									$tags->set_attribute( 'src', $new_src );
								}

								// Some alt tags had weirdness, so this cleans it up.
								$alt     = $tags->get_attribute( 'alt' );
								$alt_esc = esc_attr( sanitize_text_field( $alt ) );
								if ( $alt !== $alt_esc ) {
									$tags->set_attribute( 'alt', $alt_esc );
								}

								// Get the title attribute.
								$title = $tags->get_attribute( 'title' );
								$title_esc = esc_attr( sanitize_text_field( $title ) );
								if ( $title !== $title_esc ) {
									$tags->set_attribute( 'title', $title_esc );
								}

								// Set image classes.
								$tags->add_class( 'wp-image-' . $image_id );

								// Set block attributes.
								$class          .= ' wp-image-' . $image_id . ' align' . $align;
								$block['attrs']  = [
									'id'              => $image_id,
									'sizeSlug'        => $size,
									'linkDestination' => $link,
									'align'           => $align,
									'className'       => trim( $class ),
								];

								// Mark that we processed an image and break out of the while loop
								$processed_image  = true;
								$processed_blocks = true;
								break;
							}

							// Update the block's inner content if we processed an image
							if ( $processed_image ) {
								$new_html              = $tags->get_updated_html();
								$block['innerContent'] = [ $new_html ];
								$block['innerHTML']    = $new_html;
							}

							$new_blocks[] = $block;
						}

						$html = serialize_blocks( $new_blocks );
					} else {
						// Remove caption tags.
						$html = str_replace( ['[caption]', '[/caption]'], '', $html );

						// Set up tag processor.
						$tags = new WP_HTML_Tag_Processor( $html );

						// Loop through tags.
						while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
							// $class = (string) $tags->get_attribute( 'class' );
							$src = (string) $tags->get_attribute( 'src' );

							// Skip if no src.
							if ( empty( $src ) ) {
								$skipped_imgs++;
								continue;
							}

							// Get the image ID.
							$image_id = attachment_url_to_postid( $src );

							// Skip if no image ID.
							if ( ! $image_id ) {
								$skipped_imgs++;
								continue;
							}

							// If we found an image, update the post.
							$found_imgs[] = $image_id;

							// Add the wp-image-{$image_id} class to the image.
							$tags->add_class( 'wp-image-' . $image_id );

							// Add the size-large class to the image.
							$tags->add_class( 'size-large' );

							// Update src so it's not the full size.
							$new_src = wp_get_attachment_image_url( $image_id, 'large' );

							// If new src.
							if ( $new_src ) {
								$tags->set_attribute( 'src', $new_src );
							}

							// Some alt tags had weirdness, so this cleans it up.
							$alt     = $tags->get_attribute( 'alt' );
							$alt_esc = esc_attr( sanitize_text_field( $alt ) );
							if ( $alt !== $alt_esc ) {
								$tags->set_attribute( 'alt', $alt_esc );
							}

							// Get the title attribute.
							$title = $tags->get_attribute( 'title' );
							$title_esc = esc_attr( sanitize_text_field( $title ) );
							if ( $title !== $title_esc ) {
								$tags->set_attribute( 'title', $title_esc );
							}
						}

						// Get the updated HTML.
						$html = $tags->get_updated_html();
					}

					// Update the post content if we found an image or processed blocks.
					if ( ! empty( $found_imgs ) || $processed_blocks ) {
						if ( ! $dry_run ) {
							$post_id = wp_update_post( [
								'ID'           => $post_id,
								'post_content' => $html,
							] );
							$processed_imgs++;
							WP_CLI::success( sprintf( 'Updated post %d. Found and fixed %d images: %s', $post_id, count( $found_imgs ), get_permalink( $post_id ) ) );
						} else {
							WP_CLI::log( sprintf( 'Dry run: Would update post %d. Found and fixed %d images: %s', $post_id, count( $found_imgs ), get_permalink( $post_id ) ) );
						}

						$processed_posts++;
					} else {
						$skipped_posts++;
					}
				endwhile;
			} else {
				WP_CLI::success( 'No posts found.' );
			}
			wp_reset_postdata();

			WP_CLI::log( '' );
			WP_CLI::log( '=== Summary ===' );
			WP_CLI::log( sprintf( 'Processed posts: %d', $processed_posts ) );
			WP_CLI::log( sprintf( 'Skipped posts: %d', $skipped_posts ) );
			WP_CLI::log( sprintf( 'Found images: %d', $processed_imgs ) );
			WP_CLI::log( sprintf( 'Skipped images: %d', $skipped_imgs ) );

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Delete attachments that don't have associated files.
	 *
	 * This command scans all attachments and checks if their associated files exist on disk.
	 * It can optionally delete these orphaned attachments.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list orphaned attachments. No changes will be made.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of attachments to process per batch (default: 100).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview orphaned attachments without deleting.
	 *     wp arena delete-orphaned-attachments --dry-run
	 *
	 *     # Actually delete orphaned attachments.
	 *     wp arena delete-orphaned-attachments
	 *
	 *     # Process in batches with offset.
	 *     wp arena delete-orphaned-attachments --offset=1000 --per_page=50
	 *
	 * @subcommand delete-orphaned-attachments
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function delete_orphaned_attachments( $args, $assoc_args ) {
		$dry_run  = isset( $assoc_args['dry-run'] );
		$offset   = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 100;

		WP_CLI::log( "Loading attachments..." );

		$attachments = get_posts([
			'post_type'      => 'attachment',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		]);

		WP_CLI::log( sprintf( "Found %d attachments starting from offset %d. Processing...", count( $attachments ), $offset ) );

		if ( empty( $attachments ) ) {
			WP_CLI::success( 'No attachments found.' );
			return;
		}

		// Initialize counters
		$processed = 0;
		$orphaned  = 0;
		$skipped   = 0;
		$errors    = 0;

		foreach ( $attachments as $attachment_id ) {
			$processed++;

			try {
				$file_path = get_attached_file( $attachment_id );

				// Check if file path is associated and if file exists on disk
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$orphaned++;
					$attachment = get_post( $attachment_id );
					$title = $attachment ? $attachment->post_title : 'Unknown';
					$url = wp_get_attachment_url( $attachment_id );

					WP_CLI::log( sprintf( 'Found orphaned attachment %d: %s', $attachment_id, $title ) );
					if ( ! $file_path ) {
						WP_CLI::log( sprintf( '  No file path associated' ) );
					} else {
						WP_CLI::log( sprintf( '  File path: %s (file does not exist)', $file_path ) );
					}
					WP_CLI::log( sprintf( '  URL: %s', $url ) );

					// Delete the orphaned attachment if not in dry run mode
					if ( ! $dry_run ) {
						$result = wp_delete_attachment( $attachment_id, true );
						if ( $result ) {
							WP_CLI::success( sprintf( 'Deleted orphaned attachment %d', $attachment_id ) );
						} else {
							$errors++;
							WP_CLI::warning( sprintf( 'Failed to delete orphaned attachment %d', $attachment_id ) );
						}
					} else {
						WP_CLI::log( sprintf( '[Dry run] Would delete orphaned attachment %d', $attachment_id ) );
					}
				} else {
					$skipped++;
					// WP_CLI::log( sprintf( 'Skipped attachment %d: File exists', $attachment_id ) );
				}

			} catch ( Exception $e ) {
				$errors++;
				WP_CLI::warning( sprintf( 'Error processing attachment %d: %s', $attachment_id, $e->getMessage() ) );
			}
		}

		// Show summary
		WP_CLI::log( '' );
		WP_CLI::log( '=== Summary ===' );
		WP_CLI::log( sprintf( 'Processed: %d attachments', $processed ) );
		WP_CLI::log( sprintf( 'Orphaned: %d attachments', $orphaned ) );
		WP_CLI::log( sprintf( 'Skipped: %d attachments', $skipped ) );
		WP_CLI::log( sprintf( 'Errors: %d attachments', $errors ) );

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run completed. Found %d orphaned attachments. Use --dry-run=false to delete them.', $orphaned ) );
		} else {
			WP_CLI::success( sprintf( 'Completed! Deleted %d orphaned attachments.', $orphaned ) );
		}
	}

	/**
	 * Delete duplicate image attachments with numbered filenames (e.g., `image-1.jpg`, `photo-2.webp`).
	 *
	 * This command scans the `attachment` post type for filenames that match the pattern `-<number>.<ext>`,
	 * such as `-1.jpg`, `-2.png`, or `-3.webp`. It then deletes those attachments and associated files.
	 *
	 * You can use `--dry-run` to preview what would be deleted. Use `--offset` and `--per_page` for batch processing.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list attachments that would be deleted. No changes will be made.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview all duplicate-numbered media files without deleting.
	 *     wp arena dedupe-media --dry-run
	 *
	 *     # Actually delete duplicate-numbered media files.
	 *     wp arena dedupe-media
	 *
	 * @subcommand dedupe-media
	 */
	public function dedupe_media( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		WP_CLI::log( "Loading all attachments..." );

		$attachments = get_posts([
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		]);

		WP_CLI::log( "Found " . count( $attachments ) . " attachments. Processing..." );

		if ( empty( $attachments ) ) {
			WP_CLI::success( 'No attachments found.' );
			return;
		}

		// Group attachments by their base filename
		$file_groups = [];

		foreach ( $attachments as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file ) continue;

			$filename = basename( $file );
			$file_path = dirname( $file );

			// Check if this is a numbered file (e.g., filename-22.jpg, filename-2.jpg)
			// Only match simple numbered suffixes, not timestamps or other complex numbers
			if ( preg_match( '/^(.+)-([0-9]{1,3})\.(jpg|jpeg|png|gif|webp)$/i', $filename, $matches ) ) {
				$base_name = $matches[1];
				$number = (int) $matches[2];
				$extension = $matches[3];

				$key = $base_name . '.' . $extension;
				if ( ! isset( $file_groups[$key] ) ) {
					$file_groups[$key] = [];
				}
				$file_groups[$key][] = [
					'id' => $id,
					'filename' => $filename,
					'file_path' => $file_path,
					'number' => $number,
					'extension' => $extension
				];
			}
		}

		$count = 0;

		// Check if we have any file groups to process
		if ( empty( $file_groups ) ) {
			WP_CLI::success( 'No numbered duplicate files found to process.' );
			return;
		}

		// Process each group of files
		foreach ( $file_groups as $base_key => $files ) {
			// Sort files by number (highest to lowest)
			usort( $files, function( $a, $b ) {
				return $b['number'] - $a['number'];
			} );

			// Check if base file exists (without number)
			$base_filename = $base_key;
			$base_file_path = $files[0]['file_path'] . '/' . $base_filename;
			$base_exists = file_exists( $base_file_path );

			// Also check if the base file is actually an attachment in WordPress (not just a physical file)
			$base_attachment_id = $this->get_attachment_id_by_filename( $base_filename, $files[0]['file_path'] );
			$base_exists_in_wp = $base_attachment_id && !preg_match( '/^(.+)-([0-9]{1,3})\.(jpg|jpeg|png|gif|webp)$/i', $base_filename );

			// If base file exists both physically and in WordPress, and it's not a numbered file itself
			if ( $base_exists && $base_exists_in_wp ) {
				$base_url = wp_get_attachment_url( $base_attachment_id );

				foreach ( $files as $file_info ) {
					WP_CLI::log( ($dry_run ? '[Dry run] Would delete' : 'Deleting') . ": {$file_info['id']} - {$file_info['filename']} (base file: {$base_filename} - {$base_url})" );

					if ( ! $dry_run ) {
						wp_delete_attachment( $file_info['id'], true );
					}
					$count++;
				}
			} else {
				// Keep the lowest numbered file, delete the rest
				$lowest_file = array_pop( $files ); // Get the lowest numbered file
				$lowest_url = wp_get_attachment_url( $lowest_file['id'] );

				foreach ( $files as $file_info ) {
					WP_CLI::log( ($dry_run ? '[Dry run] Would delete' : 'Deleting') . ": {$file_info['id']} - {$file_info['filename']} (keeping {$lowest_file['filename']} - {$lowest_url})" );

					if ( ! $dry_run ) {
						wp_delete_attachment( $file_info['id'], true );
					}
					$count++;
				}
			}
		}

		WP_CLI::success( $dry_run ? "$count duplicate(s) found." : "$count duplicate(s) deleted." );
	}

	/**
	 * Set Arena featured images for posts.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be done without making changes.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 50).
	 *
	 * [--post_status=<status>]
	 * : Post status to process (default: any).
	 *
	 * ## EXAMPLES
	 *
	 *     wp arena set-arena-featured-image --dry-run
	 *     wp arena set-arena-featured-image --offset=100 --per_page=25
	 *     wp arena set-arena-featured-image --post_status=publish
	 *
	 * @subcommand set-arena-featured-image
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function set_arena_featured_image( $args, $assoc_args ) {
		$dry_run     = isset( $assoc_args['dry-run'] );
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page    = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 20;
		$post_status = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'any';

		// Validate post status
		$valid_statuses = ['any', 'publish', 'draft', 'pending', 'private', 'trash'];
		if ( ! in_array( $post_status, $valid_statuses ) ) {
			WP_CLI::error( sprintf( 'Invalid post status: %s. Valid statuses: %s', $post_status, implode( ', ', $valid_statuses ) ) );
		}

		$query = new WP_Query(
			[
				'post_type'              => 'post',
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'post_status'            => $post_status,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Initialize counters
		$processed = 0;
		$updated   = 0;
		$skipped   = 0;
		$errors    = 0;

		if ( $query->have_posts() ) {
			WP_CLI::log( sprintf( 'Processing %d posts starting from offset %d...', $query->post_count, $offset ) );

			while ( $query->have_posts() ) : $query->the_post();
				$post_id = get_the_ID();
				$processed++;

				try {
					$featured_id = get_post_thumbnail_id();
					$arena_url   = get_post_meta( $post_id, 'arena_featured_image_url', true );

					// Skip if no arena URL
					if ( ! $arena_url ) {
						$skipped++;
						// WP_CLI::log( sprintf( 'Skipped post %d: No arena URL', $post_id ) );
						continue;
					}

					WP_CLI::log( sprintf( 'Post %d: Arena URL: %s', $post_id, $arena_url ) );

					// Get image ID from arena URL
					$image_id = $this->get_image_id_by_url_internal( $arena_url );
					if ( ! $image_id ) {
						// Try to import the image
						WP_CLI::log( sprintf( 'Post %d: No image found for URL %s, attempting to import...', $post_id, $arena_url ) );
						$image_id = $this->import_image_from_url( $arena_url );

						if ( ! $image_id ) {
							$skipped++;
							WP_CLI::log( sprintf( 'Skipped post %d: Failed to import image from URL %s', $post_id, $arena_url ) );
							continue;
						} else {
							WP_CLI::success( sprintf( 'Post %d: Successfully imported image %d from URL', $post_id, $image_id ) );
						}
					}

					// Check if already set correctly
					if ( $featured_id && $image_id === $featured_id ) {
						$skipped++;
						// WP_CLI::log( sprintf( 'Skipped post %d: Featured image already set correctly', $post_id ) );
						continue;
					}

					// Set the featured image
					if ( ! $dry_run ) {
						$result = set_post_thumbnail( $post_id, $image_id );
						if ( $result ) {
							$updated++;
							WP_CLI::success( sprintf( 'Updated post %d: Set featured image %d', $post_id, $image_id ) );
						} else {
							$errors++;
							WP_CLI::warning( sprintf( 'Failed to set featured image %d for post %d', $image_id, $post_id ) );
						}
					} else {
						$updated++;
						WP_CLI::log( sprintf( 'Would update post %d: Set featured image %d (dry run)', $post_id, $image_id ) );
					}

				} catch ( Exception $e ) {
					$errors++;
					WP_CLI::warning( sprintf( 'Error processing post %d: %s', $post_id, $e->getMessage() ) );
				}
			endwhile;
		} else {
			WP_CLI::warning( 'No posts found matching the criteria.' );
		}

		wp_reset_postdata();

		// Show summary
		WP_CLI::log( '' );
		WP_CLI::log( '=== Summary ===' );
		WP_CLI::log( sprintf( 'Processed: %d posts', $processed ) );
		WP_CLI::log( sprintf( 'Updated: %d posts', $updated ) );
		WP_CLI::log( sprintf( 'Skipped: %d posts', $skipped ) );
		WP_CLI::log( sprintf( 'Errors: %d posts', $errors ) );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run completed. Use --dry-run=false to make actual changes.' );
		} else {
			WP_CLI::success( sprintf( 'Completed! Updated %d posts.', $updated ) );
		}
	}

	/**
	 * Get image ID by Arena URL.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The Arena URL to search for
	 *
	 * ## EXAMPLES
	 *
	 *     wp arena get-image-id-by-url "https://edm.com/.image/MjE2NzEzMjY1NjMwMTYwMjM5/image001.jpg"
	 *
	 * @subcommand get-image-id-by-url
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function get_image_id_by_url( $args, $assoc_args ) {
		$url = $args[0];

		if ( empty( $url ) ) {
			WP_CLI::error( 'Please provide an Arena URL.' );
		}

		$image_id = $this->get_image_id_by_url_internal( $url );

		if ( $image_id ) {
			$attachment = get_post( $image_id );
			WP_CLI::success( sprintf( 'Found attachment ID: %d', $image_id ) );
			WP_CLI::log( sprintf( 'Title: %s', $attachment->post_title ) );
			WP_CLI::log( sprintf( 'File: %s', get_attached_file( $image_id ) ) );
			WP_CLI::log( sprintf( 'Arena URL: %s', get_post_meta( $image_id, 'arena_attachment_url', true ) ) );
		} else {
			WP_CLI::warning( sprintf( 'No attachment found for Arena URL: %s', $url ) );
		}

		return $image_id;
	}

	/**
	 * Get image ID by Arena URL (internal helper method).
	 *
	 * @param string $url The Arena URL to search for.
	 * @return int|false The attachment ID or false if not found.
	 */
	private function get_image_id_by_url_internal( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Search for attachment with matching arena_attachment_url meta
		$query_args = [
			'post_type'  => 'attachment',
			'post_status'=> 'inherit',
			'fields'     => 'ids',
			'meta_query' => [
				[
					'key'     => 'arena_attachment_url',
					'value'   => $url,
					'compare' => '=',
				],
			],
			'posts_per_page' => 1,
		];

		$attachments = get_posts( $query_args );

		return $attachments && isset( $attachments[0] ) ? $attachments[0] : false;
	}

	/**
	 * Find posts that contain only an iframe as content.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be done without making changes.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 50).
	 *
	 * [--post_status=<status>]
	 * : Post status to process (default: any).
	 *
	 * [--action=<action>]
	 * : Action to take on found posts: 'list', 'delete', 'draft' (default: draft).
	 *
	 * ## EXAMPLES
	 *
	 *     wp arena find-iframe-only --dry-run
	 *     wp arena find-iframe-only --action=list --post_status=publish
	 *     wp arena find-iframe-only --action=delete --dry-run
	 *     wp arena find-iframe-only --action=draft --per_page=25
	 *
	 * @subcommand find-iframe-only
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function find_iframe_only( $args, $assoc_args ) {
		$dry_run     = isset( $assoc_args['dry-run'] );
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page    = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 50;
		$post_status = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'any';
		$action      = isset( $assoc_args['action'] ) ? $assoc_args['action'] : 'draft';

		// Validate post status
		$valid_statuses = ['any', 'publish', 'draft', 'pending', 'private', 'trash'];
		if ( ! in_array( $post_status, $valid_statuses ) ) {
			WP_CLI::error( sprintf( 'Invalid post status: %s. Valid statuses: %s', $post_status, implode( ', ', $valid_statuses ) ) );
		}

		// Validate action
		$valid_actions = ['list', 'delete', 'draft'];
		if ( ! in_array( $action, $valid_actions ) ) {
			WP_CLI::error( sprintf( 'Invalid action: %s. Valid actions: %s', $action, implode( ', ', $valid_actions ) ) );
		}

		$query = new WP_Query(
			[
				'post_type'              => 'post',
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'post_status'            => $post_status,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Initialize counters
		$processed = 0;
		$found     = 0;
		$skipped   = 0;
		$errors    = 0;

		if ( $query->have_posts() ) {
			WP_CLI::log( sprintf( 'Processing %d posts starting from offset %d...', $query->post_count, $offset ) );

			while ( $query->have_posts() ) : $query->the_post();
				$post_id = get_the_ID();
				$processed++;

				try {
					$content = get_the_content();

					// Skip if no content
					if ( empty( $content ) ) {
						$skipped++;
						WP_CLI::log( sprintf( 'Skipped post %d: Empty content', $post_id ) );
						continue;
					}

					// Check if content contains only an iframe
					if ( $this->contains_only_iframe( $content ) ) {
						$found++;
						WP_CLI::log( sprintf( 'Found post %d: %s', $post_id, get_permalink( $post_id ) ) );

						// Perform action
						if ( ! $dry_run ) {
							switch ( $action ) {
								case 'delete':
									$result = wp_delete_post( $post_id, true );
									if ( $result ) {
										WP_CLI::success( sprintf( 'Deleted post %d', $post_id ) );
									} else {
										$errors++;
										WP_CLI::warning( sprintf( 'Failed to delete post %d', $post_id ) );
									}
									break;

								case 'draft':
									// Change to draft
									$result = wp_update_post( [
										'ID'          => $post_id,
										'post_status' => 'draft',
									] );

									// Add "iframe" tag
									$iframe_tag = get_term_by( 'name', 'iframe', 'post_tag' );
									if ( ! $iframe_tag ) {
										$iframe_tag = wp_insert_term( 'iframe', 'post_tag' );
									}

									if ( $iframe_tag && ! is_wp_error( $iframe_tag ) ) {
										$tag_id = is_object( $iframe_tag ) ? $iframe_tag->term_id : $iframe_tag['term_id'];
										wp_set_object_terms( $post_id, $tag_id, 'post_tag', true );
									}

									if ( $result ) {
										WP_CLI::success( sprintf( 'Changed post %d to draft and tagged with "iframe"', $post_id ) );
									} else {
										$errors++;
										WP_CLI::warning( sprintf( 'Failed to change post %d to draft', $post_id ) );
									}
									break;

								case 'list':
								default:
									// Just list, no action needed
									break;
							}
						} else {
							// WP_CLI::log( sprintf( 'Would %s post %d (dry run)', $action, $post_id ) );
						}
					} else {
						$skipped++;
						// WP_CLI::log( sprintf( 'Skipped post %d: Contains more than just iframe', $post_id ) );
					}

				} catch ( Exception $e ) {
					$errors++;
					WP_CLI::warning( sprintf( 'Error processing post %d: %s', $post_id, $e->getMessage() ) );
				}
			endwhile;
		} else {
			WP_CLI::warning( 'No posts found matching the criteria.' );
		}

		wp_reset_postdata();

		// Show summary
		WP_CLI::log( '' );
		WP_CLI::log( '=== Summary ===' );
		WP_CLI::log( sprintf( 'Processed: %d posts', $processed ) );
		WP_CLI::log( sprintf( 'Found iframe-only: %d posts', $found ) );
		WP_CLI::log( sprintf( 'Skipped: %d posts', $skipped ) );
		WP_CLI::log( sprintf( 'Errors: %d posts', $errors ) );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run completed. Use --dry-run=false to make actual changes.' );
		} else {
			WP_CLI::success( sprintf( 'Completed! Found %d posts with iframe-only content.', $found ) );
		}
	}

	/**
	 * Check if content contains only an iframe.
	 *
	 * @param string $content The post content to check.
	 * @return bool True if content contains only an iframe, false otherwise.
	 */
	private function contains_only_iframe( $content ) {
		// Remove whitespace and normalize
		$content = trim( $content );

		// Check if content is empty
		if ( empty( $content ) ) {
			return false;
		}

		// Check if content is exactly an iframe tag
		if ( preg_match( '/^<iframe[^>]*>.*?<\/iframe>$/is', $content ) ) {
			return true;
		}

		// Check if content contains only an iframe with optional whitespace
		$iframe_pattern = '/<iframe[^>]*>.*?<\/iframe>/is';
		$iframe_matches = [];
		preg_match_all( $iframe_pattern, $content, $iframe_matches );

		if ( ! empty( $iframe_matches[0] ) ) {
			// Remove all iframes from content
			$content_without_iframes = preg_replace( $iframe_pattern, '', $content );
			// Remove all whitespace
			$content_without_iframes = preg_replace( '/\s+/', '', $content_without_iframes );

			// If nothing remains, it was only iframes
			return empty( $content_without_iframes );
		}

		return false;
	}

	/**
	 * Get attachment ID by filename and path.
	 *
	 * @param string $filename The filename to search for.
	 * @param string $file_path The file path.
	 * @return int|false The attachment ID or false if not found.
	 */
	private function get_attachment_id_by_filename( $filename, $file_path ) {
		$query_args = [
			'post_type'  => 'attachment',
			'post_status'=> 'inherit',
			'meta_query' => [
				[
					'key'     => '_wp_attached_file',
					'value'   => $file_path . '/' . $filename,
					'compare' => 'LIKE',
				],
			],
			'posts_per_page' => 1,
		];

		$attachments = get_posts( $query_args );
		return $attachments ? $attachments[0]->ID : false;
	}

	/**
	 * Import image from URL.
	 *
	 * @param string $url The image URL to import.
	 * @return int|false The attachment ID or false if import failed.
	 */
	private function import_image_from_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

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
			// Remove the original image and return the error.
			@unlink( $tmp );
			return $tmp;
		}

		// Build the file array.
		$file_array = [
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		];

		// Add the image to the media library.
		$id = media_handle_sideload( $file_array, 0 );

		// Bail if error.
		if ( is_wp_error( $id ) ) {
			// Remove the original image and return the error.
			@unlink( $file_array[ 'tmp_name' ] );
			return $id;
		}

		// Clean up the temporary file.
		if ( file_exists( $tmp ) ) {
			unlink( $tmp );
		}

		return $id;
	}
}