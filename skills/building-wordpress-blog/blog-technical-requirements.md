# Building a Blog on WordPress: Technical & SEO Requirements

Universal technical specification for adding a blog to a WordPress site (or finishing one that is half-built). Opinionated, concise, decision-ready. Suitable for a corporate blog with organic-search-first traffic and optional AI authoring with human review.

**How to use this document.** Walk §0 with the user first. The answers populate §1 (Project Profile). §2–§18 are the universal spec: they read from §1 and never need editing per project. §19 is the pass/fail checklist and the definition of done. §20 covers AI-generated hero images. Appendices A and B record deferred decisions and deliberate non-features.

**WordPress ground rules.** Every section below assumes these.

1. **Configure before you code.** WordPress core, the SEO plugin and the multilingual plugin already produce most of what this spec asks for. Change settings first; write code only for the gaps.
2. **One owner per output.** The SEO plugin owns titles, meta descriptions, canonicals, Open Graph, schema and sitemaps. The multilingual plugin owns language URLs and hreflang. The theme owns markup. Nothing else prints a second copy: no theme microdata, no extra schema plugin, no second sitemap.
3. **Logic in a plugin, presentation in the theme.** Blog behaviour (table of contents, related posts, publish checks, archive rules, FAQ schema, image formats) lives in the **Blog Kit** site plugin shipped with this skill (`site-plugin/blog-kit/`), so it survives a theme change. Templates and styles live in the theme, in a child theme when the theme is third-party. Never edit a parent theme or a third-party plugin; updates overwrite it.
4. **Know where things are stored.** Code (theme, Blog Kit) lives in files. Settings, menus, SEO-plugin options and Site Editor customizations live in the database. A template customized in the Site Editor overrides the theme file of the same name, so check for a customized copy before editing a template file (Site Editor → Templates, or `GET /wp/v2/templates`: `"source": "custom"`).
5. **Never practise on production.** Work on a local copy or staging. Apply database-side changes with WP-CLI when available; otherwise write them up as an admin checklist for a human. Production gets a deploy plus the same checklist.

**Treat plugin and library specifics as guidance, not gospel.** This spec was checked in October 2026 against WordPress 7.1.3, Yoast SEO 28.7, Rank Math 1.0.280, Polylang 3.9.1, WPML 4.9 and the Gemini API. Blog Kit ran on a WordPress 7.1.3 test site with no SEO plugin, with Yoast, with Rank Math, and with Polylang (free). WPML was checked by reading its 4.9.5 code and documentation only, not run. The hero script (§20) ran against a local stand-in that mimics the Gemini API's documented requests and responses, not against Google. They all change faster than this file. Before implementing a section that touches one of them, pull current docs (via `context7` if available, otherwise `WebFetch` on the official site) and let upstream win on conflicts. Re-verify in particular:

- §3, §12, §13: core block attributes (Time to Read, Post Date, Details, Breadcrumbs) and `supports.autoRegister` (WordPress 7.0+)
- §5: permalink and rewrite behaviour (`with_front`, category base)
- §6: multilingual plugin URL modes, hreflang rules, language-switcher filters
- §7: SEO plugin option names and filters; Google's [Article](https://developers.google.com/search/docs/appearance/structured-data/article) and [Breadcrumb](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb) docs
- §14: `web.dev` Core Web Vitals thresholds; core image-loading heuristics; Performance Lab
- §17: the analytics and consent plugins in use
- §20: Gemini image model IDs (renamed and retired twice in 2026) and request format

If something below contradicts current docs or the site's actual behaviour, the docs and the site win. Record the contradiction in §1 under "Approved overrides".

---

## §0. Intake Questionnaire

Run this section interactively before any implementation work. `SKILL.md` describes how to present the questions (`AskUserQuestion` in Claude Code, numbered plain text in CLIs without an interactive picker, assumed defaults with disclosure when fully headless).

**Order of operations:**

1. Scan the site first (SKILL.md Step 1). Pre-fill every answer you can: WordPress version, theme type, SEO and multilingual plugins, permalink structure, existing posts, categories and tags.
2. Ask the questions below in grouped order. **Skip questions the scan answered**: show the detected value and ask only for confirmation.
3. Write the resolved answers into §1.
4. Produce the high-level plan (template at the end of §0) and wait for explicit approval before changing anything.

### Questionnaire: 46 questions in 12 groups

#### A. Project identity & environment (7)

A1. **Project / brand name.** WordPress Site Title; the SEO plugin uses it for Organization schema and `og:site_name`.
A2. **Primary domain.** Canonical host including the `https` and `www` choice. Must match Settings → General (WordPress Address and Site Address).
A3. **Site purpose in one sentence.** Drives editorial voice and hero image direction.
A4. **Publisher logo.** File path or "deferred to designer". It goes into the SEO plugin's Organization settings. Google no longer requires a logo for article markup, but Organization markup uses it, and Yoast only links the article's `publisher` when both company name and logo are set.
A5. **Site type confirmation.** Corporate / promo blog (this spec), news publisher (use a different spec), docs site (use a different spec).
A6. **Working environment.** Where changes are made: local copy (LocalWP, wp-env, DDEV, Lando, Docker; recommended), staging, or production through the REST API only (read-mostly; discouraged). Is WP-CLI available? How does code reach production: git deploy, host deploy, or SFTP/zip upload?
A7. **Presentation layer.** The detected theme type: block theme, classic theme (does a child theme exist?), or page builder (Elementor, Divi, Bricks, Beaver Builder…). Confirm where the blog templates will be built (§9.4).

#### B. Internationalization (5)

B1. **Locale set.** Single locale or multiple? If multiple, list BCP-47 codes (`en`, `fr`, `de`, `es`…).
B2. **Primary locale.** Used as the `x-default` target and as the fallback language.
B3. **Locale prefix rule.** Every language in a directory, including the primary (`/en/blog/`, `/fr/…`), or the primary language at the root? *Strong default: every language prefixed.* A root-default language creates duplicate-content risk between `/` and `/en/`. Polylang: untick "Hide URL language information for default language". WPML: tick "Use directory for default language".
B4. **Translation linkage.** Should the same article in different languages be linked as translations (post-level hreflang, language switcher goes to the translation), or treated as independent posts? *Strong default: independent.* Linking needs a translation workflow and every post's language versions kept in step.
B5. **Multilingual plugin.** Detected, or chosen: Polylang (free; recommended for independent posts), WPML (paid; translation workflow, shared slugs, translation management), or TranslatePress (translates *the same post*, so every post exists in every language; only fits B4 = cross-linked).

#### C. Content model & taxonomy (7)

C1. **Author model.** One shared byline user, e.g. "Acme Editorial Team" (lowest effort) / real WordPress users shown by display name only (recommended baseline) / full author profiles with bio, photo, social links and indexable author pages (only if you will maintain per-author pages).
C2. **Number of categories.** 5 to 10 is the sweet spot. Below 5, filtering is pointless; above 10, archives fragment.
C3. **Category list.** Names, a one- or two-sentence description for each (shown on the category page and used as its meta description), and their display order. Or defer, with a placeholder in §1.
C4. **Category editability.** Editors may add categories (WordPress default) / administrators only (remove `manage_categories` from editors).
C5. **Tags.** Include or exclude? *Strong default: exclude.* Tags create thin archive pages and governance overhead at this scale, and AI authors invent tags freely.
C6. **Body block types.** Confirm the allowed list. Defaults: paragraph, headings H2–H4, lists, quote, image (alt required), table, FAQ section (§13). Off by default: code, video and embeds, pullquote, file download, custom HTML, columns, cover, buttons, gallery.
C7. **Category pages.** Indexable topic pages, kept `noindex` while a category has fewer than N posts (default 3; recommended), or always `noindex, follow` (used only for filtering).

#### D. Routing & URLs (3)

D1. **Post permalink structure.** `/blog/%postname%/` (recommended for a new blog), `/%postname%/`, or keep the current structure. *If the site already has posts with traffic or backlinks, changing the structure requires a 301 map (§5.3).* Dated (`/%year%/%monthnum%/…`) and category-in-URL (`/%category%/%postname%/`) structures are rejected: they create redirect debt on recategorization.
D2. **Slug strategy (multilingual).** Localized slugs (recommended) or shared slugs across languages (needs Polylang Pro or WPML; see §5.4).
D3. **Trailing slash and case.** WordPress default: trailing slash, lowercase, enforced by core's canonical redirect. Confirm; don't fight WordPress here.

#### E. Listing & article page (5)

E1. **Posts per page.** Settings → Reading → "Blog pages show at most". Applies to every archive. Default 12; mobile-first layouts often look better at 9.
E2. **Featured post.** Automatic: the newest post shown as a larger card on page 1 only (recommended), or sticky posts.
E3. **Pagination style.** Numbered pages (recommended), "Load more" that updates the URL, or infinite scroll. Infinite scroll harms SEO.
E4. **ToC threshold.** Show the table of contents only when the post has ≥N H2 headings AND ≥M words. Defaults: ≥4 H2 and ≥1000 words.
E5. **Related posts.** Automatic: same category, newest first, topped up with the latest posts (recommended). Or manual: an ACF Relationship field, falling back to automatic when empty.

#### F. SEO surface (4)

