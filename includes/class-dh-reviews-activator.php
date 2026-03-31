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
		$this->set_default_options();
		$this->schedule_cron();
		flush_rewrite_rules();
	}

	/**
	 * Register the CPT so rewrite rules exist before flush.
	 *
	 * Instantiating CPT calls register_post_type() and register_taxonomy()
	 * directly, which is safe to call multiple times.
	 *
	 * @return void
	 */
	private function register_post_type(): void {
		$cpt = new CPT();
		$cpt->register_post_type();
		$cpt->register_taxonomy();
	}

	/**
	 * Schedule the review sync cron event with the default 24h interval.
	 *
	 * Uses the `daily` built-in WP Cron schedule on activation so we
	 * do not depend on the custom schedules registered by the Sync class
	 * (which are only available after the init hook fires). The Sync class
	 * reschedules to the correct interval when settings are saved.
	 *
	 * @return void
	 */
	private function schedule_cron(): void {
		if ( wp_next_scheduled( Sync::CRON_HOOK ) ) {
			return;
		}

		$settings = get_option( 'dh_reviews_settings', array() );
		$freq     = $settings['sync_frequency'] ?? '24h';

		// Map to built-in WP Cron schedules only (custom ones not yet registered).
		$schedule = 'daily';
		if ( '6h' === $freq || '12h' === $freq ) {
			$schedule = 'twicedaily'; // closest built-in; Sync will correct on next settings save.
		}
		if ( 'manual' === $freq ) {
			return; // No automatic schedule.
		}

		wp_schedule_event( time(), $schedule, Sync::CRON_HOOK );
	}

	/**
	 * Populate dh_reviews_settings with defaults on first activation.
	 *
	 * Uses add_option (no-op if option already exists) so existing settings
	 * are never overwritten during re-activation.
	 *
	 * @return void
	 */
	private function set_default_options(): void {
		$defaults = array(
			// API Connection (empty by default).
			'google_client_id'                   => '',
			'google_client_secret'               => '',
			'google_account_id'                  => '',
			'google_location_id'                 => '',
			'oauth_access_token'                 => '',
			'oauth_refresh_token'                => '',
			'oauth_token_expires'                => 0,
			'oauth_connected_email'              => '',

			// Sync Configuration.
			'sync_frequency'                     => '24h',
			'min_rating_publish'                 => '1',
			'below_threshold_action'             => 'draft',

			// Business Details.
			'business_name'                      => '',
			'street_address'                     => '',
			'city'                               => '',
			'state'                              => '',
			'postcode'                           => '',
			'country'                            => 'AU',
			'business_type'                      => '',
			'google_place_id'                    => '',
			'cta_url_override'                   => '',
			'disable_schema'                     => '',
			'has_existing_local_business_schema' => '',

			// Display Defaults.
			'default_layout'                     => 'grid',
			'default_columns'                    => '3',
			'default_visible'                    => '3',
			'default_excerpt_length'             => 200,
			'default_date_format'                => 'relative',
			'show_owner_replies'                 => '1',
			'show_reviewer_photos'               => '1',
			'show_google_icon'                   => '1',
			'show_powered_by'                    => '1',
			'show_cta'                           => '1',
			'show_dots'                          => '1',
			'photo_proxy'                        => '',
			'cta_text'                           => '',
			'custom_css'                         => '',
		);

		// add_option is a no-op when the option already exists.
		add_option( 'dh_reviews_settings', $defaults );
	}
}
