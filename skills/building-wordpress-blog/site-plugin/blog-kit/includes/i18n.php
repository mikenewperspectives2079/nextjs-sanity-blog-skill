<?php
/**
 * Languages and the helpful 404 (§6, §7.4). Works with Polylang and WPML;
 * on single-language sites only the 404 message is active.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Active multilingual plugin.
 *
 * @return string 'polylang', 'wpml' or ''.
 */
function multilingual_plugin() {
	if ( function_exists( 'pll_current_language' ) ) {
		return 'polylang';
	}
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return 'wpml';
	}
	return '';
}

/**
 * URL slugs of the active languages ("en", "fr"…), or an empty array.
 *
 * @return string[]
 */
function language_slugs() {
	static $slugs = null;
	if ( null === $slugs ) {
		$slugs = array();
		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs = array_map( 'strval', (array) pll_languages_list( array( 'fields' => 'slug' ) ) );
		} elseif ( has_filter( 'wpml_active_languages' ) ) {
			$slugs = array_map( 'strval', array_keys( (array) apply_filters( 'wpml_active_languages', null ) ) );
		}
	}
	return $slugs;
}

/**
 * URL slug of a post's language ("fr"), or '' when unknown or monolingual.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function post_language( $post_id ) {
	$plugin = multilingual_plugin();
	if ( 'polylang' === $plugin ) {
		return (string) pll_get_post_language( (int) $post_id );
	}
	if ( 'wpml' === $plugin ) {
		$details = apply_filters( 'wpml_post_language_details', null, (int) $post_id );
		return is_array( $details ) ? (string) ( $details['language_code'] ?? '' ) : '';
	}
	return '';
}

/**
 * URL slug of the default language, or '' when monolingual.
 *
 * @return string
 */
function default_language() {
	$plugin = multilingual_plugin();
	if ( 'polylang' === $plugin ) {
		return (string) pll_default_language();
	}
	if ( 'wpml' === $plugin ) {
		return (string) apply_filters( 'wpml_default_language', null );
	}
	return '';
}

/*
 * §6.2 — On a post with no translation, the language switcher links to the
 * blog index in the target language, not to that language's homepage.
 *
 * Polylang 3.9+ passes '' for a missing translation (null before 3.9). Keep
 * the switcher's "hide languages with no translation" option off, or the
 * item stays hidden.
 */
add_filter(
	'pll_the_language_link',
	static function ( $url, $slug ) {
		if ( ! empty( $url ) || ! config( 'switcher_fallback' ) || ! is_singular( 'post' ) ) {
			return $url;
		}
		return blog_index_url( $slug );
	},
	10,
	2
);

/*
 * WPML: entries for missing translations carry `missing`. Requires the
 * switcher option "Link to home of language for missing translations"
 * (with "Skip language", missing languages are removed before this filter).
 */
add_filter(
	'wpml_ls_language_url',
	static function ( $url, $data ) {
		if ( empty( $data['missing'] ) || ! config( 'switcher_fallback' ) || ! is_singular( 'post' ) ) {
			return $url;
		}
		$code = (string) ( $data['language_code'] ?? '' );
		$page = (int) apply_filters( 'wpml_object_id', (int) get_option( 'page_for_posts' ), 'page', false, $code );
		return $page ? (string) apply_filters( 'wpml_permalink', get_permalink( $page ), $code ) : $url;
	},
	10,
	2
);

/*
 * §7.4 — Polylang adds x-default only on the front page. Point the blog
 * index's x-default at the default language's blog index. (WPML adds
 * x-default itself.)
 */
add_filter(
	'pll_rel_hreflang_attributes',
	static function ( $hreflangs ) {
		if ( ! is_home() || is_front_page() || is_paged() || ! function_exists( 'pll_default_language' ) ) {
			return $hreflangs;
		}
		$default = (int) pll_get_post( (int) get_option( 'page_for_posts' ), pll_default_language() );
		if ( $default ) {
			$hreflangs['x-default'] = get_permalink( $default );
		}
		return $hreflangs;
	}
);

/*
 * §7.4 — Polylang prints no hreflang on paginated pages. On /blog/page/N/,
 * link page N of every language that has a page N, plus x-default. Codes
 * follow Polylang's rule: language subtag unless two languages share it.
 */
