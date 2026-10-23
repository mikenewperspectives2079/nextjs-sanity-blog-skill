<?php
/**
 * Plugin Name:       Blog Kit
 * Description:       Blog logic for this site, built from the building-blog spec: table of contents, updated dates, reading time, related posts, category navigation, FAQ schema, publish checks, archive hygiene and image formats.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * License:           MIT
 * Text Domain:       blog-kit
 * Domain Path:       /languages
 * Update URI:        false
 *
 * `Update URI: false` stops WordPress from ever "updating" this plugin from a
 * same-named plugin on wordpress.org. Keep it when you rename the plugin.
 *
 * Per-project settings live in config.php. Every value there maps to a field
 * in the project profile (§1 of blog-technical-requirements.md).
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';
const FILE    = __FILE__;

/**
 * Returns the kit configuration, or one key of it.
 *
 * Defaults are merged with config.php, then passed through the
 * `blog_kit_config` filter so a theme can override single values.
 *
 * @param string|null $key Optional config key.
 * @return mixed
 */
function config( $key = null ) {
	static $config = null;

	if ( null === $config ) {
		$defaults = array(
			'tags'                    => false,
			'comments'                => false,
			'author_archives'         => false,
			'date_archives'           => false,
			'allowed_blocks'          => array(),
			'heading_levels'          => array( 2, 3, 4 ),
			'require_on_publish'      => array( 'title', 'single_category', 'featured_image', 'featured_image_alt' ),
			'toc_min_h2'              => 4,
			'toc_min_words'           => 1000,
			'reading_speed_wpm'       => 238,
			'updated_threshold_hours' => 24,
			'related_count'           => 3,
			'category_order'          => array(),
			'category_noindex_below'  => 0,
			'card_pattern'            => '',
			'image_output_format'     => 'webp',
			'og_image_size'           => 'blog-og',
			'faq_schema'              => true,
			'faq_class'               => 'blog-faq',
			'switcher_fallback'       => true,
			'paginated_hreflang'      => true,
			'strict_404_guessing'     => true,
			'redirect_old_urls'       => true,
			'keep_modified_on_image'  => true,
		);

		$file   = __DIR__ . '/config.php';
		$values = file_exists( $file ) ? require $file : array();
		$config = array_replace( $defaults, is_array( $values ) ? $values : array() );

		/**
		 * Filters the Blog Kit configuration.
		 *
		 * @param array $config Merged configuration.
		 */
		$config = (array) apply_filters( 'blog_kit_config', $config );
	}

	return null === $key ? $config : ( $config[ $key ] ?? null );
}

/**
 * Returns a translatable UI label. Themes can change wording with the
 * `blog_kit_label` filter without editing the kit.
 *
 * @param string $key Label key.
 * @return string
 */
function label( $key ) {
	$labels = array(
		'toc'            => __( 'On this page', 'blog-kit' ),
		'updated'        => __( 'Updated', 'blog-kit' ),
		/* translators: %s: category name. */
		'more_from'      => __( 'More from %s', 'blog-kit' ),
		'more_posts'     => __( 'More from the blog', 'blog-kit' ),
		'categories'     => __( 'Blog categories', 'blog-kit' ),
		'all_posts'      => __( 'All posts', 'blog-kit' ),
		'faq'            => __( 'Frequently asked questions', 'blog-kit' ),
		/* translators: %s: language name, e.g. "French". */
		'not_in_lang'    => __( 'This article isn’t available in %s.', 'blog-kit' ),
		'not_found'      => __( 'This article doesn’t exist or is no longer published.', 'blog-kit' ),
		'browse_blog'    => __( 'Browse the blog', 'blog-kit' ),
	);

	$value = $labels[ $key ] ?? $key;

	/**
	 * Filters a Blog Kit UI label.
	 *
	 * @param string $value Label text.
	 * @param string $key   Label key.
	 */
	return (string) apply_filters( 'blog_kit_label', $value, $key );
}

/**
 * Lower-cased names WordPress gives its placeholder category: "Uncategorized"
 * plus its translation in the site, visitor/editor and every active language
 * ("Non classé", "Allgemein", "Sin categoría"…).
 *
 * @return string[]
 */
