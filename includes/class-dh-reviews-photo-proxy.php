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
	 * Cache subdirectory within wp-uploads.
	 *
	 * @var string
	 */
	const CACHE_DIR = 'dh-reviews-photo-cache';

	/**
	 * Cache lifetime in seconds (7 days).
	 *
	 * @var int
	 */
	const CACHE_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Allowed Google photo CDN hostnames.
	 *
	 * @var string[]
	 */
	const ALLOWED_HOSTS = array(
		'lh1.googleusercontent.com',
		'lh2.googleusercontent.com',
		'lh3.googleusercontent.com',
		'lh4.googleusercontent.com',
		'lh5.googleusercontent.com',
		'lh6.googleusercontent.com',
	);

	/**
	 * Map of MIME types to file extensions.
	 *
	 * @var string[]
	 */
	const MIME_EXT_MAP = array(
		'image/jpeg' => 'jpg',
		'image/jpg'  => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
	);

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
	 * Route: GET /wp-json/dh-reviews/v1/photo/?url={encoded_url}
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'dh-reviews/v1',
			'/photo/',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'serve_photo' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => array( $this, 'validate_photo_url' ),
					),
				),
			)
		);
	}

	/**
	 * Validate that the URL is an allowed Google photo CDN hostname.
	 *
	 * @param string $url Candidate URL.
	 * @return bool|string True if valid, error message string if not.
	 */
	public function validate_photo_url( string $url ): bool|string {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return 'Invalid URL.';
		}

		$host = (string) parse_url( $url, PHP_URL_HOST );

		foreach ( self::ALLOWED_HOSTS as $allowed ) {
			if ( $host === $allowed ) {
				return true;
			}
		}

		return 'Photo host not permitted.';
	}

	/**
	 * Handle a photo proxy request.
	 *
	 * Checks the local cache, fetches from Google if the cache is cold or
	 * expired, then outputs the binary image and exits. The REST response
	 * infrastructure is bypassed deliberately so WordPress does not encode
	 * binary data as JSON.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_Error Never returns normally — exits after output or returns error.
	 */
	public function serve_photo( \WP_REST_Request $request ): \WP_Error {
		$photo_url  = (string) $request->get_param( 'url' );
		$photo_hash = md5( $photo_url );
		$cached     = $this->get_cached_photo( $photo_hash );

		if ( false === $cached ) {
			$cached = $this->cache_photo( $photo_url, $photo_hash );
		}

		if ( false === $cached ) {
			return new \WP_Error(
				'photo_proxy_failed',
				__( 'Could not fetch or cache photo.', 'dh-google-reviews' ),
				array( 'status' => 502 )
			);
		}

		$mime = wp_check_filetype( $cached );
		$type = $mime['type'] ?: 'image/jpeg';

		// Output image directly — bypass WP REST JSON encoding.
		header( 'Content-Type: ' . sanitize_mime_type( $type ) );
		header( 'Cache-Control: public, max-age=' . (int) self::CACHE_TTL );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $cached );
		exit;
	}

	/**
	 * Get the path to a valid (non-expired) cached photo file.
	 *
	 * @param string $photo_hash MD5 hash of the original photo URL.
	 * @return string|false Absolute file path if cache hit, false otherwise.
	 */
	public function get_cached_photo( string $photo_hash ): string|false {
		$cache_dir = $this->get_cache_dir();
		if ( ! $cache_dir ) {
			return false;
		}

		foreach ( array_keys( self::MIME_EXT_MAP ) as $mime ) {
			$ext  = self::MIME_EXT_MAP[ $mime ];
			$file = $cache_dir . $photo_hash . '.' . $ext;

			if ( file_exists( $file ) && ( time() - (int) filemtime( $file ) ) < self::CACHE_TTL ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Download and cache a photo locally.
	 *
	 * Fetches the remote photo via wp_remote_get and writes it to the
	 * uploads-based cache directory. Returns the absolute file path on
	 * success so the caller can serve the file immediately.
	 *
	 * @param string $photo_url  Remote photo URL.
	 * @param string $photo_hash MD5 hash of the URL used as cache filename.
	 * @return string|false Absolute cached file path on success, false on failure.
	 */
	public function cache_photo( string $photo_url, string $photo_hash ): string|false {
		$cache_dir = $this->get_cache_dir();
		if ( ! $cache_dir ) {
			return false;
		}

		$response = wp_remote_get(
			$photo_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return false;
		}

		$body         = wp_remote_retrieve_body( $response );
		$content_type = strtolower( strtok( wp_remote_retrieve_header( $response, 'content-type' ), ';' ) );

		$ext  = self::MIME_EXT_MAP[ $content_type ] ?? 'jpg';
		$file = $cache_dir . $photo_hash . '.' . $ext;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file, $body ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Return the absolute path to the local photo cache directory, creating
	 * it (and a protective .htaccess) if it does not yet exist.
	 *
	 * @return string|false Absolute directory path with trailing slash, or false on failure.
	 */
	private function get_cache_dir(): string|false {
		$upload    = wp_upload_dir();
		$cache_dir = trailingslashit( $upload['basedir'] ) . self::CACHE_DIR . '/';

		if ( ! is_dir( $cache_dir ) ) {
			if ( ! wp_mkdir_p( $cache_dir ) ) {
				return false;
			}

			// Block directory listing and deny non-image direct requests.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$cache_dir . '.htaccess',
				"Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|htm|shtml|sh|cgi)$\">\n  Deny from all\n</FilesMatch>\n"
			);
		}

		return is_dir( $cache_dir ) ? $cache_dir : false;
	}
}
