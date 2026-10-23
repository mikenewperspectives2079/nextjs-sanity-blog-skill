#!/usr/bin/env node
/**
 * Blog hero image generator + WordPress uploader (§20 of blog-technical-requirements.md).
 *
 * For each topic in the topics file:
 *   1. Finds the post in every language listed (by numeric ID or by slug, any status).
 *   2. Skips the topic if every found post already has a featured image (unless --force).
 *   3. Generates one 16:9 image with a Gemini image model.
 *   4. Optionally saves a local archive copy (HERO_ARCHIVE_DIR, §1.J3).
 *   5. Uploads it to the media library once per language, so each language has its
 *      own alt text, attaches it to the post and sets it as the featured image.
 *
 * Usage:
 *   node generate-hero-images.mjs [topics.json] [--dry-run] [--force] [slug-or-id ... | --all]
 *
 *   topics.json   defaults to hero-topics.json next to this script, then in the current folder
 *   --dry-run     resolve posts and print the plan; no image generation, no uploads
 *   --force       replace existing featured images; needs explicit slugs/IDs, or --all
 *   slug-or-id    only process topics whose primary-language post matches
 *
 * On multilingual sites each post found by slug must be in the topic's language
 * (checked through Blog Kit's `blog_kit_lang` REST field), and two languages may
 * never resolve to the same post.
 *
 * Environment:
 *   WP_URL               site URL, e.g. https://staging.example.com (no trailing slash needed)
 *   WP_USER              WordPress username of an Editor or Administrator
 *   WP_APP_PASSWORD      Application Password for that user (Users → Profile)
 *   GEMINI_API_KEY       from https://aistudio.google.com/apikey (not needed for --dry-run)
 *   GEMINI_IMAGE_MODEL   default "gemini-3-pro-image" (§1.J2) — verified before the first call
 *   GEMINI_IMAGE_SIZE    "1K" | "2K" (default) | "4K"
 *   HERO_ARCHIVE_DIR     optional folder for local copies, e.g. ./blog-hero-archive
 *   GEMINI_API_BASE      default https://generativelanguage.googleapis.com (override for tests)
 *
 * Requires Node 20+ (global fetch). No npm dependencies.
 */
import { existsSync } from "node:fs";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const MODEL = process.env.GEMINI_IMAGE_MODEL || "gemini-3-pro-image";
const IMAGE_SIZE = process.env.GEMINI_IMAGE_SIZE || "2K";
const ASPECT_RATIO = "16:9";
const GEMINI_BASE = (process.env.GEMINI_API_BASE || "https://generativelanguage.googleapis.com").replace(/\/$/, "");
const ARCHIVE_DIR = process.env.HERO_ARCHIVE_DIR || "";
const WP_URL = (process.env.WP_URL || "").replace(/\/$/, "");
const STATUSES = "publish,future,draft,pending,private";

const args = process.argv.slice(2);
const flags = new Set(args.filter((a) => a.startsWith("--")));
const positional = args.filter((a) => !a.startsWith("--"));
const besideScript = resolve(dirname(fileURLToPath(import.meta.url)), "hero-topics.json");
const topicsFile = positional[0] && positional[0].endsWith(".json")
  ? resolve(positional.shift())
  : existsSync(besideScript) ? besideScript : resolve("hero-topics.json");
const only = positional;
const dryRun = flags.has("--dry-run");
const force = flags.has("--force");

function fail(message) {
  console.error(`\n✗ ${message}`);
  process.exit(1);
}

const unknownFlags = [...flags].filter((flag) => !["--dry-run", "--force", "--all"].includes(flag));
if (unknownFlags.length) fail(`Unknown option(s): ${unknownFlags.join(", ")}. See the header of this script.`);
if (force && !only.length && !flags.has("--all")) {
  fail("--force replaces existing featured images. Name the topics to regenerate (slugs or IDs), or add --all.");
}

for (const name of ["WP_URL", "WP_USER", "WP_APP_PASSWORD"]) {
  if (!process.env[name]) fail(`Missing ${name}. See the header of this script.`);
}
if (!dryRun && !process.env.GEMINI_API_KEY) fail("Missing GEMINI_API_KEY.");

const auth = "Basic " + Buffer.from(`${process.env.WP_USER}:${process.env.WP_APP_PASSWORD.replace(/\s+/g, "")}`).toString("base64");

/** REST URL that works with and without pretty permalinks. */
function rest(route, params = {}) {
  const query = new URLSearchParams({ rest_route: route, ...params });
  return `${WP_URL}/?${query}`;
}