F1. **SEO plugin.** Detected, or install the agency's standard: Yoast SEO or Rank Math, never both. AIOSEO, SEOPress and The SEO Framework can satisfy this spec too, but Blog Kit's integrations target Yoast and Rank Math: with another plugin, Blog Kit's OG crop preference, image alt tags, thin-category noindex and FAQ schema aren't applied, and those must be set up in that plugin. The SEO Framework needs its paid Articles extension for Article schema.
F2. **Structured data scope.** BlogPosting and BreadcrumbList on every post (SEO plugin), plus FAQPage for posts with an FAQ section (Blog Kit). Google stopped showing FAQ rich results for every site on 2026-05-07; the markup is still valid and harmless. Keep or drop it? Any other schemas needed (HowTo rich results are also retired; Recipe, VideoObject)?
F3. **OG image strategy.** Featured image cropped to 1200×630 (Blog Kit's `blog-og` size; recommended) or a separate social image set per post in the SEO plugin.
F4. **Feed content.** Full text (WordPress default; recommended) or excerpt only (Settings → Reading → "For each post in a feed, include").

#### G. Performance & assets (4)

G1. **Core Web Vitals targets.** Accept the tighter defaults (LCP <2.0 s, INP <150 ms, CLS <0.05, TTFB <600 ms) or relax to Google's "good" baseline?
G2. **Hero image source.** AI-generated (§20 and `blog-image-style-guide.md`), stock photography, designer-supplied, or mixed.
G3. **Image formats.** New JPEG/PNG uploads stored as WebP by Blog Kit (recommended baseline), or AVIF via Blog Kit (falls back to WebP when the server can't encode AVIF), or Performance Lab's Modern Image Formats plugin (AVIF with an optional `<picture>` fallback), or a CDN/image service, or none.
G4. **Fonts.** Reuse the theme's self-hosted fonts (recommended) or add new ones? Confirm subsets: `latin` always; `latin-ext` for FR/DE/ES/IT/PT/PL and similar. No Google Fonts CDN.

#### H. Analytics & consent (2)

H1. **Analytics tool.** Matomo / Plausible / Fathom / GA4 / PostHog / Umami / none. Self-hosted or SaaS; loaded by which plugin (Site Kit, the vendor's plugin, a tag manager)?
H2. **Consent model.** Cookieless tool (no banner needed), consent-gated (loads only after the visitor accepts via the existing consent plugin), or no gate (only legal where the tool is genuinely cookieless and exempt). The blog inherits the site-wide consent setup; never re-implement it in blog templates.

#### I. Content authoring & workflow (3)

I1. **Editor.** Block editor (recommended), Classic Editor plugin (legacy), or a page builder for posts (strongly discouraged: heavy markup, no reliable ToC/FAQ/schema). Blog Kit's heading anchors and ToC also work on Classic Editor posts; the FAQ section (§13), the block list (C6) and the publish guard (§3.5) need the block editor.
I2. **AI drafting.** Will AI agents create drafts through the REST API? If yes: a dedicated user whose role cannot publish, plus an Application Password (§16).
I3. **Scheduled publishing.** WordPress core scheduling is built in. Is a real server cron available? It is recommended, to avoid "Missed schedule" (§16).

#### J. AI-generated hero images (3)

J1. **In scope at launch?** Yes / no / phase 2. If "no", skip §20.
J2. **Model.**
   - `gemini-3-pro-image` (Nano Banana Pro). Best instruction following, about $0.13 per 1K/2K image. This is the default.
   - `gemini-nano-banana-2.1` (Nano Banana 2.1). Cheaper, about $0.03–0.05 per image.
   - `gemini-3.1-flash-lite-image`. Cheapest; 1K only.

   Verify IDs before running: 2026 brought two rounds of renames and shutdowns (`gemini-3-pro-image-preview`, `gemini-2.5-flash-image` and Imagen 4 are gone).
J3. **Local archive copy.** Keep a copy of each generated image in the project folder (recommended, for design review and disaster recovery), or rely on the media library only.

#### K. Explicit non-features (1, multi-select)

K1. **Confirm exclusions.** Default exclusions; override only if specifically needed:
- Comments. WordPress enables them by default; Blog Kit closes them on posts.
- Site-wide search / blog search UI
- Social sharing buttons
- Author archive pages (unless C1 = full profiles)
- Date archives (`/blog/2026/10/`)
- Tag pages
- Newsletter signup *inside* the article body (footer-wide is fine)
- Reading progress bar
- Dark mode toggle specific to the blog (inherits site-wide if any)
- Embedded video, code blocks, pull quotes, downloadable files

Anything ticked moves into scope and needs its own spec section. Category pages are in scope by default (C7); this is the main deliberate difference from the Next.js + Sanity version of this spec, because WordPress creates them anyway and leaving them unmanaged is worse than either using or noindexing them.

#### L. Entry points & launch order (2)

L1. **Entry points to the blog.** Header nav link, footer link, homepage "latest posts" feed, dedicated landing page. Pick any combination.
L2. **Launch order.** Does the homepage feed ship at launch, or after the blog pages are finished? *Recommended: blog pages first, homepage feed last, to avoid empty-state rework.*

---

### High-level plan template

After §0 is answered, produce the plan from this skeleton. One page maximum. Wait for explicit approval before any change to the site.

```
# Blog Implementation Plan — <Project Name>

## Scope (locked from §1)
- Theme approach: <block theme templates / child-theme PHP templates / builder templates>
- SEO plugin: <Yoast / Rank Math> · Multilingual: <none / Polylang / WPML>
- Locales: ...
- Permalinks: <structure> · Redirect map needed: <yes / no>
- Author model: ... · Categories: <N> (<list or TBD>)
- AI hero images: yes / no · Entry points: ...

## Phases
1. Environment — local/staging copy, backup, WP-CLI or admin checklist, Search Engine Visibility check
2. Core settings — permalinks, Reading, Discussion, attachment pages, default category, users
3. SEO plugin — article type, organization, breadcrumbs, archives, sitemaps, feeds
4. Multilingual (if any) — URL mode, languages, blog page per language, switcher
5. Blog Kit — install, config.php from §1, smoke test
6. Templates — blog index, single post, category, 404, card pattern
7. Content migration — categories, tags, authors, featured images + alt text, redirects
8. Hero images (if J1 = yes) — style guide, topics file, smoke test, batch
9. Homepage feed (if L2 = last) — latest 3 cards on the home page
10. Verification — §19 checklist on staging, Lighthouse, Rich Results Test, axe; deploy; re-check on production

## Out of scope (deferred)
- <list from §0.K>

## Open decisions
- <items from Appendix A>

## Estimated effort
- Settings + SEO/multilingual configuration: <X hours>
- Blog Kit + templates: <Y hours>
- Content migration + redirects: <Z hours>
- Performance + a11y polish: <W hours>
- AI hero images (if in scope): <V hours>
- Verification + fixes: <U hours>
```

---

## §1. Project Profile

Project-specific values, populated from §0 answers and the scan. Anywhere below that says "per §1" refers here. Keep a project-local copy under `docs/blog/` in the site's repository; never edit the universal source.

| Field | Value |
|---|---|
| Project name | `[FILL from A1]` |
| Primary domain | `[FILL from A2 — e.g. https://www.example.com]` |
| Site purpose | `[FILL from A3]` |
| Publisher logo | `[FILL from A4 — or "deferred"]` |
| Working environment & deploy | `[FILL from A6 — local/staging/prod-REST · WP-CLI yes/no · deploy method]` |
| WordPress / PHP version | `[FILL from scan — WordPress 7.0+ recommended; PHP 8.1+]` |
| Theme | `[FILL from A7 — name · block / classic / builder · child theme yes/no]` |
| SEO plugin | `[FILL from F1 — Yoast SEO / Rank Math]` |
| Multilingual plugin | `[FILL from B5 — none / Polylang / WPML]` |
| Locales | `[FILL from B1]` |
| Primary locale (x-default) | `[FILL from B2]` |
| Locale prefix rule | `[FILL from B3 — "every language prefixed" recommended]` |
| Translation linkage | `[FILL from B4 — "independent" recommended]` |
| Caching / CDN | `[FILL from scan — host cache, cache plugin, CDN]` |
| Image optimization in use | `[FILL from scan — plugin / CDN / none]` |
| Author model | `[FILL from C1]` |
| Category count target | `[FILL from C2]` |
| Category list, descriptions, order | `[FILL from C3 — or "deferred"]` |
| Category editability | `[FILL from C4]` |
| Tags | `[FILL from C5 — recommended: excluded]` |
| Allowed body blocks | `[FILL from C6]` |
| Category pages | `[FILL from C7 — indexable from N posts / always noindex]` |
| Permalink structure | `[FILL from D1]` · redirect map needed: `[yes/no]` |
| Slug strategy | `[FILL from D2]` |
| Posts per page | `[FILL from E1 — default 12]` |
| Featured post strategy | `[FILL from E2 — automatic recommended]` |
| Pagination style | `[FILL from E3 — numbered recommended]` |
| ToC threshold | `[FILL from E4 — default ≥4 H2 AND ≥1000 words]` |
| Related-posts logic | `[FILL from E5 — automatic recommended]` |
| Structured data | `[FILL from F2 — BlogPosting + BreadcrumbList + FAQPage yes/no]` |
| OG image strategy | `[FILL from F3 — 1200×630 crop recommended]` |
| Feed content | `[FILL from F4 — full text recommended]` |
| CWV targets | `[FILL from G1]` |
| Hero image source | `[FILL from G2]` |
| Image formats | `[FILL from G3 — webp recommended]` |
| Fonts | `[FILL from G4]` |
| Analytics + consent | `[FILL from H1 + H2]` |
| Editor | `[FILL from I1]` |
| AI drafting user | `[FILL from I2 — none / username + role]` |
| Scheduled publishing / cron | `[FILL from I3]` |
| AI hero images | `[FILL from J1–J3 — model, archive copy]` |
| Excluded non-features | `[FILL from K1]` |
| Entry points | `[FILL from L1]` |
| Launch order | `[FILL from L2]` |
| Approved overrides | `[FILL — list explicitly, including any upstream contradictions]` |
| Deferred to implementation | `[FILL — anything answered "later"]` |

Blog Kit's `config.php` is filled from this table; each line in it names the intake question that sets it.

---

## §2. Scope

In scope: article authoring, publishing, rendering, SEO configuration, i18n (if §1 has more than one locale), performance, accessibility and analytics for a corporate blog at the scale captured in §1, on an existing WordPress site.

Out of scope (defaults; override via §0.K only when justified): comments, newsletter signup inside articles, cookie consent banner (handled site-wide), site-wide search UI, social sharing buttons, user accounts, paid subscriptions, tags, date and author archives, theme redesign.

---

## §3. Content Model

### 3.1 Post fields

Blog posts are WordPress posts (`post_type = post`). No custom post type: the posts page, feeds, sitemaps and SEO plugins all expect `post`.

| Field | WordPress source | Required | Notes |
|---|---|---|---|
| Title | `post_title` | yes | One per post. Rendered as the only `<h1>`. |
| Slug | `post_name` | yes | Generated from the title and editable; lowercase. Drafts created over REST have an **empty** slug unless one is sent explicitly; AI agents must always send `slug` (§16). |
| Language | multilingual plugin | only if §1 is multilingual | Set per post. Independent by default (§1.B4). |
| Category | `category` taxonomy | yes, exactly one | Enforced by Blog Kit at publish (§3.5). Never "Uncategorized". |
| Author | `post_author` (a WordPress user) | yes | Per §1.C1 (§3.4). |
| Hero image | featured image (`_thumbnail_id`) | yes | 16:9, at least 1600 px wide. Alt text is stored on the media item and required at publish. |
| Excerpt | `post_excerpt` | strongly recommended | 140–160 characters; the meta description and card text. Falls back to an automatic excerpt. |
| Published | `post_date_gmt` | automatic | Set on first publish; scheduling sets it in the future. |
| Modified | `post_modified_gmt` | automatic | Updated on every save. Drives "Updated" (§7.10), `dateModified` and sitemap `lastmod`. |
| Body | `post_content` (blocks) | yes | §3.2. |

**Do not store:**

- Reading time. It is computed at render (§12.2) and cached with the page by the page cache. A stored value goes stale on the first edit.
- A modified date. WordPress tracks it.
- A "featured" flag. The newest post is featured automatically (§8.3).
- An SEO title that merely repeats the post title. Set one in the SEO plugin only when it must differ.

### 3.2 Body blocks

Allowed blocks are configured in Blog Kit `config.php` (`allowed_blocks`) and enforced in the editor's inserter (`allowed_block_types_all`). Override the list only if §1.C6 captured a specific need.

- **Paragraph**; **Heading** limited to **H2, H3, H4**. No H1: it is reserved for the title. Blog Kit sets the Heading block's `levelOptions` default; this applies site-wide.
- **List** / list item, **Quote**.
- **Image**, with alt text. Captions use the block's caption.
- **Table**, with a header row (`<th scope="col">`) and a caption. Core prints the caption as `<figcaption>`, which names the figure; acceptable.
- **FAQ section**: Details blocks inside a Group (§13). This is why Group and Details are on the list.

Excluded by default: code, preformatted, video, embeds, pullquote, file, custom HTML, shortcode, columns, cover, media & text, buttons, gallery, classic/freeform. Add one only for a concrete editorial need, and record it in §1. Legacy content with excluded blocks keeps rendering; the list limits the inserter only.

### 3.3 Categories

- **Name** (translated per language by the multilingual plugin), **slug**, and **description**. The description is required: one or two sentences, shown on the category page and used as its meta description (§7.5).
- **Order**: Blog Kit `category_order` (a list of slugs); unlisted categories follow alphabetically.
- **"Uncategorized" must go.** Either rename it to a real category, changing **both its name and its slug**, or move its posts and delete it. Then set Settings → Writing → Default Post Category to a real category.
  - Blog Kit recognises the placeholder by its **name** in every site language ("Uncategorized", "Non classé"…), so the per-language copies multilingual plugins create (`uncategorized-fr`) count too.
  - It refuses to publish posts in it, hides it from the category navigation, and titles related posts "More from the blog" rather than "More from Uncategorized".
  - A slug-only rename leaves the placeholder in place; a name-only rename leaves `/category/uncategorized/` in the URL.
- Category count and editability come from §1.C2 and §1.C4.

### 3.4 Authors

Authors are WordPress users. The byline is the user's **Display name publicly as** setting; never show login names.

| §1.C1 | Setup | Author pages |
|---|---|---|
| Shared byline | One user, e.g. "Acme Editorial Team", set as author of every post. Editors keep their own accounts for editing. | Off (404) |
| Display name only (baseline) | Real users with a proper display name. | Off (404) |
| Full profiles | Plus Biographical Info, a profile photo (Gravatar or a local-avatar plugin) and social profile URLs in the SEO plugin's user fields (they become `sameAs`). | On: indexable author pages, Blog Kit `author_archives => true` |

When author pages are off:

- the SEO plugin's Person node has no `url` (Yoast omits it once author archives are disabled);
- Blog Kit prints author names as plain text: it removes the archive link from the Post Author Name and Post Author blocks and from `the_author_posts_link()`, and leaves other links (in a bio, say) alone;
- usernames still leak through `/wp-json/wp/v2/users` and `/?author=1`. Blog Kit 404s the latter; restrict the REST users endpoint with the security plugin if that matters to the client.

### 3.5 Publish guard (Blog Kit)

Blog Kit blocks the move to **publish** or **scheduled** until:

- the post has a title;
- it has **exactly one category**, and that category isn't "Uncategorized";
- it has a **featured image**;
- the featured image has **alt text**.

The editor shows the reason, e.g. "Not published yet. Please set a featured image; add alt text to the featured image (describe what the image shows)."

- **Scope.** The guard runs on REST saves, which cover the block editor and AI agents. It does not cover WP-CLI, the Classic Editor or XML-RPC.
- **Already-published posts** can still be updated, so legacy posts aren't locked.
- **Autosaves** are never blocked.
- **Legacy content.** Audit it during migration: every published post must have one real category, a featured image and alt text (§19).

---

## §4. Taxonomy

**Categories only** (per §1.C5). No tags.

- **Exactly one category per post**, enforced at publish (§3.5). If an SEO plugin's "primary category" is set, it wins for breadcrumbs and related posts.
- **Category pages are topic hubs** (§1.C7) at `/blog/category/<slug>/`.
  - Each one has an H1 with the bare category name, the description as an intro, the post cards and numbered pagination.
  - It is self-canonical and listed in the sitemap.
  - While a category has fewer posts than the threshold (default 3), Blog Kit marks it `noindex, follow` and removes it from the sitemap: thin pages stay out of the index without breaking navigation.
- **Category filter on the blog index = links to category pages.** Blog Kit's category navigation (§8.1) prints plain links: crawlable, no JavaScript, the back button works.
- **Tags off.** Blog Kit detaches tags from posts and makes tag archives and tag feeds return **404**. Before switching off, check existing tag URLs for traffic or backlinks (Search Console, Ahrefs). 301 the valuable ones to the closest category with the redirect manager. Also set tags to `noindex` in the SEO plugin so stale tag sitemaps disappear.
- **Date and author archives off.**
  - Blog Kit returns 404 for them, including their feeds and `/?author=N`.
  - It runs before Yoast's and Rank Math's own "disable archive" redirects, so the result is consistent: a 404, not a homepage redirect, which Google treats as a soft 404 anyway.
  - Author pages stay on only when §1.C1 = full profiles.
- **Attachment pages off.**
  - Set option `wp_attachment_pages_enabled` to `0`. Sites created before WordPress 6.4 still have it on, and there is no settings screen for it: `wp option update wp_attachment_pages_enabled 0`.
  - Attachment URLs then 301 to the file.
  - Yoast's "Redirect attachment URLs" setting keeps the option in sync.

Rationale: at corporate-blog scale, tags create thin archives and governance overhead, and AI authors invent arbitrary tags. Categories are few and curated, so their pages can earn their place in the index. Topic clusters come from in-body links and related posts.

---

## §5. URL Structure

### 5.1 Routes

Assuming §1.D1 = `/blog/%postname%/` (recommended) and Settings → Reading → "Your homepage displays: A static page", with a page whose slug is `blog` as the **Posts page**:

| Page | URL |
|---|---|
| Post | `/blog/<slug>/` |
| Blog index | `/blog/` (the Posts page) |
| Paginated index | `/blog/page/2/` (`/blog/page/1/` 301s to `/blog/`; past the last page → 404) |
| Category page | `/blog/category/<slug>/` (+ `/page/N/`) |
| Feed | `/feed/` (RSS 2.0, advertised in `<head>`); `/feed/atom/` also exists; `/blog/feed/` duplicates `/feed/` |
| Search results | `/?s=…` — core marks them `noindex, follow` |

Multilingual sites add the language directory: `/en/blog/<slug>/`, `/fr/blog/<slug>/` (but see §5.4 for the French index). Monolingual sites have no language segment, and the hreflang rules (§7.4) do not apply.

### 5.2 Rules

- **Every language is prefixed** when §1.B3 = every language prefixed (§6).
- **Flat post URLs.** No year or category in the path, which avoids recategorization redirect debt.
- **Trailing slash, lowercase.** WordPress's convention, enforced by core's canonical redirect. No query parameters in canonical URLs.
- **`with_front` side effects.** With `/blog/%postname%/`, WordPress also moves these under `/blog/`:
  - category, tag, author and date archives;
  - post-format archives;
  - **every custom post type registered with the default `with_front => true`** (portfolio, services and so on).
  - Pages, search and feeds stay at the root.
  - Audit custom post types *before* switching. Re-register any that must stay at the root with `'rewrite' => ['with_front' => false]`; WooCommerce products already do this.
- **Category base.** Leave it empty for `/blog/category/<slug>/`. A custom base such as `topics` drops the `/blog/` front (`/topics/<slug>/`) and changes every existing category URL.
- **Flush rewrites after any change** (Settings → Permalinks → Save, or `wp rewrite flush`), in a fresh request.

### 5.3 Changing an existing permalink structure

Verified on WordPress 7.1.3: after switching from `/%postname%/` to `/blog/%postname%/`, the old URLs return **404**. WordPress does **not** redirect them, because a bare `/<slug>/` is parsed as a page request and the 404 slug-guesser only matches post names. **Category URLs move too** (`/category/<slug>/` → `/blog/category/<slug>/`), and so does every custom post type registered with `with_front` (§5.2).

1. Before switching, export every live URL that will move: the post and category sitemaps, any `with_front` custom post type sitemaps, and the top landing pages from Search Console.
2. Switch on staging, then generate a 1:1 map from old URL to new URL: same slug, new prefix.
3. Implement the map as 301s:
   - Rank Math's Redirections module (free);
   - Yoast SEO Premium's redirect manager;
   - the free "Redirection" plugin;
   - or server rules, which are fastest.

   Use explicit rules, one per URL or one per section with a fixed prefix. Don't use a catch-all regex such as `^/([^/]+)/?$` → `/blog/$1/`: it also catches pages, attachment pages and endpoints that plugins add later.
4. **Safety net: Blog Kit's `redirect_old_urls`** (on by default) catches what the map misses. It acts only on a 404, and only when the site's structure has a front such as `/blog/`:
   - `/<slug>/` (with or without a language prefix) → the published post with exactly that slug;
   - `/category/<…>/<slug>/` and its `/page/N/` → that category page (default category base only);
   - query strings such as `utm_*` are kept.

   It runs after core's own redirects and after redirect managers (Rank Math: on `wp`), so an explicit rule always wins. It is a safety net, not the map: it can't know about renamed slugs or merged posts.
5. Crawl the old URL list (posts and categories) on staging: every one must 301 once to a 200.
6. After deploy, resubmit sitemaps in Search Console and watch the Pages report for two weeks.

New sites and sites without posts skip this subsection.

### 5.4 Multilingual URLs

- **Polylang (free)**:
  - Each language's Blog page is a translation of the primary one. Free Polylang cannot give two posts or pages the same slug, so the French Blog page cannot also be `blog`; it gets e.g. `/fr/le-blog/` or `/fr/articles/`.
  - Post URLs keep the shared `/blog/` base (`/fr/blog/<slug>/`); translating that base needs Polylang Pro.
  - Accept this, or use Polylang Pro or WPML (shared and translated slugs) when the URL pattern must be identical in every language.
- **WPML**: slugs only need to be unique within a language, so `/en/blog/` and `/fr/blog/` can coexist.
- Localized post slugs (§1.D2) are recommended either way.

---

## §6. i18n Handling

**Skip this section entirely when §1 is monolingual.**

Posts exist in any subset of the locales independently (§1.B4 = independent); there are no forced translation pairs. The blog index page is the one thing that **must** be linked across languages: each language's Blog page is a translation of the others, so hreflang and the language switcher can resolve it.

### 6.1 Wrong-language and missing posts

No URL may serve a post in one language under another language's prefix with a 200.

- **Polylang**: `/fr/blog/<english-slug>/` **301s to the post's real URL** (`/en/blog/<english-slug>/`). Verified. Acceptable: no duplicate, and the visitor still gets the article.
- **WPML**: set the post type to "Translatable – only show translated items". With "display as translated" fallback, WPML serves the English post at the French URL with a 200, which is not allowed. With the correct setting, such URLs 404.
- **Real 404s.**
  - The 404 template uses Blog Kit's not-found block.
  - It shows a message in the requested language and **one** path forward, "Browse the blog", linking to the blog index in that language.
  - When the slug exists in another language, the message says so ("This article isn't available in Français."). It never deep-links to the other-language post, because independent posts have no reliable equivalent.
  - The page has no hreflang and no Article JSON-LD.

### 6.2 Language switcher on blog routes

When §1.B4 = independent (recommended):

- On a post with no translation, the switcher's target for another language is **that language's blog index**, not that language's homepage. Blog Kit handles this:
  - **Polylang**: `pll_the_language_link` filter. Keep the switcher's "Hides languages with no translation" option **off**, or the item disappears instead of being redirected.
  - **WPML**: `wpml_ls_language_url` filter. Language switcher option "Link to home of language for missing translations", not "Skip language".
- Non-blog pages keep their normal per-page switching.

When §1.B4 = cross-linked: link the translations in the plugin; the switcher and post-level hreflang then resolve to the translation automatically.

### 6.3 Blog index per language

- Create one Blog page per language, linked as translations; assign it as the Posts page in the primary language. Polylang and WPML resolve `page_for_posts` to the current language's page automatically.
- Category pages exist per language. Categories are translated terms; link them when the topic is the same.
- Feeds are per language (`/fr/feed/`).

### 6.4 Localized UI strings

- **Theme strings.** In block themes, write translatable text in **PHP patterns** (`patterns/*.php`, `esc_html_e( 'More articles', 'theme-slug' )`), not in `.html` templates, which cannot be translated. Classic themes use `__()` / `_e()` in templates.
- **Blog Kit strings** use the `blog-kit` text domain ("On this page", "Updated", "More from %s", "All posts", the 404 messages).
  - Ship translations as `languages/blog-kit-<locale>.l10n.php` (WordPress 6.5+ PHP translation files, which Claude can write directly) or as `.po`/`.mo`.
  - Wording can be changed per site with the `blog_kit_label` filter.
- **Core blocks** (Time to Read, dates, pagination) are translated by WordPress language packs. Install each language's pack: Settings → General, or `wp language core install fr_FR`.
- **Dates** use the site's date format and the current language's locale (`wp_date`). Never hard-code month names.

---

## §7. SEO Metadata

### 7.1 SEO plugin settings

Set these in the plugin UI, or with WP-CLI where noted. They cover most of this section without code.

| Setting | Yoast SEO | Rank Math |
|---|---|---|
| Article type for posts = **BlogPosting** | Settings → Content types → Posts → Schema → Article type "Blog Post" (`wpseo_titles[schema-article-type-post]=BlogPosting`) | Titles & Meta → Posts → Schema Type "Article", Article Type "Blog Post" (default on new installs) |
| Organization name + logo (§1.A1, A4) | Settings → Site representation → Organization | Titles & Meta → Local SEO → **Person or Company = Company** (defaults to *Person* unless WooCommerce/EDD is active), name, logo |
| Breadcrumbs (§9.1) | Settings → Advanced → Breadcrumbs: show Blog page; **Content types → Posts → "Breadcrumbs title / taxonomy" = Category** (`post_types-post-maintax=category`; defaults to *none*) | General → Breadcrumbs: **enable** (off by default — and BreadcrumbList schema is only output when enabled), "Show Blog Page" on |
| Category pages indexable | Taxonomies → Categories: show in search results | Titles & Meta → Categories: index |
| Tags noindex (§4) | Taxonomies → Tags: hide from search results (`noindex-tax-post_tag`) | Default already noindex |
| Author / date archives | Settings → Advanced → Author archives **off** (`disable-author`): this also drops the author `url` from the Person node, which would otherwise point at a page Blog Kit 404s. Leave Date archives **on** (`disable-date` = false): with it off, Yoast 301s every `?m=` / `?year=` URL to the homepage before Blog Kit can answer 404. Blog Kit 404s date archives itself. | Titles & Meta → Authors → Author Archives **disabled**; Misc → Date Archives disabled (default). Blog Kit's 404 runs first. |
| Attachment URLs | Advanced → Media pages: off (redirect) | Titles & Meta → Media: Redirect Attachments |
| Meta description template for posts | `%%excerpt%%` | `%excerpt%` |
| Feed cleanup (comments off) | Settings → Advanced → Crawl optimization: remove global and post comment feeds | n/a (Blog Kit stops advertising comment feeds) |
| Sitemap | On (default). Replaces core's `/wp-sitemap.xml` with `/sitemap_index.xml` | On (default). Same URL; Categories sitemap on |
| Fresh install | — | Rank Math prints **nothing** until its setup wizard is completed or skipped |

Blog Kit adds what neither plugin does on its own:

- the 1200×630 OG crop (§7.6);
- `og:image:alt` (Yoast) and `twitter:image:alt` (both);
- FAQPage from the FAQ section (§13);
- noindex for thin category pages, plus sitemap exclusion (§4).

### 7.2 Structured data (JSON-LD)

One graph, printed by the SEO plugin. Nothing else may print Article, BreadcrumbList or Organization markup. Check for:

- theme microdata (`itemtype=` attributes; Astra and GeneratePress have toggles);
- a leftover schema plugin;
- a page builder's own schema.

**BlogPosting** (Yoast prints `"@type": ["Article","BlogPosting"]`, which is valid):

```json
{
  "@type": ["Article", "BlogPosting"],
  "headline": "<title>",
  "datePublished": "<post_date_gmt ISO>",
  "dateModified": "<post_modified_gmt ISO>",
  "author": { "@id": "…#/schema/person/…" },
  "publisher": { "@id": "https://example.com/#organization" },
  "image": { "@id": "https://example.com/blog/post/#primaryimage" },
  "mainEntityOfPage": { "@id": "https://example.com/blog/post/" },
  "articleSection": ["<category>"],
  "inLanguage": "<locale>"
}
```

- Google's Article docs list **no required properties**. Recommended ones are `author` (`name`, and `url` when a profile page exists), `datePublished`, `dateModified`, `headline` and `image`.
- The author is a Person with a `name`; `url` and `sameAs` only when §1.C1 = full profiles.
- Publisher logo guidance now lives in Organization markup (A4).

**BreadcrumbList**: `Home → Blog → <Category> → <Post title>`. The category is a real, linked page here, unlike the original Next.js spec, because category pages exist (§4). The SEO plugin generates it from the same trail it prints visibly (§9.1); keep the two identical.

**FAQPage**:

- Emitted only when the post has an FAQ section (§13); Blog Kit merges it into the SEO plugin's graph.
- Google retired FAQ rich results for all sites on 2026-05-07. The markup stays valid and harmless; it is kept for visible Q&A only and switched off with Blog Kit `faq_schema => false` if §1.F2 says so.

Any additional schemas from §0.F2 go here. HowTo rich results are retired too.

### 7.3 Canonical URLs

- Self-referencing, absolute, language-prefixed when multilingual, trailing slash, no query parameters. The SEO plugin prints them on every page type. Core alone prints canonicals only on single posts and pages, which is one more reason the SEO plugin is mandatory.
- Never cross-canonicalize between languages; that is hreflang's job.
- Paginated pages canonicalize to themselves (`/blog/page/2/` → `/blog/page/2/`), never to `/blog/`. Both Yoast and Rank Math do this by default.
- A child category reachable at two URLs (`/blog/category/parent/child/` and `/blog/category/child/`) relies on the SEO plugin's canonical; avoid nested categories at this scale.

### 7.4 Hreflang

**Skip when §1 is monolingual.** The multilingual plugin prints hreflang; the SEO plugins don't.

| Page | Expected hreflang (§1.B4 = independent) | Who prints it |
|---|---|---|
| Blog post | **None**: the post has no translations | Polylang (correct by default). WPML prints a self-only tag plus x-default for lone posts; tolerated, or strip it with the `wpml_hreflangs` filter. |
| Blog index, page 1 | Every language + `x-default` → primary language's index | Polylang prints the languages; **Blog Kit adds x-default** (Polylang adds it on the front page only). WPML adds x-default itself. |
| Blog index, page N | Each language that has a page N + `x-default` | **Blog Kit**: Polylang and WPML print nothing on paginated pages. (Blog Kit covers Polylang; with WPML, paginated pages stay without hreflang.) |
| Category page | The translated category, when linked | Polylang / WPML |

With Yoast or Rank Math active, WPML SEO moves hreflang from `<head>` into the XML sitemap by default. That is valid; just don't print both. When §1.B4 = cross-linked, post-level hreflang comes from the linked translations.

### 7.5 Meta description

- Source: the post's manual excerpt, through the SEO plugin's default template (`%%excerpt%%` in Yoast, `%excerpt%` in Rank Math). Override per post only when needed.
- Length: 140–160 characters, one sentence, primary keyword in the first 100 characters, no stuffing. Fact-check it like body copy: Google's October 2026 guidance calls out titles, descriptions, structured data and alt text in AI-assisted content.
- Category pages: the category description (§3.3).

### 7.6 Open Graph / Twitter Cards

The SEO plugin prints:

- `og:title`, `og:description`, `og:url`, `og:type=article`;
- `og:image` with width and height, `og:locale`;
- `article:published_time` and `article:modified_time`;
- `twitter:card=summary_large_image`.

Rank Math also prints `article:section`. Yoast doesn't, and that's fine.

- **OG image.** Per §1.F3:
  - Blog Kit registers `blog-og` (1200×630, hard crop) and puts it first in the plugin's candidate sizes: `wpseo_image_sizes` for Yoast, `rank_math/opengraph/image_sizes` for Rank Math.
  - Images uploaded before Blog Kit need `wp media regenerate --image_size=blog-og --yes`.
  - Yoast caches the chosen image in its index; run `wp yoast index --reindex` afterwards.
  - Keep it under 200 KB.
- **`og:image:alt` / `twitter:image:alt`.**
  - Yoast prints neither; Rank Math prints `og:image:alt` only. Blog Kit fills the gaps from the featured image's alt text.
  - It skips posts that have a custom social image, because the alt would be wrong.
- **Do not add:**
  - `article:author`, which expects profile URLs. Yoast prints it only when the author's Facebook field is filled in, so leave that field empty unless §1.C1 = full profiles.
  - `article:tag`: there are no tags.
  - `og:locale:alternate`, unless §1.B4 = cross-linked.

### 7.7 Sitemap

- The SEO plugin's sitemap (`/sitemap_index.xml`) replaces core's `/wp-sitemap.xml` (both plugins redirect the core URLs).
- `lastmod` is the real modified date. Rank Math's "Lock modified date" can keep trivial edits from bumping it.
- Exclusions:
  - Tags (noindex) and author archives.
  - Thin categories: Blog Kit adds them to the plugin's exclusion filters.
  - Attachments and post formats.
- If no SEO plugin runs (not recommended), core's sitemap is used. Blog Kit drops its users and tags providers and the thin categories.
- No sitemap index splitting is needed until the site approaches ~50k URLs.

### 7.8 robots.txt

WordPress serves a virtual robots.txt when there is no physical file and WordPress is at the domain root. Check for a physical file first: it overrides everything, including the SEO plugin's editor. The result should be:

```
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: https://<domain from §1>/sitemap_index.xml
```

- Do not block `/wp-content/` or `/wp-includes/`: Google needs the CSS, JS and images to render pages.
- Do not block AI crawlers. Organic visibility, including AI search, is the goal.
- Drafts and previews are noindexed per page, not through robots.txt.
- **Staging**: Settings → Reading → "Discourage search engines" **on** (`blog_public = 0`). **Production**: **off**. A staging copy migrated to production with this still on is the classic WordPress SEO disaster; it is a §19 item.

### 7.9 Feeds

- WordPress serves RSS 2.0 at `/feed/` (per language: `/fr/feed/`) and advertises it in `<head>` (`automatic-feed-links`, automatic in block themes). Atom also exists at `/feed/atom/` but isn't advertised; that's fine.
- Content per §1.F4: Settings → Reading → "For each post in a feed, include: Full text" (`rss_use_excerpt = 0`).
- With comments off, the comments feed must not be advertised. Blog Kit uses `feed_links_show_comments_feed`, and a post's comment feed (`/blog/<slug>/feed/`) returns 404. With Yoast's crawl-optimization toggle "Remove post comment feeds" on (§7.1), Yoast 301s those URLs to the post first; either answer is fine.
- Category feeds exist automatically and are fine. Tag and author feeds 404 with their archives.

### 7.10 "Updated on" date

- Shown visibly **only** when the post was modified more than 24 hours after publishing (Blog Kit's Updated block, `updated_threshold_hours`). A typo fix on launch day must not trigger it.
- Format: `Published <date> · Updated <date>`.
- `dateModified` in JSON-LD comes from the same field, `post_modified_gmt`. Yoast omits it when modified ≤ published. When the visible "Updated" date shows, it is the same date as `dateModified`.
- **Adding a featured image isn't an update.** A REST save of a published post that sends only `featured_media` (the hero script backfilling old posts, §20) keeps its modified date (Blog Kit `keep_modified_on_image`). Any other change still counts.

### 7.11 Unpublish, deletion and slug changes

- **Unpublished or trashed posts** return 404: WordPress does this. Make sure the page cache and CDN purge on status change (§14.7).
- **Fuzzy 404 redirects are disabled.**
  - Out of the box, WordPress 301s an unknown URL to any post whose slug starts with the same text. Verified: deleting `/blog/sample-post/` sent visitors to `/blog/sample-post-one/`.
  - With a multilingual plugin, that can even land in another language.
  - Blog Kit turns on `strict_redirect_guess_404_permalink`, so deleted posts honestly 404.
- **Slug changes** on published posts: WordPress records the old slug and 301s it automatically (`_wp_old_slug`). Verify after any bulk slug edit.
- **Deliberate removals** with backlinks: a 301 to the closest relevant post through the redirect manager, or a 410 if the plugin supports it. A plain 404 is the pragmatic default.

### 7.12 Staging gotchas

- **Search engine visibility.** Staging is noindexed; production must not be (§7.8).
- **Yoast only builds its index on production.**
  - Yoast stores its indexables only when `wp_get_environment_type() === 'production'`.
  - On staging and local sites, breadcrumbs and schema hierarchy can therefore differ from production. Verified: no category crumb on a `local` site.
  - To verify on staging, add a staging-only mu-plugin: `add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_true' );`.
- **Application Passwords** need HTTPS, or `WP_ENVIRONMENT_TYPE` = `local` (§16).

---

## §8. Blog Listing Page (`/blog/`)

### 8.1 Content per page

- **H1**: Blog Kit's archive-title block, which prints the Posts page title in the current language. Core's Query Title block prints **nothing** on the posts page, which would leave it without an H1. Verified.
- **Category navigation** under the H1: Blog Kit's category-nav block. It is a list of links to the category pages, plus "All posts", with `aria-current="page"` on the current view. It skips empty categories and "Uncategorized".
- **Posts per page** from §1.E1 (Settings → Reading).
- No search box, no author filter.

### 8.2 Card contents

One **card pattern** in the theme renders a post card everywhere: index, category pages, homepage feed and related posts. Each card shows:

- Featured image, 16:9 (`blog-card` size, 800×450). The image is **not** a separate link; the title is the single link per card.
- Category label (the post-terms block for category; it may link to the category page).
- Post title (H2 on listing pages; Blog Kit switches it to H3 inside related posts).
- Excerpt (1–2 lines).
- Published date.
- Reading time (the core Time to Read block at 238 wpm, no range, §12.2).

Show the author on cards only when §1.C1 = full profiles and it adds editorial signal (a multi-author blog). The author always appears on the article page.

### 8.3 Featured post

Per §1.E2. Default: **automatic**. The newest post renders as a larger card on page 1 only, with CSS, so pagination maths stay intact:

```css
.blog:not(.paged) .wp-block-post-template > li:first-child { grid-column: 1 / -1; }
```

No editorial "featured" flag.

### 8.4 Pagination

- Numbered pages: `/blog/`, `/blog/page/2/`, `/blog/page/3/`… The Query Loop must **inherit the main query** (`"inherit": true`). A custom Query Loop paginates with `?query-<id>-page=2`, which is duplicate content and isn't prefetched.
- Every paginated page is indexable and self-canonical. Never `noindex` pagination.
- `rel=prev/next` is not used by Google; each page stands on its own.
- No infinite scroll. "Load more" only if it also updates the URL.

### 8.5 Listing performance

- Images: core marks the first in-viewport large image `fetchpriority="high"` and lazy-loads images after the first three (`wp_omit_loading_attr_threshold`). Lazy images get `sizes="auto"`. Verified on the posts page.
- Link prefetching: core's speculative loading (WordPress 6.8+) prefetches on pointer-down, for logged-out visitors only. Keep the default. Don't add instant.page-style plugins, and don't raise eagerness to "eager" on listing pages.

---

## §9. Article Page

### 9.1 Layout

Top to bottom:

1. Breadcrumb: `Home → Blog → <Category> → <Post title>` (linked category). Use **the SEO plugin's breadcrumb** (Yoast: `yoast-seo/breadcrumbs` block or `yoast_breadcrumb()`; Rank Math: `[rank_math_breadcrumb]` shortcode or `rank_math_the_breadcrumbs()`; it has no block), so the visible trail matches its BreadcrumbList. Core's Breadcrumbs block (WordPress 7.0+) is fine visually, but it prints no schema and omits the Blog page on category pages.
2. Title (H1).
3. Metadata row: author · published date · updated date (if shown) · reading time.
4. Hero image (16:9).
5. Table of contents (when above threshold).
6. Body.
7. Related posts ("More from <Category>"), §11.
8. Site footer.

Desktop (≥1024 px): ToC in a sticky left rail. Mobile: collapsible ToC above the body.

### 9.2 Semantic HTML skeleton

```html
<main>
  <article>
    <header>
      <nav aria-label="Breadcrumb">…</nav>
      <h1>…</h1>
      <p>
        By <span>Author Name</span>
        · <time datetime="2026-04-13T09:00:00+00:00">April 13, 2026</time>
        · <span class="blog-kit-updated">Updated <time datetime="…">…</time></span>
        · <span>5 minutes</span>
      </p>
      <figure>…hero…</figure>
    </header>
    <nav class="blog-kit-toc" aria-labelledby="blog-kit-toc-title">
      <details><summary id="blog-kit-toc-title">On this page</summary><ol>…</ol></details>
    </nav>
    <div class="entry-content">… h2/h3 with ids …</div>
  </article>
  <aside class="blog-kit-related" aria-labelledby="blog-kit-related-title">
    <h2 id="blog-kit-related-title">More from <Category></h2>
    …
  </aside>
</main>
```

Rules:

- Exactly **one `<h1>`** per page. Check the theme: some classic themes print the site title as an `<h1>` on single posts.
- Heading hierarchy h1 → h2 → h3; no skipped levels.
- Every H2/H3/H4 in the body has a stable `id`. Blog Kit adds slugified ids at render, de-duplicates them (`-2`), keeps anchors set in the editor, and keeps non-Latin letters readable.
- `<time datetime>` on every date.
- The author is plain byline text. No `rel="author"` unless §1.C1 = full profiles.

### 9.3 Typography

- Body font size: **18 px** desktop, **17 px** mobile (`1.125rem` / `1.0625rem`; fluid type in `theme.json` is fine).
- Line height: **1.65** for body, 1.1–1.3 for headings.
- Line length: **~65 characters** (`max-width: 65ch` on the post content, or `contentSize` on the Post Content block's layout).
- Paragraph spacing ~0.75–1× the line height; never `<br>` for spacing.
- Pick sans-serif or a screen-optimized serif and commit.

### 9.4 Building the templates

**Block theme** (Site Editor / `templates/*.html`). Block markup verified on WordPress 7.1.3. Check for Site Editor customizations first: a customized copy in the database wins over the file.

`templates/single.html`:

```html
<!-- wp:template-part {"slug":"header"} /-->
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
<!-- wp:group {"tagName":"article","layout":{"type":"constrained"}} -->
<article class="wp-block-group">
<!-- wp:yoast-seo/breadcrumbs /-->
<!-- wp:post-title {"level":1} /-->
<!-- wp:group {"className":"blog-meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group blog-meta"><!-- wp:post-author-name /--><!-- wp:post-date /--><!-- wp:blog-kit/updated-date /--><!-- wp:post-time-to-read {"averageReadingSpeed":238,"displayAsRange":false} /--></div>
<!-- /wp:group -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","sizeSlug":"large"} /-->
<!-- wp:blog-kit/toc /-->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</article>
<!-- /wp:group -->
<!-- wp:blog-kit/related-posts /-->
</main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer"} /-->
```

With Rank Math, replace the breadcrumb line with `<!-- wp:shortcode -->[rank_math_breadcrumb]<!-- /wp:shortcode -->`. Without breadcrumbs from the SEO plugin, use core's `<!-- wp:breadcrumbs /-->` (§9.1). For the desktop rail, wrap the ToC and Post Content in a two-column group; the ToC column is `position: sticky`.

`templates/home.html` (blog index; `templates/category.html` is the same, plus `<!-- wp:term-description /-->` under the title):

```html
<!-- wp:template-part {"slug":"header"} /-->
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
<!-- wp:blog-kit/archive-title /-->
<!-- wp:blog-kit/category-nav /-->
<!-- wp:query {"queryId":1,"query":{"inherit":true}} -->
<div class="wp-block-query">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:pattern {"slug":"theme-slug/blog-card"} /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
</main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer"} /-->
```

The card pattern (`patterns/blog-card.php`) holds the card's blocks:

- `post-featured-image` with `{"aspectRatio":"16/9","sizeSlug":"blog-card"}`
- `post-terms` with `{"term":"category"}`
- `post-title` with `{"level":2,"isLink":true}`
- `post-excerpt`
- `post-date`
- `post-time-to-read` with `{"averageReadingSpeed":238,"displayAsRange":false}`

Set Blog Kit `card_pattern` to its slug so related posts reuse it. `templates/404.html` contains an H1 and `<!-- wp:blog-kit/not-found /-->`.

**Classic theme** (in a child theme): `home.php`, `single.php`, `category.php` and `404.php` with the same structure, using Blog Kit's template tags:

- `blog_kit_archive_title()`
- `blog_kit_category_nav()`
- `blog_kit_updated_date()`
- `blog_kit_reading_time()`
- `blog_kit_toc()`
- `blog_kit_related_posts()`
- `blog_kit_not_found()`

Related posts render the theme's `template-parts/blog-card.php` when it exists (it receives `$args['heading_level']`); otherwise they use a minimal card.

**Page builders**:

- Build the post and archive templates in the builder's theme builder.
- Use Blog Kit's shortcodes where the builder has no equivalent widget: `[blog_kit_toc]`, `[blog_kit_updated_date]`, `[blog_kit_reading_time]`, `[blog_kit_related_posts]`, `[blog_kit_category_nav]`, `[blog_kit_archive_title]`, `[blog_kit_not_found]`.
- Keep the builder **out of post content**: write posts in the block editor (§1.I1).
- Check the builder's own lazy-loading and schema settings against §7.2 and §14.

---

## §10. Homepage Feed

Per §1.L1. When the homepage feed is in scope:

- Show the **latest 3 posts** as cards, using the same card pattern as the blog index. Use a Query Loop that does **not** inherit (`"inherit": false`, `perPage: 3`) on the front page.
- It adds fresh internal links, editorial pulse and a crawler signal. Excerpt-level widgets are fine for duplicate content, because the full article lives on one canonical URL.
- Multilingual: the Query Loop shows the current language's posts automatically.

Build it **last** (per §1.L2 default) to avoid empty-state rework.

---

## §11. Related Posts

Block label: **"More from <Category>"** (localized via §6.4). Blog Kit's related-posts block or template tag.

Logic when §1.E5 = automatic (recommended):

1. Posts in the current post's category (its primary category if one is set), in the same language, newest first, excluding the current post. Take 3 (`related_count`).
2. If fewer than 3, fill the remaining slots with the latest posts from any category, in the same language.
3. Render 3 cards in an `<aside aria-labelledby>` below the article. Card titles are H3 under the aside's H2. In block themes the cards come from the configured card pattern, through a core Query Loop.
4. Never random, never manually curated (unless §1.E5 = manual). Nothing renders when the blog has no other posts.

The queries use `WP_Query` (not `get_posts`), so WPML and Polylang keep results in the current language. Verified with Polylang.

When §1.E5 = manual: an ACF Relationship field `related_posts`, read in the theme's single template, falling back to Blog Kit's automatic block when it is empty.

---

## §12. Table of Contents & Reading Time

### 12.1 ToC (Blog Kit)

WordPress 7.1 still has no core Table of Contents block (it remains experimental in Gutenberg), so Blog Kit provides one.

- **Conditional.** It renders only when the post has ≥N H2 headings AND ≥M words, per §1.E4 (`toc_min_h2`, `toc_min_words`). Below that it renders nothing. Words are counted with core's Time to Read counter, which handles languages counted in characters (Chinese, Japanese).
- **Content.** H2 entries only, no H3 nesting, which prevents clutter from AI over-nesting. Each entry links to the heading's id (§9.2).
- **Markup.** `<nav aria-labelledby>` → `<details>` → `<summary>` ("On this page") → `<ol>`. It is collapsed in the HTML, so mobile has no layout shift. On screens ≥1024 px a one-line inline script right after the `<nav>` opens it before first paint, so desktop has none either; the 1 KB deferred `toc.js` keeps it in sync on resize.
- **Legacy content.** Headings in Classic Editor posts (HTML without blocks) get ids and a ToC too, by the same rules.
- **Password-protected posts** get no ToC until unlocked (and no FAQ schema, §13), so headings and answers don't leak.
- **Desktop.** Sticky left rail, styled by the theme.
- **Current section.** The section being read is marked with `aria-current="true"` (IntersectionObserver).
- **Jumps.** Clicking an entry moves focus to the heading (`tabindex="-1"` + `focus()`). Headings get `scroll-margin-top` (`--blog-kit-scroll-offset`, default 6rem) so a sticky header doesn't cover them.
- **Do not use** a ToC plugin (Easy TOC and the like) or Rank Math's ToC block alongside it. Yoast's ToC block is Premium-only.

### 12.2 Reading time

- Uses the **core Time to Read block** (WordPress 6.9+) with `"averageReadingSpeed":238`.
  - **238 words per minute**, rounded to the nearest minute, minimum 1.
  - Set **`"displayAsRange":false`**: core's default is a range ("4–6 minutes").
- Core renders "5 minutes", translated by WordPress language packs.
- Classic themes and builders use `blog_kit_reading_time()` / `[blog_kit_reading_time]`, which render the same core block with the same settings.
- Computed at render and cached by the page cache. Never stored in post meta.
- Shown in the metadata row and on cards.

Yoast also computes a reading time (about 228 wpm, plus image time) for its "Est. reading time" Twitter label. It isn't shown on the page; ignore the small difference.

---

## §13. FAQ Section

A section inside the post body, not a separate post type. Skip it if §0.C6 excluded FAQs.

- **Structure.** A **Group** block with the class `blog-faq`, containing an H2 ("Frequently asked questions") and one **Details** block per question. The summary is the question; the inner paragraphs are the answer. Blog Kit registers this as the "FAQ section" block pattern; AI authors write the same markup (§16).
- **No JavaScript.** It renders as native `<details>`/`<summary>`.
  - Core's Accordion block (6.9+) is also parsed, if an editor uses it inside the group.
  - It is not the default: it loads the Interactivity API (~15 KB), and in 7.1 collapsed panels flash open until JavaScript runs.
- **FAQPage JSON-LD** mirrors the questions and answers.
  - With Yoast, the WebPage node gains the FAQPage type and the Questions are added to Yoast's graph.
  - With Rank Math, an FAQPage entity is merged into its WebPage.
  - Without an SEO plugin, a standalone block is printed.
  - With another SEO plugin (AIOSEO, SEOPress, The SEO Framework), nothing is printed, so there is never a second graph. Use that plugin's FAQ feature instead.
  - It is skipped if the SEO plugin's own FAQ block is used in the post, and on password-protected posts.
  - Answer HTML is limited to links, lists, paragraphs and emphasis.
- **Accessibility.** `<summary>` is focusable by default; give it a visible focus ring.
- **No rich result** in Google since May 2026 (§7.2). The section exists for readers first.

---

## §14. Performance Budget

### 14.1 Core Web Vitals targets

Tighter than the "good" baseline by default; that baseline is a pass threshold, not a quality target. Relax only if §1.G1 says so.

| Metric | Target | Baseline ("good", unchanged in 2026) |
|---|---|---|
| LCP | **< 2.0 s** | ≤ 2.5 s |
| INP | **< 150 ms** | ≤ 200 ms |
| CLS | **< 0.05** | ≤ 0.1 |
| TTFB | **< 600 ms** | < 800 ms |

Lighthouse (mobile): Performance, Accessibility, Best Practices, SEO all **≥ 90**.

### 14.2 JavaScript budget

| Item | Budget |
|---|---|
| JavaScript on the article page (gzipped, all sources) | < 170 KB |
| Third-party JS total (gzipped) | < 50 KB |
| Render-blocking third-party scripts | 0 |

- **jQuery.** Block themes load none by default; a single plugin can drag it back in. Audit with the browser's network panel.
- **Biggest INP offenders:**
  - chat widgets;
  - analytics loaded without `defer`;
  - sliders and page-builder runtimes;
  - embedded newsletter iframes;
  - Interactivity-API blocks you don't need, such as Accordion and Tabs.
- **Tabs (7.1)** server-renders every panel hidden. Never use it for primary content.

### 14.3 Image pipeline

- **Formats** (§1.G3).
  - Blog Kit stores new JPEG/PNG uploads as WebP (or AVIF) through `image_editor_output_format`. WordPress converts every generated size *and* the full-size file, keeps the original only as `original_image`, and never serves it. Verified.
  - Without conversion, the full-size original (often a multi-MB PNG from an AI model) is included in `srcset` and served to large screens.
  - Existing images are not converted retroactively; regenerate if needed.
  - Use exactly one converter: Blog Kit, Performance Lab Modern Image Formats, a CDN, or an optimization plugin, never two.
- **Hero.**
  - Core marks the featured image `fetchpriority="high"` and doesn't lazy-load it on single posts. Verified.
  - **Logo pitfall**: core gives `fetchpriority="high"` to only one image, the first in-viewport image of at least 50,000 px². A large raster logo in the header can take it. Use an SVG or small logo, or set the logo to `fetchpriority="low"` via the `get_custom_logo_image_attributes` filter.
- **Hero dimensions.** Source at least 1600×900, 16:9. Show it with `aspectRatio: 16/9` on the Featured Image block, or CSS `aspect-ratio`, so layout is reserved. Weight **< 150 KB** over the wire on mobile, i.e. the 768 w or 1024 w WebP size.
- **`sizes`.** Core's `sizes` isn't layout-aware. Choose the right `sizeSlug` (`large` for the article hero, `blog-card` for cards), or set `sizes` with the `wp_calculate_image_sizes` filter to match the rendered width (e.g. `(max-width: 768px) 100vw, 1200px`).
- **Placeholders.** Core has no LQIP. The Performance Lab "Image Placeholders" plugin adds a dominant-color background, which is enough. Blurry LQIP images don't improve LCP (Chrome ignores low-entropy images as LCP candidates).
- **Body images.** Lazy by default (core), always with width and height.
- **OG image.** 1200×630 (`blog-og`), < 200 KB (§7.6).
- **Metadata.** AI images carry a SynthID watermark in the pixels, which survives. The resized copies WordPress generates drop file metadata such as C2PA/IPTC. That's acceptable for web search; if provenance labels matter to the client, keep the original file.
- **MIME type.** A converted attachment's MIME type still reads `image/png` or `image/jpeg`, so some SEO plugins print that in `og:image:type`. Harmless.

**Alt text rules:**

- Every featured image needs alt text. Blog Kit blocks publishing without it, and the alt lives on the media item.
- Hero alt text **must describe the image content** (what is in frame), not restate the headline.
- Body images: informative images get descriptive alt text. Purely ornamental inline images get `alt=""`.
- Multilingual: one media item per language when alt text differs (the hero-image script does this, §20); with Polylang or WPML media translation, edit alt text per language.

### 14.4 Font loading

- Self-host. In block themes use `theme.json` `fontFace` with local `file:./assets/fonts/…` files, or Appearance → Fonts (stored in `wp-content/uploads/fonts`). No Google Fonts CDN.
- Core prints `font-display: fallback` by default. Set `"fontDisplay": "swap"` per face in `theme.json` if FOIT is visible.
- Max **2 families** (body and optional display) and **3 weights**; prefer one variable font file.
- Subsets: `latin`, plus `latin-ext` for FR/DE/ES/IT/PT/PL and similar.
- **Preload the body font only.** Core never preloads fonts, so use the `wp_preload_resources` filter:

  ```php
  add_filter( 'wp_preload_resources', fn( $r ) => array_merge( $r, array( array(
  	'href' => get_theme_file_uri( 'assets/fonts/body-var.woff2' ),
  	'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous',
  ) ) ) );
  ```

### 14.5 Motion

- CSS only: transform and opacity transitions; no layout animation on body content; no animation libraries for the blog.
- Everything honours `prefers-reduced-motion: reduce`.
- **Article hero parallax: optional.** A subtle CSS scroll-driven transform (±8–10%) on the hero image, wrapped in `@media (prefers-reduced-motion: no-preference)` and `@supports (animation-timeline: view())`.
  - The image must keep `fetchpriority="high"` and stay eager.
  - Clip the transform inside the aspect-locked container, so CLS stays 0.
  - No Ken Burns effect, no parallax on body images, no zoom.

### 14.6 Speculative loading

- Keep core's default: prefetch on pointer-down, logged-out visitors only, query-string URLs excluded.
- Hosts may change it via `WP_SPECULATIVE_LOADING_DEFAULT_MODE`/`_EAGERNESS` (7.1).
- Prerendering runs analytics before the visitor sees the page. Don't switch to prerender unless the analytics tag handles `prerenderingchange`.
- Remove duplicate link-preloading features (Perfmatters/FlyingPress "preload links", instant.page).

### 14.7 Caching and optimization plugins

- **Page cache.**
  - A full-page cache for anonymous visitors is mandatory for the TTFB target: host-level (Kinsta, WP Engine, SiteGround, LiteSpeed) or a plugin (WP Rocket, LiteSpeed Cache, W3TC, WP Super Cache).
  - Managed hosts often forbid cache plugins; use the host's cache.
  - Verify that publishing, updating and unpublishing purge the post, the blog index, its category pages, the homepage and the feed, including the CDN (Cloudflare APO needs its WordPress plugin).
- **One lazy-load layer.**
  - JavaScript lazy-loaders in WP Rocket, LiteSpeed, Perfmatters, FlyingPress or W3TC can lazy-load the hero even though core left it eager.
  - Disable theirs, or exclude above-the-fold images: WP Rocket's "Optimize critical images", Perfmatters' leading-image count, FlyingPress's automatic detection.
- **Delay-JS features** break consent banners, Consent Mode defaults, analytics and the ToC scripts. Exclude them, and exclude the keyword `blog-kit`: it matches the Blog Kit plugin's `assets/toc.js` and the ToC's inline opener (`data-blog-kit="toc"`). Delayed, the opener runs too late and the desktop ToC shifts the layout.
- **Generated critical / unused CSS** must be regenerated after every deploy; stale CSS causes CLS.
- **Object cache** (Redis/Memcached) helps dynamic and logged-in performance. Recommended, not required.

---

## §15. Accessibility (WCAG 2.2 AA)

| Rule | Target |
|---|---|
| Body text contrast | ≥ 4.5:1 |
| Large text contrast | ≥ 3:1 |
| Non-text / UI contrast | ≥ 3:1 |
| Focus indicator | ≥ 2 CSS px thick, ≥ 3:1 against adjacent colours; never `outline: none` without a replacement |
| Target size | ≥ 24×24 CSS px (category pills, pagination, ToC links) |
| Motion | Honours `prefers-reduced-motion: reduce` globally |

Required features:

- **Skip-to-content link**, the first focusable element and visible on focus. Block themes get one automatically when the template has a `<main>`; classic themes must provide it.
- **Alt text** on every informative image (§14.3). Never `alt=""` on heroes.
- **Tables** have a header row with `scope` and a caption; no layout tables.
- **FAQ** uses native `<details>`/`<summary>`.
- **ToC** is keyboard-operable, with focus moving to the heading on jump.
- **Language switcher** has an accessible name ("Choose a language"), and each item has `lang`/`hreflang` (Polylang does this).
- **Heading hierarchy** is never skipped (§9.2).
- Font sizes in `rem`; never lock `html { font-size }` below 100%.

Testing:

- Keyboard-only traversal: every interactive element is reachable and focus is always visible.
- Screen reader: `<main>`, `<article>`, `<aside>` and the ToC `<nav>` are announced with names.
- Automated: `axe-core` or the Lighthouse accessibility audit with zero critical violations.

---

## §16. Content Authoring Workflow

- **Editor.** Posts are written in the block editor (§1.I1). Draft → review → publish uses WordPress's built-in statuses; no custom workflow states.
- **AI drafting (§1.I2).** AI agents create drafts through the REST API (`POST /wp-json/wp/v2/posts` with `status: draft`). A human reviews and publishes.
  - **Use a dedicated user that can neither publish nor touch published posts.** With WP-CLI:

    ```bash
    wp role create ai_drafter "AI Drafter" --clone=author
    wp cap remove ai_drafter publish_posts edit_published_posts delete_published_posts
    ```

    Then create the user with that role. Cloning Author keeps media uploads (Contributors can't upload). Removing `edit_published_posts` matters: an Author can keep editing its own posts after a human publishes them.
  - **At publish, the reviewer sets the author** to the byline user (§3.4), so the AI account never appears as the byline.
  - **Create an Application Password for it**: Users → Profile, or `wp user application-password create <user> ai-drafts --porcelain`.
  - **Availability.** Application Passwords need HTTPS (or `WP_ENVIRONMENT_TYPE` = `local`).
  - **Header-stripping hosts.** On Apache/CGI hosts that strip the `Authorization` header, re-save Permalinks (core's `.htaccess` passes it through) or add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`.
  - **What every draft must send:**
    - `slug`: drafts have an empty slug otherwise;
    - `excerpt`;
    - exactly one category ID;
    - the language (multilingual sites);
    - block markup for the body: `<!-- wp:paragraph -->…`, `<!-- wp:heading -->…`, and the FAQ pattern markup from §13.
  - **Featured images** come from the hero script (§20) or a human. Adding one to a published post doesn't change its modified date (§7.10).
  - **Human review checks facts and wording**, including title, meta description, alt text and structured data (Google, October 2026).
- **Required at publish.** Title, one real category, featured image and alt text (§3.5, enforced).
- **Scheduled publishing (§1.I3).**
  - Core supports it. Scheduled posts publish when WP-Cron runs, and WP-Cron only runs on visits.
  - Use a real cron: `define( 'DISABLE_WP_CRON', true );` plus a server or host cron hitting `wp-cron.php` (or `wp cron event run --due-now`) every 5 minutes. This avoids "Missed schedule".
- **Preview.** Core preview works for logged-in users. For external reviewers, the "Public Post Preview" plugin creates expiring links. Previews are `noindex` by core.
- **Revisions.** Kept by core. Cap them (`define( 'WP_POST_REVISIONS', 20 );`) to keep the database lean.
- **Publishing sets `post_date`**; every later save updates `post_modified`, except a REST save that only sets the featured image (Blog Kit, §7.10). Bulk edits and some plugins also touch `post_modified`. Rank Math's "Lock modified date" exists for minor edits.

---

## §17. Analytics

- **Tool**: whatever §1 specifies, loaded site-wide by its plugin or the theme, never inside blog templates.
- **Consent model:**
  - Cookieless tool → load directly, no consent gate required.
  - Consent-gated tool → load only after the visitor has granted consent via the existing consent plugin (Complianz, CookieYes, Cookiebot, Borlabs…). Before consent, no analytics script is injected and no beacons fire.
- **Script loading:** `wp_enqueue_script( …, array( 'strategy' => 'defer' ) )` or the plugin's equivalent; never render-blocking in `<head>`.
- **No other tags** (no GA4, Meta Pixel or Hotjar) unless explicitly added to §1.H1. Each one counts against the third-party JS budget (§14.2).
- **Script weight**: ideally ≤ 5 KB gzipped.
- **Optimization plugins**: if a delay-JS feature is on, exclude the consent tool and the analytics loader (§14.7).

---

## §18. Stack & Dependencies

### 18.1 Platform

| Component | Requirement |
|---|---|
| WordPress | **7.0+** recommended (tested on 7.1.3); **6.9 minimum** (core Time to Read block). WordPress won't activate Blog Kit on older versions. On 6.9 everything works except the editor preview of Blog Kit blocks (`supports.autoRegister` is 7.0+): place them in templates as markup, or use the shortcodes. WordPress 7.0 requires PHP 7.4+. |
| PHP | 8.1+ recommended (8.3 tested). |
| Theme | Block theme (preferred) or a classic child theme. Never edit a third-party parent theme. |

### 18.2 Plugins

Required:

```
<SEO plugin>       Yoast SEO or Rank Math — exactly one (§7.1)
Blog Kit           this skill's site-plugin/blog-kit/, renamed per project (§18.3)
<page cache>       host-level or plugin (§14.7)
```

Conditional:

```
Polylang / WPML                         only if §1 is multilingual (§6)
Redirection (or SEO plugin redirects)   only if §5.3 applies or tags with traffic are removed
Performance Lab: Image Placeholders     optional (§14.3)
Performance Lab: Modern Image Formats   only if §1.G3 = AVIF with <picture> fallback
ACF                                     only if §1.E5 = manual
Public Post Preview                     only if external reviewers need draft links
```

Deliberately not used:

- reading-time plugins (core block);
- table-of-contents plugins (Blog Kit);
- related-posts plugins (Jetpack Related Posts, YARPP: heavy, off-site queries);
- social-share plugins;
- separate schema plugins (duplicate graphs);
- "disable comments" plugins (Blog Kit closes them on posts).

Install the latest stable versions and let the host's or the agency's update policy track them. Don't copy version numbers from this file.

### 18.3 Blog Kit (site plugin)

`site-plugin/blog-kit/` in this skill. Copy it to `wp-content/plugins/<project>-blog/` and set the plugin name in the header.

- **Keep `Update URI: false`**, so wordpress.org can never "update" it with an unrelated plugin of the same slug.
- **Fill `config.php`** from §1; each key names its intake question.
- **Keep the plugin in the site's repository** and deploy it like theme code.

| Module | Spec | What it does |
|---|---|---|
| `content-model.php` | §3, §4, §7.10, K1 | Allowed blocks; H2–H4 headings; tags detached and hidden on posts; author names unlinked when author pages are off; comments closed and hidden; publish guard; featured-image-only saves keep the modified date |
| `archives.php` | §4, §5.3, §7.11 | Tag/date/author archives, their feeds and post comment feeds → 404; old post/category URLs → 301 after a permalink change; strict 404 guessing; core-sitemap cleanup |
| `media.php` | §14.3 | `blog-card` (800×450) and `blog-og` (1200×630) sizes; WebP/AVIF output |
| `toc.php` | §9.2, §12.1 | Heading ids (block and Classic Editor content); ToC block |
| `post-meta.php` | §7.10, §12.2 | Updated-date block; reading-time wrapper for classic/builders |
| `related-posts.php` | §11 | Related-posts block |
| `listing.php` | §8.1 | Blog index/category H1; category navigation |
| `faq.php` | §13 | FAQ pattern; FAQPage into Yoast/Rank Math/standalone |
| `seo.php` | §7.6, §4 | OG crop preference; image alt tags; thin-category noindex + sitemap exclusion |
| `i18n.php` | §6, §7.4 | Switcher fallback; x-default and paginated hreflang (Polylang); REST `blog_kit_lang`; not-found block |

Blocks (each also a shortcode `[blog_kit_*]` and a template tag `blog_kit_*()`):

- `blog-kit/toc`
- `blog-kit/updated-date`
- `blog-kit/related-posts`
- `blog-kit/category-nav`
- `blog-kit/archive-title`
- `blog-kit/not-found`

Reading time is the core block (shortcode/template tag for classic themes).

The SEO integrations target Yoast SEO and Rank Math, and fall back to core when no SEO plugin runs. With another SEO plugin they stay out of the way (§1.F1).

### 18.4 WordPress gotchas

- **Site Editor customizations override theme files** (§ ground rules). Reset or edit the database copy, not just the file.
- **Permalink changes need a rewrite flush** in a fresh request, plus a redirect map for existing content (§5.3).
- **`with_front`** moves custom post types under `/blog/` (§5.2).
- **"Discourage search engines"** must be off on production (§7.8).
- **Yoast on staging** doesn't build its index (§7.12). **Rank Math** prints nothing until its wizard is completed or skipped.
- **Application Passwords** need HTTPS (or `WP_ENVIRONMENT_TYPE=local`) and an `Authorization` header that survives the host (§16).
- **Drafts created over REST** have an empty slug unless one is sent (§16).
- **One converter, one lazy-loader, one schema graph, one sitemap.**
- **Core Time to Read defaults to a range**: set `displayAsRange: false` (§12.2).
- **Core Query Title prints nothing on the posts page**: use Blog Kit's archive-title (§8.1).
- **Core 404 guessing** redirects deleted posts to similar slugs unless strict mode is on (§7.11; Blog Kit).

---

## §19. Pass/Fail Checklist

A blog is "done" only when every applicable item is true **on production** (verify on staging first). Items tied to §1 conditionals are skipped when the condition doesn't apply.

### Environment & settings

- [ ] Changes made on local/staging first; production deployed from them
- [ ] Production: Settings → Reading → "Discourage search engines" **off**; staging: on
- [ ] Permalinks per §1.D1; rewrite rules flushed; custom post types checked for `with_front`
- [ ] Posts page assigned (Settings → Reading); posts per page per §1.E1
- [ ] Default category is a real category; no published post in "Uncategorized"
- [ ] `wp_attachment_pages_enabled` = 0
- [ ] (If §5.3 applies) every old post and category URL 301s once to its new URL (crawl the exported list)

### Content model

- [ ] Every published post has exactly one real category, a featured image and alt text (migration audit done)
- [ ] Publish guard active: publishing without a category/image/alt text is refused with a clear message
- [ ] Editor inserter offers only the §1.C6 blocks; heading levels H2–H4 only
- [ ] Tags detached from posts (unless §1.C5 = include); valuable old tag URLs redirected
- [ ] Authors per §1.C1, shown by display name

### Routing

- [ ] Posts at `/blog/<slug>/` (or the §1.D1 structure); flat, lowercase, trailing slash
- [ ] Blog index at `/blog/`; pagination at `/blog/page/N/` (index Query Loop inherits the main query)
- [ ] Category pages at `/blog/category/<slug>/`; category navigation links to them
- [ ] Tag, date and author archives (and their feeds, `/?author=1`, `?m=` / `?year=` and post comment feeds) return 404 (author pages only if §1.C1 = full)
- [ ] No internal links to those 404s: no tag lists, linked author names or date links in templates, menus or widgets
- [ ] Unknown or deleted post URLs return 404, never a redirect to a similar slug
- [ ] (Multilingual) Every language prefixed; wrong-language post URLs never return 200 with foreign content
- [ ] (Multilingual) Switcher on untranslated posts leads to the target language's blog index

### SEO

- [ ] Exactly one JSON-LD graph per page, from the SEO plugin; no theme microdata or second schema source
- [ ] Article node typed BlogPosting, with headline, dates, author, image, publisher (Organization with logo), inLanguage
- [ ] BreadcrumbList `Home → Blog → <Category> → <Post>`, identical to the visible breadcrumb
- [ ] (§1.F2) FAQPage present only on posts with an FAQ section
- [ ] Self-referencing absolute canonical on every page type, including `/blog/page/N/` and category pages
- [ ] (Multilingual) hreflang per §7.4: none on independent posts; full set + x-default on the blog index; page-N sets on paginated pages
- [ ] Meta description from the excerpt (140–160 chars); category pages use the category description
- [ ] `og:image` is the 1200×630 crop (< 200 KB), with `og:image:alt` and `twitter:image:alt`; no `article:author` (unless full profiles)
- [ ] Sitemap at `/sitemap_index.xml` with real `lastmod`; no tags, authors or attachments; thin categories absent
- [ ] robots.txt allows all, disallows `/wp-admin/` (allows `admin-ajax.php`), lists the sitemap; no physical file overriding it unintentionally
- [ ] RSS feed advertised (`/feed/`, per language); comments feed not advertised
- [ ] Visible "Updated" only when modified > 24 h after publishing, matching `dateModified`
- [ ] Every paginated and category page indexable (except categories below the §1.C7 threshold)

### Article page

- [ ] Exactly one `<h1>`; no skipped heading levels; every body H2/H3 has a stable `id`
- [ ] `<main>`, `<article>`, `<time datetime>`, author byline, `<aside>` for related posts
- [ ] ToC renders only above the §1.E4 thresholds (Classic Editor posts included; never on locked password-protected posts): `<nav>` + `<details>`, sticky on desktop and open before first paint, collapsed on mobile, focus moves on jump
- [ ] Reading time from core Time to Read at 238 wpm, single value (no range)
- [ ] Related posts: 3 same-category, same-language posts newest first, topped up with latest posts; H3 card titles
- [ ] Breadcrumb visible: Home → Blog → Category → Post

### Listing page

- [ ] One `<h1>` on the blog index (the Posts page title) and on category pages (bare category name)
- [ ] Posts per page per §1.E1; featured first card on page 1 only
- [ ] Cards show image, category, title, excerpt, date, reading time (author only if §1.C1 = full); one link per card
- [ ] Category navigation with `aria-current="page"` on the current view; no "Uncategorized"

### Performance

- [ ] LCP < 2.0 s, INP < 150 ms, CLS < 0.05 (mobile, field data or lab equivalent), or the §1.G1 baseline
- [ ] Lighthouse mobile ≥ 90 in all four categories
- [ ] JS on article page < 170 KB gzipped; third-party < 50 KB; no render-blocking third-party scripts
- [ ] New uploads stored as WebP/AVIF (§1.G3); no full-size PNG/JPEG original in any `srcset`
- [ ] Hero has `fetchpriority="high"`, is not lazy-loaded (not by a cache plugin either), < 150 KB on mobile, with a descriptive alt; the logo doesn't take the high priority
- [ ] Every image has width/height (or a sized container)
- [ ] Self-hosted fonts, ≤ 2 families / 3 weights, latin(+ext), body font preloaded
- [ ] Page cache on; publish/update/unpublish purges post, index, category pages, home, feed and CDN
- [ ] Exactly one image converter, one lazy-load layer, no duplicate link preloading

### Accessibility

- [ ] Text contrast ≥ 4.5:1; focus ring ≥ 2 px and ≥ 3:1
- [ ] Interactive targets ≥ 24×24 px
- [ ] Skip-to-content link present and visible on focus
- [ ] Body prose max-width ~65ch, 18 px base (17 px mobile), line-height 1.65
- [ ] All animations honour `prefers-reduced-motion`
- [ ] FAQ uses `<details>`/`<summary>`; tables have header `scope` and a caption
- [ ] Keyboard-only traversal verified; screen reader announces landmarks and the ToC nav
- [ ] `axe-core` / Lighthouse a11y audit: zero critical violations

### i18n (skip if monolingual)

- [ ] Blog page exists per language, linked as translations; feeds per language
- [ ] Blog Kit and theme strings translated for every locale (`.l10n.php` or `.mo`); WordPress language packs installed
- [ ] Dates and reading time render in the page's language
- [ ] 404 under each language links to that language's blog index

### Build & quality

- [ ] Blog Kit installed as `<project>-blog` with `Update URI: false` and `config.php` filled from §1
- [ ] `php -l` clean on theme and Blog Kit files; no PHP notices/warnings with `WP_DEBUG` on (staging)
- [ ] No hard-coded UI strings in templates (patterns / gettext only)
- [ ] Rich Results Test and Schema Markup Validator pass on a sample post

---

## §20. AI-Generated Hero Images

Skip entirely when §1.G2 ≠ AI. Otherwise this section is self-contained: the style-guide template in `blog-image-style-guide.md` and the generator script in `scripts/`.

### 20.1 Model & tooling

- **Model** per §1.J2. Default `gemini-3-pro-image` (Nano Banana Pro, GA since 2026-05-28) on the Gemini API:
  - about $0.13 per 1K/2K image and $0.24 per 4K;
  - chosen over the cheaper variants because it follows strict brand rules ("no text", precise colours, element placement) reliably on the first try.
- **Alternatives on the same key:**
  - `gemini-nano-banana-2.1`: cheaper, about $0.03–0.05.
  - `gemini-3.1-flash-lite-image`: cheapest, 1K only.

  Retired: `gemini-3-pro-image-preview`, `gemini-2.5-flash-image`, Imagen 4. **Model IDs change; the script verifies the model before spending anything.** List what your key can use:
  `curl -H "x-goog-api-key: $GEMINI_API_KEY" https://generativelanguage.googleapis.com/v1beta/models | grep -i image`
- **Request.** `generateContent` with `responseModalities: ["IMAGE"]` and `imageConfig: { aspectRatio: "16:9", imageSize: "2K" }`. 16:9 at 1K is 1344×768; 2K is about twice that.
- **Output.** PNG by default, and you can't choose another format on this API. The script detects the real type from the file bytes. WordPress converts it to WebP or AVIF on upload (§14.3).
- **Auth.**
  - `GEMINI_API_KEY` from [aistudio.google.com/apikey](https://aistudio.google.com/apikey).
  - WordPress: `WP_URL`, `WP_USER` (an **Editor** or Administrator: it must upload media and edit others' drafts) and `WP_APP_PASSWORD`.
  - Keep all of them in `.env.local` or the shell, never committed.
- **Upload limits.** A 2K PNG can be several MB. If the host's `upload_max_filesize` is low, set `GEMINI_IMAGE_SIZE=1K`; the script explains a 413.

### 20.2 Style guide

The style guide lives in `blog-image-style-guide.md`. Walk its intake questions first to tailor the brand voice, palette, references, topic table and worked examples to this project. The structure is universal; the content is per-project.

### 20.3 Generator script

`scripts/generate-hero-images.mjs`: Node 20+, no npm dependencies. Copy it, together with `scripts/hero-topics.example.json` renamed to `hero-topics.json`, into the project's `scripts/` folder. The script reads `hero-topics.json` from its own folder, then from the current folder; or pass a path as the first argument.

**Topics file:**

```json
{
  "locales": ["en", "fr"],
  "topics": [
    {
      "posts": { "en": "how-to-choose-a-trade-show-booth", "fr": 482 },
      "prompt": "Editorial documentary photograph, 35mm lens … No text, no words, no logos.",
      "alt": { "en": "Gloved hands tightening an aluminium frame joint…", "fr": "Mains gantées…" },
      "title": { "en": "Exhibition stand under construction", "fr": "Stand en montage" }
    }
  ]
}
```

- **`locales`** lists §1.B1 in order; the first one is the primary.
- **Posts** are referenced by **slug or numeric ID**. Use IDs for drafts, which have no slug until one is set.
- **Missing posts.** A language whose post doesn't exist is skipped (§1.B4 = independent).
- **Language check (multilingual).** Each post must be in its topic language, read from Blog Kit's `blog_kit_lang` REST field. Slug lookups also send `lang`, which WPML and Polylang Pro filter by.
  - A slug that exists only in another language is skipped for that language.
  - A numeric ID that points at another language fails the topic.
  - Two languages resolving to the same post fail the topic.
- **One image per topic, one media item per language**, so each language keeps its own alt text.

**What it does, per topic:**

1. Finds each language's post through the REST API (any status).
2. Skips posts that already have a featured image, unless `--force`.
3. Generates one image, skipping the model's interim "thought" images.
4. Optionally saves a local copy under `HERO_ARCHIVE_DIR/<primary-slug>.<ext>` (§1.J3).
5. Uploads it with the language's alt text and title, attached to the post.
6. Sets it as the featured image and verifies that WordPress kept it (WordPress ignores a bad `featured_media` silently). The post's modified date doesn't change (§7.10), so backfilled posts don't show "Updated".

```bash
node scripts/generate-hero-images.mjs --dry-run           # resolve posts, print the plan, no spend
node scripts/generate-hero-images.mjs                     # every topic still missing a hero
node scripts/generate-hero-images.mjs my-slug other-slug  # subset (primary-language slug or ID)
node scripts/generate-hero-images.mjs --force my-slug     # replace one topic's hero
node scripts/generate-hero-images.mjs --force --all       # replace every hero (deliberately explicit)
```

`--force` without slugs or `--all` is refused, so a stray flag can't replace every hero on the site. Unknown options are refused too.

**Environment:**

- `WP_URL`, `WP_USER`, `WP_APP_PASSWORD`
- `GEMINI_API_KEY`
- Optional: `GEMINI_IMAGE_MODEL` (default `gemini-3-pro-image`), `GEMINI_IMAGE_SIZE` (`1K`/`2K`/`4K`, default `2K`), `HERO_ARCHIVE_DIR`

The script uses `?rest_route=` URLs, so it works with any permalink setting.

**With WP-CLI access instead:** generate the images, then attach each one with

```bash
wp media import hero.png --post_id=123 --title="…" --alt="…" --featured_image
```

### 20.4 Frontend integration

- The hero is the post's featured image. It renders through the Featured Image block or `the_post_thumbnail()`, with `fetchpriority="high"` set by core (§14.3).
- An optional archive copy (§1.J3) exists only for design review and disaster recovery. The site always serves the media library's converted sizes. Media folders usually aren't in git; commit the archive folder only if the client wants image history in the repository.
- Optional subtle parallax per §14.5.
- Re-run with `--force <slug>` to regenerate one article's hero without touching the others. Old media items stay in the library; delete them there if needed.
- The same image is shared across languages by default, with per-language alt text. If a project needs locale-specific imagery (rare, since images contain no text), give that language its own topic entry.

### 20.5 Checklist

- [ ] `GEMINI_API_KEY`, `WP_URL`, `WP_USER` (Editor), `WP_APP_PASSWORD` set locally; nothing committed
- [ ] Model from §1.J2 verified (the script fails fast if not)
- [ ] `blog-image-style-guide.md` intake completed and tailored to this project
- [ ] `hero-topics.json` has one entry per article, with alt text per language
- [ ] `--dry-run` resolves every post; one smoke-test image reviewed for brand adherence before the batch
- [ ] Uploaded images converted to WebP/AVIF; hero renders with `fetchpriority="high"` and the 16:9 crop
- [ ] Alt text describes what's in frame (not the headline), reviewed by a human
- [ ] `og:image` resolves to the image's 1200×630 crop

---

## Appendix A — Open decisions deferred to implementation

Most project-specific deferrals live in §1. Items below are implementation-time decisions that don't belong in the profile:

- Exact sticky-ToC breakpoint, if the site-wide layout disagrees with the default ≥1024 px.
- Whether the ToC highlight should also track H3s if H3 entries are added later.
- Whether body images use captions routinely (if yes, style `<figcaption>`; if rare, leave the default).
- Redirect implementation (SEO plugin, Redirection plugin or server rules) when §5.3 applies.
- Whether paginated listing pages get a "Page N" suffix in the `<title>` (the SEO plugin's title template).

## Appendix B — Deliberate non-features

Considered and excluded by default. Move an item into scope only by an explicit decision in §0.K.

- Tags and tag pages
- Date archives
- Author archive pages (unless §1.C1 = full profiles)
- Comments
- Site-wide search / blog search UI
- Social sharing buttons
- Newsletter signup inside articles (site-wide footer only)
- Reading progress bar
- Dark mode toggle specific to the blog (inherits site-wide if any)
- Embedded video, code blocks, pull quotes, downloadable files
- Page builders inside post content
- Cross-language translation linkage (only when §1.B4 = cross-linked)
- Post-level hreflang (only when §1.B4 = cross-linked)
- A separate schema plugin, a second sitemap, a second image converter or lazy-loader
- Multiple image crops in `BlogPosting` JSON-LD. A single hero image is emitted; Google accepts this. Multiple aspect ratios (16:9, 4:3, 1:1) are a "best results" suggestion that adds complexity without proportional return at this scale.
