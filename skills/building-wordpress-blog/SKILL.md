---
name: building-wordpress-blog
description: Use when adding a blog to a WordPress site, building or rebuilding its blog section, or setting up an SEO-optimized article surface on WordPress (block theme, classic theme or page builder), including Yoast SEO / Rank Math and Polylang / WPML configuration. Triggers on phrases like "add a blog to this WordPress site", "build the blog", "set up the blog page", "blog templates", "blog SEO setup", "WordPress blog architecture", or any new-blog scoping conversation on a WordPress project.
---

# Building a Blog (WordPress)

## Overview

Universal workflow for adding a blog to an existing WordPress site. Targets a corporate blog: SEO-first, i18n-ready, performance-budgeted, accessibility-compliant. It works *with* the site's theme (block, classic or page builder), its SEO plugin (Yoast SEO or Rank Math) and, when multilingual, Polylang or WPML, configuring them rather than duplicating them.

**SKILL.md is the orchestrator. The spec lives in the reference files. The site plugin and the hero-image script are tested implementations of the parts WordPress doesn't do on its own.**

## Files

- `blog-technical-requirements.md`: §0 intake questionnaire (46 questions) → §1 Project Profile → §2–§18 universal spec → §19 pass/fail checklist → §20 AI hero images. Edit only §0 answers and §1, in a project-local copy.
- `blog-image-style-guide.md`: universal template for AI-generated hero imagery (Gemini image models). Intake questions on top, aesthetic skeleton in the middle, example slots at the bottom.
- `site-plugin/blog-kit/`: **Blog Kit**, the site plugin.
  - Covers the table of contents, heading anchors, "Updated" date, related posts, category navigation, blog-index H1, FAQ section plus schema, the publish guard, archive 404s, WebP/AVIF output, OG crop, image alt tags, the language-switcher fallback and hreflang gaps.
  - Configured by `config.php`. See its README.
  - Tested on WordPress 7.1.3 with no SEO plugin, Yoast SEO 28.7, Rank Math 1.0.280 and Polylang 3.9.1, on block and classic themes.
- `scripts/generate-hero-images.mjs` + `scripts/hero-topics.example.json`: Node 20+, no dependencies. Generates hero images with Gemini and sets them as featured images through the REST API, with per-language alt text.

## Workflow

### Step 1 — Scan the site (before asking anything)

Pre-filling answers from what is already there beats asking. Find out what the workspace actually contains first:

- **Full install**: `wp-config.php`, `wp-content/`, `wp-includes/version.php`.
- **Bedrock**: `composer.json` requiring `roots/wordpress`; code under `web/app/`.
- **Local stacks**: `.wp-env.json`, `.ddev/`, `.lando.yml`, a `docker-compose.yml` with a WordPress image, or a LocalWP site folder.
- **Just a theme or plugin repository**: `style.css` with a `Theme Name:` header (a `Template:` line means child theme), or a plugin header.

Then check:

- Theme type:
  - `theme.json` + `templates/` → block theme;
  - `single.php` / `functions.php` → classic theme;
  - a page builder plugin (Elementor, Divi, Bricks, Beaver Builder) and whether its theme builder overrides single posts or archives.
- `CLAUDE.md`, `AGENTS.md`: project conventions and approved overrides.
- `.env*`: the *names* of WordPress/Gemini credentials (never print values).

**With WP-CLI** (run `wp --info` first):

```bash
wp core version; wp eval 'echo PHP_VERSION, " ", wp_get_environment_type();'
wp theme list --status=active,parent --fields=name,status,version
wp plugin list --status=active --fields=name,version          # SEO, multilingual, cache, image, consent, builder
wp option get permalink_structure; wp option get category_base
wp option get show_on_front; wp option get page_for_posts; wp option get posts_per_page
wp option get blog_public                                       # 0 = "Discourage search engines"
wp option get wp_attachment_pages_enabled; wp option get default_category; wp option get rss_use_excerpt
wp post list --post_type=post --post_status=publish --format=count
wp term list category --fields=term_id,name,slug,count; wp term list post_tag --format=count
wp user list --role__in=administrator,editor,author --fields=ID,user_login,display_name
wp post list --post_type=wp_template,wp_template_part --fields=ID,post_name   # Site Editor overrides
wp post-type list --_builtin=0 --fields=name,rewrite             # CPTs affected by /blog/ front (§5.2)
```

**Without WP-CLI**, use the REST API:

- Public: `GET /wp-json/` (site name, plugin namespaces such as `yoast/v1`, `rankmath/v1`, `pll/v1`), `/wp-json/wp/v2/posts?per_page=1` (read the `X-WP-Total` header), `/wp-json/wp/v2/categories?per_page=100`.
- With an Application Password: `/wp-json/wp/v2/plugins`, `/wp-json/wp/v2/themes?status=active`, `/wp-json/wp/v2/settings`, `/wp-json/wp/v2/templates`.

**Front end** (fetch a post, `/blog/` or the posts page, a category page, `/robots.txt`, `/sitemap_index.xml` or `/wp-sitemap.xml`):

- the number of `<h1>`s;
- JSON-LD blocks (how many graphs, from whom);
- `itemtype=` microdata;
- the canonical, robots meta, hreflang and `og:` tags;
- the hero `<img>` (`fetchpriority`, `loading`, `srcset` format);
- render-blocking scripts;
- cache headers (`x-litespeed-cache`, `x-kinsta-cache`, `cf-cache-status`, `x-cache`).

Record findings as a brief "Detected" list before the questionnaire.

### Step 1.5 — Fetch the latest docs for the stack you'll touch

