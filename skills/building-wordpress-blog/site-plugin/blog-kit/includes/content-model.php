<?php
/**
 * Content model (§3, §4): allowed blocks, heading levels, tags, comments,
 * and the publish guard.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/*
 * §3.2 — Only the configured blocks can be inserted into posts.
 * Existing content keeps rendering; this limits the inserter only.
 */
add_filter(
	'allowed_block_types_all',
	static function ( $allowed, $context ) {
		$list = config( 'allowed_blocks' );
		if ( empty( $list ) || empty( $context->post ) || 'post' !== $context->post->post_type ) {
			return $allowed;
		}
		return array_values( (array) $list );
	},
	10,
	2
);

/*
 * §3.2 — No H1 in the body (the title is the H1). Sets the default level
 * options of every Heading block, so it also applies to pages.
 */
add_filter(
	'register_block_type_args',
	static function ( $args, $name ) {
		$levels = config( 'heading_levels' );
		if ( 'core/heading' === $name && ! empty( $levels ) && isset( $args['attributes']['levelOptions'] ) ) {
			$args['attributes']['levelOptions']['default'] = array_map( 'intval', (array) $levels );
		}
		return $args;
	},
	10,
	2
);

/*
 * §4 — Tags off: detach the taxonomy from posts (hides the UI and REST
 * field). Tag archives are turned into 404s in archives.php.
 */
add_action(
	'init',
	static function () {
		if ( ! config( 'tags' ) ) {
			unregister_taxonomy_for_object_type( 'post_tag', 'post' );
		}
	},
	20
);

/*
 * §0.K — Comments off for posts: close them, hide any old ones, and stop
 * advertising comment feeds.
 */
add_action(
	'init',
	static function () {
		if ( config( 'comments' ) ) {
			return;
		}
		remove_post_type_support( 'post', 'comments' );
		remove_post_type_support( 'post', 'trackbacks' );

		$closed_for_posts = static function ( $open, $post_id ) {
			return 'post' === get_post_type( $post_id ) ? false : $open;
		};
		add_filter( 'comments_open', $closed_for_posts, 20, 2 );
		add_filter( 'pings_open', $closed_for_posts, 20, 2 );
		add_filter(
			'comments_array',
			static function ( $comments, $post_id ) {
				return 'post' === get_post_type( $post_id ) ? array() : $comments;
			},
			20,
			2
		);
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
	},
	100
);

/**
 * §3.5 — Publish guard. Blocks the move to "publish" or "future" through the
 * REST API (the block editor and AI agents both save through it) until the
 * post has a title, exactly one real category, and a featured image with
 * alt text. Already-published posts can still be updated, and autosaves are
 * never blocked.
 *
 * @param \stdClass        $prepared Post about to be saved.
 * @param \WP_REST_Request $request  Request.
 * @return \stdClass|\WP_Error
 */
function publish_guard( $prepared, $request ) {
	$rules = (array) config( 'require_on_publish' );
	if ( empty( $rules ) || false !== strpos( $request->get_route(), '/autosaves' ) ) {
		return $prepared;
	}

	$post_id    = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
	$new_status = $prepared->post_status ?? null;
	$old_status = $post_id ? get_post_status( $post_id ) : 'new';

	if ( ! in_array( $new_status, array( 'publish', 'future' ), true ) || in_array( $old_status, array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}

	$problems = array();

	if ( in_array( 'title', $rules, true ) ) {
		$title = $prepared->post_title ?? ( $post_id ? get_post_field( 'post_title', $post_id ) : '' );
		if ( '' === trim( (string) $title ) ) {
			$problems[] = __( 'add a title', 'blog-kit' );
		}
	}

	if ( in_array( 'single_category', $rules, true ) ) {
		$categories = $request->has_param( 'categories' )
			? array_filter( array_map( 'intval', (array) $request['categories'] ) )
			: ( $post_id ? wp_get_post_categories( $post_id ) : array() );
		$term       = 1 === count( $categories ) ? get_term( (int) reset( $categories ), 'category' ) : null;

		if ( ! $term || is_wp_error( $term ) || is_placeholder_category( $term ) ) {
			$problems[] = __( 'choose exactly one category (not “Uncategorized”)', 'blog-kit' );
		}
	}

	$thumbnail_id = $request->has_param( 'featured_media' )
		? (int) $request['featured_media']
		: ( $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0 );

	if ( in_array( 'featured_image', $rules, true ) && ! $thumbnail_id ) {
		$problems[] = __( 'set a featured image', 'blog-kit' );
	}

	if ( in_array( 'featured_image_alt', $rules, true ) && $thumbnail_id ) {
		$alt = (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
		if ( '' === trim( $alt ) ) {
			$problems[] = __( 'add alt text to the featured image (describe what the image shows)', 'blog-kit' );
		}
	}

	if ( ! $problems ) {
		return $prepared;
	}

	return new \WP_Error(
		'blog_kit_publish_blocked',
		/* translators: %s: list of missing items. */
		sprintf( __( 'Not published yet. Please %s.', 'blog-kit' ), implode( '; ', $problems ) ),
		array( 'status' => 400 )
	);
}
add_filter( 'rest_pre_insert_post', __NAMESPACE__ . '\\publish_guard', 10, 2 );
