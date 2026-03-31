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
	 * WP Cron event hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'dh_reviews_sync';

	/**
	 * Mapping from settings sync_frequency value to WP Cron schedule name.
	 *
	 * @var string[]
	 */
	const SCHEDULE_MAP = array(
		'6h'  => 'dh_reviews_6h',
		'12h' => 'dh_reviews_12h',
		'24h' => 'daily',
	);

	/**
	 * Google API starRating enum to integer mapping.
	 *
	 * @var int[]
	 */
	const STAR_MAP = array(
		'ONE'   => 1,
		'TWO'   => 2,
		'THREE' => 3,
		'FOUR'  => 4,
		'FIVE'  => 5,
	);

	/**
	 * Constructor.
	 *
	 * Registers the cron callback, custom schedules, AJAX handler,
	 * and settings-change listener for rescheduling.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
		add_action( 'wp_ajax_dh_reviews_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( 'update_option_' . 'dh_reviews_settings', array( $this, 'reschedule_cron' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Cron scheduling
	// -------------------------------------------------------------------------

	/**
	 * Register custom cron intervals (6h and 12h).
	 *
	 * @param array $schedules Existing registered cron schedules.
	 * @return array Modified schedules with plugin intervals added.
	 */
	public function register_cron_schedules( array $schedules ): array {
		$schedules['dh_reviews_6h'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'dh-google-reviews' ),
		);
		$schedules['dh_reviews_12h'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 hours', 'dh-google-reviews' ),
		);
		return $schedules;
	}

	/**
	 * Reschedule the cron event when settings are saved.
	 *
	 * Called via update_option_dh_reviews_settings action.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function reschedule_cron( $old_value, $new_value ): void {
		$freq = $new_value['sync_frequency'] ?? '24h';
		$this->schedule_cron( $freq );
	}

	/**
	 * Schedule or clear the WP Cron event for the given frequency.
	 *
	 * @param string $freq Frequency key: '6h', '12h', '24h', or 'manual'.
	 * @return void
	 */
	public function schedule_cron( string $freq ): void {
		// Remove any existing scheduled event first.
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		if ( 'manual' === $freq || ! isset( self::SCHEDULE_MAP[ $freq ] ) ) {
			return;
		}

		wp_schedule_event( time(), self::SCHEDULE_MAP[ $freq ], self::CRON_HOOK );
	}

	// -------------------------------------------------------------------------
	// Sync engine
	// -------------------------------------------------------------------------

	/**
	 * Run the full sync process.
	 *
	 * Fetches all reviews from the GBP API, deduplicates against the CPT,
	 * creates or updates posts, trashes reviews removed from Google, and
	 * logs the result.
	 *
	 * @return array Sync result with keys: created, updated, trashed, errors.
	 */
	public function run_sync(): array {
		$result = array(
			'timestamp' => time(),
			'created'   => 0,
			'updated'   => 0,
			'trashed'   => 0,
			'errors'    => array(),
		);

		$api      = new API();
		$settings = get_option( 'dh_reviews_settings', array() );

		if ( ! $api->is_connected() ) {
			$result['errors'][] = __( 'API not connected. Sync aborted.', 'dh-google-reviews' );
			$this->log_sync_result( $result );
			return $result;
		}

		$location_name = $settings['google_location_id'] ?? '';
		if ( '' === $location_name ) {
			$result['errors'][] = __( 'No location configured. Sync aborted.', 'dh-google-reviews' );
			$this->log_sync_result( $result );
			return $result;
		}

		$min_rating        = (int) ( $settings['min_rating_publish'] ?? 1 );
		$below_threshold   = $settings['below_threshold_action'] ?? 'draft';

		// --- Fetch all reviews from API ---
		$all_reviews = array();
		$page_token  = null;

		do {
			$page = $api->list_reviews( $location_name, $page_token );
			if ( false === $page ) {
				$result['errors'][] = __( 'Failed to fetch reviews from Google API.', 'dh-google-reviews' );
				break;
			}
			$all_reviews = array_merge( $all_reviews, $page['reviews'] );
			$page_token  = $page['nextPageToken'];
		} while ( null !== $page_token );

		if ( ! empty( $result['errors'] ) ) {
			$this->log_sync_result( $result );
			return $result;
		}

		// --- Build set of review IDs currently in DB ---
		$local_ids = $this->get_all_local_review_ids();

		// --- Process each API review ---
		$seen_ids = array();

		foreach ( $all_reviews as $raw_review ) {
			$review = $this->normalize_review( $raw_review );

			if ( '' === $review['gbp_review_id'] ) {
				continue;
			}

			$seen_ids[] = $review['gbp_review_id'];

			$existing_post_id = $this->find_existing_review( $review['gbp_review_id'] );

			if ( false === $existing_post_id ) {
				// New review — create it.
				$status = ( $review['star_rating'] >= $min_rating ) ? 'publish' : $below_threshold;
				if ( 'skip' === $status ) {
					continue;
				}
				$review['post_status'] = ( 'skip' === $below_threshold && $review['star_rating'] < $min_rating ) ? false : $status;
				if ( false === $review['post_status'] ) {
					continue;
				}

				$post_id = $this->create_review( $review );
				if ( $post_id ) {
					$result['created']++;
				} else {
					$result['errors'][] = sprintf(
						/* translators: %s: GBP review ID */
						__( 'Failed to create review %s.', 'dh-google-reviews' ),
						$review['gbp_review_id']
					);
				}
			} else {
				// Existing review — update if updateTime changed.
				$stored_update = get_post_meta( $existing_post_id, '_dh_review_updated', true );
				if ( $stored_update !== $review['update_time'] ) {
					$updated = $this->update_review( $existing_post_id, $review );
					if ( $updated ) {
						$result['updated']++;
					}
				}
			}
		}

		// --- Trash local reviews absent from API response ---
		foreach ( $local_ids as $gbp_id => $post_id ) {
			if ( ! in_array( $gbp_id, $seen_ids, true ) ) {
				wp_trash_post( $post_id );
				$result['trashed']++;
			}
		}

		// --- Update aggregate transient ---
		$this->update_aggregate();

		// --- Log and fire action ---
		$this->log_sync_result( $result );

		do_action( 'dh_reviews_after_sync', $result );

		// Clear WP Rocket page cache for pages using the shortcode.
		if ( function_exists( 'rocket_clean_post' ) ) {
			$this->clear_rocket_cache();
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the manual "Sync Now" AJAX request from admin settings.
	 *
	 * Expects POST fields: nonce, action.
	 * Returns JSON with sync result counts or an error message.
	 *
	 * @return void
	 */
	public function handle_manual_sync(): void {
		check_ajax_referer( 'dh_reviews_manual_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'dh-google-reviews' ) ), 403 );
		}

		$result = $this->run_sync();

		if ( ! empty( $result['errors'] ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $result['errors'] ),
				'result'  => $result,
			) );
		}

		wp_send_json_success( array(
			/* translators: 1: created count, 2: updated count, 3: trashed count */
			'message' => sprintf(
				__( 'Sync complete. %1$d new, %2$d updated, %3$d trashed.', 'dh-google-reviews' ),
				$result['created'],
				$result['updated'],
				$result['trashed']
			),
			'result'  => $result,
		) );
	}

	// -------------------------------------------------------------------------
	// CPT operations
	// -------------------------------------------------------------------------

	/**
	 * Create a new review CPT post from normalised API data.
	 *
	 * @param array $review Normalised review data (output of normalize_review() + post_status).
	 * @return int|false New post ID on success, false on failure.
	 */
	public function create_review( array $review ): int|false {
		$date = ! empty( $review['create_time'] )
			? gmdate( 'Y-m-d H:i:s', (int) strtotime( $review['create_time'] ) )
			: current_time( 'mysql' );

		$post_id = wp_insert_post( array(
			'post_type'    => CPT::POST_TYPE,
			'post_status'  => $review['post_status'] ?? 'publish',
			'post_title'   => $review['reviewer_name'] ?: __( 'Anonymous', 'dh-google-reviews' ),
			'post_content' => $review['review_text'],
			'post_date'    => $date,
			'post_date_gmt'=> $date,
		), true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		$this->save_review_meta( $post_id, $review );

		return $post_id;
	}

	/**
	 * Update an existing review CPT post with fresh API data.
	 *
	 * Updates post content and all meta fields.
	 *
	 * @param int   $post_id Existing CPT post ID.
	 * @param array $review  Normalised review data.
	 * @return bool True on success.
	 */
	public function update_review( int $post_id, array $review ): bool {
		$updated = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $review['review_text'],
			'post_title'   => $review['reviewer_name'] ?: get_the_title( $post_id ),
		), true );

		if ( is_wp_error( $updated ) ) {
			return false;
		}

		$this->save_review_meta( $post_id, $review );
		return true;
	}

	/**
	 * Find an existing review CPT post by its GBP review ID.
	 *
	 * @param string $gbp_review_id The Google review ID (reviewId field).
	 * @return int|false Post ID if found, false otherwise.
	 */
	public function find_existing_review( string $gbp_review_id ): int|false {
		$query = new \WP_Query( array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_dh_gbp_review_id',
					'value' => $gbp_review_id,
				),
			),
		) );

		if ( empty( $query->posts ) ) {
			return false;
		}

		return (int) $query->posts[0];
	}

	// -------------------------------------------------------------------------
	// Logging and aggregate
	// -------------------------------------------------------------------------

	/**
	 * Log a sync result to the dh_reviews_sync_log option.
	 *
	 * Keeps the most recent 10 sync entries.
	 *
	 * @param array $result Sync result data with keys: timestamp, created, updated, trashed, errors.
	 * @return void
	 */
	public function log_sync_result( array $result ): void {
		$log = get_option( 'dh_reviews_sync_log', array() );

		$log[] = array(
			'timestamp' => $result['timestamp'] ?? time(),
			'new'       => $result['created'] ?? 0,
			'updated'   => $result['updated'] ?? 0,
			'trashed'   => $result['trashed'] ?? 0,
			'errors'    => $result['errors'] ?? array(),
		);

		// Keep last 10 entries.
		if ( count( $log ) > 10 ) {
			$log = array_slice( $log, -10 );
		}

		update_option( 'dh_reviews_sync_log', $log );
	}

	/**
	 * Recalculate and cache the aggregate rating transient after a sync.
	 *
	 * Deletes any stale transients and recalculates from the CPT directly
	 * so the new values are hot-cached for the next page render.
	 *
	 * @param string $location_slug Location taxonomy slug, or empty for global.
	 * @return void
	 */
	public function update_aggregate( string $location_slug = '' ): void {
		// Clear all location-specific and global transients.
		delete_transient( 'dh_reviews_aggregate_all' );

		// Also clear any location-specific ones we know about.
		$terms = get_terms( array( 'taxonomy' => CPT::TAXONOMY, 'hide_empty' => false, 'fields' => 'slugs' ) );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $slug ) {
				delete_transient( 'dh_reviews_aggregate_' . $slug );
			}
		}

		// Recalculate and cache the global aggregate.
		$this->calculate_and_cache_aggregate( '' );

		// Recalculate for the specific location if one was synced.
		if ( $location_slug ) {
			$this->calculate_and_cache_aggregate( $location_slug );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalise a raw API review array to internal field names.
	 *
	 * @param array $raw Raw review data from the GBP API response.
	 * @return array Normalised review data.
	 */
	private function normalize_review( array $raw ): array {
		return array(
			'gbp_review_id'  => $raw['reviewId'] ?? '',
			'reviewer_name'  => $raw['reviewer']['displayName'] ?? '',
			'reviewer_photo' => $raw['reviewer']['profilePhotoUrl'] ?? '',
			'star_rating'    => self::STAR_MAP[ $raw['starRating'] ?? '' ] ?? 0,
			'review_text'    => $raw['comment'] ?? '',
			'create_time'    => $raw['createTime'] ?? '',
			'update_time'    => $raw['updateTime'] ?? '',
			'owner_reply'    => $raw['reviewReply']['comment'] ?? '',
			'reply_time'     => $raw['reviewReply']['updateTime'] ?? '',
		);
	}

	/**
	 * Persist all meta fields for a review post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $review  Normalised review data.
	 * @return void
	 */
	private function save_review_meta( int $post_id, array $review ): void {
		update_post_meta( $post_id, '_dh_reviewer_name', $review['reviewer_name'] );
		update_post_meta( $post_id, '_dh_reviewer_photo', $review['reviewer_photo'] );
		update_post_meta( $post_id, '_dh_star_rating', $review['star_rating'] );
		update_post_meta( $post_id, '_dh_gbp_review_id', $review['gbp_review_id'] );
		update_post_meta( $post_id, '_dh_review_updated', $review['update_time'] );
		update_post_meta( $post_id, '_dh_review_source', 'gbp_api' );
		update_post_meta( $post_id, '_dh_review_verified', '1' );

		if ( '' !== $review['owner_reply'] ) {
			update_post_meta( $post_id, '_dh_owner_reply', $review['owner_reply'] );
			update_post_meta( $post_id, '_dh_reply_date', $review['reply_time'] );
		}
	}

	/**
	 * Retrieve all GBP review IDs for locally stored reviews, keyed by GBP ID.
	 *
	 * Used to detect reviews that have been removed from Google and should be
	 * trashed locally.
	 *
	 * @return array<string, int> Map of GBP review ID → local post ID.
	 */
	private function get_all_local_review_ids(): array {
		$query = new \WP_Query( array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_dh_gbp_review_id',
					'compare' => 'EXISTS',
				),
			),
		) );

		$map = array();
		foreach ( $query->posts as $post_id ) {
			$gbp_id = get_post_meta( (int) $post_id, '_dh_gbp_review_id', true );
			if ( $gbp_id ) {
				$map[ $gbp_id ] = (int) $post_id;
			}
		}
		return $map;
	}

	/**
	 * Calculate the aggregate rating for a location and store it in a transient.
	 *
	 * @param string $location_slug Taxonomy slug, or empty string for all locations.
	 * @return void
	 */
	private function calculate_and_cache_aggregate( string $location_slug ): void {
		$cache_key = 'dh_reviews_aggregate_' . ( $location_slug ?: 'all' );

		$args = array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
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

		$query = new \WP_Query( $args );
		$total = 0;
		$count = 0;

		foreach ( $query->posts as $post_id ) {
			$rating = (int) get_post_meta( (int) $post_id, '_dh_star_rating', true );
			if ( $rating >= 1 && $rating <= 5 ) {
				$total += $rating;
				$count++;
			}
		}

		$data = array(
			'ratingValue' => $count > 0 ? round( $total / $count, 1 ) : 0,
			'reviewCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 1,
		);

		set_transient( $cache_key, $data, DAY_IN_SECONDS );
	}

	/**
	 * Clear WP Rocket page cache for all pages containing the dh_reviews shortcode.
	 *
	 * @return void
	 */
	private function clear_rocket_cache(): void {
		$pages = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		) );

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'dh_reviews' )
				|| has_block( 'dh/google-reviews', $page )
			) {
				rocket_clean_post( $page->ID ); // @phpstan-ignore-line
			}
		}
	}
}
