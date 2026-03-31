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
	 * Maximum allowed CSV file size in bytes (5 MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Expected CSV column headers (in any order).
	 *
	 * @var string[]
	 */
	const EXPECTED_HEADERS = array(
		'reviewer_name',
		'star_rating',
		'review_text',
		'review_date',
		'owner_reply',
		'location',
	);

	/**
	 * Last validation error message set by validate_row().
	 *
	 * @var string
	 */
	private string $last_validation_error = '';

	/**
	 * Constructor.
	 *
	 * Registers the admin POST handler for CSV upload.
	 */
	public function __construct() {
		add_action( 'admin_post_dh_reviews_csv_import', array( $this, 'handle_csv_upload' ) );
	}

	// -------------------------------------------------------------------------
	// Upload handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the CSV file upload form submission.
	 *
	 * Verifies nonce and capability, validates file, parses and imports rows,
	 * then redirects back to the import page with a result transient.
	 *
	 * @return void
	 */
	public function handle_csv_upload(): void {
		check_admin_referer( 'dh_reviews_csv_import', 'dh_reviews_import_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dh-google-reviews' ) );
		}

		$redirect = admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-import' );

		// Validate uploaded file.
		$file      = isset( $_FILES['dh_reviews_csv'] ) ? $_FILES['dh_reviews_csv'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$file_path = $this->validate_file( $file );

		if ( false === $file_path ) {
			$this->redirect_with_result( array( 'error' => $this->last_validation_error ), $redirect );
			return;
		}

		// Parse CSV.
		$rows = $this->parse_csv( $file_path );

		if ( empty( $rows ) ) {
			$this->redirect_with_result( array( 'error' => __( 'The CSV file contained no readable data rows.', 'dh-google-reviews' ) ), $redirect );
			return;
		}

		$default_status = sanitize_text_field( $_POST['dh_reviews_import_status'] ?? 'publish' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $default_status, array( 'publish', 'draft' ), true ) ) {
			$default_status = 'publish';
		}

		$created      = 0;
		$skipped      = 0;
		$error_list   = array();

		foreach ( $rows as $line => $row ) {
			$validated = $this->validate_row( $row, $line );

			if ( false === $validated ) {
				$skipped++;
				$error_list[] = sprintf(
					/* translators: 1: line number, 2: error detail */
					__( 'Line %1$d: %2$s', 'dh-google-reviews' ),
					$line,
					$this->last_validation_error
				);
				continue;
			}

			$validated['post_status'] = $default_status;
			$post_id = $this->create_review_from_row( $validated );

			if ( $post_id ) {
				$created++;
			} else {
				$skipped++;
				$error_list[] = sprintf(
					/* translators: %d: line number */
					__( 'Line %d: failed to create post (database error).', 'dh-google-reviews' ),
					$line
				);
			}
		}

		// Bust aggregate transients so they recalculate on next page load.
		$this->bust_aggregate_transients();

		$this->redirect_with_result(
			array(
				'created'       => $created,
				'skipped'       => $skipped,
				'errors'        => count( $error_list ),
				'error_details' => $error_list,
			),
			$redirect
		);
	}

	// -------------------------------------------------------------------------
	// Parsing
	// -------------------------------------------------------------------------

	/**
	 * Parse a CSV file and return an array of keyed row data.
	 *
	 * Uses the first row as the column header map. Rows that do not map
	 * to the expected headers are still returned; validate_row() handles
	 * missing required fields.
	 *
	 * @param string $file_path Absolute path to the uploaded CSV file.
	 * @return array<int, array<string, string>> Rows keyed by 1-based line number.
	 */
	public function parse_csv( string $file_path ): array {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return array();
		}

		$rows    = array();
		$headers = null;
		$line    = 0;

		while ( false !== ( $cols = fgetcsv( $handle, 0, ',' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line++;

			// First row is the header.
			if ( null === $headers ) {
				$headers = array_map( 'strtolower', array_map( 'trim', $cols ) );
				continue;
			}

			// Skip completely blank rows.
			$joined = implode( '', $cols );
			if ( '' === trim( $joined ) ) {
				continue;
			}

			// Pad short rows with empty strings so array_combine doesn't fail.
			while ( count( $cols ) < count( $headers ) ) {
				$cols[] = '';
			}

			// Trim to the width of the header row.
			$cols = array_slice( $cols, 0, count( $headers ) );

			$rows[ $line ] = array_combine( $headers, $cols );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate and sanitize a single CSV row.
	 *
	 * Sets $this->last_validation_error when returning false so the caller
	 * can record a per-row error message.
	 *
	 * @param array $row  Associative row data from parse_csv().
	 * @param int   $line 1-based line number for error context.
	 * @return array|false Sanitized row data ready for create_review_from_row(), or false.
	 */
	public function validate_row( array $row, int $line ) {
		$this->last_validation_error = '';

		$reviewer_name = sanitize_text_field( trim( $row['reviewer_name'] ?? '' ) );
		if ( '' === $reviewer_name ) {
			$this->last_validation_error = __( 'reviewer_name is required.', 'dh-google-reviews' );
			return false;
		}

		$star_rating_raw = trim( $row['star_rating'] ?? '' );
		if ( '' === $star_rating_raw ) {
			$this->last_validation_error = __( 'star_rating is required.', 'dh-google-reviews' );
			return false;
		}

		$star_rating = (int) $star_rating_raw;
		if ( $star_rating < 1 || $star_rating > 5 || (string) $star_rating !== $star_rating_raw ) {
			$this->last_validation_error = sprintf(
				/* translators: %s: the invalid value provided */
				__( 'star_rating must be an integer from 1 to 5 (got: %s).', 'dh-google-reviews' ),
				esc_html( $star_rating_raw )
			);
			return false;
		}

		// review_text: allowed to be empty (Google allows rating-only reviews).
		$review_text = sanitize_textarea_field( trim( $row['review_text'] ?? '' ) );

		// review_date: parse with strtotime; fall back to now.
		$date_raw    = trim( $row['review_date'] ?? '' );
		$timestamp   = ( '' !== $date_raw ) ? strtotime( $date_raw ) : false;
		$review_date = $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : current_time( 'mysql' );

		// Optional fields.
		$owner_reply = sanitize_textarea_field( trim( $row['owner_reply'] ?? '' ) );
		$location    = sanitize_text_field( trim( $row['location'] ?? '' ) );

		return array(
			'reviewer_name' => $reviewer_name,
			'star_rating'   => $star_rating,
			'review_text'   => $review_text,
			'review_date'   => $review_date,
			'owner_reply'   => $owner_reply,
			'location'      => $location,
		);
	}

	// -------------------------------------------------------------------------
	// Post creation
	// -------------------------------------------------------------------------

	/**
	 * Create a dh_review CPT post from a validated CSV row.
	 *
	 * Assigns the dh_review_location taxonomy term if a location slug is
	 * present, creating the term if it does not yet exist.
	 *
	 * @param array $row Validated row data (output of validate_row() with post_status added).
	 * @return int|false New post ID on success, false on failure.
	 */
	public function create_review_from_row( array $row ) {
		$post_id = wp_insert_post(
			array(
				'post_type'     => CPT::POST_TYPE,
				'post_status'   => $row['post_status'] ?? 'publish',
				'post_title'    => $row['reviewer_name'],
				'post_content'  => $row['review_text'],
				'post_date'     => $row['review_date'],
				'post_date_gmt' => get_gmt_from_date( $row['review_date'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_dh_reviewer_name', $row['reviewer_name'] );
		update_post_meta( $post_id, '_dh_star_rating', $row['star_rating'] );
		update_post_meta( $post_id, '_dh_review_source', 'csv_import' );
		update_post_meta( $post_id, '_dh_review_verified', '' );

		if ( '' !== $row['owner_reply'] ) {
			update_post_meta( $post_id, '_dh_owner_reply', $row['owner_reply'] );
		}

		// Assign location taxonomy term, creating it if necessary.
		if ( '' !== $row['location'] ) {
			$term = term_exists( $row['location'], CPT::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $row['location'], CPT::TAXONOMY, array( 'slug' => $row['location'] ) );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				wp_set_post_terms( $post_id, array( $term_id ), CPT::TAXONOMY );
			}
		}

		return $post_id;
	}

	// -------------------------------------------------------------------------
	// File validation
	// -------------------------------------------------------------------------

	/**
	 * Validate the uploaded file for type and size.
	 *
	 * Sets $this->last_validation_error on failure.
	 *
	 * @param array $file $_FILES element (name, tmp_name, size, type, error).
	 * @return string|false Temporary file path on success, false on failure.
	 */
	public function validate_file( array $file ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) ) {
			$this->last_validation_error = __( 'No file was uploaded.', 'dh-google-reviews' );
			return false;
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$this->last_validation_error = sprintf(
				/* translators: %d: PHP upload error code */
				__( 'Upload error (code %d). Check your server upload limits.', 'dh-google-reviews' ),
				(int) $file['error']
			);
			return false;
		}

		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_FILE_SIZE ) {
			$this->last_validation_error = __( 'File exceeds the 5 MB size limit.', 'dh-google-reviews' );
			return false;
		}

		// Validate MIME type using WordPress file type checker (checks content, not just extension).
		$tmp_path  = $file['tmp_name'] ?? '';
		$orig_name = sanitize_file_name( $file['name'] ?? 'upload.csv' );
		$checked   = wp_check_filetype_and_ext( $tmp_path, $orig_name );

		$allowed_types = array( 'csv', 'txt' );
		$ext           = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_types, true ) ) {
			$this->last_validation_error = __( 'Only .csv files are accepted.', 'dh-google-reviews' );
			return false;
		}

		if ( ! is_uploaded_file( $tmp_path ) ) {
			$this->last_validation_error = __( 'Invalid file source.', 'dh-google-reviews' );
			return false;
		}

		return $tmp_path;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Delete all aggregate rating transients so they are recalculated on next request.
	 *
	 * @return void
	 */
	private function bust_aggregate_transients(): void {
		delete_transient( 'dh_reviews_aggregate_all' );

		$terms = get_terms( array(
			'taxonomy'   => CPT::TAXONOMY,
			'hide_empty' => false,
			'fields'     => 'slugs',
		) );

		if ( is_array( $terms ) ) {
			foreach ( $terms as $slug ) {
				delete_transient( 'dh_reviews_aggregate_' . $slug );
			}
		}
	}

	/**
	 * Store the import result in a per-user transient and redirect.
	 *
	 * @param array  $result   Result data array.
	 * @param string $redirect URL to redirect to.
	 * @return void
	 */
	private function redirect_with_result( array $result, string $redirect ): void {
		set_transient(
			'dh_reviews_import_result_' . get_current_user_id(),
			$result,
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
