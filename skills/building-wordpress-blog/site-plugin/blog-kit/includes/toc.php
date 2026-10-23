<?php
/**
 * Heading anchors and table of contents (§9.2, §12.1).
 *
 * Headings are read from the saved block markup (cheap, no rendering), given
 * stable unique ids, and the same ids are injected into the rendered H2–H4
 * tags. The ToC lists H2s only and renders nothing below the thresholds.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Turns heading text into an id. Keeps Unicode letters, so non-Latin headings
 * get readable ids, and strips accents from Latin text.
 *
 * @param string $text Heading text.
 * @return string
 */
function heading_slug( $text ) {
	$text = remove_accents( (string) $text );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$slug = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', $text ), '-' );
	return '' === $slug ? 'section' : $slug;
}

/**
 * Plain text of an HTML fragment.
 *
 * @param string $html HTML.
 * @return string
 */
function plain_text( $html ) {
	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/**
 * Headings of a post in document order, each with a unique id.
 * Explicit anchors set in the editor are kept and never reused.
 *
 * @param int $post_id Post ID.
 * @return array<int, array{level: int, text: string, id: string, generated: bool}>
 */
function headings( $post_id ) {
	static $cache = array();

	$post_id = (int) $post_id;
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$found = array();
	$post  = get_post( $post_id );

	if ( $post ) {
		$walk = static function ( array $blocks ) use ( &$walk, &$found ) {
			foreach ( $blocks as $block ) {
				if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
					$html = (string) $block['innerHTML'];
					$tags = new \WP_HTML_Tag_Processor( $html );
					if ( $tags->next_tag() && preg_match( '/^H([1-6])$/', (string) $tags->get_tag(), $match ) ) {
						$id      = $tags->get_attribute( 'id' );
						$found[] = array(
							'level'     => (int) $match[1],
							'text'      => plain_text( $html ),
							'id'        => is_string( $id ) ? $id : '',
							'generated' => false,
						);
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( parse_blocks( $post->post_content ) );
	}

	$used = array();
	foreach ( $found as $heading ) {
		if ( '' !== $heading['id'] ) {
			$used[ $heading['id'] ] = true;
		}
	}
	foreach ( $found as $index => $heading ) {
		if ( '' !== $heading['id'] ) {
			continue;
		}
		$base = heading_slug( $heading['text'] );
		$id   = $base;
		for ( $n = 2; isset( $used[ $id ] ); $n++ ) {
			$id = $base . '-' . $n;
		}
		$used[ $id ]                  = true;
		$found[ $index ]['id']        = $id;
		$found[ $index ]['generated'] = true;
	}

	$cache[ $post_id ] = $found;
	return $found;
}

/**
 * Injects the ids from headings() into the rendered heading tags of the
 * single post being viewed. Matching is by text in document order, so
 * headings added by other plugins are left alone.
 */
final class Heading_Ids {

	/** @var int Post whose content is being rendered, 0 when idle. */
	private static $post_id = 0;

	/** @var int Next index into headings() to consider. */
	private static $cursor = 0;

	/**
	 * Starts tracking when the main post's content is rendered (before do_blocks at 9).
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function start( $content ) {
		$post_id = (int) get_the_ID();
		if ( $post_id && is_singular( 'post' ) && (int) get_queried_object_id() === $post_id ) {
			self::$post_id = $post_id;
			self::$cursor  = 0;
		}
		return $content;
	}

	/**
	 * Stops tracking after blocks are rendered.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function stop( $content ) {
		self::$post_id = 0;
		return $content;
	}

	/**
	 * Adds the id to one rendered heading block.
	 *
	 * @param string $html Rendered heading.
	 * @return string
	 */
	public static function add_id( $html ) {
		if ( ! self::$post_id ) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() || null !== $tags->get_attribute( 'id' ) ) {
			return $html;
		}

		$text = plain_text( $html );
		$list = headings( self::$post_id );
		$size = count( $list );
		for ( $i = self::$cursor; $i < $size; $i++ ) {
			if ( $list[ $i ]['text'] === $text ) {
				self::$cursor = $i + 1;
				if ( $list[ $i ]['generated'] ) {
					$tags->set_attribute( 'id', $list[ $i ]['id'] );
					return $tags->get_updated_html();
				}
				return $html;
			}
		}
		return $html;
	}
}
add_filter( 'the_content', array( Heading_Ids::class, 'start' ), 8 );
add_filter( 'render_block_core/heading', array( Heading_Ids::class, 'add_id' ), 10 );
add_filter( 'the_content', array( Heading_Ids::class, 'stop' ), 10 );

/**
 * Word count using the same rules as the core Time to Read block, so the
 * ToC threshold and the reading time agree (handles CJK character counting).
 *
 * @param int $post_id Post ID.
 * @return int
 */
function word_count( $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( function_exists( 'block_core_post_time_to_read_word_count' ) ) {
		return (int) block_core_post_time_to_read_word_count( $content, wp_get_word_count_type() );
	}
	return str_word_count( wp_strip_all_tags( $content ) );
}

/**
 * Resolves the post a renderer should use.
 *
 * @param \WP_Block|null $block Block instance when rendered as a block.
 * @return int
 */
function context_post_id( $block ) {
	if ( $block instanceof \WP_Block && ! empty( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Builds an HTML attribute string, using block supports when rendered as a block.
 *
 * @param \WP_Block|null $block Block instance or null.
 * @param array          $attrs Attributes.
 * @return string
 */
function wrapper_attributes( $block, array $attrs ) {
	if ( $block instanceof \WP_Block ) {
		return get_block_wrapper_attributes( $attrs );
	}
	$out = array();
	foreach ( $attrs as $name => $value ) {
		$out[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}
	return implode( ' ', $out );
}

/**
 * Renders the table of contents, or nothing below the thresholds.
 *
 * Markup: <nav> → <details> (closed by default, opened by script on wide
 * screens) → <summary> → <ol> of H2 links.
 *
 * @param array          $attributes Unused.
 * @param \WP_Block|null $block      Block instance.
 * @return string
 */
function render_toc( $attributes = array(), $block = null ) {
	$post_id = context_post_id( $block );
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return '';
	}

	$h2 = array_values(
		array_filter(
			headings( $post_id ),
			static function ( $heading ) {
				return 2 === $heading['level'];
			}
		)
	);

	if ( count( $h2 ) < (int) config( 'toc_min_h2' ) || word_count( $post_id ) < (int) config( 'toc_min_words' ) ) {
		return '';
	}

	wp_enqueue_script( 'blog-kit-toc' );

	$items = '';
	foreach ( $h2 as $heading ) {
		$items .= sprintf( '<li><a href="#%1$s">%2$s</a></li>', esc_attr( rawurlencode( $heading['id'] ) ), esc_html( $heading['text'] ) );
	}

	return sprintf(
		'<nav %1$s><details class="blog-kit-toc__details"><summary class="blog-kit-toc__title" id="blog-kit-toc-title">%2$s</summary><ol class="blog-kit-toc__list">%3$s</ol></details></nav>',
		wrapper_attributes(
			$block,
			array(
				'class'           => 'blog-kit-toc',
				'aria-labelledby' => 'blog-kit-toc-title',
			)
		),
		esc_html( label( 'toc' ) ),
		$items
	);
}

add_action(
	'init',
	static function () {
		wp_register_script(
			'blog-kit-toc',
			plugins_url( 'assets/toc.js', FILE ),
			array(),
			VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_register_style( 'blog-kit', plugins_url( 'assets/blog-kit.css', FILE ), array(), VERSION );

		register_dynamic_block( 'toc', __( 'Table of contents', 'blog-kit' ), __NAMESPACE__ . '\\render_toc' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_singular( 'post' ) || is_home() || is_category() ) {
			wp_enqueue_style( 'blog-kit' );
		}
	}
);