The spec was verified in October 2026; WordPress and its plugins change faster than this skill. Before implementing, pull current docs for what this site actually runs. Use [`context7`](https://context7.com) if available, otherwise `WebFetch` on the official docs, and cross-check against the spec section you're about to implement.

Minimum:

- **WordPress**:
  - the current release's field guide;
  - Block Editor Handbook (templates, template parts, patterns, `theme.json`, Query Loop);
  - `register_block_type` (`supports.autoRegister`);
  - template hierarchy;
  - REST API Handbook (posts, media, Application Passwords).
- **The SEO plugin in use**: Yoast developer portal (schema API, filters) or Rank Math's filters and hooks.
- **The multilingual plugin in use** (if any): Polylang function and filter reference, or WPML hooks reference.

Just-in-time, at the matching section:

- **Google**: [Article](https://developers.google.com/search/docs/appearance/structured-data/article) and [Breadcrumb](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb) structured data (§7); `web.dev` Core Web Vitals (§14).
- **Gemini image generation** (§20, only if hero images are in scope): current model IDs and request format.

If the docs contradict the spec, the docs win. Note the contradiction in §1 and proceed against the docs.

### Step 2 — Run the intake questionnaire

Open `blog-technical-requirements.md` and walk through §0.

**Pre-fill first.** If the site has a Site Identity Profile (`building-site-identity`; `sites/<domain>/identity-profile.md` in the fleet workspace), take every answer it holds: author model and byline (C1), categories from its content pillars (C2–C3), audience, languages (B1), hero-image direction (J1–J3) and its §12 site-build inputs. If the operator keeps fleet defaults, apply them next. Ask only what neither covers.

- **Claude Code:** use `AskUserQuestion`. Group related questions into single calls (max 4 per call). Pre-fill recommended answers from the scan.
- **Codex / CLIs without an interactive picker:** list questions numerically in plain text and ask the user to answer in batches of 5–10. Show recommended answers from the scan.
- **Headless / non-interactive:** apply the defaults to whatever the prompt didn't cover; list every assumption.

Write the answers into §1 "Project Profile" in a project-local copy (e.g. `docs/blog/blog-technical-requirements.md` in the site's repository), not in the universal source.

### Step 3 — Produce the high-level plan

Use the plan template at the end of §0. One page: phases, locked-in scope, out-of-scope items, open decisions. **Wait for explicit user approval. Do not change the site before that.**

### Step 4 — Implement against the spec

Work on a local copy or staging, never directly on production. Back up first (`wp db export`, or the host's backup). Then, in this order:

1. **Core settings** (§3–§5): permalinks (with the §5.3 redirect map for posts and categories if content already exists), Reading, Discussion, default category, attachment pages, users.
2. **SEO plugin settings** (§7.1).
3. **Multilingual setup** (§6), if any.
4. **Blog Kit** (§18.3):
   1. Copy `site-plugin/blog-kit/` to `wp-content/plugins/<project>-blog/` and set the plugin name in the header; keep `Update URI: false`.
   2. Fill `config.php` from §1.
   3. Activate it (WordPress 6.9+).
   4. Regenerate image sizes: `wp media regenerate --image_size=blog-og --yes`, and the same for `blog-card`.
   5. With Yoast, run `wp yoast index --reindex`.
5. **Templates** (§8–§10, §9.4): blog index, single post, category, 404, card pattern. Check for Site Editor customizations first; a customized copy in the database wins over the theme file.
6. **Content migration**: real categories with descriptions, tags redirected or retired, author display names, a featured image and alt text on every published post.
7. **Hero images** (§20), if in scope; then the **homepage feed** (§10), if launched last.

Settings live in the database:

- **With WP-CLI**, apply them with `wp option update`, `wp rewrite structure`, and so on.
- **Without WP-CLI**, write an admin checklist with exact click paths for the user. Don't bake one-off settings into Blog Kit.

Code changes go through the project's normal deploy, never by editing files on the live server.

### Step 5 — Verify

§19 (pass/fail checklist) is the definition of done. Verify on staging, then again on production after deploy.

- **Fetch pages and check:**
  - one `<h1>`;
  - one JSON-LD graph containing BlogPosting and BreadcrumbList;
  - self-canonicals on `/blog/page/2/` and category pages;
  - hreflang per §7.4;
  - the `og:image` crop and alt;
  - hero `fetchpriority="high"` with a WebP/AVIF `srcset`.
- **Check status codes:**
  - tag, date and author archives, `/?author=1`, `?m=` and post comment feeds → 404;
  - a deleted or unknown post slug → 404, not a redirect;
  - old post and category URLs → one 301 (§5.3).
- **Check for links to those 404s**: no tag lists or linked author names on posts, none in menus or widgets.
- **Try to publish a draft without a featured image.** Blog Kit must refuse.
- **Run** the Rich Results Test or Schema Markup Validator on a sample post, Lighthouse mobile, and `axe-core`.
- **Confirm** "Discourage search engines" is off on production.

## When NOT to use this skill

- **Headless WordPress** (Next.js, Astro or similar front end on WPGraphQL/REST). The front-end rules in the Next.js version of this spec fit better.
- **Static / Markdown-only blogs** (no CMS): different stack, different patterns.
- **High-velocity news publishers**: sitemap chunking, Google News and editorial tooling become primary concerns.
- **Documentation sites**: use a docs framework instead.
- **Marketing landing pages** that aren't really a blog.

## Recommended companion skills

- `frontend-design`: if the visual identity is unclear or the theme is mid-redesign, settle the direction first.
- `superpowers:brainstorming`: for scope conversations beyond what §0 captures.
- `superpowers:writing-plans`: to expand the high-level plan into a per-phase implementation plan before coding.
