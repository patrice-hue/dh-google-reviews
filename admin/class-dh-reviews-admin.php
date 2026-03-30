<?php
/**
 * Admin interface.
 *
 * Registers the admin menu structure, settings pages, and admin
 * asset enqueuing. Handles Settings API registration for all
 * plugin configuration sections.
 * See SPEC.md Section 7 for menu structure and settings fields.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Manages the WordPress admin interface for the plugin.
 */
class Admin {

	/**
	 * Constructor.
	 *
	 * Registers hooks for admin menus, settings, and asset loading.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the admin menu and submenus.
	 *
	 * Menu structure:
	 *   Reviews (top level)
	 *   ├── All Reviews (CPT list)
	 *   ├── Add Manual Review (CPT new)
	 *   ├── Import / Export
	 *   ├── Settings
	 *   └── Sync Log
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Stub: add_menu_page and add_submenu_page calls per Section 7.1.
	}

	/**
	 * Register plugin settings using the WordPress Settings API.
	 *
	 * Sections: API Connection, Sync Configuration, Business Details, Display Defaults.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Stub: register_setting, add_settings_section, add_settings_field
		// for all fields in Section 7.2.
	}

	/**
	 * Render the main settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Stub: include admin/views/settings.php template.
	}

	/**
	 * Render the import/export page.
	 *
	 * @return void
	 */
	public function render_import_page(): void {
		// Stub: include admin/views/import.php template.
	}

	/**
	 * Render the sync log page.
	 *
	 * @return void
	 */
	public function render_sync_log_page(): void {
		// Stub: include admin/views/sync-log.php template.
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Stub: conditionally enqueue admin/css/dh-reviews-admin.css.
	}

	/**
	 * Sanitize the plugin settings array before saving.
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitised settings.
	 */
	public function sanitize_settings( array $input ): array {
		// Stub: sanitize each field appropriately.
		return $input;
	}
}
