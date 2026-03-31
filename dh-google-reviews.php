<?php
/**
 * Plugin Name: DH Google Reviews
 * Plugin URI:  https://digitalhitmen.com.au
 * Description: Display Google Business Profile reviews on your WordPress site with shortcodes, Gutenberg blocks, and automatic schema markup output.
 * Version:     1.0.0
 * Author:      Digital Hitmen
 * Author URI:  https://digitalhitmen.com.au
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dh-google-reviews
 * Requires PHP: 7.4
 * Requires at least: 6.4
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'DH_REVIEWS_VERSION', '1.0.0' );

/**
 * Plugin directory path (with trailing slash).
 *
 * @var string
 */
define( 'DH_REVIEWS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL (with trailing slash).
 *
 * @var string
 */
define( 'DH_REVIEWS_URL', plugin_dir_url( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Autoload class files
|--------------------------------------------------------------------------
*/
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-encryption.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-cpt.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-api.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-sync.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-schema.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-render.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-block.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-import.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-export.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-photo-proxy.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-activator.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-deactivator.php';
require_once DH_REVIEWS_PATH . 'includes/class-dh-reviews-updater.php';
require_once DH_REVIEWS_PATH . 'admin/class-dh-reviews-admin.php';

/*
|--------------------------------------------------------------------------
| Auto-updater (must run before init so update checks are available early)
|--------------------------------------------------------------------------
*/
add_action( 'plugins_loaded', function () {
	new Updater();
} );

/*
|--------------------------------------------------------------------------
| Activation and deactivation hooks
|--------------------------------------------------------------------------
*/
register_activation_hook( __FILE__, array( new Activator(), 'activate' ) );
register_deactivation_hook( __FILE__, array( new Deactivator(), 'deactivate' ) );

/*
|--------------------------------------------------------------------------
| Initialise plugin components
|--------------------------------------------------------------------------
*/

/**
 * Boot classes that must run on every request (CPT, shortcode, schema, REST).
 *
 * @return void
 */
function dh_reviews_init(): void {
	new CPT();
	new Render();
	new Schema();
	new Block();
	new Photo_Proxy();
	new API();
	new Sync();
}
add_action( 'init', __NAMESPACE__ . '\\dh_reviews_init' );

/**
 * Boot admin-only classes on init so that add_action('admin_menu', ...)
 * calls inside their constructors are registered before admin_menu fires.
 * (admin_init runs after admin_menu, so instantiating there is too late.)
 *
 * @return void
 */
function dh_reviews_admin_init(): void {
	if ( ! is_admin() ) {
		return;
	}
	new Admin();
	new Import();
	new Export();
}
add_action( 'init', __NAMESPACE__ . '\\dh_reviews_admin_init' );
