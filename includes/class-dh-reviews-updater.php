<?php
/**
 * GitHub releases auto-updater.
 *
 * Hooks into WordPress's built-in update machinery to check the
 * public GitHub repository for new releases and surface them as
 * standard plugin updates.  No authentication token is required
 * because the repository is public.
 *
 * How it works:
 *  1. pre_set_site_transient_update_plugins  – inject update data
 *     when a newer release tag exists on GitHub.
 *  2. plugins_api                            – return plugin info
 *     for the "View details" thickbox modal.
 *  3. upgrader_post_install                  – rename the folder
 *     WordPress extracted from the GitHub zip back to the expected
 *     dh-google-reviews slug so the plugin stays active.
 *  4. plugin_row_meta                        – add "View details"
 *     link to the plugin row in the admin plugins list.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Updater
 *
 * Provides automatic updates from GitHub releases.
 */
class Updater {

	/**
	 * GitHub repository identifier (owner/repo).
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'patrice-hue/dh-google-reviews';

	/**
	 * Plugin folder slug (directory name inside wp-content/plugins/).
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'dh-google-reviews';

	/**
	 * Plugin file path relative to the plugins directory.
	 *
	 * @var string
	 */
	const PLUGIN_FILE = 'dh-google-reviews/dh-google-reviews.php';

	/**
	 * Transient key used to cache the GitHub API response.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'dh_reviews_github_release';

	/**
	 * GitHub REST API endpoint for the latest release.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.github.com/repos/patrice-hue/dh-google-reviews/releases/latest';

	/**
	 * Constructor.
	 *
	 * Registers all update-related hooks.
	 */
	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Update check
	// -------------------------------------------------------------------------

