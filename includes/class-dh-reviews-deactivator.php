<?php
/**
 * Plugin deactivator.
 *
 * Handles tasks that run on plugin deactivation: clearing the sync
 * cron event. Data is preserved for reactivation; full cleanup
 * happens in uninstall.php.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Runs on plugin deactivation.
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->clear_cron();
	}

	/**
	 * Remove the scheduled sync cron event.
	 *
	 * @return void
	 */
	private function clear_cron(): void {
		// Stub: clear dh_reviews_sync scheduled event.
	}
}
