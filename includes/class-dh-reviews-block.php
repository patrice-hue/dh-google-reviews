<?php
/**
 * Gutenberg block registration.
 *
 * Registers the dh/google-reviews block with server side rendering.
 * Block attributes mirror all shortcode attributes from Section 6.1.
 * Uses ServerSideRender for live preview in the editor.
 * See SPEC.md Section 6.2 for block specification.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block
 *
 * Handles Gutenberg block registration and server side render callback.
 */
class Block {

	/**
	 * Constructor.
	 *
	 * Called during the init action so register_block() is called directly
	 * rather than adding another init hook (which would never fire).
	 */
	public function __construct() {
		$this->register_block();
	}

	/**
	 * Register the Gutenberg block using block.json metadata.
	 *
	 * @return void
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			DH_REVIEWS_PATH . 'blocks/google-reviews/block.json',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Server side render callback for the block.
	 *
	 * Converts camelCase block attributes to shortcode snake_case format
	 * and delegates to the existing [dh_reviews] shortcode handler.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_callback( array $attributes ): string {
		$map = array(
			'count'                   => $attributes['count'] ?? 5,
			'min_rating'              => $attributes['minRating'] ?? 1,
			'layout'                  => $attributes['layout'] ?? 'grid',
			'columns'                 => $attributes['columns'] ?? 3,
			'show_reply'              => isset( $attributes['showReply'] ) ? ( $attributes['showReply'] ? 'true' : 'false' ) : 'true',
			'show_date'               => isset( $attributes['showDate'] ) ? ( $attributes['showDate'] ? 'true' : 'false' ) : 'true',
			'show_photo'              => isset( $attributes['showPhoto'] ) ? ( $attributes['showPhoto'] ? 'true' : 'false' ) : 'true',
			'show_stars'              => isset( $attributes['showStars'] ) ? ( $attributes['showStars'] ? 'true' : 'false' ) : 'true',
			'show_aggregate'          => isset( $attributes['showAggregate'] ) ? ( $attributes['showAggregate'] ? 'true' : 'false' ) : 'true',
			'schema'                  => isset( $attributes['schema'] ) ? ( $attributes['schema'] ? 'true' : 'false' ) : 'true',
			'location'                => $attributes['location'] ?? '',
			'orderby'                 => $attributes['orderby'] ?? 'date',
			'order'                   => $attributes['order'] ?? 'DESC',
			'excerpt_length'          => $attributes['excerptLength'] ?? 150,
			'show_google_icon'        => isset( $attributes['showGoogleIcon'] ) ? ( $attributes['showGoogleIcon'] ? 'true' : 'false' ) : 'true',
			'show_google_attribution' => isset( $attributes['showGoogleAttribution'] ) ? ( $attributes['showGoogleAttribution'] ? 'true' : 'false' ) : 'true',
			'show_cta'                => isset( $attributes['showCta'] ) ? ( $attributes['showCta'] ? 'true' : 'false' ) : 'true',
			'cta_text'                => $attributes['ctaText'] ?? 'Review Us On Google',
			'show_dots'               => isset( $attributes['showDots'] ) ? ( $attributes['showDots'] ? 'true' : 'false' ) : 'true',
			'visible_cards'           => $attributes['visibleCards'] ?? 3,
			'date_format'             => $attributes['dateFormat'] ?? 'relative',
			'aggregate_position'      => $attributes['aggregatePosition'] ?? 'top',
			'class'                   => $attributes['className'] ?? '',
		);

		$atts_str = '';
		foreach ( $map as $key => $value ) {
			$atts_str .= ' ' . sanitize_key( $key ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return do_shortcode( '[dh_reviews' . $atts_str . ']' );
	}
}
