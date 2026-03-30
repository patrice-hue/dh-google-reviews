<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin data: options, CPT posts, transients, and cron events.
 *
 * @package DH_Reviews
 */

// Abort if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin options.
 */
function dh_reviews_delete_options(): void {
	$options = array(
		'dh_reviews_access_token',
		'dh_reviews_refresh_token',
		'dh_reviews_token_expiry',
		'dh_reviews_gcp_client_id',
		'dh_reviews_gcp_client_secret',
		'dh_reviews_sync_log',
		'dh_reviews_settings',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete all dh_review CPT posts and associated meta.
 */
function dh_reviews_delete_posts(): void {
	// Stub: query and delete all dh_review posts.
}

/**
 * Delete aggregate rating transients.
 */
function dh_reviews_delete_transients(): void {
	// Stub: delete transients matching dh_reviews_aggregate_*.
}

/**
 * Clear scheduled cron events.
 */
function dh_reviews_clear_cron(): void {
	wp_clear_scheduled_hook( 'dh_reviews_sync' );
}

// Execute cleanup.
dh_reviews_delete_options();
dh_reviews_delete_posts();
dh_reviews_delete_transients();
dh_reviews_clear_cron();
