# Blog Kit

The site plugin that implements the parts of the blog spec WordPress doesn't do on its own (`blog-technical-requirements.md`, §18.3). Logic only: templates and styling stay in the theme.

Requires WordPress 6.8+ (7.0+ for live editor previews of the blocks) and PHP 7.4+.

Tested on WordPress 7.1.3 in these setups:

- no SEO plugin;
- Yoast SEO 28.7;
- Rank Math 1.0.280;
- Polylang 3.9.1;
- a block theme and a classic child theme.

## Install

1. Copy this folder to `wp-content/plugins/<project>-blog/`, then change `Plugin Name:` in `blog-kit.php` to "<Project> Blog Kit".
   - Keep `Update URI: false`: it stops WordPress from ever replacing this plugin with a same-named one from wordpress.org.
   - Keep the folder in the site's repository and deploy it like theme code.
2. Fill in `config.php` from the project profile. Each line names the intake question that decides it.
3. Activate: `wp plugin activate <project>-blog`.
4. Regenerate image sizes for existing images:
   ```
   wp media regenerate --image_size=blog-card --yes
   wp media regenerate --image_size=blog-og --yes
   ```
   With Yoast SEO, also run `wp yoast index --reindex` (it caches the chosen share image).
5. Place the blocks in the theme's templates (spec §9.4), or use the template tags or shortcodes.

## What it does

| Area | Behaviour | Spec |
|---|---|---|
| Content model | Inserter limited to `allowed_blocks`; headings H2–H4; tags detached from posts; comments closed on posts and comment feeds not advertised | §3, §4 |
| Publish guard | Publishing or scheduling through the REST API (block editor, AI agents) is refused until the post has a title, exactly one real category (not "Uncategorized" or a per-language copy), a featured image and alt text on it. Already-published posts and autosaves are never blocked. | §3.5 |
| Archives | Tag, date and author archives, their feeds and `/?author=N` return 404 (runs before Yoast/Rank Math redirects); strict 404 guessing, so deleted posts don't 301 to similar slugs; core sitemap drops users/tags | §4, §7.11 |
| Images | `blog-card` 800×450 and `blog-og` 1200×630 crops; JPEG/PNG uploads stored as WebP (or AVIF with WebP fallback) for every size including full | §14.3 |
| Headings + ToC | Unique, stable `id` on every body heading (explicit anchors kept, Unicode-friendly); ToC of H2s only above the thresholds, `<nav>` + `<details>`, opened on wide screens, current section marked, focus moved on jump | §9.2, §12.1 |
| Byline | "Updated <date>" only when modified more than 24 h after publishing; reading time = core Time to Read at 238 wpm, single value | §7.10, §12.2 |
| Related posts | Same primary category, same language, newest first, topped up with latest posts; theme's card pattern (block themes) or `template-parts/blog-card.php` (classic); H3 titles under an H2 in a labelled `<aside>` | §11 |
| Listing | H1 for the blog index (posts page title, per language) and category pages (bare name); category navigation with `aria-current` | §8.1 |
| FAQ | "FAQ section" block pattern (Details blocks in a `blog-faq` Group); FAQPage merged into Yoast's or Rank Math's graph, or printed standalone | §13 |
| SEO gaps | OG image prefers the 1200×630 crop (Yoast, Rank Math); `og:image:alt` (Yoast) and `twitter:image:alt` (both); thin category pages `noindex, follow` and removed from the sitemap (Yoast, Rank Math, core) | §7.6, §4 |
| Languages | Switcher on untranslated posts → blog index of the target language (Polylang, WPML); x-default on the blog index and hreflang on paginated index pages (Polylang); REST field `blog_kit_lang`; localized "not found" message | §6, §7.4 |

## Blocks, shortcodes and template tags

Every presentational piece exists in three forms that render the same markup:

- a server-rendered block, for block themes; with WordPress 7.0+ `supports.autoRegister`, it gets a live editor preview with no build step;
- a shortcode, for page builders;
- a template tag, for classic themes.

| Block | Shortcode | Template tag |
|---|---|---|
| `blog-kit/toc` | `[blog_kit_toc]` | `blog_kit_toc()` |
| `blog-kit/updated-date` | `[blog_kit_updated_date]` | `blog_kit_updated_date()` |
| core `post-time-to-read` (`{"averageReadingSpeed":238,"displayAsRange":false}`) | `[blog_kit_reading_time]` | `blog_kit_reading_time()` |
| `blog-kit/related-posts` | `[blog_kit_related_posts]` | `blog_kit_related_posts()` |
| `blog-kit/category-nav` | `[blog_kit_category_nav]` | `blog_kit_category_nav()` |
| `blog-kit/archive-title` | `[blog_kit_archive_title]` | `blog_kit_archive_title()` |
| `blog-kit/not-found` | `[blog_kit_not_found]` | `blog_kit_not_found()` |

## Configuration

`config.php` returns an array. Every key, with its intake question, is documented inline. A theme can override single keys:

```php
add_filter( 'blog_kit_config', function ( $config ) {
	$config['toc_min_h2'] = 3;
	return $config;
} );
```

UI wording ("On this page", "Updated", "More from %s", "All posts", the 404 messages) uses the `blog-kit` text domain. Translate it with `languages/blog-kit-<locale>.l10n.php` or `.po`/`.mo`, or reword it per site with the `blog_kit_label` filter.

## Files

```
blog-kit.php            bootstrap: config(), label(), blog_index_url(), block/shortcode registration helper
config.php              per-project settings
includes/content-model.php   allowed blocks, heading levels, tags, comments, publish guard
includes/archives.php        disabled archives → 404, strict 404 guessing, core sitemap cleanup
includes/media.php           image sizes, WebP/AVIF output
includes/toc.php             heading ids, ToC
includes/post-meta.php       updated date, reading-time wrapper
includes/related-posts.php   related posts
includes/listing.php         archive title, category navigation
includes/faq.php             FAQ pattern + FAQPage schema
includes/seo.php             OG crop, image alt tags, thin-category noindex
includes/i18n.php            switcher fallback, hreflang gaps, REST language field, not-found block
includes/template-tags.php   global template tags
assets/toc.js                ToC behaviour (~1 KB, deferred, only on posts with a ToC)
assets/blog-kit.css          structural styles only (scroll offset, list resets, 24 px targets)
```
