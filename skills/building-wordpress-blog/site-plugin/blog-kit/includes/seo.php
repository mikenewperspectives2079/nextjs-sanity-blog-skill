<?php
/**
 * SEO-plugin integration (§7). The SEO plugin owns titles, descriptions,
 * canonicals, Open Graph, schema and sitemaps; this file only fills the gaps
 * the spec needs, for Yoast SEO and Rank Math (and core when neither runs):
 *
 * - og:image uses the 1200×630 `blog-og` crop when it exists.
 * - og:image:alt / twitter:image:alt from the featured image's alt text,
 *   where the plugin doesn't print them.
 * - Category archives with fewer than N posts get noindex,follow and leave
 *   the sitemap (config `category_noindex_below`, 0 = off).
 *
 * Settings that need no code (Article type, Organization logo, breadcrumbs,
 * archive toggles) are listed in §7 of the spec and done in the plugin UI.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the OG crop first in an SEO plugin's list of candidate image sizes.
 * The plugin falls back to its usual sizes when an image lacks the crop.
 *
 * @param array $sizes Image size names.
 * @return array
 */
function prefer_og_size( $sizes ) {
	$size = (string) config( 'og_image_size' );
	if ( '' === $size ) {
		return $sizes;
	}
	return array_values( array_unique( array_merge( array( $size ), (array) $sizes ) ) );
}

// Yoast caches the chosen image: after activating, run `wp yoast index --reindex`.
add_filter( 'wpseo_image_sizes', __NAMESPACE__ . '\\prefer_og_size' );
add_filter( 'rank_math/opengraph/image_sizes', __NAMESPACE__ . '\\prefer_og_size' );

/*
 * Alt text for the share image. Yoast prints neither og:image:alt nor
 * twitter:image:alt; Rank Math prints og:image:alt only. Printed right after
 * the plugin's own tags (priority 1), and only when the share image is the
 * featured image (no custom social image set on the post).
 */
add_action(
	'wp_head',
	static function () {
		$yoast     = defined( 'WPSEO_VERSION' );
		$rank_math = defined( 'RANK_MATH_VERSION' );
		if ( ( ! $yoast && ! $rank_math ) || ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		$custom  = $yoast
			? array( '_yoast_wpseo_opengraph-image', '_yoast_wpseo_opengraph-image-id', '_yoast_wpseo_twitter-image', '_yoast_wpseo_twitter-image-id' )
			: array( 'rank_math_facebook_image', 'rank_math_facebook_image_id', 'rank_math_twitter_image', 'rank_math_twitter_image_id' );
		foreach ( $custom as $meta_key ) {
			if ( get_post_meta( $post_id, $meta_key, true ) ) {
				return;
			}
		}

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
		$alt          = $thumbnail_id ? trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) : '';
		if ( '' === $alt ) {
			return;
		}

		if ( $yoast ) {
			printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $alt ) );
		}
		printf( '<meta name="twitter:image:alt" content="%s" />' . "\n", esc_attr( $alt ) );
	},
	2
);

/**
 * Whether a term is a category below the configured post-count threshold.
 *
 * @param mixed $term Term object (or anything).
 * @return bool
 */
function is_thin_category( $term ) {
	$min = (int) config( 'category_noindex_below' );
	return $min > 0 && $term instanceof \WP_Term && 'category' === $term->taxonomy && (int) $term->count < $min;
}

/**
 * IDs of thin categories (for sitemap exclusion).
 *
 * @return int[]
 */
function thin_category_ids() {
	if ( (int) config( 'category_noindex_below' ) < 1 ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$ids = array();
	foreach ( $terms as $term ) {
		if ( is_thin_category( $term ) ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return $ids;
}

// Yoast: robots meta + sitemap.
add_filter(
	'wpseo_robots',
	static function ( $robots ) {
		return ( is_category() && is_thin_category( get_queried_object() ) ) ? 'noindex, follow' : $robots;
	}
);
add_filter(
	'wpseo_exclude_from_sitemap_by_term_ids',
	static function ( $ids ) {
		return array_merge( (array) $ids, thin_category_ids() );
	}
);

// Rank Math: robots meta + sitemap.
add_filter(
	'rank_math/frontend/robots',
	static function ( $robots ) {
		if ( is_category() && is_thin_category( get_queried_object() ) ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	}
);
add_filter(
	'rank_math/sitemap/entry',
	static function ( $url, $type, $object ) {
		return ( 'term' === $type && is_thin_category( $object ) ) ? array() : $url;
	},
	10,
	3
);

// Core (no SEO plugin): robots meta + core sitemap.
add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( is_category() && is_thin_category( get_queried_object() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);
add_filter(
	'wp_sitemaps_taxonomies_query_args',
	static function ( $args, $taxonomy ) {
		if ( 'category' === $taxonomy ) {
			$args['exclude'] = array_merge( (array) ( $args['exclude'] ?? array() ), thin_category_ids() );
		}
		return $args;
	},
	10,
	2
);
