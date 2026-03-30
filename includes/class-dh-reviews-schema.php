<?php
/**
 * Schema markup generation.
 *
 * Generates JSON-LD structured data for AggregateRating and individual
 * Review markup, injected into wp_head. Supports LocalBusiness wrapper
 * with configurable business type. Can be disabled globally or per
 * shortcode instance.
 * See SPEC.md Section 5 for schema structure and placement rules.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 *
 * Handles JSON-LD schema markup output for review data.
 */
class Schema {

	/**
	 * Constructor.
	 *
	 * Registers the wp_head hook for schema output.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ) );
	}

	/**
	 * Output JSON-LD schema markup in the page head.
	 *
	 * Only outputs once per page regardless of shortcode instances.
	 * Respects the global and per shortcode schema toggle.
	 *
	 * @return void
	 */
	public function output_schema(): void {
		// Stub: check if schema should render, build and output JSON-LD.
	}

	/**
	 * Build the complete schema array including LocalBusiness wrapper.
	 *
	 * @param string $location_slug Optional location slug for filtering.
	 * @return array Schema data array ready for json_encode.
	 */
	public function build_schema( string $location_slug = '' ): array {
		// Stub: build LocalBusiness + AggregateRating + Review array.
		return array();
	}

	/**
	 * Get the aggregate rating data for a location.
	 *
	 * @param string $location_slug Location taxonomy slug.
	 * @return array Aggregate data with ratingValue, reviewCount, bestRating, worstRating.
	 */
	public function get_aggregate_data( string $location_slug = '' ): array {
		// Stub: retrieve from transient or calculate.
		return array();
	}

	/**
	 * Build the review schema array from published review posts.
	 *
	 * @param string $location_slug Optional location filter.
	 * @return array Array of Review schema items.
	 */
	public function build_review_items( string $location_slug = '' ): array {
		// Stub: query dh_review posts, format as schema Review items.
		return array();
	}

	/**
	 * Get business details from plugin settings for the LocalBusiness wrapper.
	 *
	 * @return array Business details array.
	 */
	public function get_business_details(): array {
		// Stub: retrieve business name, address, type from settings.
		return array();
	}

	/**
	 * Flag that schema has been queued for output on this page load.
	 *
	 * Prevents duplicate output when multiple shortcodes exist on a page.
	 *
	 * @param string $location_slug Location slug to mark as queued.
	 * @return void
	 */
	public function mark_schema_queued( string $location_slug = '' ): void {
		// Stub: set static flag.
	}

	/**
	 * Check whether schema has already been queued for a location.
	 *
	 * @param string $location_slug Location slug to check.
	 * @return bool True if already queued.
	 */
	public function is_schema_queued( string $location_slug = '' ): bool {
		// Stub: check static flag.
		return false;
	}
}
