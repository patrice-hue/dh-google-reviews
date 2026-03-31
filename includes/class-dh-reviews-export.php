<?php
/**
 * JSON export handler.
 *
 * Exports all dh_review CPT posts with meta fields as JSON
 * for backup or migration purposes.
 * See SPEC.md Section 7.4 for export specification.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export
 *
 * Handles JSON export of review data.
 */
class Export {

	/**
	 * Constructor.
	 *
	 * Registers the admin POST handler for JSON export download.
	 */
	public function __construct() {
		add_action( 'admin_post_dh_reviews_json_export', array( $this, 'handle_export' ) );
	}

	// -------------------------------------------------------------------------
	// Export handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the export request: verify nonce, build data, send as file download.
	 *
	 * Exits after sending the response so WordPress does not append extra output.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		check_admin_referer( 'dh_reviews_json_export', 'dh_reviews_export_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dh-google-reviews' ) );
		}

		$data     = $this->build_export_data();
		$json     = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		$filename = 'dh-reviews-export-' . gmdate( 'Y-m-d' ) . '.json';

		if ( ! $json ) {
			wp_die( esc_html__( 'Failed to encode export data.', 'dh-google-reviews' ) );
		}

		// Send file download headers.
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	// -------------------------------------------------------------------------
	// Data builder
	// -------------------------------------------------------------------------

	/**
	 * Build the complete export data structure.
	 *
	 * Includes export metadata, aggregate stats with per-location breakdown,
	 * and a flat array of all review records with all meta fields.
	 *
	 * @return array Export data array ready for json_encode.
	 */
	public function build_export_data(): array {
		$reviews_data = $this->fetch_all_reviews();
		$aggregate    = $this->build_aggregate_stats( $reviews_data );

		return array(
			'export_date'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'plugin_version' => DH_REVIEWS_VERSION,
			'aggregate'      => $aggregate,
			'reviews'        => $reviews_data,
		);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch all dh_review posts with all meta fields and taxonomy terms.
	 *
	 * @return array Array of review data arrays.
	 */
	private function fetch_all_reviews(): array {
		$query = new \WP_Query( array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$reviews = array();

		foreach ( $query->posts as $post ) {
			$terms    = wp_get_post_terms( $post->ID, CPT::TAXONOMY, array( 'fields' => 'slugs' ) );
			$location = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : '';

			$reviews[] = array(
				'id'              => $post->ID,
				'status'          => $post->post_status,
				'reviewer_name'   => get_post_meta( $post->ID, '_dh_reviewer_name', true ) ?: $post->post_title,
				'star_rating'     => (int) get_post_meta( $post->ID, '_dh_star_rating', true ),
				'review_text'     => $post->post_content,
				'review_date'     => $post->post_date,
				'owner_reply'     => get_post_meta( $post->ID, '_dh_owner_reply', true ),
				'reply_date'      => get_post_meta( $post->ID, '_dh_reply_date', true ),
				'reviewer_photo'  => get_post_meta( $post->ID, '_dh_reviewer_photo', true ),
				'source'          => get_post_meta( $post->ID, '_dh_review_source', true ),
				'gbp_review_id'   => get_post_meta( $post->ID, '_dh_gbp_review_id', true ),
				'review_updated'  => get_post_meta( $post->ID, '_dh_review_updated', true ),
				'verified'        => (bool) get_post_meta( $post->ID, '_dh_review_verified', true ),
				'location'        => $location,
			);
		}

		return $reviews;
	}

	/**
	 * Build aggregate stats from the export reviews array.
	 *
	 * @param array $reviews Reviews array from fetch_all_reviews().
	 * @return array Aggregate stats with total, mean, and per-location breakdown.
	 */
	private function build_aggregate_stats( array $reviews ): array {
		$published = array_filter( $reviews, fn( $r ) => 'publish' === $r['status'] );

		$total  = count( $published );
		$sum    = array_sum( array_column( $published, 'star_rating' ) );
		$mean   = $total > 0 ? round( $sum / $total, 2 ) : 0.0;

		// Per-location breakdown.
		$by_location_raw = array();
		foreach ( $published as $r ) {
			$loc = $r['location'] ?: '__global__';
			if ( ! isset( $by_location_raw[ $loc ] ) ) {
				$by_location_raw[ $loc ] = array( 'count' => 0, 'sum' => 0 );
			}
			$by_location_raw[ $loc ]['count']++;
			$by_location_raw[ $loc ]['sum'] += (int) $r['star_rating'];
		}

		$by_location = array();
		foreach ( $by_location_raw as $slug => $data ) {
			$by_location[] = array(
				'location' => '__global__' === $slug ? '' : $slug,
				'count'    => $data['count'],
				'mean'     => $data['count'] > 0 ? round( $data['sum'] / $data['count'], 2 ) : 0.0,
			);
		}

		return array(
			'total_reviews' => $total,
			'mean_rating'   => $mean,
			'by_location'   => $by_location,
		);
	}
}
