<?php
/**
 * Review sync orchestration.
 *
 * Manages the WP Cron based sync process: fetching reviews from
 * the GBP API, deduplicating against existing CPT posts, creating
 * or updating review posts, and logging sync results.
 * See SPEC.md Section 3.3 for sync logic and scheduling.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync
 *
 * Orchestrates review sync between GBP API and local CPT storage.
 */
class Sync {

	/**
	 * Constructor.
	 *
	 * Registers the cron callback and admin AJAX handler for manual sync.
	 */
	public function __construct() {
		add_action( 'dh_reviews_sync', array( $this, 'run_sync' ) );
		add_action( 'wp_ajax_dh_reviews_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
	}

	/**
	 * Register custom cron intervals (6h, 12h).
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function register_cron_schedules( array $schedules ): array {
		// Stub: add dh_reviews_6h and dh_reviews_12h intervals.
		return $schedules;
	}

	/**
	 * Run the full sync process.
	 *
	 * Fetches all reviews from the API, deduplicates, creates or
	 * updates CPT posts, trashes removed reviews, and logs the result.
	 *
	 * @return array Sync result with counts (created, updated, trashed, errors).
	 */
	public function run_sync(): array {
		// Stub: implement sync logic per Section 3.3.
		return array();
	}

	/**
	 * Handle the manual "Sync Now" AJAX request from admin.
	 *
	 * @return void
	 */
	public function handle_manual_sync(): void {
		// Stub: verify nonce and capability, run sync, return JSON response.
	}

	/**
	 * Create a new review CPT post from API data.
	 *
	 * @param array $review_data Normalised review data from the API.
	 * @return int|false Post ID on success, false on failure.
	 */
	public function create_review( array $review_data ): int|false {
		// Stub: wp_insert_post with meta fields.
		return false;
	}

	/**
	 * Update an existing review CPT post with fresh API data.
	 *
	 * @param int   $post_id     Existing post ID.
	 * @param array $review_data Updated review data from the API.
	 * @return bool True on success.
	 */
	public function update_review( int $post_id, array $review_data ): bool {
		// Stub: wp_update_post and update_post_meta.
		return false;
	}

	/**
	 * Find existing review post by GBP review ID.
	 *
	 * @param string $gbp_review_id The Google review ID.
	 * @return int|false Post ID if found, false otherwise.
	 */
	public function find_existing_review( string $gbp_review_id ): int|false {
		// Stub: meta query for _dh_gbp_review_id.
		return false;
	}

	/**
	 * Log a sync result to the dh_reviews_sync_log option.
	 *
	 * Keeps the last 10 sync entries.
	 *
	 * @param array $result Sync result data.
	 * @return void
	 */
	public function log_sync_result( array $result ): void {
		// Stub: append to sync log, trim to 10 entries.
	}

	/**
	 * Recalculate and update the aggregate rating transient.
	 *
	 * @param string $location_slug Location taxonomy slug.
	 * @return void
	 */
	public function update_aggregate( string $location_slug = '' ): void {
		// Stub: calculate mean rating, count, set transient.
	}
}
