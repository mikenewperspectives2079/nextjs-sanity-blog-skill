<?php
/**
 * Related posts (§11): "More from <Category>", three newest same-category
 * posts in the same language, topped up with the latest posts.
 *
 * Block themes: cards are rendered with core Query Loop blocks, so they use
 * the theme's own card pattern (config `card_pattern`). Classic themes: the
 * theme's template-parts/blog-card.php is used when it exists.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * The post's primary category: the SEO plugin's "primary category" when it is
 * still assigned, otherwise the first (and per §4, only) category.
 *
 * @param int $post_id Post ID.
 * @return int Term ID or 0.
 */
function primary_category_id( $post_id ) {
	$assigned = array_map( 'intval', (array) wp_get_post_categories( $post_id ) );
	foreach ( array( '_yoast_wpseo_primary_category', 'rank_math_primary_category' ) as $meta_key ) {
		$term_id = (int) get_post_meta( $post_id, $meta_key, true );
		if ( $term_id && in_array( $term_id, $assigned, true ) ) {
			return $term_id;
		}
	}
	return $assigned ? $assigned[0] : 0;
}

/**
 * IDs of related posts. Uses WP_Query (not get_posts) so multilingual
 * plugins keep results in the current language.
 *
 * @param int      $post_id Current post.
 * @param int|null $count   How many (default from config).
 * @return int[]
 */
function related_post_ids( $post_id, $count = null ) {
	$post_id = (int) $post_id;
	$count   = null === $count ? (int) config( 'related_count' ) : (int) $count;
	if ( $count < 1 ) {
		return array();
	}

	$base = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'fields'              => 'ids',
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	$ids      = array();
	$category = primary_category_id( $post_id );
	if ( $category ) {
		$same = new \WP_Query(
			$base + array(
				'cat'            => $category,
				'post__not_in'   => array( $post_id ),
				'posts_per_page' => $count,
			)
		);
		$ids  = array_map( 'intval', $same->posts );
	}

	if ( count( $ids ) < $count ) {
		$latest = new \WP_Query(
			$base + array(
				'post__not_in'   => array_merge( array( $post_id ), $ids ),
				'posts_per_page' => $count - count( $ids ),
			)
		);
		$ids    = array_merge( $ids, array_map( 'intval', $latest->posts ) );
	}

	return $ids;
}

/**
 * Makes a Query Loop fetch exactly the given posts when its query attribute
 * carries `blogKitIds` (used by the related-posts renderer below).
 */
add_filter(
	'query_loop_block_query_vars',
	static function ( $query, $block ) {
		$ids = $block->context['query']['blogKitIds'] ?? null;
		if ( is_array( $ids ) && $ids ) {
			$query['post__in']            = array_map( 'intval', $ids );
			$query['orderby']             = 'post__in';
			$query['posts_per_page']      = count( $ids );
			$query['ignore_sticky_posts'] = true;
			unset( $query['offset'], $query['post__not_in'] );
		}
		return $query;
	},
	10,
	2
);

/**
 * Card titles inside the related aside are H3 (the aside heading is H2),
 * even when the shared card pattern uses H2 on listing pages.
 *
 * @param bool|null $set Set the flag (true/false) or read it (null).
 * @return bool
 */
function rendering_related( $set = null ) {
	static $active = false;
	if ( null !== $set ) {
		$active = (bool) $set;
	}
	return $active;
}

add_filter(
	'render_block_data',
	static function ( $parsed ) {
		if ( rendering_related() && 'core/post-title' === ( $parsed['blockName'] ?? '' ) ) {
			$parsed['attrs']['level'] = 3;
		}
		return $parsed;
	}
);

/**
 * Card markup for block themes: the configured pattern, or a sensible default
 * made of core blocks (image is not a link, so each card has one link).
 *
 * @return string Block markup.
 */