async function wp(route, { params, method = "GET", headers = {}, body } = {}) {
  const response = await fetch(rest(route, params), { method, headers: { Authorization: auth, ...headers }, body });
  const text = await response.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    data = { message: text.slice(0, 300) };
  }
  if (!response.ok) {
    let hint = "";
    if (response.status === 401) {
      hint = " — check WP_USER/WP_APP_PASSWORD; on Apache/CGI hosts the Authorization header may be stripped (re-save Permalinks, or add SetEnvIf Authorization to .htaccess).";
    } else if (response.status === 413) {
      hint = " — the image exceeds the server's upload limit; set GEMINI_IMAGE_SIZE=1K or raise upload_max_filesize.";
    } else if (response.status === 403) {
      hint = " — the user needs the Editor role (upload_files, edit_others_posts).";
    }
    throw new Error(`WordPress ${method} ${route} → ${response.status} ${data.code || ""} ${data.message || ""}${hint}`);
  }
  return data;
}

const FIELDS = "id,slug,status,featured_media,link,blog_kit_lang";

let warnedNoLanguage = false;

/**
 * Finds one language's post by numeric ID or slug, in any status.
 * Returns { post } or, when there is nothing to update, { post: null, reason }.
 * On multilingual sites the post must be in `locale`: a slug that only exists
 * in another language is skipped, an ID that points at another language fails.
 */
async function findPost(ref, locale, multilingual) {
  const wrongLanguage = (post) => {
    if (multilingual && post.blog_kit_lang === undefined && !warnedNoLanguage) {
      warnedNoLanguage = true;
      console.warn("  ! posts have no blog_kit_lang field (is Blog Kit active?) — languages can't be checked");
    }
    return multilingual && Boolean(post.blog_kit_lang) && post.blog_kit_lang !== locale;
  };

  if (typeof ref === "number" || /^\d+$/.test(String(ref))) {
    let post;
    try {
      post = await wp(`/wp/v2/posts/${ref}`, { params: { context: "edit", _fields: FIELDS } });
    } catch (error) {
      if (String(error.message).includes("404")) return { post: null, reason: `no post ${ref}` };
      throw error;
    }
    if (wrongLanguage(post)) throw new Error(`Post ${ref} is in "${post.blog_kit_lang}", not "${locale}". Fix the topic.`);
    return { post };
  }

  const params = { slug: String(ref), status: STATUSES, context: "edit", _fields: FIELDS };
  if (multilingual) params.lang = locale; // Polylang Pro and WPML filter by it; elsewhere it is ignored.
  const found = await wp("/wp/v2/posts", { params });
  const candidates = found.filter((post) => !wrongLanguage(post));
  if (candidates.length === 1) return { post: candidates[0] };
  if (candidates.length > 1) {
    const exact = candidates.filter((post) => post.blog_kit_lang === locale);
    if (exact.length === 1) return { post: exact[0] };
    throw new Error(`Slug "${ref}" matches ${candidates.length} posts; use the numeric post ID for ${locale}.`);
  }
  if (found.length) {
    const languages = [...new Set(found.map((post) => post.blog_kit_lang))].join(", ");
    return { post: null, reason: `post "${ref}" is in ${languages}, not ${locale}` };
  }
  return { post: null, reason: `no post "${ref}"` };
}

