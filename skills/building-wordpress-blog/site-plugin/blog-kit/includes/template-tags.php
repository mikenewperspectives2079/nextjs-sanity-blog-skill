<?php
/**
 * Template tags for classic themes and page-builder templates.
 *
 * Each tag echoes the same markup as its block (`blog-kit/<name>`) and its
 * shortcode (`[blog_kit_<name>]`), so all three presentation paths match.
 *
 * @package BlogKit
 */

defined( 'ABSPATH' ) || exit;

/** Table of contents. Renders nothing below the §12 thresholds. */
function blog_kit_toc() {
	echo \BlogKit\render_toc(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer.
}

/** "Updated <date>". Renders nothing unless modified more than the threshold after publishing. */
function blog_kit_updated_date() {
	echo \BlogKit\render_updated_date(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Reading time, via the core Time to Read block at the configured speed. */
function blog_kit_reading_time() {
	echo \BlogKit\render_reading_time(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** "More from <Category>" aside with up to three cards. */
function blog_kit_related_posts() {
	echo \BlogKit\render_related_posts(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Category navigation ("filter pills") for the blog index and category archives. */
function blog_kit_category_nav() {
	echo \BlogKit\render_category_nav(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** H1 for the blog index (posts page title) and category pages (bare category name). */
function blog_kit_archive_title() {
	echo \BlogKit\render_archive_title(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Localized "not found" message with a link to the blog index, for 404 templates. */
function blog_kit_not_found() {
	echo \BlogKit\render_not_found(); // phpcs:ignore WordPress.Security.EscapeOutput
}