function card_block_markup() {
	$pattern = (string) config( 'card_pattern' );
	if ( '' !== $pattern ) {
		return sprintf( '<!-- wp:pattern {"slug":%s} /-->', wp_json_encode( $pattern ) );
	}
	return '<!-- wp:post-featured-image {"aspectRatio":"16/9","sizeSlug":"blog-card"} /-->'
		. '<!-- wp:post-terms {"term":"category"} /-->'
		. '<!-- wp:post-title {"level":3,"isLink":true} /-->'
		. '<!-- wp:post-excerpt {"excerptLength":24} /-->'
		. '<!-- wp:post-date /-->';
}

/**
 * Renders the cards for the given post IDs.
 *
 * @param int[] $ids Post IDs.
 * @return string HTML.
 */
function cards_html( array $ids ) {
	if ( wp_is_block_theme() ) {
		$query = array(
			'queryId'   => 9001,
			'query'     => array(
				'perPage'    => count( $ids ),
				'postType'   => 'post',
				'inherit'    => false,
				'blogKitIds' => array_values( $ids ),
			),
			'className' => 'blog-kit-related__query',
		);
		$markup = sprintf(
			'<!-- wp:query %1$s --><div class="wp-block-query blog-kit-related__query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->%2$s<!-- /wp:post-template --></div><!-- /wp:query -->',
			wp_json_encode( $query ),
			card_block_markup()
		);

		rendering_related( true );
		$html = do_blocks( $markup );
		rendering_related( false );
		return $html;
	}

	$items = '';
	$theme = locate_template( 'template-parts/blog-card.php' );
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			continue;
		}
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- restored below.
		setup_postdata( $post );

		ob_start();
		if ( $theme ) {
			get_template_part(
				'template-parts/blog-card',
				null,
				array(
					'heading_level' => 3,
					'context'       => 'related',
				)
			);
		} else {
			$category = get_term( primary_category_id( $id ), 'category' );
			printf(
				'<article class="blog-kit-card">%1$s%2$s<h3 class="blog-kit-card__title"><a href="%3$s">%4$s</a></h3><p class="blog-kit-card__excerpt">%5$s</p><time datetime="%6$s">%7$s</time></article>',
				get_the_post_thumbnail( $id, 'blog-card' ), // phpcs:ignore WordPress.Security.EscapeOutput
				( $category && ! is_wp_error( $category ) ) ? '<p class="blog-kit-card__category">' . esc_html( $category->name ) . '</p>' : '',
				esc_url( get_permalink( $id ) ),
				esc_html( get_the_title( $id ) ),
				esc_html( get_the_excerpt( $id ) ),
				esc_attr( get_the_date( 'c', $id ) ),
				esc_html( get_the_date( '', $id ) )
			);
		}
		$items .= '<li>' . ob_get_clean() . '</li>';
	}
	wp_reset_postdata();

	return '' === $items ? '' : '<ul class="blog-kit-related__list">' . $items . '</ul>';
}

/**
 * Renders the related-posts aside, or nothing when there are no other posts.
 *
 * @param array          $attributes Unused.
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_related_posts( $attributes = array(), $block = null ) {
	$post_id = context_post_id( $block );
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return '';
	}

	$ids = related_post_ids( $post_id );
	if ( ! $ids ) {
		return '';
	}

	$cards = cards_html( $ids );
	if ( '' === trim( wp_strip_all_tags( $cards, true ) ) && false === strpos( $cards, '<img' ) ) {
		return '';
	}

	$category = get_term( primary_category_id( $post_id ), 'category' );
	$heading  = ( $category && ! is_wp_error( $category ) )
		? sprintf( label( 'more_from' ), $category->name )
		: label( 'more_posts' );

	return sprintf(
		'<aside %1$s><h2 class="blog-kit-related__title" id="blog-kit-related-title">%2$s</h2>%3$s</aside>',
		wrapper_attributes(
			$block,
			array(
				'class'           => 'blog-kit-related',
				'aria-labelledby' => 'blog-kit-related-title',
			)
		),
		esc_html( $heading ),
		$cards
	);
}

add_action(
	'init',
	static function () {
		register_dynamic_block( 'related-posts', __( 'Related posts', 'blog-kit' ), __NAMESPACE__ . '\\render_related_posts' );
	}
);
