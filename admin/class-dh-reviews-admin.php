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
	 * Option name for the plugin settings array.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'dh_reviews_settings';

	/**
	 * Page hook suffixes for plugin admin pages.
	 *
	 * @var string[]
	 */
	private array $page_hooks = array();

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

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Register the admin menu and submenus.
	 *
	 * Menu structure:
	 *   Reviews (top level)
	 *   +-- All Reviews (CPT list)
	 *   +-- Add Manual Review (CPT new)
	 *   +-- Import / Export
	 *   +-- Settings
	 *   +-- Sync Log
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Top level: redirects to CPT list table.
		add_menu_page(
			__( 'DH Google Reviews', 'dh-google-reviews' ),
			__( 'Reviews', 'dh-google-reviews' ),
			'manage_options',
			'edit.php?post_type=' . CPT::POST_TYPE,
			'',
			'dashicons-star-filled',
			25
		);

		// All Reviews — links to CPT list table.
		add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'All Reviews', 'dh-google-reviews' ),
			__( 'All Reviews', 'dh-google-reviews' ),
			'manage_options',
			'edit.php?post_type=' . CPT::POST_TYPE
		);

		// Add Manual Review — new CPT post form.
		add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'Add Manual Review', 'dh-google-reviews' ),
			__( 'Add Manual Review', 'dh-google-reviews' ),
			'manage_options',
			'post-new.php?post_type=' . CPT::POST_TYPE
		);

		// Import / Export.
		$this->page_hooks['import'] = add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'Import / Export', 'dh-google-reviews' ),
			__( 'Import / Export', 'dh-google-reviews' ),
			'manage_options',
			'dh-reviews-import',
			array( $this, 'render_import_page' )
		);

		// Settings.
		$this->page_hooks['settings'] = add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'DH Reviews Settings', 'dh-google-reviews' ),
			__( 'Settings', 'dh-google-reviews' ),
			'manage_options',
			'dh-reviews-settings',
			array( $this, 'render_settings_page' )
		);

		// Sync Log.
		$this->page_hooks['sync_log'] = add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'Sync Log', 'dh-google-reviews' ),
			__( 'Sync Log', 'dh-google-reviews' ),
			'manage_options',
			'dh-reviews-sync-log',
			array( $this, 'render_sync_log_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	/**
	 * Register plugin settings using the WordPress Settings API.
	 *
	 * Sections: API Connection, Sync Configuration, Business Details,
	 * Display Defaults.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'dh_reviews_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// ---- API Connection ----
		add_settings_section(
			'dh_reviews_api',
			__( 'API Connection', 'dh-google-reviews' ),
			array( $this, 'render_api_section_intro' ),
			'dh-reviews-settings'
		);

		if ( ! defined( 'DH_REVIEWS_CLIENT_ID' ) ) {
			add_settings_field(
				'google_client_id',
				__( 'Google Cloud Client ID', 'dh-google-reviews' ),
				array( $this, 'render_field_text' ),
				'dh-reviews-settings',
				'dh_reviews_api',
				array( 'field' => 'google_client_id', 'class' => 'regular-text' )
			);
		}

		if ( ! defined( 'DH_REVIEWS_CLIENT_SECRET' ) ) {
			add_settings_field(
				'google_client_secret',
				__( 'Google Cloud Client Secret', 'dh-google-reviews' ),
				array( $this, 'render_field_password' ),
				'dh-reviews-settings',
				'dh_reviews_api',
				array( 'field' => 'google_client_secret' )
			);
		}

		add_settings_field(
			'oauth_connect',
			__( 'Google Account', 'dh-google-reviews' ),
			array( $this, 'render_field_oauth_connect' ),
			'dh-reviews-settings',
			'dh_reviews_api'
		);

		add_settings_field(
			'google_account_id',
			__( 'Account', 'dh-google-reviews' ),
			array( $this, 'render_field_account_selector' ),
			'dh-reviews-settings',
			'dh_reviews_api',
			array( 'field' => 'google_account_id' )
		);

		add_settings_field(
			'google_location_id',
			__( 'Location', 'dh-google-reviews' ),
			array( $this, 'render_field_location_selector' ),
			'dh-reviews-settings',
			'dh_reviews_api',
			array( 'field' => 'google_location_id' )
		);

		// ---- Sync Configuration ----
		add_settings_section(
			'dh_reviews_sync',
			__( 'Sync Configuration', 'dh-google-reviews' ),
			'__return_false',
			'dh-reviews-settings'
		);

		add_settings_field(
			'sync_frequency',
			__( 'Sync Frequency', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_sync',
			array(
				'field'   => 'sync_frequency',
				'options' => array(
					'6h'     => __( 'Every 6 hours', 'dh-google-reviews' ),
					'12h'    => __( 'Every 12 hours', 'dh-google-reviews' ),
					'24h'    => __( 'Every 24 hours', 'dh-google-reviews' ),
					'manual' => __( 'Manual only', 'dh-google-reviews' ),
				),
				'default' => '24h',
			)
		);

		add_settings_field(
			'min_rating_publish',
			__( 'Minimum Star Rating to Auto Publish', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_sync',
			array(
				'field'   => 'min_rating_publish',
				'options' => array(
					'1' => '1 ' . __( 'star', 'dh-google-reviews' ),
					'2' => '2 ' . __( 'stars', 'dh-google-reviews' ),
					'3' => '3 ' . __( 'stars', 'dh-google-reviews' ),
					'4' => '4 ' . __( 'stars', 'dh-google-reviews' ),
					'5' => '5 ' . __( 'stars', 'dh-google-reviews' ),
				),
				'default' => '1',
			)
		);

		add_settings_field(
			'below_threshold_action',
			__( 'Reviews Below Threshold', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_sync',
			array(
				'field'   => 'below_threshold_action',
				'options' => array(
					'draft'  => __( 'Save as draft', 'dh-google-reviews' ),
					'skip'   => __( 'Do not import', 'dh-google-reviews' ),
				),
				'default' => 'draft',
			)
		);

		add_settings_field(
			'sync_now',
			__( 'Manual Sync', 'dh-google-reviews' ),
			array( $this, 'render_field_sync_now' ),
			'dh-reviews-settings',
			'dh_reviews_sync'
		);

		// ---- Business Details ----
		add_settings_section(
			'dh_reviews_business',
			__( 'Business Details for Schema', 'dh-google-reviews' ),
			array( $this, 'render_business_section_intro' ),
			'dh-reviews-settings'
		);

		add_settings_field(
			'business_name',
			__( 'Business Name', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'business_name', 'class' => 'regular-text' )
		);

		add_settings_field(
			'street_address',
			__( 'Street Address', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'street_address', 'class' => 'regular-text' )
		);

		add_settings_field(
			'city',
			__( 'City', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'city', 'class' => 'regular-text' )
		);

		add_settings_field(
			'state',
			__( 'State', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'state', 'class' => 'small-text' )
		);

		add_settings_field(
			'postcode',
			__( 'Postcode', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'postcode', 'class' => 'small-text' )
		);

		add_settings_field(
			'country',
			__( 'Country', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array(
				'field'       => 'country',
				'class'       => 'small-text',
				'placeholder' => 'AU',
				'description' => __( 'ISO 3166-1 alpha-2 country code (e.g. AU, US, GB).', 'dh-google-reviews' ),
			)
		);

		add_settings_field(
			'business_type',
			__( 'Business Type Override', 'dh-google-reviews' ),
			array( $this, 'render_field_business_type' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array( 'field' => 'business_type' )
		);

		add_settings_field(
			'google_place_id',
			__( 'Google Place ID', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array(
				'field'       => 'google_place_id',
				'class'       => 'regular-text',
				'description' => __( 'Used for the CTA button link and schema sameAs URL. Leave blank to hide the CTA button.', 'dh-google-reviews' ),
			)
		);

		add_settings_field(
			'cta_url_override',
			__( 'CTA URL Override', 'dh-google-reviews' ),
			array( $this, 'render_field_url' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array(
				'field'       => 'cta_url_override',
				'class'       => 'large-text',
				'description' => __( 'Optional. Overrides the auto-generated Google Maps CTA link.', 'dh-google-reviews' ),
			)
		);

		add_settings_field(
			'disable_schema',
			__( 'Schema Output', 'dh-google-reviews' ),
			array( $this, 'render_field_checkbox' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array(
				'field' => 'disable_schema',
				'label' => __( 'Disable JSON-LD schema output', 'dh-google-reviews' ),
			)
		);

		add_settings_field(
			'has_existing_local_business_schema',
			__( 'Existing LocalBusiness Schema', 'dh-google-reviews' ),
			array( $this, 'render_field_checkbox' ),
			'dh-reviews-settings',
			'dh_reviews_business',
			array(
				'field' => 'has_existing_local_business_schema',
				'label' => __( 'My site already outputs a LocalBusiness schema entity (e.g. via Yoast or SEOPress). Omit business name and address from this plugin\'s schema output to avoid duplication.', 'dh-google-reviews' ),
			)
		);

		// ---- Display Defaults ----
		add_settings_section(
			'dh_reviews_display',
			__( 'Display Defaults', 'dh-google-reviews' ),
			array( $this, 'render_display_section_intro' ),
			'dh-reviews-settings'
		);

		add_settings_field(
			'default_layout',
			__( 'Default Layout', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'   => 'default_layout',
				'options' => array(
					'grid'   => __( 'Grid', 'dh-google-reviews' ),
					'slider' => __( 'Slider', 'dh-google-reviews' ),
					'list'   => __( 'List', 'dh-google-reviews' ),
				),
				'default' => 'grid',
			)
		);

		add_settings_field(
			'default_columns',
			__( 'Default Columns (Grid)', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'   => 'default_columns',
				'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
				'default' => '3',
			)
		);

		add_settings_field(
			'default_visible',
			__( 'Default Visible Cards (Slider)', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'   => 'default_visible',
				'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
				'default' => '3',
			)
		);

		add_settings_field(
			'default_excerpt_length',
			__( 'Excerpt Length (characters)', 'dh-google-reviews' ),
			array( $this, 'render_field_number' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'   => 'default_excerpt_length',
				'min'     => 50,
				'max'     => 1000,
				'default' => 200,
			)
		);

		add_settings_field(
			'default_date_format',
			__( 'Date Format', 'dh-google-reviews' ),
			array( $this, 'render_field_select' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'   => 'default_date_format',
				'options' => array(
					'relative' => __( 'Relative (e.g. 3 months ago)', 'dh-google-reviews' ),
					'absolute' => __( 'Absolute (e.g. 10 March 2025)', 'dh-google-reviews' ),
				),
				'default' => 'relative',
			)
		);

		$toggles = array(
			'show_owner_replies'   => __( 'Show owner replies', 'dh-google-reviews' ),
			'show_reviewer_photos' => __( 'Show reviewer photos', 'dh-google-reviews' ),
			'show_google_icon'     => __( 'Show Google "G" icon on cards', 'dh-google-reviews' ),
			'show_powered_by'      => __( 'Show "powered by Google" attribution', 'dh-google-reviews' ),
			'show_cta'             => __( 'Show "Review Us On Google" CTA button', 'dh-google-reviews' ),
			'show_dots'            => __( 'Show dot pagination (slider)', 'dh-google-reviews' ),
			'photo_proxy'          => __( 'Serve reviewer photos through WordPress (prevents Google tracking)', 'dh-google-reviews' ),
		);

		foreach ( $toggles as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field_toggle' ),
				'dh-reviews-settings',
				'dh_reviews_display',
				array( 'field' => $field )
			);
		}

		add_settings_field(
			'cta_text',
			__( 'CTA Button Text', 'dh-google-reviews' ),
			array( $this, 'render_field_text' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'       => 'cta_text',
				'class'       => 'regular-text',
				'placeholder' => __( 'Review Us On Google', 'dh-google-reviews' ),
			)
		);

		add_settings_field(
			'custom_css',
			__( 'Custom CSS', 'dh-google-reviews' ),
			array( $this, 'render_field_textarea' ),
			'dh-reviews-settings',
			'dh_reviews_display',
			array(
				'field'       => 'custom_css',
				'rows'        => 8,
				'description' => __( 'Extra CSS appended to the frontend stylesheet. Use .dh-reviews-wrap as your scope.', 'dh-google-reviews' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the main settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require DH_REVIEWS_PATH . 'admin/views/settings.php';
	}

	/**
	 * Render the import/export page.
	 *
	 * @return void
	 */
	public function render_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require DH_REVIEWS_PATH . 'admin/views/import.php';
	}

	/**
	 * Render the sync log page.
	 *
	 * @return void
	 */
	public function render_sync_log_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require DH_REVIEWS_PATH . 'admin/views/sync-log.php';
	}

	// -------------------------------------------------------------------------
	// Asset enqueuing
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Load on all dh_review CPT screens: list table, edit, add new.
		if ( CPT::POST_TYPE === $screen->post_type ) {
			wp_enqueue_style(
				'dh-reviews-admin',
				DH_REVIEWS_URL . 'admin/css/dh-reviews-admin.css',
				array(),
				DH_REVIEWS_VERSION
			);
			return;
		}

		// Load on plugin custom admin pages: settings, import/export, sync log.
		if ( false !== strpos( $hook_suffix, 'dh-reviews' ) ) {
			wp_enqueue_style(
				'dh-reviews-admin',
				DH_REVIEWS_URL . 'admin/css/dh-reviews-admin.css',
				array(),
				DH_REVIEWS_VERSION
			);
		}
	}

	// -------------------------------------------------------------------------
	// Sanitization
	// -------------------------------------------------------------------------

	/**
	 * Sanitize the plugin settings array before saving.
	 *
	 * @param mixed $input Raw settings input (expected array).
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$out = array();

		// --- API Connection ---
		if ( ! defined( 'DH_REVIEWS_CLIENT_ID' ) ) {
			$out['google_client_id'] = sanitize_text_field( $input['google_client_id'] ?? '' );
		}
		if ( ! defined( 'DH_REVIEWS_CLIENT_SECRET' ) ) {
			// Preserve existing secret when the masked field is submitted blank.
			$existing = get_option( self::OPTION_NAME, array() );
			$new_val  = $input['google_client_secret'] ?? '';
			$out['google_client_secret'] = ( '' !== $new_val )
				? sanitize_text_field( $new_val )
				: ( $existing['google_client_secret'] ?? '' );
		}
		$out['google_account_id']  = sanitize_text_field( $input['google_account_id'] ?? '' );
		$out['google_location_id'] = sanitize_text_field( $input['google_location_id'] ?? '' );

		// Preserve OAuth tokens (not submitted via settings form).
		$existing                      = get_option( self::OPTION_NAME, array() );
		$out['oauth_access_token']     = $existing['oauth_access_token'] ?? '';
		$out['oauth_refresh_token']    = $existing['oauth_refresh_token'] ?? '';
		$out['oauth_token_expires']    = $existing['oauth_token_expires'] ?? 0;
		$out['oauth_connected_email']  = $existing['oauth_connected_email'] ?? '';

		// --- Sync Configuration ---
		$out['sync_frequency'] = in_array(
			$input['sync_frequency'] ?? '',
			array( '6h', '12h', '24h', 'manual' ),
			true
		) ? $input['sync_frequency'] : '24h';

		$min_rating = (int) ( $input['min_rating_publish'] ?? 1 );
		$out['min_rating_publish'] = ( $min_rating >= 1 && $min_rating <= 5 ) ? (string) $min_rating : '1';

		$out['below_threshold_action'] = in_array(
			$input['below_threshold_action'] ?? '',
			array( 'draft', 'skip' ),
			true
		) ? $input['below_threshold_action'] : 'draft';

		// --- Business Details ---
		$out['business_name']   = sanitize_text_field( $input['business_name'] ?? '' );
		$out['street_address']  = sanitize_text_field( $input['street_address'] ?? '' );
		$out['city']            = sanitize_text_field( $input['city'] ?? '' );
		$out['state']           = sanitize_text_field( $input['state'] ?? '' );
		$out['postcode']        = sanitize_text_field( $input['postcode'] ?? '' );
		$out['country']         = sanitize_text_field( $input['country'] ?? 'AU' );
		$out['business_type']   = sanitize_text_field( $input['business_type'] ?? '' );
		$out['google_place_id'] = sanitize_text_field( $input['google_place_id'] ?? '' );
		$out['cta_url_override'] = esc_url_raw( $input['cta_url_override'] ?? '' );
		$out['disable_schema']  = ! empty( $input['disable_schema'] ) ? '1' : '';
		$out['has_existing_local_business_schema'] = ! empty( $input['has_existing_local_business_schema'] ) ? '1' : '';

		// --- Display Defaults ---
		$out['default_layout'] = in_array(
			$input['default_layout'] ?? '',
			array( 'grid', 'slider', 'list' ),
			true
		) ? $input['default_layout'] : 'grid';

		$cols = (int) ( $input['default_columns'] ?? 3 );
		$out['default_columns'] = ( $cols >= 1 && $cols <= 4 ) ? (string) $cols : '3';

		$visible = (int) ( $input['default_visible'] ?? 3 );
		$out['default_visible'] = ( $visible >= 1 && $visible <= 4 ) ? (string) $visible : '3';

		$excerpt = (int) ( $input['default_excerpt_length'] ?? 200 );
		$out['default_excerpt_length'] = ( $excerpt >= 50 && $excerpt <= 1000 ) ? $excerpt : 200;

		$out['default_date_format'] = in_array(
			$input['default_date_format'] ?? '',
			array( 'relative', 'absolute' ),
			true
		) ? $input['default_date_format'] : 'relative';

		$toggles = array(
			'show_owner_replies',
			'show_reviewer_photos',
			'show_google_icon',
			'show_powered_by',
			'show_cta',
			'show_dots',
			'photo_proxy',
		);
		foreach ( $toggles as $toggle ) {
			$out[ $toggle ] = ! empty( $input[ $toggle ] ) ? '1' : '';
		}

		$out['cta_text']   = sanitize_text_field( $input['cta_text'] ?? '' );
		$out['custom_css'] = wp_strip_all_tags( $input['custom_css'] ?? '' );

		return $out;
	}

	// -------------------------------------------------------------------------
	// Section intros
	// -------------------------------------------------------------------------

	/**
	 * Render intro text for the API Connection section.
	 *
	 * @return void
	 */
	public function render_api_section_intro(): void {
		$id_set     = defined( 'DH_REVIEWS_CLIENT_ID' );
		$secret_set = defined( 'DH_REVIEWS_CLIENT_SECRET' );

		if ( $id_set || $secret_set ) {
			echo '<div class="dh-reviews-admin-notice"><p>';
			if ( $id_set && $secret_set ) {
				esc_html_e( 'Client ID and Client Secret are set via wp-config.php constants and cannot be edited here.', 'dh-google-reviews' );
			} elseif ( $id_set ) {
				esc_html_e( 'Client ID is set via the DH_REVIEWS_CLIENT_ID constant in wp-config.php.', 'dh-google-reviews' );
			} else {
				esc_html_e( 'Client Secret is set via the DH_REVIEWS_CLIENT_SECRET constant in wp-config.php.', 'dh-google-reviews' );
			}
			echo '</p></div>';
		}
	}

	/**
	 * Render intro text for the Business Details section.
	 *
	 * @return void
	 */
	public function render_business_section_intro(): void {
		echo '<p>' . esc_html__( 'Used to populate the LocalBusiness JSON-LD schema on pages where the reviews shortcode or block is present.', 'dh-google-reviews' ) . '</p>';
	}

	/**
	 * Render intro text for the Display Defaults section.
	 *
	 * @return void
	 */
	public function render_display_section_intro(): void {
		echo '<p>' . esc_html__( 'These values are used when the shortcode or block attributes are not explicitly set.', 'dh-google-reviews' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	/**
	 * Render a text input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_text( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$value    = esc_attr( $settings[ $field ] ?? '' );
		$class    = esc_attr( $args['class'] ?? 'regular-text' );
		$ph       = esc_attr( $args['placeholder'] ?? '' );
		$desc     = $args['description'] ?? '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="%4$s" placeholder="%5$s" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$value,
			$class,
			$ph
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Render a URL input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_url( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$value    = esc_attr( $settings[ $field ] ?? '' );
		$class    = esc_attr( $args['class'] ?? 'large-text' );
		$desc     = $args['description'] ?? '';

		printf(
			'<input type="url" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="%4$s" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$value,
			$class
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Render a password input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_password( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$has_val  = ! empty( $settings[ $field ] );

		// Never echo the stored secret. Show a placeholder if one is saved.
		printf(
			'<input type="password" id="%1$s" name="%2$s[%1$s]" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$has_val ? esc_attr__( 'Leave blank to keep existing value', 'dh-google-reviews' ) : ''
		);
		if ( $has_val ) {
			echo '<p class="description">' . esc_html__( 'A secret is already saved. Enter a new value to replace it, or leave blank to keep the existing one.', 'dh-google-reviews' ) . '</p>';
		}
	}

	/**
	 * Render a select dropdown field.
	 *
	 * @param array $args Field arguments including 'field', 'options', 'default'.
	 * @return void
	 */
	public function render_field_select( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$options  = $args['options'] ?? array();
		$default  = $args['default'] ?? '';
		$current  = $settings[ $field ] ?? $default;

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME )
		);
		foreach ( $options as $val => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $val ),
				selected( $current, (string) $val, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_number( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$default  = $args['default'] ?? 0;
		$value    = (int) ( $settings[ $field ] ?? $default );
		$min      = (int) ( $args['min'] ?? 0 );
		$max      = (int) ( $args['max'] ?? 9999 );

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$d" min="%4$d" max="%5$d" class="small-text" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$value,
			$min,
			$max
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_checkbox( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$label    = $args['label'] ?? '';
		$checked  = ! empty( $settings[ $field ] );

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render a toggle switch (styled checkbox) field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_toggle( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$checked  = ! empty( $settings[ $field ] );

		printf(
			'<label class="dh-reviews-toggle"><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1"%3$s /><span class="dh-reviews-toggle__slider"></span></label>',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false )
		);
	}

	/**
	 * Render a textarea field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_textarea( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? '';
		$rows     = (int) ( $args['rows'] ?? 5 );
		$desc     = $args['description'] ?? '';

		printf(
			'<textarea id="%1$s" name="%2$s[%1$s]" rows="%3$d" class="large-text code">%4$s</textarea>',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$rows,
			esc_textarea( $value )
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Render the Business Type field with a datalist of common Schema.org types.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_business_type( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$field    = $args['field'];
		$value    = esc_attr( $settings[ $field ] ?? '' );
		$types    = Schema::ALLOWED_TYPES;

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" list="dh-reviews-business-type-list" placeholder="LocalBusiness" />',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			$value
		);

		echo '<datalist id="dh-reviews-business-type-list">';
		foreach ( $types as $type ) {
			printf( '<option value="%s">', esc_attr( $type ) );
		}
		echo '</datalist>';
		echo '<p class="description">' . esc_html__( 'Schema.org type for the LocalBusiness entity. Leave blank to use LocalBusiness.', 'dh-google-reviews' ) . '</p>';
	}

	/**
	 * Render the OAuth connect/disconnect button area.
	 *
	 * @return void
	 */
	public function render_field_oauth_connect(): void {
		$settings  = get_option( self::OPTION_NAME, array() );
		$email     = $settings['oauth_connected_email'] ?? '';
		$token     = $settings['oauth_refresh_token'] ?? '';
		$connected = ! empty( $token );

		if ( $connected ) {
			echo '<span class="dh-reviews-status dh-reviews-status--connected">' . esc_html__( 'Connected', 'dh-google-reviews' ) . '</span>';
			if ( $email ) {
				echo ' <span class="description">' . esc_html( $email ) . '</span>';
			}
			echo '<br><br>';
			$disconnect_url = wp_nonce_url(
				admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_oauth_disconnect=1' ),
				'dh_oauth_disconnect'
			);
			printf(
				'<a href="%s" class="button" style="color:#b91c1c;" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $disconnect_url ),
				esc_js( __( 'Disconnect Google account? This will stop automatic syncing.', 'dh-google-reviews' ) ),
				esc_html__( 'Disconnect', 'dh-google-reviews' )
			);
		} else {
			echo '<span class="dh-reviews-status dh-reviews-status--disconnected">' . esc_html__( 'Not connected', 'dh-google-reviews' ) . '</span>';
			echo '<br><br>';
			$connect_url = wp_nonce_url(
				admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_oauth_initiate=1' ),
				'dh_oauth_initiate'
			);
			printf(
				'<a href="%s" class="button button-primary">%s</a>',
				esc_url( $connect_url ),
				esc_html__( 'Connect Google Account', 'dh-google-reviews' )
			);
			echo '<p class="description">' . esc_html__( 'Save your Client ID and Secret first, then click Connect.', 'dh-google-reviews' ) . '</p>';
		}
	}

	/**
	 * Render the Account selector dropdown (empty until OAuth is connected).
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_account_selector( array $args ): void {
		$settings  = get_option( self::OPTION_NAME, array() );
		$field     = $args['field'];
		$current   = $settings[ $field ] ?? '';
		$connected = ! empty( $settings['oauth_refresh_token'] ?? '' );

		if ( ! $connected ) {
			echo '<select disabled><option>' . esc_html__( 'Connect Google Account first', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		// Populated dynamically after OAuth (Session 7).
		printf(
			'<select id="%1$s" name="%2$s[%1$s]"><option value="">%3$s</option></select>',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_html__( 'Select account...', 'dh-google-reviews' )
		);
	}

	/**
	 * Render the Location selector dropdown (empty until account is selected).
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_location_selector( array $args ): void {
		$settings  = get_option( self::OPTION_NAME, array() );
		$field     = $args['field'];
		$current   = $settings[ $field ] ?? '';
		$connected = ! empty( $settings['oauth_refresh_token'] ?? '' );

		if ( ! $connected ) {
			echo '<select disabled><option>' . esc_html__( 'Connect Google Account first', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		printf(
			'<select id="%1$s" name="%2$s[%1$s]"><option value="">%3$s</option></select>',
			esc_attr( $field ),
			esc_attr( self::OPTION_NAME ),
			esc_html__( 'Select location...', 'dh-google-reviews' )
		);
	}

	/**
	 * Render the Sync Now button with AJAX handler.
	 *
	 * @return void
	 */
	public function render_field_sync_now(): void {
		$settings  = get_option( self::OPTION_NAME, array() );
		$connected = ! empty( $settings['oauth_refresh_token'] ?? '' )
			&& ! empty( $settings['google_location_id'] ?? '' );

		$nonce = wp_create_nonce( 'dh_reviews_manual_sync' );

		printf(
			'<button type="button" id="dh-reviews-sync-now" class="button" data-nonce="%s"%s>%s</button>
			<span id="dh-reviews-sync-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
			<span id="dh-reviews-sync-result" style="margin-left:8px;"></span>',
			esc_attr( $nonce ),
			$connected ? '' : ' disabled',
			esc_html__( 'Sync Now', 'dh-google-reviews' )
		);

		if ( ! $connected ) {
			echo '<p class="description">' . esc_html__( 'Connect a Google account and select a location to enable manual sync.', 'dh-google-reviews' ) . '</p>';
		}

		?>
		<script>
		( function () {
			var btn = document.getElementById( 'dh-reviews-sync-now' );
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', function () {
				var spinner = document.getElementById( 'dh-reviews-sync-spinner' );
				var result  = document.getElementById( 'dh-reviews-sync-result' );
				btn.disabled = true;
				spinner.style.visibility = 'visible';
				result.textContent = '';
				var data = new FormData();
				data.append( 'action', 'dh_reviews_manual_sync' );
				data.append( 'nonce', btn.dataset.nonce );
				fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( json ) {
						spinner.style.visibility = 'hidden';
						btn.disabled = false;
						result.style.color = json.success ? '#065f46' : '#b91c1c';
						result.textContent = json.data && json.data.message ? json.data.message : ( json.success ? '<?php echo esc_js( __( 'Done.', 'dh-google-reviews' ) ); ?>' : '<?php echo esc_js( __( 'Sync failed.', 'dh-google-reviews' ) ); ?>' );
					} )
					.catch( function () {
						spinner.style.visibility = 'hidden';
						btn.disabled = false;
						result.style.color = '#b91c1c';
						result.textContent = '<?php echo esc_js( __( 'Request failed. Check your connection.', 'dh-google-reviews' ) ); ?>';
					} );
			} );
		}() );
		</script>
		<?php
	}
}
