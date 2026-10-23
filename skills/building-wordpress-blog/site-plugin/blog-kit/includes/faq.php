<?php
/**
 * FAQ section (§13): a block pattern built from core Details blocks inside a
 * Group with the class `blog-faq`, mirrored into FAQPage JSON-LD.
 *
 * Google stopped showing FAQ rich results for all sites on 2026-05-07; the
 * markup is still valid and harmless, so it is kept for Q&A that is visible
 * on the page. Turn it off with config `faq_schema` => false.
 *
 * Schema goes into the SEO plugin's graph when Yoast SEO or Rank Math is
 * active (so there is one graph, not two), otherwise into its own script tag.
 *
 * @package BlogKit
 */

namespace BlogKit;

defined( 'ABSPATH' ) || exit;

/**
 * Block markup of the FAQ pattern. Matches core's saved markup exactly, so the
 * editor shows no "invalid block" warnings.
 *
 * @return string
 */
function faq_pattern_markup() {
	$item = static function ( $question, $answer ) {
		return '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( $question ) . '</summary><!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( $answer ) . '</p>' . "\n" . '<!-- /wp:paragraph --></details><!-- /wp:details -->';
	};

	return '<!-- wp:group {"className":"' . esc_attr( (string) config( 'faq_class' ) ) . '","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group ' . esc_attr( (string) config( 'faq_class' ) ) . '">'
		. '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( label( 'faq' ) ) . '</h2><!-- /wp:heading -->'
		. $item( __( 'First question, phrased the way readers ask it?', 'blog-kit' ), __( 'A direct answer in one or two sentences.', 'blog-kit' ) )
		. $item( __( 'Second question?', 'blog-kit' ), __( 'A direct answer in one or two sentences.', 'blog-kit' ) )
		. $item( __( 'Third question?', 'blog-kit' ), __( 'A direct answer in one or two sentences.', 'blog-kit' ) )
		. '</div><!-- /wp:group -->';
}

add_action(
	'init',
	static function () {
		register_block_pattern(
			'blog-kit/faq',
			array(
				'title'       => __( 'FAQ section', 'blog-kit' ),
				'description' => __( 'Questions and answers. Each question is a Details block; FAQ structured data is generated from it.', 'blog-kit' ),
				'categories'  => array( 'text' ),
				'postTypes'   => array( 'post' ),
				'content'     => faq_pattern_markup(),
			)
		);
	}
);

/**
 * Whether a block's className contains the FAQ class.
 *
 * @param array $block Parsed block.
 * @return bool
 */
function is_faq_group( array $block ) {
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return false;
	}
	$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ) );
	return in_array( (string) config( 'faq_class' ), $classes, true );
}

/**
 * Answer HTML limited to the tags search engines accept in FAQ answers.
 *
 * @param array[] $blocks Parsed inner blocks.
 * @return string
 */
function faq_answer_html( array $blocks ) {
	$html = '';
	foreach ( $blocks as $inner ) {
		$html .= render_block( $inner );
	}
	$allowed = array(
		'a'      => array( 'href' => true ),
		'br'     => array(),
		'p'      => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'h3'     => array(),
		'h4'     => array(),
	);
	return trim( (string) preg_replace( '/\s+/u', ' ', wp_kses( $html, $allowed ) ) );
}

/**
 * Question/answer pairs from the post's FAQ group(s), in order. Reads core
 * Details blocks, and core Accordion items if an editor used those instead.
 *
 * @param int $post_id Post ID.
 * @return array<int, array{question: string, answer: string}>
 */
