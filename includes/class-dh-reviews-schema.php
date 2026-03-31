<?php
/**
 * Schema markup generation.
 *
 * Generates JSON-LD structured data for AggregateRating and individual
 * Review markup, injected into wp_head. Supports LocalBusiness wrapper
 * with configurable business type and a reduced-output mode for sites
 * that already publish LocalBusiness schema via SEOPress, Yoast, etc.
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
	 * Tracks whether the schema block has already been output on this page.
	 * Prevents duplicate output when multiple shortcode instances exist.
	 *
	 * @var bool
	 */
	private static bool $output_done = false;

	/**
	 * Allowed Schema.org @type values for business type override.
	 * Covers common subtypes used by agency clients.
	 *
	 * @var string[]
	 */
	const ALLOWED_TYPES = array(
		'LocalBusiness',
		'AccountingService',
		'AutoDealer',
		'Dentist',
		'FinancialService',
		'FoodEstablishment',
		'GeneralContractor',
		'HealthAndBeautyBusiness',
		'HomeAndConstructionBusiness',
		'Hospital',
		'InsuranceAgency',
		'LegalService',
		'MedicalBusiness',
		'ProfessionalService',
		'RealEstateAgent',
		'Restaurant',
		'Store',
	);

	/**
	 * Constructor.
	 *
	 * Registers the wp_head hook for schema output.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 99 );
	}

	// -------------------------------------------------------------------------
	// Output
	// -------------------------------------------------------------------------

	/**
	 * Output JSON-LD schema markup in the page head.
	 *
	 * Checks at wp_head time whether the current singular page contains the
	 * shortcode or block, and whether schema output is enabled. Outputs at
	 * most once per page regardless of shortcode instance count.
	 *
	 * @return void
	 */
	public function output_schema(): void {
		if ( self::$output_done ) {
			return;
		}

		// Respect global schema disable toggle in settings.
		$settings = get_option( 'dh_reviews_settings', array() );
		if ( ! empty( $settings['disable_schema'] ) ) {
			return;
		}

		// Schema only makes sense on singular posts/pages.
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$has_block     = has_block( 'dh/google-reviews', $post );
		$has_shortcode = has_shortcode( $post->post_content, 'dh_reviews' );

		if ( ! $has_shortcode && ! $has_block ) {
			return;
		}

		// For shortcodes: check whether at least one instance has schema enabled.
		// A shortcode with schema="false" explicitly opts out; all others default to enabled.
		if ( $has_shortcode && ! $has_block ) {
			if ( ! $this->page_has_schema_enabled( $post->post_content ) ) {
				return;
			}
		}

		// Determine location slug from the first shortcode on the page.
		$location_slug = $this->get_page_location_slug( $post->post_content );

		$schema = $this->build_schema( $location_slug );
		if ( empty( $schema ) ) {
			return;
		}

		$schema = apply_filters( 'dh_reviews_schema_data', $schema );

		$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! $json ) {
			return;
		}

		self::$output_done = true;

		echo '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -------------------------------------------------------------------------
	// Schema construction
	// -------------------------------------------------------------------------

	/**
	 * Build the complete schema array.
	 *
	 * When the "already have LocalBusiness schema" toggle is on, omits the
	 * business name and address so as not to duplicate the parent entity.
	 * When off, includes the full LocalBusiness wrapper per Section 5.1.
	 *
	 * @param string $location_slug Optional location taxonomy slug for filtering.
	 * @return array Schema data array ready for json_encode, or empty on failure.
	 */
	public function build_schema( string $location_slug = '' ): array {
		$settings          = get_option( 'dh_reviews_settings', array() );
		$has_local_schema  = ! empty( $settings['has_existing_local_business_schema'] );

		$aggregate = $this->get_aggregate_data( $location_slug );

		// Do not output schema if there are no published reviews.
		if ( empty( $aggregate['reviewCount'] ) || $aggregate['reviewCount'] < 1 ) {
			return array();
		}

		$business_type = $this->get_business_type( $settings );

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => $business_type,
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $aggregate['ratingValue'],
				'reviewCount' => (string) $aggregate['reviewCount'],
				'bestRating'  => '5',
				'worstRating' => '1',
			),
			'review'          => $this->build_review_items( $location_slug ),
		);

		// Full entity mode: include name, address, and sameAs.
		// Skipped when site already outputs a LocalBusiness entity.
		if ( ! $has_local_schema ) {
			$business = $this->get_business_details();

			if ( ! empty( $business['name'] ) ) {
				$schema['name'] = $business['name'];
			}

			if ( ! empty( $business['address'] ) ) {
				$schema['address'] = $business['address'];
			}

			if ( ! empty( $business['same_as'] ) ) {
				$schema['sameAs'] = $business['same_as'];
			}
		}

		return $schema;
	}

	/**
	 * Get the aggregate rating data for a location.
	 *
	 * Reads from the transient cache set by the Render class. If the transient
	 * is absent (e.g. first load), calculates directly from the CPT.
	 *
	 * @param string $location_slug Location taxonomy slug, or empty for all.
	 * @return array Keys: ratingValue, reviewCount, bestRating, worstRating.
	 */
	public function get_aggregate_data( string $location_slug = '' ): array {
		$cache_key = 'dh_reviews_aggregate_' . ( $location_slug ?: 'all' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Calculate from the CPT directly.
		$args = array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => false,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_dh_star_rating',
					'compare' => 'EXISTS',
				),
			),
		);

		if ( $location_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $location_slug,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$post_ids = $query->posts;
		$total    = 0;
		$count    = 0;

		foreach ( $post_ids as $post_id ) {
			$rating = (int) get_post_meta( $post_id, '_dh_star_rating', true );
			if ( $rating >= 1 && $rating <= 5 ) {
				$total += $rating;
				$count++;
			}
		}

		return array(
			'ratingValue' => $count > 0 ? round( $total / $count, 1 ) : 0,
			'reviewCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	}

	/**
	 * Build the array of Review schema objects from published review posts.
	 *
	 * Fetches up to 50 most recent reviews. Reviews without a body or rating
	 * are skipped as they would fail Google's Rich Results validation.
	 *
	 * @param string $location_slug Optional location taxonomy slug.
	 * @return array Array of Review schema items.
	 */
	public function build_review_items( string $location_slug = '' ): array {
		$args = array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( $location_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $location_slug,
				),
			);
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $review ) {
			$reviewer_name = (string) get_post_meta( $review->ID, '_dh_reviewer_name', true );
			if ( ! $reviewer_name ) {
				$reviewer_name = get_the_title( $review );
			}

			$star_rating = (int) get_post_meta( $review->ID, '_dh_star_rating', true );
			$review_body = wp_strip_all_tags( $review->post_content );
			$date        = gmdate( 'Y-m-d', (int) strtotime( $review->post_date ) );

			// Google requires both a rating value and a reviewBody.
			if ( ! $star_rating || ! $review_body ) {
				continue;
			}

			$items[] = array(
				'@type'          => 'Review',
				'author'         => array(
					'@type' => 'Person',
					'name'  => $reviewer_name,
				),
				'datePublished'  => $date,
				'reviewRating'   => array(
					'@type'       => 'Rating',
					'ratingValue' => (string) $star_rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				),
				'reviewBody'     => $review_body,
			);
		}

		return $items;
	}

	/**
	 * Get business details from plugin settings for the LocalBusiness wrapper.
	 *
	 * Omits address fields that are empty so the JSON-LD stays clean.
	 * If Google Place ID is configured, constructs a sameAs Google Maps URL.
	 *
	 * @return array Keys: name (string), address (array|empty), same_as (string).
	 */
	public function get_business_details(): array {
		$settings = get_option( 'dh_reviews_settings', array() );

		// Build PostalAddress, keeping only fields with values.
		$raw_address = array(
			'streetAddress'   => $settings['street_address'] ?? '',
			'addressLocality' => $settings['city'] ?? '',
			'addressRegion'   => $settings['state'] ?? '',
			'postalCode'      => $settings['postcode'] ?? '',
			'addressCountry'  => $settings['country'] ?? 'AU',
		);

		$filled = array_filter( $raw_address, fn( $v ) => '' !== $v );
		$address = array();
		if ( ! empty( $filled ) ) {
			$address          = $filled;
			$address['@type'] = 'PostalAddress';
			// Ensure @type is first in the array.
			uksort( $address, fn( $a ) => '@type' === $a ? -1 : 1 );
		}

		// Build Google Maps sameAs URL from Place ID.
		$place_id = trim( $settings['google_place_id'] ?? '' );
		$same_as  = '';
		if ( $place_id ) {
			$same_as = 'https://www.google.com/maps/search/?api=1&query_place_id=' . rawurlencode( $place_id );
		}

		return array(
			'name'    => trim( $settings['business_name'] ?? '' ),
			'address' => $address,
			'same_as' => $same_as,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Determine the @type value for the schema entity.
	 *
	 * Falls back to LocalBusiness if the stored type is empty or not in the
	 * allowed list. Accepts any Schema.org type identifier matching /^[A-Z][A-Za-z]+$/
	 * in addition to the explicit ALLOWED_TYPES list.
	 *
	 * @param array $settings Plugin settings array.
	 * @return string Schema.org type string.
	 */
	private function get_business_type( array $settings ): string {
		$type = trim( $settings['business_type'] ?? '' );

		if ( ! $type ) {
			return 'LocalBusiness';
		}

		// Accept type if it is in our explicit list.
		if ( in_array( $type, self::ALLOWED_TYPES, true ) ) {
			return $type;
		}

		// Accept any PascalCase identifier (catches valid Schema.org types not in list).
		if ( preg_match( '/^[A-Z][A-Za-z]+$/', $type ) ) {
			return $type;
		}

		return 'LocalBusiness';
	}

	/**
	 * Check whether at least one [dh_reviews] shortcode in the page content
	 * has schema output enabled (i.e. schema attribute is not explicitly false).
	 *
	 * @param string $content Post content to scan.
	 * @return bool True if schema should be output.
	 */
	private function page_has_schema_enabled( string $content ): bool {
		preg_match_all( '/\[dh_reviews[^\]]*\]/i', $content, $matches );

		if ( empty( $matches[0] ) ) {
			return false;
		}

		foreach ( $matches[0] as $shortcode_str ) {
			// Strip the tag name and brackets so shortcode_parse_atts gets just the atts.
			$raw  = preg_replace( '/^\[dh_reviews\s*/i', '', rtrim( $shortcode_str, ']' ) );
			$atts = shortcode_parse_atts( $raw );

			$schema_val = isset( $atts['schema'] ) ? strtolower( (string) $atts['schema'] ) : 'true';
			if ( 'false' !== $schema_val && '0' !== $schema_val ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract the location slug from the first [dh_reviews] shortcode in the content.
	 *
	 * @param string $content Post content to scan.
	 * @return string Location taxonomy slug, or empty string for all locations.
	 */
	private function get_page_location_slug( string $content ): string {
		if ( ! preg_match( '/\[dh_reviews([^\]]*)\]/i', $content, $match ) ) {
			return '';
		}

		$atts = shortcode_parse_atts( trim( $match[1] ) );
		return isset( $atts['location'] ) ? sanitize_text_field( $atts['location'] ) : '';
	}

	/**
	 * Flag that schema has been queued for output on this page load.
	 *
	 * Called by Render::render_shortcode() for future extensibility.
	 *
	 * @param string $location_slug Location slug to mark as queued.
	 * @return void
	 */
	public function mark_schema_queued( string $location_slug = '' ): void {
		// Reserved for external callers. Output gating is handled via static flag.
	}

	/**
	 * Check whether schema output has already been done for this page.
	 *
	 * @param string $location_slug Location slug (unused; retained for API compatibility).
	 * @return bool True if schema has already been output.
	 */
	public function is_schema_queued( string $location_slug = '' ): bool {
		return self::$output_done;
	}
}
