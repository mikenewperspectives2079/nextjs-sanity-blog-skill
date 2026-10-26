# WordPress Blog (Claude Code skills)

Two Claude Code skills for launching WordPress sites:

- **building-site-identity** interviews you about the person or organization behind a new site and writes their identity profile (story, credentials, audience, voice, topics, content rules) as a Markdown file plus a Google Doc, the reference every article is written from.
- **building-wordpress-blog** adds an SEO-first blog to the site.

The identity skill is tuned for sites run by people in addiction recovery, recovery and mental-health advocates, therapists and other clinicians, and nonprofits, where getting the facts and the language right matters most.

## Identity profiles (building-site-identity)

Say "new site for <name>" and Claude:

1. Asks a few setup questions, including consent: the person or organization approves their own profile.
2. Reads what already exists (an interview transcript, their bio, their site) and checks public records (NPI Registry for clinicians, nonprofit records), so it asks fewer questions.
3. Asks only the gaps, in short rounds: identity, story, credentials, audience, voice, topics and stances, boundaries, brand.
4. Writes the profile, with a source on every fact, a story bank of approved anecdotes, and the rules any AI writer must follow (no invented stories or credentials, safe messaging on suicide, crisis resources, no client testimonials on clinicians' sites).
5. Saves it as Markdown and as a Google Doc for review, and pulls the person's edits back from the Doc.

It includes a 45-minute, trauma-informed interview guide: one recorded call answers most of the questions in the person's own words. Profiles hold health information, so they live in a private workspace and Drive folder, never in this repository.

## The blog (building-wordpress-blog)

An opinionated, batteries-included spec for adding a blog to a WordPress site. Say "add a blog to this WordPress site". The agent then:

1. Scans the site.
2. Walks 46 intake questions.
3. Drafts a one-page plan and waits for your approval.
4. Implements against a technical spec that covers SEO, i18n, performance, accessibility and AI hero images, ending on a pass/fail checklist.

Aimed at corporate blogs. Organic-search-first. Works with the site you already have (block theme, classic theme or page builder; Yoast SEO or Rank Math; Polylang or WPML), configuring them instead of fighting or duplicating them.

### What the blog skill gives you

- **A 46-question intake** that covers every fork that changes the build:
  - the site's environment, theme type, SEO plugin and multilingual plugin;
  - locales, author model, categories, permalinks;
  - analytics and consent, AI imagery, and the rest.

  The agent detects most answers from the site itself, so you mostly confirm defaults.
- **A one-page plan template** the agent fills in from your answers and stops at, waiting for explicit sign-off before touching the site.
- **An 18-section technical spec**:
  - settings tables for Yoast SEO and Rank Math, and the multilingual rules for Polylang and WPML;
  - URL structure, with a redirect plan for sites that change permalinks;
  - structured data, Open Graph, sitemaps, robots.txt and feeds;
  - performance budgets tighter than Google's "good" baseline, and WCAG 2.2 AA targets;
  - template skeletons for block themes, classic themes and page builders.
- **Blog Kit**, a small site plugin for the gaps WordPress leaves:
  - **Navigation:** table of contents, heading anchors, related posts, category navigation, and a proper H1 on the blog index.
  - **Byline:** the "Updated" date and reading time.
  - **FAQ:** an FAQ section plus its schema.
  - **Guardrails:** a publish guard (no post goes live without one real category, a featured image and alt text), honest 404s for tag, date and author archives with no leftover links to them, a safety-net 301 for old post and category URLs after a permalink change, WebP/AVIF uploads.
  - **SEO and language fixes:** a 1200×630 share image with alt text, noindex for thin categories, the language-switcher fallback and hreflang gaps.

  It ships as blocks, shortcodes and template tags, so it works with any theme type.
- **An AI hero-image generator** (Node, no dependencies). It makes on-brand 16:9 images with Google's Gemini image models, uploads them to the media library with per-language alt text, and sets them as featured images. A per-project style-guide template comes with it.
- **A pass/fail checklist** that is the definition of done.

## Install

**As a Claude Code plugin** (recommended):

```
/plugin marketplace add mikenewperspectives2079/nextjs-sanity-blog-skill
/plugin install building-wordpress-blog@wordpress-blog
```

**Manual**: clone the repo and copy the skill folder into your Claude skills directory.

```
git clone https://github.com/mikenewperspectives2079/nextjs-sanity-blog-skill.git
cp -R nextjs-sanity-blog-skill/skills/building-wordpress-blog ~/.claude/skills/
```

If you rename the repository (for example to `wordpress-blog-skill`), update the URLs above and in `.claude-plugin/`.

## How to use it

Open Claude Code in the site's project folder: a full WordPress install, a Bedrock project, a local site (LocalWP, wp-env, DDEV, Lando), or just the theme's repository. Then say one of:

- "add a blog to this WordPress site"
- "set up the blog section"
- "build the blog templates and SEO setup"

Claude will:

1. Scan the site: WordPress version, theme type, plugins, permalinks, existing posts, categories, tags, and what the front end currently outputs. It uses WP-CLI when available and the REST API otherwise.
2. Walk the intake questions, showing recommended defaults so you can move quickly.
3. Hand you a one-page implementation plan and wait for your sign-off.
4. Build it on a local copy or staging, phase by phase:
   1. settings;
   2. SEO plugin;
   3. multilingual setup;
   4. Blog Kit;
   5. templates;
   6. content migration;
   7. hero images.

   It finishes on the pass/fail checklist. Settings it can't change itself (no WP-CLI) come back to you as a click-by-click admin checklist.

## What's in the skills

- `skills/building-site-identity/SKILL.md`: the identity-profile workflow.
  - `identity-questionnaire.md`: the question bank (Round 0 setup, rounds 1–9, branches for people and organizations).
  - `interview-guide.md`: the recorded-interview script.
  - `identity-profile-template.md` and `private-notes-template.md`: the outputs.
- `skills/building-wordpress-blog/SKILL.md`: the orchestrator. Workflow, site-scan checklist, verification steps, fallbacks for non-interactive environments.
- `skills/building-wordpress-blog/blog-technical-requirements.md`: the engineering spec.
  - §0 holds the intake questionnaire; §1 is the per-project profile filled in from the answers.
  - §2–§18 is the universal spec that reads from §1.
  - §19 is the pass/fail checklist; §20 covers AI hero images.
- `skills/building-wordpress-blog/blog-image-style-guide.md`: a brand-tailorable template for AI-generated hero images.
- `skills/building-wordpress-blog/site-plugin/blog-kit/`: the Blog Kit site plugin, with its own README and a French translation as a template.
- `skills/building-wordpress-blog/scripts/`: the hero-image generator and an example topics file.

## Stack assumptions

- **WordPress 7.0+** recommended (tested on 7.1.3). 6.9 is the minimum; it works without live editor previews of Blog Kit's blocks. PHP 8.1+ recommended.
- **Theme:** a block theme (preferred), a classic theme with a child theme, or a page builder for templates (not for post content).
- **SEO plugin:** Yoast SEO or Rank Math, exactly one. AIOSEO, SEOPress and The SEO Framework can meet the spec, but Blog Kit's integrations target the first two.
- **Multilingual:** Polylang (recommended for independent posts per language) or WPML. TranslatePress only fits "every post translated" sites. Single-language sites skip the i18n sections.
- **Caching:** a page cache, host-level or plugin.
- **Working copy:** a local copy or staging site to work on. Production is only touched by deploys.

## How it was verified

Every hook name, option key and behaviour in the spec was checked against source code:

- WordPress 7.1.3;
- Yoast SEO 28.7;
- Rank Math 1.0.280;
- Polylang 3.9.1;
- WPML 4.9.5 (code and documentation only: WPML is commercial and wasn't run);
- the Gemini API documentation and SDKs.

Blog Kit was then run on a WordPress 7.1.3 test site. Each setup passed an end-to-end suite of real HTTP checks (one H1, ToC ids, schema graph, canonicals, hreflang, 404s, old-URL redirects, sitemap contents, image formats, publish guard, and so on) plus PHP-level checks of the parts HTTP can't see:

| Setup | Checks passed |
|---|---|
| No SEO plugin | 81 / 81 |
| Yoast SEO | 84 / 84 |
| Rank Math | 82 / 82 |
| Polylang (English + French) with Yoast SEO | 24 / 24 |
| Classic theme (template tags) | 12 / 12 |

On top of the suites:

- The shortcodes were smoke-tested.
- The hero-image script ran against a local stand-in for the Gemini API (not Google itself): dry run, real uploads, idempotent re-run, `--force` on one topic, `--force` without topics (refused), a typo'd option (refused), a retired model ID, a missing language, a numeric post ID, a wrong-language slug (skipped), a wrong-language ID (refused), two languages pointing at one post (refused), and the default topics-file lookup. Uploading a hero left each post's modified date untouched.

Several defaults in the spec exist because of what that testing found. For example:

- WordPress does not redirect old URLs when you change the permalink structure.
- Core's Time to Read block shows a range unless told otherwise.
- The blog index has no H1 with core blocks alone.
- Yoast doesn't build its index on staging sites.
- Rank Math prints nothing until its setup wizard is done or skipped.
- Yoast's "disable date archives" switch turns `?m=` URLs into homepage redirects instead of 404s.
- Once a post's comment feed is switched off, WordPress's own URL guessing bounces it to `/feed/feed/` unless told not to.

## Differences from the Next.js + Sanity version

This repository started as the Next.js + Sanity `building-blog` skill. The SEO, accessibility and performance opinions carry over; the implementation is WordPress-native:

- **Category pages are in scope.** WordPress creates them anyway, so they become indexable topic hubs (noindexed while thin) instead of query-parameter filters.
- **The SEO plugin owns metadata, schema and sitemaps.** The spec configures it and Blog Kit fills only the gaps.
- **Trailing slashes** follow WordPress's convention. Feeds are WordPress's RSS 2.0.
- **Reading time** uses core's block (238 wpm). The ToC, related posts and the publish guard come from Blog Kit.
- **FAQ markup is optional.** Google retired FAQ rich results in May 2026.
- **Hero images** go to the media library as featured images, with one media item per language for alt text.

## What this is not

- Not for headless WordPress. With a Next.js or Astro front end, the Next.js version of this skill fits better.
- Not a docs site generator. Use Nextra, Docusaurus or Starlight.
- Not for high-velocity news publishers, where sitemap chunking, Google News and editorial workflow become primary concerns.
- Not a theme or a design system. It assumes the site already has one.
- Not a page-builder content workflow. Posts are written in the block editor.

## Why this exists

Ask an AI to "add a blog to my WordPress site" and you get a different answer every time:

- some forget alt text on featured images;
- some leave tag, date and author archives indexable, fragmenting a small archive into thin pages;
- some install three plugins that each print their own schema;
- some change permalinks and lose every old URL to a 404.

This skill writes down what a careful, opinionated implementation looks like, once, so you don't relitigate it on every client site. The opinions have rationale inline. Where you disagree, override them through the §0 intake; that is exactly what §0 is for.

## Roadmap (loose)

- Integrations for AIOSEO and SEOPress in Blog Kit.
- WP-CLI commands in Blog Kit for the legacy-content audit (category, featured image and alt text on every published post).
- A sanitized example project showing the spec applied to a real site.

## Contributing

Issues and PRs are welcome. The spec is opinionated on purpose. Especially useful:

- Real-world results: "I shipped a blog with this, and here is what broke or what I had to change."
- Counter-examples where a default in §2–§18 is provably wrong for a common case.
- Translations of Blog Kit's strings (`site-plugin/blog-kit/languages/`) and of the questionnaire.

## License

MIT. See `LICENSE`.

## Credits

- WordPress adaptation: [mikenewperspectives2079](https://github.com/mikenewperspectives2079).
- Based on the Next.js + Sanity `building-blog` skill by Vladimir Terekhov ([BuildShipGrowRepeat](https://github.com/BuildShipGrowRepeat) · [attractgroup.com](https://attractgroup.com)), MIT.

---

Keywords for search: wordpress blog, claude code skill, claude skill, wordpress seo blog, yoast seo blog setup, rank math blog setup, polylang blog, wpml blog, hreflang wordpress, blogposting schema wordpress, wordpress block theme blog, ai blog images, nano banana wordpress, gemini image wordpress, opinionated blog spec.
