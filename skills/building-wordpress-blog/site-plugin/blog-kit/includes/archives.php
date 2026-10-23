<?php
/**
 * Archive hygiene (§4, §5.3, §7.11): disabled archives return real 404s,
 * WordPress stops "guessing" redirects from deleted posts to similar slugs,
 * and old post/category URLs follow a permalink-structure change.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request is an archive or feed this site has switched off.
 *
 * @return bool
 */
function is_disabled_archive() {
	return ( is_tag() && ! config( 'tags' ) )
		|| ( is_date() && ! config( 'date_archives' ) )
		|| ( is_author() && ! config( 'author_archives' ) )
		|| ( is_comment_feed() && is_singular( 'post' ) && ! config( 'comments' ) );
}

/*
 * Tag, date and author archives (and their feeds), and posts' comment feeds,
 * answer 404 when switched off. A 404 is honest; redirecting them to the
 * homepage is treated by Google as a soft 404 anyway. Runs on `wp` at
 * priority 1, before Yoast SEO and Rank Math redirect "disabled" archives to
 * the homepage (both use `wp` at 10).
 */
add_action(
	'wp',
	static function () {
		if ( ! is_disabled_archive() ) {
			return;
		}
		global $wp_query;
		$was_feed = is_feed();
		$wp_query->set_404();
		$wp_query->is_feed = false;
		// No canonical "fix-ups" for a switched-off URL: core's 404 guess would
		// send a post's comment feed to …/feed/feed/ (301, then 404 again).
		add_filter( 'redirect_canonical', '__return_false' );
		status_header( 404 );
		nocache_headers();
		if ( $was_feed && ! headers_sent() ) {
			// WordPress already announced an RSS content type for the feed.
			header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ), true );
		}
	},
	1
);

/*
 * §7.11 — When a post is deleted, WordPress may 301 its URL to any post whose
 * slug merely starts with the same text ("seo-tips" → "seo-tips-2024"). Strict
 * mode keeps only exact-slug matches, so deleted posts return a real 404.
 */
add_filter(
	'strict_redirect_guess_404_permalink',
	static function ( $strict ) {
		return config( 'strict_404_guessing' ) ? true : $strict;
	}
);

/**
 * §5.3 — Old URLs after the permalink structure gained a front such as
 * /blog/ (e.g. /%postname%/ → /blog/%postname%/). WordPress doesn't redirect
 * them: they 404. On a 404 only, this sends
 *   /<slug>/                 → the published post with exactly that slug
 *   /category/<…>/<slug>/    → that category's page (page numbers kept)
 * with or without a language prefix. Pages, files, feeds and anything that
 * already resolves are never touched. Explicit redirects (server rules, a
 * redirect manager, core's own redirects) run earlier and win: this runs on
 * `template_redirect` at 20, after core (10) and Rank Math (`wp` or 11).
 */
function redirect_old_url() {
	if ( ! config( 'redirect_old_urls' ) || ! is_404() ) {
		return;
	}

	global $wp_rewrite, $wpdb;
	if ( '' === trim( (string) $wp_rewrite->front, '/' ) ) {
		return; // No front in the structure: post and category URLs never moved.
	}

	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched against slugs below.
	$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}

	$segments = array_values( array_filter( explode( '/', rawurldecode( $path ) ), 'strlen' ) );
	$language = default_language();
	if ( $segments && in_array( $segments[0], language_slugs(), true ) ) {
		$language = (string) array_shift( $segments );
	}

	$paged = 0;
	$count = count( $segments );
	if ( $count >= 3 && $wp_rewrite->pagination_base === $segments[ $count - 2 ] && ctype_digit( $segments[ $count - 1 ] ) ) {
		$paged    = (int) $segments[ $count - 1 ];
		$segments = array_slice( $segments, 0, -2 );
	}

	$target = '';
	if ( 1 === count( $segments ) && ! $paged ) {
		// Exact slug, any language (the query bypasses language filters); when
		// languages share a slug (WPML), the one in the URL's language wins.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- 404s only, indexed column, one query.
		$ids     = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish' ORDER BY ID LIMIT 20", sanitize_title( $segments[0] ) ) ) );
		$post_id = $ids ? $ids[0] : 0;
		if ( count( $ids ) > 1 && '' !== $language ) {
			foreach ( $ids as $id ) {
				if ( post_language( $id ) === $language ) {
					$post_id = $id;
					break;
				}
			}
		}
		$target = $post_id ? (string) get_permalink( $post_id ) : '';
	} elseif ( count( $segments ) >= 2 && 'category' === $segments[0] && '' === (string) get_option( 'category_base' ) ) {
		$term = get_term_by( 'slug', sanitize_title( (string) end( $segments ) ), 'category' );
		$link = $term ? get_term_link( $term ) : '';
		$target = is_string( $link ) ? $link : '';
	}

	if ( '' === $target ) {
		return;
	}
	if ( $paged > 1 ) {
		$target = trailingslashit( $target ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
	}
	$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passed through; wp_safe_redirect sanitizes.
	if ( '' !== $query ) {
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query; // Keep campaign tags (utm_*).
	}
	if ( untrailingslashit( $target ) !== untrailingslashit( home_url( '/' . $path ) ) ) {
		wp_safe_redirect( $target, 301, 'Blog Kit' );
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\\redirect_old_url', 20 );

/*
 * Core sitemap (used only when no SEO plugin replaces it): drop the archives
 * that are switched off.
 */
add_filter(
	'wp_sitemaps_taxonomies',
	static function ( $taxonomies ) {
		if ( ! config( 'tags' ) ) {
			unset( $taxonomies['post_tag'] );
		}
		return $taxonomies;
	}
);

add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return ( 'users' === $name && ! config( 'author_archives' ) ) ? false : $provider;
	},
	10,
	2
);