function placeholder_category_names() {
	static $names = null;
	if ( null !== $names ) {
		return $names;
	}

	$locales = array( get_locale(), determine_locale() );
	if ( function_exists( 'pll_languages_list' ) ) {
		$locales = array_merge( $locales, (array) pll_languages_list( array( 'fields' => 'locale' ) ) );
	} elseif ( has_filter( 'wpml_active_languages' ) ) {
		foreach ( (array) apply_filters( 'wpml_active_languages', null ) as $language ) {
			if ( ! empty( $language['default_locale'] ) ) {
				$locales[] = $language['default_locale'];
			}
		}
	}

	$lower = static function ( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $text ), 'UTF-8' ) : strtolower( trim( $text ) );
	};

	$names = array( 'uncategorized' );
	foreach ( array_unique( array_filter( $locales ) ) as $locale ) {
		$current = determine_locale() === $locale;
		if ( ! $current && ! file_exists( WP_LANG_DIR . "/{$locale}.l10n.php" ) && ! file_exists( WP_LANG_DIR . "/{$locale}.mo" ) ) {
			continue; // No core translation installed: the name is the English one.
		}
		$switched = ! $current && switch_to_locale( $locale );
		$names[]  = $lower( __( 'Uncategorized' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string.
		if ( $switched ) {
			restore_previous_locale();
		}
	}

	$names = array_values( array_unique( $names ) );
	return $names;
}

/**
 * Whether a category is still WordPress's placeholder ("Uncategorized"), in
 * any language, including the per-language copies multilingual plugins
 * create. Judged by name: a category renamed to a real topic counts as real
 * even if its slug is still "uncategorized" (rename the slug too, §3.3).
 *
 * @param \WP_Term $term Category.
 * @return bool
 */
function is_placeholder_category( $term ) {
	if ( ! $term instanceof \WP_Term ) {
		return false;
	}
	$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $term->name ), 'UTF-8' ) : strtolower( trim( $term->name ) );
	return in_array( $name, placeholder_category_names(), true );
}

/**
 * Returns the blog index (posts page) URL, in the given or current language
 * when a multilingual plugin is active.
 *
 * @param string|null $lang Language slug (Polylang) or code (WPML).
 * @return string
 */
function blog_index_url( $lang = null ) {
	$page_id = (int) get_option( 'page_for_posts' );

	if ( $page_id && function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id, $lang ? $lang : ( function_exists( 'pll_current_language' ) ? pll_current_language() : '' ) );
		if ( $translated ) {
			$page_id = (int) $translated;
		}
	} elseif ( $page_id && has_filter( 'wpml_object_id' ) ) {
		$page_id = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang );
	}

	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	return home_url( '/' );
}

/**
 * Registers a server-rendered block, a shortcode and nothing else.
 *
 * On WordPress 7.0+ `supports.autoRegister` gives the block a live preview in
 * the editor with no JavaScript build. On older versions the block still
 * renders on the front end; use the shortcode in the editor instead.
 *
 * @param string   $name     Block name without namespace, e.g. "toc".
 * @param string   $title    Block title shown in the inserter.
 * @param callable $render   Render callback returning HTML.
 * @param array    $extra    Extra block type args (attributes, usesContext).
 */
function register_dynamic_block( $name, $title, $render, array $extra = array() ) {
	register_block_type(
		'blog-kit/' . $name,
		array_merge(
			array(
				'api_version'     => 3,
				'title'           => $title,
				'category'        => 'theme',
				'render_callback' => static function ( $attributes, $content, $block ) use ( $render ) {
					return (string) call_user_func( $render, (array) $attributes, $block );
				},
				'supports'        => array(
					'autoRegister' => true,
					'html'         => false,
				),
				'uses_context'    => array( 'postId', 'postType' ),
			),
			$extra
		)
	);

	add_shortcode(
		'blog_kit_' . str_replace( '-', '_', $name ),
		static function ( $atts ) use ( $render ) {
			return (string) call_user_func( $render, (array) $atts, null );
		}
	);
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'blog-kit', false, dirname( plugin_basename( FILE ) ) . '/languages' );
	}
);

foreach ( array( 'content-model', 'archives', 'media', 'toc', 'post-meta', 'related-posts', 'listing', 'faq', 'seo', 'i18n', 'template-tags' ) as $blog_kit_module ) {
	require __DIR__ . '/includes/' . $blog_kit_module . '.php';
}
unset( $blog_kit_module );
