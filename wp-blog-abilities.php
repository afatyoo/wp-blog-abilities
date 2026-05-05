<?php
/**
 * WordPress Blog Abilities for MCP
 *
 * @wordpress-plugin
 * Plugin Name: Blog Abilities for MCP
 * Description: Registers blog post abilities (create, update, list, delete) for the MCP Adapter.
 * Version: 1.0.0
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
		'description' => 'Update title, content, or status of an existing post.',
		'category'    => 'content',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'id'      => [ 'type' => 'integer', 'description' => 'Post ID to update' ],
				'title'   => [ 'type' => 'string', 'description' => 'New title (optional)' ],
				'content' => [ 'type' => 'string', 'description' => 'New content (optional)' ],
				'status'  => [
					'type' => 'string',
					'enum' => [ 'publish', 'draft', 'pending', 'trash' ],
					'description' => 'New status (optional)',
				],
				'excerpt' => [ 'type' => 'string', 'description' => 'New excerpt (optional)' ],
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
			return current_user_can( 'edit_posts' );
		},
		'execute_callback' => function ( $input ) {
			$post = get_post( (int) $input['id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$args = [ 'ID' => (int) $input['id'] ];
			if ( isset( $input['title'] ) )   $args['post_title']   = sanitize_text_field( $input['title'] );
			if ( isset( $input['content'] ) ) $args['post_content'] = wp_kses_post( $input['content'] );
			if ( isset( $input['status'] ) )  $args['post_status']  = $input['status'];
			if ( isset( $input['excerpt'] ) ) $args['post_excerpt'] = sanitize_text_field( $input['excerpt'] );

			$updated = wp_update_post( $args, true );
			if ( is_wp_error( $updated ) ) {
				return new WP_Error( 'update_failed', $updated->get_error_message() );
			}

			return [
				'id'        => $updated,
				'title'     => get_the_title( $updated ),
				'status'    => get_post_status( $updated ),
				'permalink' => get_permalink( $updated ),
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
