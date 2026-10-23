<?php
/**
 * Archive hygiene (§4, §7.11): disabled archives return real 404s, and
 * WordPress stops "guessing" redirects from deleted posts to similar slugs.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request is an archive type this site has switched off.
 *
 * @return bool
 */
function is_disabled_archive() {
	return ( is_tag() && ! config( 'tags' ) )
		|| ( is_date() && ! config( 'date_archives' ) )
		|| ( is_author() && ! config( 'author_archives' ) );
}

/*
 * Tag, date and author archives (and their feeds) answer 404 when switched
 * off. A 404 is honest; redirecting them to the homepage is treated by Google
 * as a soft 404 anyway. Runs on `wp` at priority 1, before Yoast SEO and Rank
 * Math redirect "disabled" archives to the homepage (both use `wp` at 10).
 */
add_action(
	'wp',
	static function () {
		if ( ! is_disabled_archive() ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		$wp_query->is_feed = false; // Also catch /tag/x/feed/ and friends.
		status_header( 404 );
		nocache_headers();
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
