<?php
/**
 * Frontend rendering engine.
 *
 * Registers the [dh_reviews] shortcode and the legacy WP Widget.
 * Queries review CPT posts, loads layout templates (grid, slider, list),
 * and handles conditional asset enqueuing.
 * See SPEC.md Section 6 for shortcode attributes, HTML structure,
 * and rendering behaviour.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Render
 *
 * Handles shortcode registration, review queries, and template rendering.
 */
class Render {

	/**
	 * Constructor.
	 *
	 * Registers the shortcode and widget, and sets up asset enqueuing.
	 */
	public function __construct() {
		add_shortcode( 'dh_reviews', array( $this, 'render_shortcode' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Render the [dh_reviews] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( array|string $atts = array() ): string {
		// Stub: parse attributes, query reviews, load template, return HTML.
		return '';
	}

	/**
	 * Parse and validate shortcode attributes with defaults.
	 *
	 * @param array|string $atts Raw shortcode attributes.
	 * @return array Validated attributes with defaults applied.
	 */
	public function parse_attributes( array|string $atts ): array {
		// Stub: shortcode_atts with all defaults from Section 6.1.
		return array();
	}

	/**
	 * Query review posts based on shortcode attributes.
	 *
	 * @param array $atts Parsed shortcode attributes.
	 * @return \WP_Post[] Array of review posts.
	 */
	public function query_reviews( array $atts ): array {
		// Stub: build WP_Query args with filters, apply dh_reviews_query_args filter.
		return array();
	}

	/**
	 * Load a layout template (grid, slider, or list).
	 *
	 * Checks for theme override first via locate_template().
	 *
	 * @param string     $layout  Layout type (grid, slider, list).
	 * @param \WP_Post[] $reviews Array of review posts.
	 * @param array      $atts    Shortcode attributes.
	 * @return string Rendered layout HTML.
	 */
	public function load_template( string $layout, array $reviews, array $atts ): string {
		// Stub: locate template, include with variables, return buffered output.
		return '';
	}

	/**
	 * Render a single review card.
	 *
	 * @param \WP_Post $review Review post object.
	 * @param array    $atts   Shortcode attributes.
	 * @return string Card HTML.
	 */
	public function render_card( \WP_Post $review, array $atts ): string {
		// Stub: load review-card.php template with review data.
		return '';
	}

	/**
	 * Render the aggregate rating bar.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Aggregate bar HTML.
	 */
	public function render_aggregate( array $atts ): string {
		// Stub: load aggregate-bar.php template.
		return '';
	}

	/**
	 * Render star rating SVG icons.
	 *
	 * @param int $rating Star rating (1 to 5).
	 * @return string SVG stars HTML.
	 */
	public function render_stars( int $rating ): string {
		// Stub: output inline SVG stars per rating value.
		return '';
	}

	/**
	 * Format a review date as relative or absolute.
	 *
	 * @param string $date        Review date string.
	 * @param string $date_format Format type: relative or absolute.
	 * @return string Formatted date string.
	 */
	public function format_date( string $date, string $date_format = 'relative' ): string {
		// Stub: calculate relative time or format with get_option('date_format').
		return '';
	}

	/**
	 * Get the avatar HTML for a reviewer (photo or initial fallback).
	 *
	 * @param int $post_id Review post ID.
	 * @return string Avatar HTML.
	 */
	public function get_avatar( int $post_id ): string {
		// Stub: check for photo, generate initial circle fallback if empty.
		return '';
	}

	/**
	 * Register the classic WP Widget for sidebar use.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		// Stub: register DH_Reviews_Widget.
	}

	/**
	 * Conditionally enqueue frontend CSS and JS.
	 *
	 * Only loads assets on pages where the shortcode or block is present.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Stub: check has_shortcode and block detection, enqueue if needed.
	}
}