function faq_items( $post_id ) {
	static $cache = array();

	$post_id = (int) $post_id;
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$items = array();
	$post  = get_post( $post_id );

	$collect = static function ( array $blocks ) use ( &$collect, &$items ) {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( 'core/details' === $name && preg_match( '#<summary[^>]*>(.*?)</summary>#s', (string) $block['innerHTML'], $match ) ) {
				$items[] = array(
					'question' => plain_text( $match[1] ),
					'answer'   => faq_answer_html( (array) $block['innerBlocks'] ),
				);
				continue;
			}
			if ( 'core/accordion-item' === $name ) {
				$question = '';
				$answer   = '';
				foreach ( (array) $block['innerBlocks'] as $part ) {
					if ( 'core/accordion-heading' === ( $part['blockName'] ?? '' ) ) {
						$tags = new \WP_HTML_Tag_Processor( (string) $part['innerHTML'] );
						$text = '';
						if ( $tags->next_tag( array( 'class_name' => 'wp-block-accordion-heading__toggle-title' ) ) ) {
							while ( $tags->next_token() && ! ( $tags->is_tag_closer() && 'SPAN' === $tags->get_tag() ) ) {
								if ( '#text' === $tags->get_token_type() ) {
									$text .= $tags->get_modifiable_text();
								}
							}
						}
						$question = plain_text( '' !== $text ? $text : (string) $part['innerHTML'] );
					} elseif ( 'core/accordion-panel' === ( $part['blockName'] ?? '' ) ) {
						$answer = faq_answer_html( (array) $part['innerBlocks'] );
					}
				}
				if ( '' !== $question && '' !== $answer ) {
					$items[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$collect( $block['innerBlocks'] );
			}
		}
	};

	if ( $post ) {
		$walk = static function ( array $blocks ) use ( &$walk, $collect ) {
			foreach ( $blocks as $block ) {
				if ( is_faq_group( $block ) ) {
					$collect( (array) $block['innerBlocks'] );
				} elseif ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		$walk( parse_blocks( $post->post_content ) );
	}

	$items = array_values(
		array_filter(
			$items,
			static function ( $item ) {
				return '' !== $item['question'] && '' !== $item['answer'];
			}
		)
	);

	$cache[ $post_id ] = $items;
	return $items;
}

/**
 * Question nodes for FAQPage.mainEntity.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Canonical URL of the post.
 * @return array[]
 */
function faq_questions( $post_id, $url ) {
	$questions = array();
	foreach ( faq_items( $post_id ) as $index => $item ) {
		$questions[] = array(
			'@type'          => 'Question',
			'@id'            => $url . '#faq-' . ( $index + 1 ),
			'name'           => $item['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['answer'],
			),
		);
	}
	return $questions;
}

/**
 * Whether FAQ schema should be produced for the current request.
 *
 * @return int Post ID, or 0.
 */
function faq_schema_post_id() {
	if ( ! config( 'faq_schema' ) || ! is_singular( 'post' ) ) {
		return 0;
	}
	$post_id = (int) get_queried_object_id();
	if ( post_password_required( $post_id ) ) {
		return 0; // Never expose a protected post's answers in markup.
	}
	return faq_items( $post_id ) ? $post_id : 0;
}

/*
 * Yoast SEO: mark the WebPage as FAQPage and add the Question nodes, the same
 * way Yoast's own FAQ block does. Skipped when that block is used.
 */
add_filter(
	'wpseo_schema_webpage',
	static function ( $piece, $context ) {
		$post_id = faq_schema_post_id();
		if ( ! $post_id || ! empty( $context->blocks['yoast/faq-block'] ) ) {
			return $piece;
		}
		$piece['@type']      = array_values( array_unique( array_merge( (array) $piece['@type'], array( 'FAQPage' ) ) ) );
		$piece['mainEntity'] = array_map(
			static function ( $question ) {
				return array( '@id' => $question['@id'] );
			},
			faq_questions( $post_id, get_permalink( $post_id ) )
		);
		return $piece;
	},
	10,
	2
);

add_filter(
	'wpseo_schema_graph',
	static function ( $graph, $context ) {
		$post_id = faq_schema_post_id();
		if ( ! $post_id || ! empty( $context->blocks['yoast/faq-block'] ) ) {
			return $graph;
		}
		foreach ( faq_questions( $post_id, get_permalink( $post_id ) ) as $index => $question ) {
			$question['position']    = $index + 1;
			$question['answerCount'] = 1;
			$question['inLanguage']  = get_bloginfo( 'language' );
			$graph[]                 = $question;
		}
		return $graph;
	},
	10,
	2
);

/*
 * Rank Math: add an FAQPage entity; Rank Math merges it into the WebPage
 * node at priority 99. Skipped when Rank Math's own FAQ block already did.
 */
add_filter(
	'rank_math/json_ld',
	static function ( $data ) {
		$post_id = faq_schema_post_id();
		if ( ! $post_id || isset( $data['faqs'] ) ) {
			return $data;
		}
		$data['faqs'] = array(
			'@type'      => 'FAQPage',
			'mainEntity' => faq_questions( $post_id, get_permalink( $post_id ) ),
		);
		return $data;
	},
	20
);

/*
 * No SEO plugin at all: print a standalone FAQPage block. With an SEO plugin
 * Blog Kit doesn't integrate with (AIOSEO, SEOPress, The SEO Framework),
 * print nothing rather than a second graph — use that plugin's FAQ feature.
 */
add_action(
	'wp_head',
	static function () {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return;
		}
		$post_id = faq_schema_post_id();
		if ( ! $post_id ) {
			return;
		}
		$url = get_permalink( $post_id );
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'@id'        => $url . '#faq',
					'url'        => $url,
					'mainEntity' => faq_questions( $post_id, $url ),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			)
		);
	},
	30
);