add_action(
	'wp_head',
	static function () {
		if ( 'polylang' !== multilingual_plugin() || ! config( 'paginated_hreflang' ) || ! is_home() || ! is_paged() ) {
			return;
		}

		global $wp_rewrite;
		$paged    = (int) get_query_var( 'paged' );
		$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
		$blog_id  = (int) get_option( 'page_for_posts' );
		$links    = array();

		foreach ( pll_languages_list( array( 'fields' => '' ) ) as $language ) {
			$page = (int) pll_get_post( $blog_id, $language->slug );
			if ( ! $page || (int) ceil( pll_count_posts( $language->slug ) / $per_page ) < $paged ) {
				continue;
			}
			$links[ $language->get_locale( 'display' ) ] = array(
				'slug' => $language->slug,
				'url'  => trailingslashit( get_permalink( $page ) ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' ),
			);
		}

		if ( count( $links ) < 2 ) {
			return;
		}

		$subtags = array();
		foreach ( array_keys( $links ) as $locale ) {
			$subtags[ $locale ] = strtok( $locale, '-' );
		}
		$counts = array_count_values( $subtags );

		foreach ( $links as $locale => $link ) {
			$code = $counts[ $subtags[ $locale ] ] > 1 ? $locale : $subtags[ $locale ];
			printf( '<link rel="alternate" href="%s" hreflang="%s" />' . "\n", esc_url( $link['url'] ), esc_attr( $code ) );
		}
		foreach ( $links as $link ) {
			if ( pll_default_language() === $link['slug'] ) {
				printf( '<link rel="alternate" href="%s" hreflang="x-default" />' . "\n", esc_url( $link['url'] ) );
			}
		}
	},
	2
);

/*
 * REST: expose each post's language (`blog_kit_lang`) so scripts that look
 * posts up by slug can confirm they found the right one. Free Polylang and
 * WPML add no language field of their own.
 */
add_action(
	'rest_api_init',
	static function () {
		if ( ! multilingual_plugin() ) {
			return;
		}
		register_rest_field(
			'post',
			'blog_kit_lang',
			array(
				'get_callback' => static function ( $post ) {
					return post_language( (int) $post['id'] );
				},
				'schema'       => array(
					'description' => 'Language of the post (Blog Kit).',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}
);

/**
 * Name of the current language, for messages.
 *
 * @return string
 */
function current_language_name() {
	if ( 'polylang' === multilingual_plugin() ) {
		return (string) pll_current_language( 'name' );
	}
	if ( 'wpml' === multilingual_plugin() ) {
		$code      = (string) apply_filters( 'wpml_current_language', null );
		$languages = (array) apply_filters( 'wpml_active_languages', null );
		return (string) ( $languages[ $code ]['native_name'] ?? $code );
	}
	return '';
}

/**
 * §6.1 — Message and link for the 404 template. When the requested slug is a
 * published post in another language, says so; never links to that post
 * (independent posts have no reliable equivalent).
 *
 * @param array          $attributes Unused.
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_not_found( $attributes = array(), $block = null ) {
	$message = label( 'not_found' );

	if ( multilingual_plugin() && is_404() ) {
		global $wpdb;
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$slug = sanitize_title( rawurldecode( basename( untrailingslashit( $path ) ) ) );
		$name = current_language_name();
		if ( '' !== $slug && '' !== $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- language-agnostic existence check on 404s only.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish' LIMIT 1", $slug ) );
			if ( $exists ) {
				$message = sprintf( label( 'not_in_lang' ), $name );
			}
		}
	}

	return sprintf(
		'<div %1$s><p>%2$s</p><p><a href="%3$s">%4$s</a></p></div>',
		wrapper_attributes( $block, array( 'class' => 'blog-kit-not-found' ) ),
		esc_html( $message ),
		esc_url( blog_index_url() ),
		esc_html( label( 'browse_blog' ) )
	);
}

add_action(
	'init',
	static function () {
		register_dynamic_block( 'not-found', __( 'Blog “not found” message', 'blog-kit' ), __NAMESPACE__ . '\\render_not_found' );
	}
);
