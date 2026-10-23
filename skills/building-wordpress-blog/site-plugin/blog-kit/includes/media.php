<?php
/**
 * Image pipeline (§14.3): card and Open Graph crops, and modern output
 * formats for new uploads.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/*
 * `blog-card` — 16:9 crop for listing and related-post cards.
 * `blog-og`   — 1200×630 crop used for og:image / twitter:image (§7.6).
 * Sizes apply to new uploads. For existing images run:
 *   wp media regenerate --image_size=blog-card --yes
 *   wp media regenerate --image_size=blog-og --yes
 */
add_action(
	'init',
	static function () {
		add_image_size( 'blog-card', 800, 450, true );
		add_image_size( 'blog-og', 1200, 630, true );
	}
);

/**
 * §1.G3 — Store JPEG and PNG uploads as WebP (or AVIF) instead.
 *
 * WordPress converts every generated size *and* the full-size image; the
 * untouched original is kept on disk as `original_image` for regeneration
 * but is never served. Falls back to WebP when the server's image editor
 * cannot write AVIF, and does nothing when it can write neither.
 *
 * @param array $formats Map of source MIME type => output MIME type.
 * @return array
 */
function output_formats( $formats ) {
	static $resolving = false;

	$target = config( 'image_output_format' );
	if ( $resolving || ! in_array( $target, array( 'webp', 'avif' ), true ) ) {
		return $formats;
	}

	$resolving = true;
	$mime      = 'image/' . $target;
	if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
		$mime = ( 'image/avif' === $mime && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) ? 'image/webp' : '';
	}
	$resolving = false;

	if ( $mime ) {
		$formats['image/jpeg'] = $mime;
		$formats['image/png']  = $mime;
	}
	return $formats;
}
add_filter( 'image_editor_output_format', __NAMESPACE__ . '\\output_formats' );
