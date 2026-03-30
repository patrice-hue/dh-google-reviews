<?php
/**
 * Photo proxy REST endpoint.
 *
 * Provides a local REST API endpoint to serve reviewer profile photos
 * through WordPress instead of directly from Google CDN. Photos are
 * cached locally for 7 days to reduce external requests.
 * See SPEC.md Section 8 (Security, photo proxy) for requirements.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Photo_Proxy
 *
 * Registers a REST endpoint for proxied reviewer photos.
 */
class Photo_Proxy {

	/**
	 * Constructor.
	 *
	 * Registers the REST API route.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the photo proxy REST route.
	 *
	 * Route: /wp-json/dh-reviews/v1/photo/
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Stub: register_rest_route for photo proxy endpoint.
	}

	/**
	 * Handle a photo proxy request.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Photo data or error.
	 */
	public function serve_photo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Stub: check cache, fetch from Google if needed, return image data.
		return new \WP_Error( 'not_implemented', 'Not implemented yet.' );
	}

	/**
	 * Get cached photo from local storage.
	 *
	 * @param string $photo_hash Hash of the photo URL for cache key.
	 * @return string|false Cached file path or false if not cached.
	 */
	public function get_cached_photo( string $photo_hash ): string|false {
		// Stub: check upload directory for cached photo file.
		return false;
	}

	/**
	 * Cache a photo locally.
	 *
	 * @param string $photo_url  Remote photo URL.
	 * @param string $photo_hash Hash of the URL for the cache key.
	 * @return string|false Local file path on success, false on failure.
	 */
	public function cache_photo( string $photo_url, string $photo_hash ): string|false {
		// Stub: download photo via wp_remote_get, save to uploads directory.
		return false;
	}
}
