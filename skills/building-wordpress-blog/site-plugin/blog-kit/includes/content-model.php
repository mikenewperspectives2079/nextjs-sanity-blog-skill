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
 * Legacy posts keep their old tag relationships, and many themes still print
 * them (Post Terms block for tags, the_tags()). Those links would point at
 * 404s, so tags read as empty on posts while tags are off.
 */
add_filter(
	'get_the_terms',
	static function ( $terms, $post_id, $taxonomy ) {
		if ( 'post_tag' === $taxonomy && ! config( 'tags' ) && 'post' === get_post_type( $post_id ) ) {
			return false;
		}
		return $terms;
	},
	10,
	3
);

/*
 * Author archives off (§1.C1): author names render as plain text, not as
 * links to archives that 404. Covers the Post Author Name / Post Author
 * blocks and classic the_author_posts_link().
 */
$blog_kit_unlink_author = static function ( $html, $block, $instance ) {
	if ( config( 'author_archives' ) ) {
		return $html;
	}
	$post_id   = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$author_id = (int) get_post_field( 'post_author', $post_id );
	if ( ! $author_id ) {
		return $html;
	}
	// Only the link to the author archive; links in a bio stay.
	$url = get_author_posts_url( $author_id );
	$alt = array_unique( array( preg_quote( $url, '#' ), preg_quote( esc_url( $url ), '#' ) ) );
	return (string) preg_replace( '#<a\b[^>]*\bhref=(["\'])(?:' . implode( '|', $alt ) . ')\1[^>]*>(.*?)</a>#is', '$2', (string) $html );
};
add_filter( 'render_block_core/post-author-name', $blog_kit_unlink_author, 10, 3 );
add_filter( 'render_block_core/post-author', $blog_kit_unlink_author, 10, 3 );
add_filter(
	'the_author_posts_link',
	static function ( $link ) {
		return config( 'author_archives' ) ? $link : esc_html( get_the_author() );
	}
);
unset( $blog_kit_unlink_author );

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

		// Block themes: the Comments block queries comments itself, so drop it on posts.
		add_filter(
			'render_block_core/comments',
			static function ( $html, $block, $instance ) {
				$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
				return 'post' === get_post_type( $post_id ) ? '' : $html;
			},
			10,
			3
		);
		// Classic themes: an empty comments template on posts.
		add_filter(
			'comments_template',
			static function ( $template ) {
				return is_singular( 'post' ) ? __DIR__ . '/no-comments.php' : $template;
			}
		);
	},
	100
);

/*
 * §7.10 — Setting a featured image over the REST API saves the whole post, so
 * WordPress bumps its modified date. Backfilling hero images on old posts
 * would then show "Updated <today>" everywhere and refresh every sitemap
 * lastmod: a fake freshness signal. When a REST update of a published post
 * changes nothing but the featured image, keep the modified date.
 */
final class Image_Only_Update {

	/** @var int Post being updated with only a featured image change, or 0. */
	private static $post_id = 0;

	/**
	 * Notes image-only updates of published posts.
	 *
	 * @param \stdClass        $prepared Post about to be saved.
	 * @param \WP_REST_Request $request  Request.
	 * @return \stdClass
	 */
	public static function detect( $prepared, $request ) {
		self::$post_id = 0;
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$post_id = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
		if ( ! config( 'keep_modified_on_image' ) || ! $post_id || 'publish' !== get_post_status( $post_id ) || ! $request->has_param( 'featured_media' ) ) {
			return $prepared;
		}
		$sent  = array_keys( array_merge( (array) $request->get_query_params(), (array) $request->get_body_params(), (array) $request->get_json_params() ) );
		$extra = array_diff( $sent, array( 'featured_media', 'id', 'context', '_fields', '_locale', '_method', 'rest_route' ) );
		if ( ! $extra ) {
			self::$post_id = $post_id;
		}
		return $prepared;
	}

	/**
	 * Restores the stored modified date for the noted post.
	 *
	 * @param array $data    Post data about to be written.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public static function keep_modified( $data, $postarr ) {
		if ( self::$post_id && isset( $postarr['ID'] ) && (int) $postarr['ID'] === self::$post_id ) {
			$old = get_post( self::$post_id );
			if ( $old ) {
				$data['post_modified']     = $old->post_modified;
				$data['post_modified_gmt'] = $old->post_modified_gmt;
			}
			self::$post_id = 0;
		}
		return $data;
	}
}
add_filter( 'rest_pre_insert_post', array( Image_Only_Update::class, 'detect' ), 20, 2 );
add_filter( 'wp_insert_post_data', array( Image_Only_Update::class, 'keep_modified' ), 99, 2 );

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
