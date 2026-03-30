<?php
/**
 * Plugin activator.
 *
 * Handles tasks that run on plugin activation: flushing rewrite rules,
 * scheduling the sync cron event, and setting default option values.
 * See SPEC.md Section 3.3 (cron schedule) and Section 7.2 (default settings).
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Runs on plugin activation to set up initial state.
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->register_post_type();
		$this->schedule_cron();
		$this->set_default_options();
		flush_rewrite_rules();
	}

	/**
	 * Register the CPT so rewrite rules can be flushed correctly.
	 *
	 * @return void
	 */
	private function register_post_type(): void {
		// Stub: call CPT registration to ensure rewrite rules exist before flush.
	}

	/**
	 * Schedule the review sync cron event.
	 *
	 * @return void
	 */
	private function schedule_cron(): void {
		// Stub: schedule dh_reviews_sync event if not already scheduled.
	}

	/**
	 * Set default plugin options on first activation.
	 *
	 * @return void
	 */
	private function set_default_options(): void {
		// Stub: populate dh_reviews_settings with defaults if not set.
	}
}
