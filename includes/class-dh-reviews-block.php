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
	 * Registers the block type on init.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the Gutenberg block using block.json metadata.
	 *
	 * @return void
	 */
	public function register_block(): void {
		// Stub: register_block_type from blocks/google-reviews/block.json.
	}

	/**
	 * Server side render callback for the block.
	 *
	 * Converts block attributes to shortcode attributes and renders
	 * through the existing Render class.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_callback( array $attributes ): string {
		// Stub: map block attributes to shortcode atts, call Render.
		return '';
	}
}
