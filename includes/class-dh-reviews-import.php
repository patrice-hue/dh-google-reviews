<?php
/**
 * CSV import handler.
 *
 * Processes CSV file uploads containing review data, validates rows,
 * creates dh_review CPT posts, and recalculates aggregate ratings
 * after import.
 * See SPEC.md Section 7.3 for CSV format and import logic.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Import
 *
 * Handles CSV review import including validation and error reporting.
 */
class Import {

	/**
	 * Constructor.
	 *
	 * Registers the admin POST handler for CSV upload.
	 */
	public function __construct() {
		add_action( 'admin_post_dh_reviews_import_csv', array( $this, 'handle_csv_upload' ) );
	}

	/**
	 * Handle the CSV file upload form submission.
	 *
	 * @return void
	 */
	public function handle_csv_upload(): void {
		// Stub: verify nonce, validate file, process CSV, redirect with results.
	}

	/**
	 * Parse a CSV file and return an array of review data rows.
	 *
	 * @param string $file_path Path to the uploaded CSV file.
	 * @return array Array of parsed row data.
	 */
	public function parse_csv( string $file_path ): array {
		// Stub: open CSV, read header, map columns, return rows.
		return array();
	}

	/**
	 * Validate a single row of CSV data.
	 *
	 * @param array $row    Row data with column keys.
	 * @param int   $line   Line number for error reporting.
	 * @return array|false Validated row data or false if invalid.
	 */
	public function validate_row( array $row, int $line ): array|false {
		// Stub: check required fields (reviewer_name, star_rating, review_text).
		return false;
	}

	/**
	 * Create a review CPT post from validated CSV row data.
	 *
	 * @param array $row Validated row data.
	 * @return int|false Post ID on success, false on failure.
	 */
	public function create_review_from_row( array $row ): int|false {
		// Stub: wp_insert_post with _dh_review_source = csv_import.
		return false;
	}

	/**
	 * Validate the uploaded file (type check, size limit).
	 *
	 * @param array $file $_FILES array element.
	 * @return string|false File path on success, false on failure.
	 */
	public function validate_file( array $file ): string|false {
		// Stub: check file type is CSV, size <= 5MB.
		return false;
	}
}