/** Detects the real image type from magic bytes (models sometimes mislabel). */
function sniff(buffer) {
  if (buffer.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return { mime: "image/png", ext: "png" };
  if (buffer[0] === 0xff && buffer[1] === 0xd8 && buffer[2] === 0xff) return { mime: "image/jpeg", ext: "jpg" };
  if (buffer.subarray(0, 4).toString("ascii") === "RIFF" && buffer.subarray(8, 12).toString("ascii") === "WEBP") return { mime: "image/webp", ext: "webp" };
  throw new Error("Gemini returned data that is not a PNG, JPEG or WebP image.");
}

async function gemini(path, init = {}) {
  const response = await fetch(`${GEMINI_BASE}/v1beta/${path}`, {
    ...init,
    headers: { "content-type": "application/json", "x-goog-api-key": process.env.GEMINI_API_KEY, ...(init.headers || {}) },
  });
  if (!response.ok) {
    throw new Error(`Gemini ${path} → ${response.status}: ${(await response.text()).slice(0, 400)}`);
  }
  return response.json();
}

/** Fails early, before any spend, if the model ID is wrong or retired. */
async function verifyModel() {
  try {
    await gemini(`models/${MODEL}`);
  } catch (error) {
    fail(`Image model "${MODEL}" is not available to this key (${error.message}).\nList models: curl -H "x-goog-api-key: $GEMINI_API_KEY" ${GEMINI_BASE}/v1beta/models | grep -i image\nThen set GEMINI_IMAGE_MODEL.`);
  }
}

async function generateImage(prompt) {
  const data = await gemini(`models/${MODEL}:generateContent`, {
    method: "POST",
    body: JSON.stringify({
      contents: [{ role: "user", parts: [{ text: prompt }] }],
      generationConfig: { responseModalities: ["IMAGE"], imageConfig: { aspectRatio: ASPECT_RATIO, imageSize: IMAGE_SIZE } },
    }),
  });
  // Thinking models may return interim images marked `thought: true`; use the last final one.
  const parts = (data.candidates?.[0]?.content?.parts || []).filter((part) => part.inlineData && !part.thought);
  const image = parts[parts.length - 1];
  if (!image) {
    const reason = data.candidates?.[0]?.finishReason || data.promptFeedback?.blockReason || "no image part";
    throw new Error(`No image in Gemini response (${reason}).`);
  }
  const buffer = Buffer.from(image.inlineData.data, "base64");
  return { buffer, ...sniff(buffer) };
}

async function uploadFor(post, locale, topic, image, primarySlug) {
  const filename = `${post.slug || primarySlug}-hero.${image.ext}`.replace(/[^a-z0-9._-]/gi, "-");
  const media = await wp("/wp/v2/media", {
    method: "POST",
    params: { alt_text: topic.alt[locale], title: topic.title?.[locale] || topic.alt[locale], post: String(post.id) },
    headers: { "Content-Type": image.mime, "Content-Disposition": `attachment; filename="${filename}"` },
    body: image.buffer,
  });
  const updated = await wp(`/wp/v2/posts/${post.id}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ featured_media: media.id }),
  });
  if (updated.featured_media !== media.id) {
    throw new Error(`Post ${post.id}: featured image did not stick (WordPress silently ignored it).`);
  }
  return media;
}

async function main() {
  const config = JSON.parse(await readFile(resolve(topicsFile), "utf8"));
  const locales = config.locales || ["en"];
  const primary = locales[0];
  const multilingual = locales.length > 1;
  const topics = (config.topics || []).filter((topic) => !only.length || only.includes(String(topic.posts?.[primary])));

  if (only.length && topics.length !== only.length) {
    const known = new Set((config.topics || []).map((topic) => String(topic.posts?.[primary])));
    fail(`Unknown topic(s): ${only.filter((ref) => !known.has(ref)).join(", ")}`);
  }

  for (const [index, topic] of topics.entries()) {
    for (const locale of locales) {
      if (topic.posts?.[locale] !== undefined && !topic.alt?.[locale]) fail(`Topic ${index + 1}: missing alt text for "${locale}".`);
    }
    if (!topic.prompt) fail(`Topic ${index + 1}: missing prompt.`);
  }

  console.log(`${dryRun ? "[dry run] " : ""}${topics.length} topic(s), model ${MODEL} @ ${IMAGE_SIZE}, languages: ${locales.join(", ")}`);
  if (!dryRun && topics.length) await verifyModel();

  let failures = 0;
  for (const topic of topics) {
    const primaryRef = topic.posts?.[primary];
    console.log(`\n[${primaryRef}]`);
    try {
      const targets = [];
      for (const locale of locales) {
        if (topic.posts?.[locale] === undefined) continue;
        const { post, reason } = await findPost(topic.posts[locale], locale, multilingual);
        if (!post) {
          console.log(`  ↷ ${locale}: ${reason} — skipping that language`);
          continue;
        }
        const clash = targets.find((target) => target.post.id === post.id);
        if (clash) {
          throw new Error(`"${clash.locale}" and "${locale}" both point at post ${post.id}; each language needs its own post.`);
        }
        targets.push({ locale, post });
        console.log(`  • ${locale}: post ${post.id} (${post.status})${post.featured_media ? `, has featured image ${post.featured_media}` : ""}`);
      }

      if (!targets.length) {
        console.log("  ↷ no posts found — nothing to do");
        continue;
      }
      // Posts that already have a featured image keep it unless --force.
      const pending = force ? targets : targets.filter(({ post }) => !post.featured_media);
      if (!pending.length) {
        console.log("  ↷ every post already has a featured image — skipping (use --force to regenerate)");
        continue;
      }
      if (dryRun) {
        console.log(`  ✓ would generate 1 image and upload it for: ${pending.map(({ locale }) => locale).join(", ")}`);
        continue;
      }

      console.log(`  • generating with ${MODEL}…`);
      const image = await generateImage(topic.prompt);
      console.log(`  • got ${image.mime}, ${(image.buffer.length / 1024).toFixed(0)} KB`);

      const primarySlug = targets[0].post.slug || String(primaryRef);
      if (ARCHIVE_DIR) {
        await mkdir(resolve(ARCHIVE_DIR), { recursive: true });
        const path = resolve(ARCHIVE_DIR, `${primarySlug}.${image.ext}`);
        await writeFile(path, image.buffer);
        console.log(`  • archived ${path}`);
      }

      for (const { locale, post } of pending) {
        const media = await uploadFor(post, locale, topic, image, primarySlug);
        console.log(`  ✓ ${locale}: media ${media.id} set as featured image of post ${post.id}`);
      }
    } catch (error) {
      failures++;
      console.error(`  ✗ ${error.message}`);
    }
  }

  console.log(failures ? `\nDone with ${failures} failure(s).` : "\nDone.");
  process.exit(failures ? 1 : 0);
}

main().catch((error) => fail(error.stack || error.message));
