<?php
/**
 * Listing pages (§4, §8): the H1 of the blog index and category pages, and
 * the category navigation ("filter pills"). The nav is plain links to the
 * category pages, so filtering is crawlable, works without JavaScript and
 * keeps the back button working.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Categories for the nav: non-empty, never "Uncategorized", ordered by the
 * configured slug list (§1.C3) and then by name.
 *
 * @return \WP_Term[]
 */
function nav_categories() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$terms = array_values(
		array_filter(
			$terms,
			static function ( $term ) {
				return ! is_placeholder_category( $term );
			}
		)
	);

	$order = array_flip( array_values( (array) config( 'category_order' ) ) );
	if ( $order ) {
		usort(
			$terms,
			static function ( $a, $b ) use ( $order ) {
				$pa = $order[ $a->slug ] ?? PHP_INT_MAX;
				$pb = $order[ $b->slug ] ?? PHP_INT_MAX;
				return $pa === $pb ? strcasecmp( $a->name, $b->name ) : $pa <=> $pb;
			}
		);
	}

	return $terms;
}

/**
 * Renders the category nav. The current view gets aria-current="page".
 *
 * @param array          $attributes Unused.
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_category_nav( $attributes = array(), $block = null ) {
	$terms = nav_categories();
	if ( ! $terms ) {
		return '';
	}

	$current = is_category() ? (int) get_queried_object_id() : 0;
	$items   = sprintf(
		'<li><a href="%1$s"%2$s>%3$s</a></li>',
		esc_url( blog_index_url() ),
		is_home() ? ' aria-current="page"' : '',
		esc_html( label( 'all_posts' ) )
	);

	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$items .= sprintf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( $link ),
			$current === (int) $term->term_id ? ' aria-current="page"' : '',
			esc_html( $term->name )
		);
	}

	return sprintf(
		'<nav %1$s><ul class="blog-kit-categories__list">%2$s</ul></nav>',
		wrapper_attributes(
			$block,
			array(
				'class'      => 'blog-kit-categories',
				'aria-label' => label( 'categories' ),
			)
		),
		$items
	);
}

/**
 * Renders the listing H1: the posts page's own title on the blog index (in
 * the current language), the bare category name on category pages (no
 * "Category:" prefix). Core's Query Title block prints nothing on the posts
 * page, which would leave the blog index without an H1.
 *
 * @param array          $attributes Optional `level` (1–6, default 1).
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_archive_title( $attributes = array(), $block = null ) {
	if ( is_home() && ! is_front_page() ) {
		$page_id = (int) get_option( 'page_for_posts' );
		$title   = $page_id ? get_the_title( $page_id ) : '';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$title = single_term_title( '', false );
	} elseif ( is_archive() ) {
		$title = wp_strip_all_tags( get_the_archive_title() );
	} else {
		return '';
	}

	if ( '' === trim( (string) $title ) ) {
		return '';
	}

	$level = max( 1, min( 6, (int) ( $attributes['level'] ?? 1 ) ) );
	return sprintf(
		'<h%1$d %2$s>%3$s</h%1$d>',
		$level,
		wrapper_attributes( $block, array( 'class' => 'blog-kit-archive-title' ) ),
		esc_html( $title )
	);
}

add_action(
	'init',
	static function () {
		register_dynamic_block( 'category-nav', __( 'Blog category navigation', 'blog-kit' ), __NAMESPACE__ . '\\render_category_nav' );
		register_dynamic_block(
			'archive-title',
			__( 'Blog index / category title', 'blog-kit' ),
			__NAMESPACE__ . '\\render_archive_title',
			array(
				'attributes' => array(
					'level' => array(
						'type'    => 'number',
						'default' => 1,
					),
				),
			)
		);
	}
);
