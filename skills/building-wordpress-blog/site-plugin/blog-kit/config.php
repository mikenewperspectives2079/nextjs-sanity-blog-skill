<?php
/**
 * Blog Kit — per-project settings.
 *
 * Fill these from the project profile (§1 of blog-technical-requirements.md).
 * The comment on each line names the intake question that decides it.
 * Defaults below are the spec's recommended answers.
 *
 * @package BlogKit
 */

defined( 'ABSPATH' ) || exit;

return array(

	// C5 — Tags. false: tags are detached from posts and tag archives return 404.
	'tags'                    => false,

	// K1 — Comments. false: closed and hidden on posts, comment feeds not advertised.
	'comments'                => false,

	// C1 — true only when authors have public profile pages (full author entity).
	'author_archives'         => false,

	// K1 — Date archives (/blog/2026/10/). false: they return 404.
	'date_archives'           => false,

	// C6 — Blocks editors can insert into posts. Empty array = no restriction.
	'allowed_blocks'          => array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/image',
		'core/table',
		'core/details', // FAQ items (§13).
		'core/group',   // FAQ wrapper (§13).
	),

	// §3.2 — Heading levels offered in the editor (no H1: the title is the H1). Applies site-wide.
	'heading_levels'          => array( 2, 3, 4 ),

	// §3.5 — What must be true before a post can be published or scheduled.
	'require_on_publish'      => array( 'title', 'single_category', 'featured_image', 'featured_image_alt' ),

	// E4 — Table of contents shows only with at least this many H2s AND words.
	'toc_min_h2'              => 4,
	'toc_min_words'           => 1000,

	// §12.2 — Reading speed for the reading-time label (core Time to Read block).
	'reading_speed_wpm'       => 238,

	// §7.10 — "Updated <date>" shows only when modified this long after publishing.
	'updated_threshold_hours' => 24,

	// E5 — Number of related posts ("More from <Category>").
	'related_count'           => 3,

	// C3 — Order of the category navigation, as category slugs. Empty = alphabetical.
	'category_order'          => array(),

	// C7 — Category archives with fewer posts than this get noindex,follow and
	// leave the sitemap. 0 = always indexable.
	'category_noindex_below'  => 3,

	// §8.2 — Block themes: slug of the theme pattern that renders one post card
	// (used for related posts). Empty = a default card made of core blocks.
	'card_pattern'            => '',

	// G3 — Format new JPEG/PNG uploads are stored in: 'webp', 'avif' (falls back
	// to WebP when the server can't write AVIF) or 'none'.
	'image_output_format'     => 'webp',

	// F3 — Image size used for og:image (registered by the kit as 1200×630).
	'og_image_size'           => 'blog-og',

	// F2 — FAQPage structured data from the FAQ section. No Google rich result
	// since 2026-05-07; harmless and still valid.
	'faq_schema'              => true,

	// §13 — Class on the Group block that wraps an FAQ section.
	'faq_class'               => 'blog-faq',

	// B4 / §6.2 — Language switcher on untranslated posts goes to the blog index.
	'switcher_fallback'       => true,

	// §7.4 — hreflang on paginated blog index pages (Polylang skips them).
	'paginated_hreflang'      => true,

	// §7.11 — Deleted posts 404 instead of redirecting to a similar slug.
	'strict_404_guessing'     => true,

	// D1 / §5.3 — After the structure gains a front (/%postname%/ → /blog/%postname%/),
	// 404s at an old post or category URL 301 to the moved page. Only acts on 404s.
	'redirect_old_urls'       => true,

	// §7.10 — Setting only a featured image on a published post (e.g. the hero
	// script backfilling old posts) doesn't bump its modified date.
	'keep_modified_on_image'  => true,
);
