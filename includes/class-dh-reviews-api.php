<?php
/**
 * Google Business Profile API client.
 *
 * Handles OAuth 2.0 authentication, token management (refresh, encryption),
 * and all GBP API requests (accounts, locations, reviews).
 * Uses wp_remote_get/wp_remote_post exclusively; no Google SDK.
 * See SPEC.md Sections 3.1 and 3.2 for endpoints and auth flow.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API
 *
 * GBP API client for authentication and review data retrieval.
 */
class API {

	/**
	 * Constructor.
	 *
	 * Registers hooks for the OAuth callback handler.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
	}

	/**
	 * Get the OAuth authorization URL to redirect the admin to Google consent.
	 *
	 * @return string Authorization URL.
	 */
	public function get_auth_url(): string {
		// Stub: build and return the Google OAuth consent URL.
		return '';
	}

	/**
	 * Handle the OAuth callback and exchange the authorization code for tokens.
	 *
	 * @return void
	 */
	public function handle_oauth_callback(): void {
		// Stub: verify state, exchange code, store encrypted tokens.
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh_access_token(): bool {
		// Stub: POST to Google token endpoint, update stored tokens.
		return false;
	}

	/**
	 * Get a valid access token, refreshing if expired.
	 *
	 * @return string|false Access token string or false on failure.
	 */
	public function get_access_token(): string|false {
		// Stub: check expiry, refresh if needed, return token.
		return false;
	}

	/**
	 * Fetch the list of GBP accounts for the authenticated user.
	 *
	 * @return array|false Array of accounts or false on failure.
	 */
	public function list_accounts(): array|false {
		// Stub: GET accounts endpoint per Section 3.2.
		return false;
	}

	/**
	 * Fetch locations for a given GBP account.
	 *
	 * @param string $account_name Account resource name.
	 * @return array|false Array of locations or false on failure.
	 */
	public function list_locations( string $account_name ): array|false {
		// Stub: GET locations endpoint per Section 3.2.
		return false;
	}

	/**
	 * Fetch reviews for a given GBP location with pagination.
	 *
	 * @param string      $location_name Location resource name.
	 * @param string|null $page_token    Pagination token for next page.
	 * @return array|false Array with reviews and nextPageToken, or false on failure.
	 */
	public function list_reviews( string $location_name, ?string $page_token = null ): array|false {
		// Stub: GET reviews endpoint per Section 3.2, handle pagination.
		return false;
	}

	/**
	 * Disconnect the API by removing stored tokens.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		// Stub: delete all token options.
	}

	/**
	 * Check whether the plugin currently has a valid API connection.
	 *
	 * @return bool True if connected with valid tokens.
	 */
	public function is_connected(): bool {
		// Stub: check for presence of refresh token.
		return false;
	}
}