	/**
	 * Inject update data into the WordPress update transient when a newer
	 * release is available on GitHub.
	 *
	 * Hooked on pre_set_site_transient_update_plugins.
	 *
	 * @param object $transient The update_plugins site transient object.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );

		if ( version_compare( $remote_version, DH_REVIEWS_VERSION, '>' ) ) {
			$update = new \stdClass();

			$update->id              = self::PLUGIN_FILE;
			$update->slug            = self::PLUGIN_SLUG;
			$update->plugin          = self::PLUGIN_FILE;
			$update->new_version     = $remote_version;
			$update->url             = 'https://github.com/' . self::GITHUB_REPO;
			$update->package         = $release['zipball_url'];
			$update->icons           = array();
			$update->banners         = array();
			$update->banners_rtl     = array();
			$update->requires        = '6.4';
			$update->requires_php    = '7.4';
			$update->tested          = '';
			$update->compatibility   = new \stdClass();

			$transient->response[ self::PLUGIN_FILE ] = $update;

			// Remove from no_update list if it was placed there previously.
			unset( $transient->no_update[ self::PLUGIN_FILE ] );
		} else {
			// Tell WordPress this plugin is up to date so it doesn't query .org.
			if ( ! isset( $transient->no_update[ self::PLUGIN_FILE ] ) ) {
				$no_update = new \stdClass();

				$no_update->id           = self::PLUGIN_FILE;
				$no_update->slug         = self::PLUGIN_SLUG;
				$no_update->plugin       = self::PLUGIN_FILE;
				$no_update->new_version  = DH_REVIEWS_VERSION;
				$no_update->url          = 'https://github.com/' . self::GITHUB_REPO;
				$no_update->package      = '';
				$no_update->icons        = array();
				$no_update->banners      = array();
				$no_update->banners_rtl  = array();
				$no_update->requires     = '6.4';
				$no_update->requires_php = '7.4';

				$transient->no_update[ self::PLUGIN_FILE ] = $no_update;
			}
		}

		return $transient;
	}

	// -------------------------------------------------------------------------
	// Plugin information modal
	// -------------------------------------------------------------------------

	/**
	 * Return plugin information for the WordPress "View details" thickbox.
	 *
	 * Hooked on plugins_api with priority 10.
	 *
	 * @param false|object $result The existing result; false means not yet handled.
	 * @param string       $action The API action being requested.
	 * @param object       $args   Request arguments (slug, fields, etc.).
	 * @return false|object Plugin info stdClass or the original $result.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );

		// Convert the GitHub release body (markdown) to something readable.
		$changelog = isset( $release['body'] ) ? esc_html( $release['body'] ) : '';

		$info = new \stdClass();

		$info->name          = 'DH Google Reviews';
		$info->slug          = self::PLUGIN_SLUG;
		$info->version       = $remote_version;
		$info->author        = '<a href="https://digitalhitmen.com.au">Digital Hitmen</a>';
		$info->author_profile = 'https://digitalhitmen.com.au';
		$info->homepage      = 'https://github.com/' . self::GITHUB_REPO;
		$info->requires      = '6.4';
		$info->requires_php  = '7.4';
		$info->tested        = '';
		$info->download_link = $release['zipball_url'];
		$info->last_updated  = isset( $release['published_at'] ) ? $release['published_at'] : '';

		$info->sections = array(
			'description' => '<p>Display Google Business Profile reviews on your WordPress site with shortcodes, Gutenberg blocks, and automatic JSON-LD schema markup. Supports manual review entry and CSV import for sites without API access.</p>',
			'changelog'   => $changelog ? '<pre>' . $changelog . '</pre>' : '<p>See <a href="https://github.com/' . esc_attr( self::GITHUB_REPO ) . '/releases">GitHub releases</a> for the full changelog.</p>',
		);

		$info->banners = array();

		return $info;
	}

	// -------------------------------------------------------------------------
	// Post-install folder rename
	// -------------------------------------------------------------------------

	/**
	 * Rename the extracted GitHub zip folder to the expected plugin slug.
	 *
	 * GitHub zip archives extract to a folder named after the repo and the
	 * commit hash (e.g. patrice-hue-dh-google-reviews-abc1234).  WordPress
	 * then cannot find the plugin and deactivates it.  This callback renames
	 * the folder to dh-google-reviews so the plugin remains active after update.
	 *
	 * Hooked on upgrader_post_install with priority 10.
	 *
	 * @param bool  $response   Whether the install was successful.
	 * @param array $hook_extra Extra data about what was just installed.
	 * @param array $result     Result data (includes 'destination' key).
	 * @return bool The original $response value.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Only act on updates/installs for this specific plugin.
		if ( ! isset( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $response;
		}

		if ( empty( $result['destination'] ) ) {
			return $response;
		}

		$proper_destination = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . self::PLUGIN_SLUG;

		// Only rename when the extracted folder has a different name.
		if ( rtrim( $result['destination'], DIRECTORY_SEPARATOR ) === rtrim( $proper_destination, DIRECTORY_SEPARATOR ) ) {
			return $response;
		}

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			$moved = $wp_filesystem->move( $result['destination'], $proper_destination, true );

			if ( $moved ) {
				// Re-activate the plugin from its correct location.
				activate_plugin( self::PLUGIN_FILE );
			}
		}

		return $response;
	}

	// -------------------------------------------------------------------------
	// Plugin row meta
	// -------------------------------------------------------------------------

	/**
	 * Add a "View details" link to the plugin row in wp-admin/plugins.php.
	 *
	 * The link opens the standard WordPress plugin information thickbox and
	 * is populated by the plugin_info() callback above.
	 *
	 * Hooked on plugin_row_meta with priority 10.
	 *
	 * @param string[] $links Existing row meta links.
	 * @param string   $file  Plugin file (relative to plugins dir).
	 * @return string[] Modified links array.
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( self::PLUGIN_FILE !== $file ) {
			return $links;
		}

		$url = add_query_arg(
			array(
				'tab'       => 'plugin-information',
				'plugin'    => self::PLUGIN_SLUG,
				'TB_iframe' => 'true',
				'width'     => 772,
				'height'    => 889,
			),
			admin_url( 'plugin-install.php' )
		);

		$links[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
			esc_url( $url ),
			/* translators: %s: plugin name */
			esc_attr( sprintf( __( 'More information about %s', 'dh-google-reviews' ), 'DH Google Reviews' ) ),
			esc_html__( 'View details', 'dh-google-reviews' )
		);

		return $links;
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch the latest release data from the GitHub API.
	 *
	 * Caches the response in a transient for 12 hours to stay well within
	 * GitHub's 60 unauthenticated requests per hour rate limit.
	 *
	 * @return array|false Decoded release array or false on failure.
	 */
	private function get_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'headers'    => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) || empty( $data['zipball_url'] ) ) {
			return false;
		}

		set_transient( self::CACHE_KEY, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Strip a leading 'v' from a git tag so version_compare works correctly.
	 *
	 * @param string $tag Raw tag string (e.g. "v1.2.0" or "1.2.0").
	 * @return string Normalised version string (e.g. "1.2.0").
	 */
	private function parse_version( $tag ) {
		return ltrim( (string) $tag, 'vV' );
	}
}
