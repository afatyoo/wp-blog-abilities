<?php
/**
 * WordPress Blog Abilities for MCP
 *
 * @wordpress-plugin
 * Plugin Name: Blog Abilities for MCP
 * Description: Registers blog post abilities (create, update, list, delete) for the MCP Adapter.
 * Version: 1.6.1
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

add_action( 'wp_abilities_api_categories_init', function () {
	wp_register_ability_category( 'content', [
		'label'       => 'Content',
		'description' => 'Abilities for managing WordPress content.',
	] );
} );

add_action( 'wp_abilities_api_init', function () {

	// Create post
	wp_register_ability( 'blog/create-post', [
		'label'       => 'Create Blog Post',
		'description' => 'Create a new WordPress blog post.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'title'   => [ 'type' => 'string', 'description' => 'Post title' ],
				'content' => [ 'type' => 'string', 'description' => 'Post content (HTML or plain text)' ],
				'status'  => [
					'type'    => 'string',
					'enum'    => [ 'publish', 'draft', 'pending' ],
					'default' => 'draft',
					'description' => 'Post status',
				],
				'excerpt' => [ 'type' => 'string', 'description' => 'Short excerpt (optional)' ],
				'tags'    => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'Tag names to attach (optional)',
				],
				'categories' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'Category names to attach (optional)',
				],
			],
			'required' => [ 'title', 'content' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'        => [ 'type' => 'integer' ],
				'title'     => [ 'type' => 'string' ],
				'status'    => [ 'type' => 'string' ],
				'permalink' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'publish_posts' );
		},
		'execute_callback' => function ( $input ) {
			$args = [
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => wp_kses_post( $input['content'] ),
				'post_status'  => $input['status'] ?? 'draft',
				'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
				'post_author'  => get_current_user_id(),
			];

			$post_id = wp_insert_post( $args, true );
			if ( is_wp_error( $post_id ) ) {
				return new WP_Error( 'create_failed', $post_id->get_error_message() );
			}

			if ( ! empty( $input['tags'] ) ) {
				wp_set_post_tags( $post_id, $input['tags'], false );
			}

			if ( ! empty( $input['categories'] ) ) {
				$cat_ids = array_filter( array_map( function ( $name ) {
					$cat = get_term_by( 'name', $name, 'category' );
					if ( ! $cat ) {
						$result = wp_insert_term( $name, 'category' );
						return is_wp_error( $result ) ? null : $result['term_id'];
					}
					return $cat->term_id;
				}, $input['categories'] ) );
				wp_set_post_categories( $post_id, $cat_ids );
			}

			return [
				'id'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'status'    => get_post_status( $post_id ),
				'permalink' => get_permalink( $post_id ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Update post
	wp_register_ability( 'blog/update-post', [
		'label'       => 'Update Blog Post',
		'description' => 'Update title, content, status, excerpt, tags, or categories of an existing post.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'           => [ 'type' => 'integer', 'description' => 'Post ID to update' ],
				'title'        => [ 'type' => 'string', 'description' => 'New title (optional)' ],
				'content'      => [ 'type' => 'string', 'description' => 'New content (optional)' ],
				'status'       => [
					'type' => 'string',
					'enum' => [ 'publish', 'draft', 'pending', 'trash' ],
					'description' => 'New status (optional)',
				],
				'excerpt'      => [ 'type' => 'string', 'description' => 'New excerpt (optional)' ],
				'tags'         => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'Tag names to set — replaces existing tags (optional)',
				],
				'tag_ids'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'Tag IDs to set directly — replaces existing tags (optional)',
				],
				'categories'   => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'Category names to set — replaces existing categories (optional)',
				],
				'category_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'Category IDs to set directly — replaces existing categories (optional)',
				],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'title'       => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'permalink'   => [ 'type' => 'string' ],
				'tags'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'categories'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$post_id = (int) $input['id'];
			$post    = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$args = [ 'ID' => $post_id ];
			if ( isset( $input['title'] ) )   $args['post_title']   = sanitize_text_field( $input['title'] );
			if ( isset( $input['content'] ) ) $args['post_content'] = wp_kses_post( $input['content'] );
			if ( isset( $input['status'] ) )  $args['post_status']  = $input['status'];
			if ( isset( $input['excerpt'] ) ) $args['post_excerpt'] = sanitize_text_field( $input['excerpt'] );

			$updated = wp_update_post( $args, true );
			if ( is_wp_error( $updated ) ) {
				return new WP_Error( 'update_failed', $updated->get_error_message() );
			}

			// Tags by name
			if ( ! empty( $input['tags'] ) ) {
				wp_set_post_tags( $post_id, $input['tags'], false );
			}

			// Tags by ID
			if ( ! empty( $input['tag_ids'] ) ) {
				wp_set_object_terms( $post_id, array_map( 'intval', $input['tag_ids'] ), 'post_tag' );
			}

			// Categories by name
			if ( ! empty( $input['categories'] ) ) {
				$cat_ids = array_filter( array_map( function ( $name ) {
					$cat = get_term_by( 'name', $name, 'category' );
					if ( ! $cat ) {
						$result = wp_insert_term( $name, 'category' );
						return is_wp_error( $result ) ? null : $result['term_id'];
					}
					return $cat->term_id;
				}, $input['categories'] ) );
				wp_set_post_categories( $post_id, array_values( $cat_ids ) );
			}

			// Categories by ID
			if ( ! empty( $input['category_ids'] ) ) {
				wp_set_post_categories( $post_id, array_map( 'intval', $input['category_ids'] ) );
			}

			$tag_terms = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
			$cat_terms = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );

			return [
				'id'         => $post_id,
				'title'      => get_the_title( $post_id ),
				'status'     => get_post_status( $post_id ),
				'permalink'  => get_permalink( $post_id ),
				'tags'       => is_array( $tag_terms ) ? $tag_terms : [],
				'categories' => is_array( $cat_terms ) ? $cat_terms : [],
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// List posts
	wp_register_ability( 'blog/list-posts', [
		'label'       => 'List Blog Posts',
		'description' => 'Retrieve a list of blog posts with optional filters.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'status'       => [
					'type'    => 'string',
					'enum'    => [ 'publish', 'draft', 'pending', 'any' ],
					'default' => 'any',
				],
				'numberposts' => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ],
				'search'       => [ 'type' => 'string', 'description' => 'Keyword search (optional)' ],
			],
		],
		'output_schema' => [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'id'        => [ 'type' => 'integer' ],
					'title'     => [ 'type' => 'string' ],
					'status'    => [ 'type' => 'string' ],
					'date'      => [ 'type' => 'string' ],
					'permalink' => [ 'type' => 'string' ],
				],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$args = [
				'post_status'  => $input['status'] ?? 'any',
				'numberposts'  => $input['numberposts'] ?? 10,
				'post_type'    => 'post',
			];
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$posts = get_posts( $args );
			return array_map( function ( $p ) {
				return [
					'id'        => $p->ID,
					'title'     => $p->post_title,
					'status'    => $p->post_status,
					'date'      => $p->post_date,
					'permalink' => get_permalink( $p->ID ),
				];
			}, $posts );
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Get single post with full content
	wp_register_ability( 'blog/get-post', [
		'label'       => 'Get Blog Post',
		'description' => 'Retrieve full content and metadata of a single post by ID.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id' => [ 'type' => 'integer', 'description' => 'Post ID to retrieve' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'title'       => [ 'type' => 'string' ],
				'content'     => [ 'type' => 'string' ],
				'excerpt'     => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'date'        => [ 'type' => 'string' ],
				'permalink'   => [ 'type' => 'string' ],
				'tags'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'tag_ids'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'categories'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'category_ids'=> [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$post = get_post( (int) $input['id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$tag_terms = wp_get_post_tags( $post->ID );
			$cat_terms = get_the_category( $post->ID );

			return [
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'content'      => $post->post_content,
				'excerpt'      => $post->post_excerpt,
				'status'       => $post->post_status,
				'date'         => $post->post_date,
				'permalink'    => get_permalink( $post->ID ),
				'tags'         => array_map( fn( $t ) => $t->name, $tag_terms ),
				'tag_ids'      => array_map( fn( $t ) => $t->term_id, $tag_terms ),
				'categories'   => array_map( fn( $c ) => $c->name, $cat_terms ),
				'category_ids' => array_map( fn( $c ) => $c->term_id, $cat_terms ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// List tags
	wp_register_ability( 'blog/list-tags', [
		'label'       => 'List Tags',
		'description' => 'Retrieve all post tags with their IDs, names, slugs, and post counts.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'search' => [ 'type' => 'string', 'description' => 'Filter tags by name (optional)' ],
				'hide_empty' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Exclude tags with no posts (default: false)' ],
			],
		],
		'output_schema' => [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'name'  => [ 'type' => 'string' ],
					'slug'  => [ 'type' => 'string' ],
					'count' => [ 'type' => 'integer' ],
				],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$args = [
				'taxonomy'   => 'post_tag',
				'hide_empty' => $input['hide_empty'] ?? false,
				'number'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
			];
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}
			$tags = get_terms( $args );
			if ( is_wp_error( $tags ) ) {
				return new WP_Error( 'fetch_failed', $tags->get_error_message() );
			}
			return array_map( function ( $t ) {
				return [
					'id'    => $t->term_id,
					'name'  => $t->name,
					'slug'  => $t->slug,
					'count' => $t->count,
				];
			}, $tags );
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// List categories
	wp_register_ability( 'blog/list-categories', [
		'label'       => 'List Categories',
		'description' => 'Retrieve all post categories with their IDs, names, slugs, and post counts.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'search' => [ 'type' => 'string', 'description' => 'Filter categories by name (optional)' ],
				'hide_empty' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Exclude categories with no posts (default: false)' ],
			],
		],
		'output_schema' => [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'id'     => [ 'type' => 'integer' ],
					'name'   => [ 'type' => 'string' ],
					'slug'   => [ 'type' => 'string' ],
					'count'  => [ 'type' => 'integer' ],
					'parent' => [ 'type' => 'integer' ],
				],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$args = [
				'taxonomy'   => 'category',
				'hide_empty' => $input['hide_empty'] ?? false,
				'number'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
			];
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}
			$cats = get_terms( $args );
			if ( is_wp_error( $cats ) ) {
				return new WP_Error( 'fetch_failed', $cats->get_error_message() );
			}
			return array_map( function ( $c ) {
				return [
					'id'     => $c->term_id,
					'name'   => $c->name,
					'slug'   => $c->slug,
					'count'  => $c->count,
					'parent' => $c->parent,
				];
			}, $cats );
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Schedule post
	wp_register_ability( 'blog/schedule-post', [
		'label'       => 'Schedule Post',
		'description' => 'Schedule a post to be published automatically at a future date and time.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'   => [ 'type' => 'integer', 'description' => 'Post ID to schedule' ],
				'date' => [ 'type' => 'string', 'description' => 'Publish date in YYYY-MM-DD HH:MM:SS format (site timezone)' ],
			],
			'required' => [ 'id', 'date' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'             => [ 'type' => 'integer' ],
				'title'          => [ 'type' => 'string' ],
				'status'         => [ 'type' => 'string' ],
				'scheduled_date' => [ 'type' => 'string' ],
				'permalink'      => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$post_id = (int) $input['id'];
			$post    = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$date = sanitize_text_field( $input['date'] );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
				return new WP_Error( 'invalid_date', 'Date must be in YYYY-MM-DD HH:MM:SS format.' );
			}

			$now = current_time( 'mysql' );
			if ( $date <= $now ) {
				return new WP_Error( 'past_date', 'Scheduled date must be in the future.' );
			}

			$updated = wp_update_post( [
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
				'edit_date'     => true,
			], true );

			if ( is_wp_error( $updated ) ) {
				return new WP_Error( 'schedule_failed', $updated->get_error_message() );
			}

			return [
				'id'             => $post_id,
				'title'          => get_the_title( $post_id ),
				'status'         => get_post_status( $post_id ),
				'scheduled_date' => $date,
				'permalink'      => get_permalink( $post_id ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Create tag
	wp_register_ability( 'blog/create-tag', [
		'label'       => 'Create Tag',
		'description' => 'Create a new post tag.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Tag name' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Tag slug (optional, auto-generated if omitted)' ],
				'description' => [ 'type' => 'string', 'description' => 'Tag description (optional)' ],
			],
			'required' => [ 'name' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$args = [];
			if ( ! empty( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( ! empty( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );

			$existing = get_term_by( 'name', $input['name'], 'post_tag' );
			if ( $existing ) {
				return new WP_Error( 'tag_exists', "Tag \"{$input['name']}\" already exists with ID {$existing->term_id}." );
			}

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'post_tag', $args );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'create_failed', $result->get_error_message() );
			}

			$term = get_term( $result['term_id'], 'post_tag' );
			return [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Duplicate post
	wp_register_ability( 'blog/duplicate-post', [
		'label'       => 'Duplicate Post',
		'description' => 'Duplicate an existing post as a new draft, preserving content, tags, and categories.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'    => [ 'type' => 'integer', 'description' => 'Post ID to duplicate' ],
				'title' => [ 'type' => 'string', 'description' => 'Title for the duplicate (optional, defaults to original title + " (Copy)")' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'        => [ 'type' => 'integer' ],
				'title'     => [ 'type' => 'string' ],
				'status'    => [ 'type' => 'string' ],
				'permalink' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'publish_posts' );
		},
		'execute_callback' => function ( $input ) {
			$original = get_post( (int) $input['id'] );
			if ( ! $original ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$new_title = ! empty( $input['title'] )
				? sanitize_text_field( $input['title'] )
				: $original->post_title . ' (Copy)';

			$new_id = wp_insert_post( [
				'post_title'   => $new_title,
				'post_content' => $original->post_content,
				'post_excerpt' => $original->post_excerpt,
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
				'post_type'    => 'post',
			], true );

			if ( is_wp_error( $new_id ) ) {
				return new WP_Error( 'duplicate_failed', $new_id->get_error_message() );
			}

			// Copy tags and categories
			$tags = wp_get_post_tags( $original->ID, [ 'fields' => 'ids' ] );
			$cats = wp_get_post_categories( $original->ID );
			if ( ! empty( $tags ) ) wp_set_object_terms( $new_id, $tags, 'post_tag' );
			if ( ! empty( $cats ) ) wp_set_post_categories( $new_id, $cats );

			return [
				'id'        => $new_id,
				'title'     => get_the_title( $new_id ),
				'status'    => get_post_status( $new_id ),
				'permalink' => get_permalink( $new_id ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// List comments
	wp_register_ability( 'blog/list-comments', [
		'label'       => 'List Comments',
		'description' => 'Retrieve comments, optionally filtered by post or status.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_id' => [ 'type' => 'integer', 'description' => 'Filter by post ID (optional)' ],
				'status'  => [
					'type'    => 'string',
					'enum'    => [ 'approve', 'hold', 'spam', 'trash', 'all' ],
					'default' => 'approve',
					'description' => 'Comment status filter (default: approve)',
				],
				'number'  => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100, 'description' => 'Number of comments to return (default: 20)' ],
			],
		],
		'output_schema' => [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'id'      => [ 'type' => 'integer' ],
					'post_id' => [ 'type' => 'integer' ],
					'author'  => [ 'type' => 'string' ],
					'email'   => [ 'type' => 'string' ],
					'date'    => [ 'type' => 'string' ],
					'content' => [ 'type' => 'string' ],
					'status'  => [ 'type' => 'string' ],
				],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$args = [
				'status' => $input['status'] ?? 'approve',
				'number' => $input['number'] ?? 20,
				'type'   => 'comment',
				'orderby'=> 'comment_date',
				'order'  => 'DESC',
			];
			if ( ! empty( $input['post_id'] ) ) {
				$args['post_id'] = (int) $input['post_id'];
			}

			$comments = get_comments( $args );
			return array_map( function ( $c ) {
				return [
					'id'      => (int) $c->comment_ID,
					'post_id' => (int) $c->comment_post_ID,
					'author'  => $c->comment_author,
					'email'   => $c->comment_author_email,
					'date'    => $c->comment_date,
					'content' => wp_strip_all_tags( $c->comment_content ),
					'status'  => $c->comment_approved === '1' ? 'approve' : ( $c->comment_approved === '0' ? 'hold' : $c->comment_approved ),
				];
			}, $comments );
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Create category
	wp_register_ability( 'blog/create-category', [
		'label'       => 'Create Category',
		'description' => 'Create a new post category.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Category name' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Category slug (optional, auto-generated if omitted)' ],
				'description' => [ 'type' => 'string', 'description' => 'Category description (optional)' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'Parent category ID for nested categories (optional)' ],
			],
			'required' => [ 'name' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'parent'      => [ 'type' => 'integer' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$existing = get_term_by( 'name', $input['name'], 'category' );
			if ( $existing ) {
				return new WP_Error( 'category_exists', "Category \"{$input['name']}\" already exists with ID {$existing->term_id}." );
			}

			$args = [];
			if ( ! empty( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( ! empty( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );
			if ( ! empty( $input['parent'] ) )      $args['parent']      = (int) $input['parent'];

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'category', $args );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'create_failed', $result->get_error_message() );
			}

			$term = get_term( $result['term_id'], 'category' );
			return [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => $term->parent,
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Update comment status
	wp_register_ability( 'blog/update-comment', [
		'label'       => 'Update Comment Status',
		'description' => 'Approve, hold, spam, or trash a comment.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'     => [ 'type' => 'integer', 'description' => 'Comment ID' ],
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'approve', 'hold', 'spam', 'trash' ],
					'description' => 'New status for the comment',
				],
			],
			'required' => [ 'id', 'status' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'      => [ 'type' => 'integer' ],
				'status'  => [ 'type' => 'string' ],
				'success' => [ 'type' => 'boolean' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$comment_id = (int) $input['id'];
			if ( ! get_comment( $comment_id ) ) {
				return new WP_Error( 'not_found', 'Comment not found.' );
			}

			$result = wp_set_comment_status( $comment_id, $input['status'] );
			if ( ! $result ) {
				return new WP_Error( 'update_failed', 'Failed to update comment status.' );
			}

			return [
				'id'      => $comment_id,
				'status'  => $input['status'],
				'success' => true,
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Upload media from URL
	wp_register_ability( 'blog/upload-media', [
		'label'       => 'Upload Media',
		'description' => 'Upload a media file to the WordPress Media Library by fetching it from a public URL.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'url'      => [ 'type' => 'string', 'description' => 'Publicly accessible URL of the file to upload' ],
				'title'    => [ 'type' => 'string', 'description' => 'Media title (optional)' ],
				'alt_text' => [ 'type' => 'string', 'description' => 'Alt text for images (optional)' ],
				'post_id'  => [ 'type' => 'integer', 'description' => 'Attach to this post ID (optional)' ],
			],
			'required' => [ 'url' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'       => [ 'type' => 'integer' ],
				'url'      => [ 'type' => 'string' ],
				'filename' => [ 'type' => 'string' ],
				'title'    => [ 'type' => 'string' ],
				'alt_text' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'upload_files' );
		},
		'execute_callback' => function ( $input ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$post_id       = ! empty( $input['post_id'] ) ? (int) $input['post_id'] : 0;
			$attachment_id = media_sideload_image( esc_url_raw( $input['url'] ), $post_id, null, 'id' );

			if ( is_wp_error( $attachment_id ) ) {
				return new WP_Error( 'upload_failed', $attachment_id->get_error_message() );
			}

			if ( ! empty( $input['title'] ) ) {
				wp_update_post( [ 'ID' => $attachment_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
			}

			if ( ! empty( $input['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
			}

			$file = get_attached_file( $attachment_id );
			return [
				'id'       => $attachment_id,
				'url'      => wp_get_attachment_url( $attachment_id ),
				'filename' => $file ? basename( $file ) : '',
				'title'    => get_the_title( $attachment_id ),
				'alt_text' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Set featured image
	wp_register_ability( 'blog/set-featured-image', [
		'label'       => 'Set Featured Image',
		'description' => 'Set the featured image of a post using a media attachment ID.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_id'       => [ 'type' => 'integer', 'description' => 'Post ID' ],
				'attachment_id' => [ 'type' => 'integer', 'description' => 'Media attachment ID to use as featured image' ],
			],
			'required' => [ 'post_id', 'attachment_id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_id'            => [ 'type' => 'integer' ],
				'attachment_id'      => [ 'type' => 'integer' ],
				'featured_image_url' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$post_id       = (int) $input['post_id'];
			$attachment_id = (int) $input['attachment_id'];

			if ( ! get_post( $post_id ) ) {
				return new WP_Error( 'post_not_found', 'Post not found.' );
			}
			if ( ! get_post( $attachment_id ) ) {
				return new WP_Error( 'attachment_not_found', 'Attachment not found.' );
			}

			$result = set_post_thumbnail( $post_id, $attachment_id );
			if ( ! $result ) {
				return new WP_Error( 'set_failed', 'Failed to set featured image.' );
			}

			return [
				'post_id'            => $post_id,
				'attachment_id'      => $attachment_id,
				'featured_image_url' => wp_get_attachment_url( $attachment_id ),
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Update tag
	wp_register_ability( 'blog/update-tag', [
		'label'       => 'Update Tag',
		'description' => 'Edit the name, slug, or description of an existing tag.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer', 'description' => 'Tag ID to update' ],
				'name'        => [ 'type' => 'string', 'description' => 'New tag name (optional)' ],
				'slug'        => [ 'type' => 'string', 'description' => 'New tag slug (optional)' ],
				'description' => [ 'type' => 'string', 'description' => 'New tag description (optional)' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$term = get_term( (int) $input['id'], 'post_tag' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'not_found', 'Tag not found.' );
			}

			$args = [];
			if ( isset( $input['name'] ) )        $args['name']        = sanitize_text_field( $input['name'] );
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );

			$result = wp_update_term( (int) $input['id'], 'post_tag', $args );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'update_failed', $result->get_error_message() );
			}

			$updated = get_term( $result['term_id'], 'post_tag' );
			return [
				'id'          => $updated->term_id,
				'name'        => $updated->name,
				'slug'        => $updated->slug,
				'description' => $updated->description,
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Update category
	wp_register_ability( 'blog/update-category', [
		'label'       => 'Update Category',
		'description' => 'Edit the name, slug, description, or parent of an existing category.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer', 'description' => 'Category ID to update' ],
				'name'        => [ 'type' => 'string', 'description' => 'New category name (optional)' ],
				'slug'        => [ 'type' => 'string', 'description' => 'New category slug (optional)' ],
				'description' => [ 'type' => 'string', 'description' => 'New category description (optional)' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'New parent category ID (optional, 0 to remove parent)' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'parent'      => [ 'type' => 'integer' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$term = get_term( (int) $input['id'], 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'not_found', 'Category not found.' );
			}

			$args = [];
			if ( isset( $input['name'] ) )        $args['name']        = sanitize_text_field( $input['name'] );
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['parent'] ) )      $args['parent']      = (int) $input['parent'];

			$result = wp_update_term( (int) $input['id'], 'category', $args );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'update_failed', $result->get_error_message() );
			}

			$updated = get_term( $result['term_id'], 'category' );
			return [
				'id'          => $updated->term_id,
				'name'        => $updated->name,
				'slug'        => $updated->slug,
				'description' => $updated->description,
				'parent'      => $updated->parent,
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Delete tag
	wp_register_ability( 'blog/delete-tag', [
		'label'       => 'Delete Tag',
		'description' => 'Permanently delete a tag. Posts using this tag will have it removed.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id' => [ 'type' => 'integer', 'description' => 'Tag ID to delete' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$term_id = (int) $input['id'];
			$term    = get_term( $term_id, 'post_tag' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'not_found', 'Tag not found.' );
			}

			$result = wp_delete_term( $term_id, 'post_tag' );
			if ( is_wp_error( $result ) || ! $result ) {
				return new WP_Error( 'delete_failed', 'Failed to delete tag.' );
			}

			return [
				'success' => true,
				'message' => "Tag \"{$term->name}\" (ID {$term_id}) permanently deleted.",
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Delete category
	wp_register_ability( 'blog/delete-category', [
		'label'       => 'Delete Category',
		'description' => 'Permanently delete a category. Posts will be reassigned to the default category.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id' => [ 'type' => 'integer', 'description' => 'Category ID to delete' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'execute_callback' => function ( $input ) {
			$term_id = (int) $input['id'];
			$term    = get_term( $term_id, 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'not_found', 'Category not found.' );
			}

			$default_cat = (int) get_option( 'default_category' );
			if ( $term_id === $default_cat ) {
				return new WP_Error( 'default_category', 'Cannot delete the default category.' );
			}

			$result = wp_delete_term( $term_id, 'category' );
			if ( is_wp_error( $result ) || ! $result ) {
				return new WP_Error( 'delete_failed', 'Failed to delete category.' );
			}

			return [
				'success' => true,
				'message' => "Category \"{$term->name}\" (ID {$term_id}) permanently deleted. Posts reassigned to default category.",
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Reply to comment
	wp_register_ability( 'blog/reply-comment', [
		'label'       => 'Reply to Comment',
		'description' => 'Post a reply to an existing comment as the current user.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'comment_id' => [ 'type' => 'integer', 'description' => 'Parent comment ID to reply to' ],
				'content'    => [ 'type' => 'string', 'description' => 'Reply content' ],
			],
			'required' => [ 'comment_id', 'content' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'      => [ 'type' => 'integer' ],
				'content' => [ 'type' => 'string' ],
				'date'    => [ 'type' => 'string' ],
				'status'  => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$parent = get_comment( (int) $input['comment_id'] );
			if ( ! $parent ) {
				return new WP_Error( 'not_found', 'Parent comment not found.' );
			}

			$user    = wp_get_current_user();
			$new_id  = wp_insert_comment( [
				'comment_post_ID'      => $parent->comment_post_ID,
				'comment_parent'       => $parent->comment_ID,
				'comment_content'      => wp_kses_post( $input['content'] ),
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => $user->user_url,
				'user_id'              => $user->ID,
				'comment_approved'     => 1,
			] );

			if ( ! $new_id ) {
				return new WP_Error( 'reply_failed', 'Failed to post reply.' );
			}

			$comment = get_comment( $new_id );
			return [
				'id'      => (int) $comment->comment_ID,
				'content' => wp_strip_all_tags( $comment->comment_content ),
				'date'    => $comment->comment_date,
				'status'  => 'approve',
			];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

	// Delete / trash post
	wp_register_ability( 'blog/delete-post', [
		'label'       => 'Delete Blog Post',
		'description' => 'Move a post to trash (or permanently delete if already trashed).',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'        => [ 'type' => 'integer', 'description' => 'Post ID to delete' ],
				'force'     => [ 'type' => 'boolean', 'default' => false, 'description' => 'Permanently delete instead of trash' ],
			],
			'required' => [ 'id' ],
		],
		'output_schema' => [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () {
			return current_user_can( 'delete_posts' );
		},
		'execute_callback' => function ( $input ) {
			$result = wp_delete_post( (int) $input['id'], (bool) ( $input['force'] ?? false ) );
			if ( ! $result ) {
				return [ 'success' => false, 'message' => 'Failed to delete post or post not found.' ];
			}
			$action = ( $input['force'] ?? false ) ? 'permanently deleted' : 'moved to trash';
			return [ 'success' => true, 'message' => "Post {$input['id']} {$action}." ];
		},
		'meta' => [ 'mcp' => [ 'public' => true ] ],
	] );

} );
