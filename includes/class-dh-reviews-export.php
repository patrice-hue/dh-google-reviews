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
		add_action( 'admin_post_dh_reviews_export_json', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle the export request and send JSON file download.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		// Stub: verify nonce and capability, build export data, send as download.
	}

	/**
	 * Build the export data array from all review posts.
	 *
	 * @return array Array of review data with all meta fields.
	 */
	public function build_export_data(): array {
		// Stub: query all dh_review posts, include all meta.
		return array();
	}
}
