<?php
/**
 * Byline helpers (§7.10, §12.2): the conditional "Updated" date and a
 * reading-time wrapper for classic themes and page builders.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Renders "Updated <date>" only when the post was modified more than the
 * configured threshold (default 24 h) after it was published, so typo fixes
 * on launch day don't trigger it. Uses the same modified date the SEO plugin
 * puts in dateModified.
 *
 * @param array          $attributes Optional `format` (PHP date format).
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_updated_date( $attributes = array(), $block = null ) {
	$post_id = context_post_id( $block );
	if ( ! $post_id ) {
		return '';
	}

	$published = (int) get_post_time( 'U', true, $post_id );
	$modified  = (int) get_post_modified_time( 'U', true, $post_id );
	if ( ! $published || $modified - $published <= (float) config( 'updated_threshold_hours' ) * HOUR_IN_SECONDS ) {
		return '';
	}

	$format = ! empty( $attributes['format'] ) ? (string) $attributes['format'] : (string) get_option( 'date_format' );

	return sprintf(
		'<span %1$s>%2$s <time datetime="%3$s">%4$s</time></span>',
		wrapper_attributes( $block, array( 'class' => 'blog-kit-updated' ) ),
		esc_html( label( 'updated' ) ),
		esc_attr( (string) get_post_modified_time( 'c', false, $post_id ) ),
		esc_html( wp_date( $format, $modified ) )
	);
}

/**
 * Reading time for classic themes and page builders: renders the core Time to
 * Read block at the configured speed, so wording and translations match block
 * themes (which place core/post-time-to-read with averageReadingSpeed and
 * displayAsRange:false directly).
 *
 * @return string
 */
function render_reading_time() {
	if ( ! get_the_ID() ) {
		return '';
	}
	return render_block(
		array(
			'blockName'    => 'core/post-time-to-read',
			'attrs'        => array(
				'averageReadingSpeed' => (int) config( 'reading_speed_wpm' ),
				'displayAsRange'      => false, // Core defaults to a range ("4–6 minutes").
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

add_action(
	'init',
	static function () {
		register_dynamic_block(
			'updated-date',
			__( 'Updated date (if changed after publishing)', 'blog-kit' ),
			__NAMESPACE__ . '\\render_updated_date',
			array(
				'attributes' => array(
					'format' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		add_shortcode(
			'blog_kit_reading_time',
			static function () {
				return render_reading_time();
			}
		);
	}
);
