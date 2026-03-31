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
	 * Google OAuth 2.0 authorization endpoint.
	 *
	 * @var string
	 */
	const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * Google OAuth 2.0 token endpoint.
	 *
	 * @var string
	 */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Google token revocation endpoint.
	 *
	 * @var string
	 */
	const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

	/**
	 * Google userinfo endpoint (to fetch connected account email).
	 *
	 * @var string
	 */
	const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

	/**
	 * Required OAuth scope for reading GBP reviews.
	 *
	 * @var string
	 */
	const SCOPE = 'https://www.googleapis.com/auth/business.manage';

	/**
	 * Option name used for the plugin settings array.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'dh_reviews_settings';

	/**
	 * Transient key for storing admin API error notices.
	 *
	 * @var string
	 */
	const ERROR_TRANSIENT = 'dh_reviews_api_error';

	/**
	 * Constructor.
	 *
	 * Registers hooks for the OAuth callback handler and admin notices.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
		add_action( 'admin_notices', array( $this, 'output_admin_notices' ) );
	}

	// -------------------------------------------------------------------------
	// OAuth flow
	// -------------------------------------------------------------------------

	/**
	 * Build the Google OAuth consent URL and redirect the admin browser to it.
	 *
	 * Stores a one-time state token to guard against CSRF.
	 *
	 * @return string Authorization URL.
	 */
	public function get_auth_url(): string {
		$state = wp_generate_password( 24, false );
		set_transient( 'dh_reviews_oauth_state', $state, 10 * MINUTE_IN_SECONDS );

		$params = array(
			'client_id'     => $this->get_client_id(),
			'redirect_uri'  => $this->get_redirect_uri(),
			'response_type' => 'code',
			'scope'         => self::SCOPE,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle admin_init events for the OAuth flow.
	 *
	 * Three sub-flows handled here:
	 *   1. Admin clicks Connect  → ?dh_oauth_initiate=1&_wpnonce=...
	 *   2. Google redirects back → ?page=dh-reviews-oauth-callback&code=...
	 *   3. Admin clicks Disconnect → ?dh_oauth_disconnect=1&_wpnonce=...
	 *
	 * @return void
	 */
	public function handle_oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// --- Initiate OAuth ---
		if ( isset( $_GET['dh_oauth_initiate'] ) && '1' === $_GET['dh_oauth_initiate'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dh_oauth_initiate' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'dh-google-reviews' ) );
			}
			if ( empty( $this->get_client_id() ) || empty( $this->get_client_secret() ) ) {
				$this->set_error( __( 'Please save your Google Cloud Client ID and Client Secret before connecting.', 'dh-google-reviews' ) );
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
				exit;
			}
			wp_redirect( $this->get_auth_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		// --- Disconnect ---
		if ( isset( $_GET['dh_oauth_disconnect'] ) && '1' === $_GET['dh_oauth_disconnect'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dh_oauth_disconnect' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'dh-google-reviews' ) );
			}
			$this->revoke_and_disconnect();
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_disconnected=1' ) );
			exit;
		}

		// --- OAuth code exchange ---
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'dh-reviews-oauth-callback' !== $page ) {
			return;
		}

		// Error returned by Google.
		if ( isset( $_GET['error'] ) ) {
			$this->set_error( sprintf(
				/* translators: %s: error code returned by Google */
				__( 'Google OAuth error: %s', 'dh-google-reviews' ),
				sanitize_text_field( wp_unslash( $_GET['error'] ) )
			) );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
			exit;
		}

		if ( ! isset( $_GET['code'], $_GET['state'] ) ) {
			return;
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

		// Verify state to prevent CSRF.
		$stored_state = get_transient( 'dh_reviews_oauth_state' );
		delete_transient( 'dh_reviews_oauth_state' );

		if ( ! hash_equals( (string) $stored_state, $state ) ) {
			$this->set_error( __( 'OAuth state mismatch. Please try connecting again.', 'dh-google-reviews' ) );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
			exit;
		}

		// Exchange code for tokens.
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $this->get_client_id(),
					'client_secret' => $this->get_client_secret(),
					'redirect_uri'  => $this->get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_error( $response->get_error_message() );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
			$msg = $body['error_description'] ?? $body['error'] ?? __( 'Unknown error during token exchange.', 'dh-google-reviews' );
			$this->set_error( $msg );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
			exit;
		}

		// Fetch connected account email.
		$email = $this->fetch_connected_email( $body['access_token'] );

		// Persist tokens into settings.
		$settings = get_option( self::OPTION_NAME, array() );
		$settings['oauth_access_token']    = Encryption::encrypt( $body['access_token'] );
		$settings['oauth_refresh_token']   = Encryption::encrypt( $body['refresh_token'] );
		$settings['oauth_token_expires']   = time() + ( (int) ( $body['expires_in'] ?? 3600 ) );
		$settings['oauth_connected_email'] = $email;
		update_option( self::OPTION_NAME, $settings );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_connected=1' ) );
		exit;
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh_access_token(): bool {
		$settings = get_option( self::OPTION_NAME, array() );
		$refresh  = Encryption::decrypt( $settings['oauth_refresh_token'] ?? '' );

		if ( '' === $refresh ) {
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'refresh_token' => $refresh,
					'client_id'     => $this->get_client_id(),
					'client_secret' => $this->get_client_secret(),
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_error( $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$msg = $body['error_description'] ?? $body['error'] ?? __( 'Token refresh failed.', 'dh-google-reviews' );
			$this->set_error( $msg );
			return false;
		}

		$settings['oauth_access_token']  = Encryption::encrypt( $body['access_token'] );
		$settings['oauth_token_expires'] = time() + ( (int) ( $body['expires_in'] ?? 3600 ) );
		update_option( self::OPTION_NAME, $settings );

		return true;
	}

	/**
	 * Get a valid access token, refreshing if it is expired or nearly expired.
	 *
	 * @return string Access token, or empty string on failure.
	 */
	public function get_access_token(): string {
		$settings = get_option( self::OPTION_NAME, array() );
		$expiry   = (int) ( $settings['oauth_token_expires'] ?? 0 );

		// Refresh when within 5 minutes of expiry.
		if ( time() > $expiry - 300 ) {
			if ( ! $this->refresh_access_token() ) {
				return '';
			}
			$settings = get_option( self::OPTION_NAME, array() );
		}

		return Encryption::decrypt( $settings['oauth_access_token'] ?? '' );
	}

	// -------------------------------------------------------------------------
	// API methods
	// -------------------------------------------------------------------------

	/**
	 * Fetch the list of GBP accounts for the authenticated user.
	 *
	 * @return array|false Array of account objects or false on failure.
	 */
	public function list_accounts() {
		$token = $this->get_access_token();
		if ( '' === $token ) {
			return false;
		}

		$response = wp_remote_get(
			'https://mybusinessaccountmanagement.googleapis.com/v1/accounts',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		return $this->parse_response( $response, 'accounts' );
	}

	/**
	 * Fetch locations for a given GBP account.
	 *
	 * @param string $account_name Account resource name (e.g. accounts/123456789).
	 * @return array|false Array of location objects or false on failure.
	 */
	public function list_locations( string $account_name ) {
		$token = $this->get_access_token();
		if ( '' === $token ) {
			return false;
		}

		$url = 'https://mybusinessbusinessinformation.googleapis.com/v1/'
			. ltrim( $account_name, '/' ) . '/locations';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		return $this->parse_response( $response, 'locations' );
	}

	/**
	 * Fetch reviews for a given GBP location.
	 *
	 * Returns an array with keys 'reviews' (array) and 'nextPageToken' (string|null).
	 *
	 * @param string      $location_name Location resource name (e.g. accounts/x/locations/y).
	 * @param string|null $page_token    Pagination token for the next page.
	 * @return array|false Array with reviews and nextPageToken, or false on failure.
	 */
	public function list_reviews( string $location_name, $page_token = null ) {
		$token = $this->get_access_token();
		if ( '' === $token ) {
			return false;
		}

		$url = 'https://mybusiness.googleapis.com/v4/'
			. ltrim( $location_name, '/' ) . '/reviews'
			. '?pageSize=50'
			. ( $page_token ? '&pageToken=' . rawurlencode( $page_token ) : '' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_error( $response->get_error_message() );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || null === $body ) {
			$msg = $body['error']['message'] ?? sprintf( 'HTTP %d', $code );
			$this->set_error( $msg );
			return false;
		}

		return array(
			'reviews'       => $body['reviews'] ?? array(),
			'nextPageToken' => $body['nextPageToken'] ?? null,
		);
	}

	// -------------------------------------------------------------------------
	// Connection management
	// -------------------------------------------------------------------------

	/**
	 * Revoke the access token with Google and clear all stored tokens.
	 *
	 * @return void
	 */
	public function revoke_and_disconnect(): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$refresh  = Encryption::decrypt( $settings['oauth_refresh_token'] ?? '' );

		// Best-effort revocation; ignore errors.
		if ( $refresh ) {
			wp_remote_post(
				self::REVOKE_URL,
				array(
					'timeout' => 10,
					'body'    => array( 'token' => $refresh ),
				)
			);
		}

		$this->disconnect();
	}

	/**
	 * Remove all stored token data from the settings option.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		$settings = get_option( self::OPTION_NAME, array() );

		$settings['oauth_access_token']    = '';
		$settings['oauth_refresh_token']   = '';
		$settings['oauth_token_expires']   = 0;
		$settings['oauth_connected_email'] = '';
		$settings['google_account_id']     = '';
		$settings['google_location_id']    = '';

		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check whether the plugin currently has a valid API connection.
	 *
	 * @return bool True if a refresh token is stored.
	 */
	public function is_connected(): bool {
		$settings = get_option( self::OPTION_NAME, array() );
		return ! empty( $settings['oauth_refresh_token'] );
	}

	// -------------------------------------------------------------------------
	// Admin notices
	// -------------------------------------------------------------------------

	/**
	 * Output any queued API error or status notices in the admin.
	 *
	 * @return void
	 */
	public function output_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Show notices only on plugin pages and CPT screens.
		$on_plugin_page = ( false !== strpos( $screen->id, 'dh-reviews' ) )
			|| ( CPT::POST_TYPE === ( $screen->post_type ?? '' ) );

		if ( ! $on_plugin_page ) {
			return;
		}

		// Connection success.
		if ( isset( $_GET['dh_connected'] ) && '1' === $_GET['dh_connected'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Google account connected successfully.', 'dh-google-reviews' )
				. '</p></div>';
		}

		// Disconnection confirmation.
		if ( isset( $_GET['dh_disconnected'] ) && '1' === $_GET['dh_disconnected'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-info is-dismissible"><p>'
				. esc_html__( 'Google account disconnected.', 'dh-google-reviews' )
				. '</p></div>';
		}

		// Queued error.
		$error = get_transient( self::ERROR_TRANSIENT . '_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( self::ERROR_TRANSIENT . '_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>'
				. '<strong>' . esc_html__( 'DH Reviews API Error:', 'dh-google-reviews' ) . '</strong> '
				. esc_html( $error )
				. '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the Google Cloud Client ID from constant or settings.
	 *
	 * @return string Client ID.
	 */
	public function get_client_id(): string {
		if ( defined( 'DH_REVIEWS_CLIENT_ID' ) ) {
			return (string) DH_REVIEWS_CLIENT_ID;
		}
		$settings = get_option( self::OPTION_NAME, array() );
		return $settings['google_client_id'] ?? '';
	}

	/**
	 * Get the Google Cloud Client Secret from constant or settings.
	 *
	 * @return string Client secret.
	 */
	public function get_client_secret(): string {
		if ( defined( 'DH_REVIEWS_CLIENT_SECRET' ) ) {
			return (string) DH_REVIEWS_CLIENT_SECRET;
		}
		$settings = get_option( self::OPTION_NAME, array() );
		return $settings['google_client_secret'] ?? '';
	}

	/**
	 * Build the OAuth callback redirect URI.
	 *
	 * @return string Redirect URI registered in Google Cloud Console.
	 */
	public function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=dh-reviews-oauth-callback' );
	}

	/**
	 * Fetch the authenticated user's email address from Google userinfo.
	 *
	 * @param string $access_token A fresh access token.
	 * @return string Email address, or empty string on failure.
	 */
	private function fetch_connected_email( string $access_token ): string {
		$response = wp_remote_get(
			self::USERINFO_URL,
			array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['email'] ?? '';
	}

	/**
	 * Parse a wp_remote_get response and extract a named key from the JSON body.
	 *
	 * @param mixed  $response      Response from wp_remote_get.
	 * @param string $data_key      JSON key to extract (e.g. 'accounts', 'locations').
	 * @return array|false Extracted array or false on any error.
	 */
	private function parse_response( $response, string $data_key ) {
		if ( is_wp_error( $response ) ) {
			$this->set_error( $response->get_error_message() );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || null === $body ) {
			$msg = $body['error']['message'] ?? sprintf( 'HTTP %d from API.', $code );
			$this->set_error( $msg );
			return false;
		}

		return $body[ $data_key ] ?? array();
	}

	/**
	 * Store an API error message in a per-user transient for display on the next page load.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function set_error( string $message ): void {
		set_transient(
			self::ERROR_TRANSIENT . '_' . get_current_user_id(),
			$message,
			5 * MINUTE_IN_SECONDS
		);
	}
}
