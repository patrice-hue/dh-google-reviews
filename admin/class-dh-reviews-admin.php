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
		add_action( 'admin_init', array( $this, 'handle_cache_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_dh_reviews_connect_oauth', array( $this, 'handle_connect_oauth' ) );
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

		// Locations taxonomy.
		add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'Locations', 'dh-google-reviews' ),
			__( 'Locations', 'dh-google-reviews' ),
			'manage_options',
			'edit-tags.php?taxonomy=' . CPT::TAXONOMY . '&post_type=' . CPT::POST_TYPE
		);

		// Shortcode Generator.
		$this->page_hooks['shortcode_gen'] = add_submenu_page(
			'edit.php?post_type=' . CPT::POST_TYPE,
			__( 'Shortcode Generator', 'dh-google-reviews' ),
			__( 'Shortcode Generator', 'dh-google-reviews' ),
			'manage_options',
			'dh-reviews-shortcode-gen',
			array( $this, 'render_shortcode_generator_page' )
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

		// OAuth callback — hidden page (null parent), never shown in the menu.
		// Registering it here makes WordPress accept the URL as a valid admin page
		// so the capability check passes when Google redirects back after consent.
		// The actual token exchange is handled by API::handle_oauth_callback() on
		// the admin_init hook; this render callback is only reached if that handler
		// does not redirect first (e.g. if the code param is missing).
		add_submenu_page(
			null,
			__( 'Google OAuth Callback', 'dh-google-reviews' ),
			'',
			'manage_options',
			'dh-reviews-oauth-callback',
			array( $this, 'render_oauth_callback_page' )
		);
	}

	/**
	 * Fallback render for the OAuth callback page.
	 *
	 * Under normal operation the admin_init handler in API::handle_oauth_callback()
	 * processes the authorisation code and redirects before this method is called.
	 * It is only reached when the code parameter is absent (e.g. user navigates
	 * directly to the URL), in which case a safe redirect to settings is performed.
	 *
	 * @return void
	 */
	public function render_oauth_callback_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dh-google-reviews' ) );
		}

		// API::handle_oauth_callback() should have already redirected. If we reach
		// here the request has no code param — send the user back to settings.
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
		exit;
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
				array(
					'field'       => 'google_client_id',
					'class'       => 'regular-text',
					'description' => __( 'Found in Google Cloud Console > APIs & Services > Credentials', 'dh-google-reviews' ),
				)
			);
		}

		if ( ! defined( 'DH_REVIEWS_CLIENT_SECRET' ) ) {
			add_settings_field(
				'google_client_secret',
				__( 'Google Cloud Client Secret', 'dh-google-reviews' ),
				array( $this, 'render_field_password' ),
				'dh-reviews-settings',
				'dh_reviews_api',
				array(
					'field'       => 'google_client_secret',
					'description' => __( 'Shown only once when creating the credential. If lost, create a new one.', 'dh-google-reviews' ),
				)
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
	 * Render the Shortcode Generator page.
	 *
	 * Provides a visual form for building [dh_reviews] shortcodes with live
	 * preview. Only attributes that differ from the defaults are included in
	 * the generated output.
	 *
	 * @return void
	 */
	public function render_shortcode_generator_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dh-google-reviews' ) );
		}

		// Taxonomy terms for the Location dropdown.
		$terms = get_terms(
			array(
				'taxonomy'   => CPT::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		// Toggles: [ attr => label ]
		$display_toggles = array(
			'show_reply'     => __( 'Show reply', 'dh-google-reviews' ),
			'show_date'      => __( 'Show date', 'dh-google-reviews' ),
			'show_photo'     => __( 'Show reviewer photo', 'dh-google-reviews' ),
			'show_stars'     => __( 'Show star rating', 'dh-google-reviews' ),
			'show_aggregate' => __( 'Show aggregate score', 'dh-google-reviews' ),
			'show_dots'      => __( 'Show slider dots', 'dh-google-reviews' ),
		);

		$branding_toggles = array(
			'show_google_icon'        => __( 'Show Google icon', 'dh-google-reviews' ),
			'show_google_attribution' => __( 'Show Google attribution', 'dh-google-reviews' ),
			'show_cta'                => __( 'Show call-to-action button', 'dh-google-reviews' ),
		);
		?>
		<div class="wrap dh-reviews-settings-wrap">
			<h1><?php esc_html_e( 'Shortcode Generator', 'dh-google-reviews' ); ?></h1>
			<p><?php esc_html_e( 'Adjust the controls below to build a [dh_reviews] shortcode. Only attributes that differ from the defaults are included in the output — [dh_reviews] with no attributes uses all defaults.', 'dh-google-reviews' ); ?></p>

			<!-- ── Layout ──────────────────────────────────────────────────────── -->
			<h2><?php esc_html_e( 'Layout', 'dh-google-reviews' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="dh-gen-layout"><?php esc_html_e( 'Layout', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-layout" data-attr="layout">
							<option value="grid"><?php esc_html_e( 'Grid', 'dh-google-reviews' ); ?></option>
							<option value="slider"><?php esc_html_e( 'Slider', 'dh-google-reviews' ); ?></option>
							<option value="list"><?php esc_html_e( 'List', 'dh-google-reviews' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-count"><?php esc_html_e( 'Count', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="number" id="dh-gen-count" data-attr="count" value="5" min="1" max="50" class="small-text">
						<p class="description"><?php esc_html_e( 'Number of reviews to display (1–50).', 'dh-google-reviews' ); ?></p>
					</td>
				</tr>
				<tr id="dh-gen-row-columns">
					<th scope="row">
						<label for="dh-gen-columns"><?php esc_html_e( 'Columns', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="number" id="dh-gen-columns" data-attr="columns" value="3" min="1" max="4" class="small-text">
						<p class="description"><?php esc_html_e( 'Grid columns (1–4). Grid layout only.', 'dh-google-reviews' ); ?></p>
					</td>
				</tr>
				<tr id="dh-gen-row-visible_cards" style="display:none;">
					<th scope="row">
						<label for="dh-gen-visible_cards"><?php esc_html_e( 'Visible cards', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="number" id="dh-gen-visible_cards" data-attr="visible_cards" value="3" min="1" max="4" class="small-text">
						<p class="description"><?php esc_html_e( 'Cards visible at once in the slider (1–4).', 'dh-google-reviews' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- ── Filtering ────────────────────────────────────────────────────── -->
			<h2><?php esc_html_e( 'Filtering', 'dh-google-reviews' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="dh-gen-min_rating"><?php esc_html_e( 'Minimum rating', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-min_rating" data-attr="min_rating">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>">
									<?php
									/* translators: %d: number of stars */
									echo esc_html( sprintf( _n( '%d star', '%d stars', $i, 'dh-google-reviews' ), $i ) );
									?>
								</option>
							<?php endfor; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-orderby"><?php esc_html_e( 'Order by', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-orderby" data-attr="orderby">
							<option value="date"><?php esc_html_e( 'Date', 'dh-google-reviews' ); ?></option>
							<option value="rating"><?php esc_html_e( 'Rating', 'dh-google-reviews' ); ?></option>
							<option value="random"><?php esc_html_e( 'Random', 'dh-google-reviews' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-order"><?php esc_html_e( 'Order', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-order" data-attr="order">
							<option value="DESC"><?php esc_html_e( 'Descending', 'dh-google-reviews' ); ?></option>
							<option value="ASC"><?php esc_html_e( 'Ascending', 'dh-google-reviews' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-excerpt_length"><?php esc_html_e( 'Excerpt length', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="number" id="dh-gen-excerpt_length" data-attr="excerpt_length" value="150" min="0" class="small-text">
						<p class="description"><?php esc_html_e( 'Characters before truncation. 0 = no limit.', 'dh-google-reviews' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-date_format"><?php esc_html_e( 'Date format', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-date_format" data-attr="date_format">
							<option value="relative"><?php esc_html_e( 'Relative (e.g. "3 months ago")', 'dh-google-reviews' ); ?></option>
							<option value="absolute"><?php esc_html_e( 'Absolute (e.g. "12 January 2025")', 'dh-google-reviews' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-location"><?php esc_html_e( 'Location', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<select id="dh-gen-location" data-attr="location">
							<option value=""><?php esc_html_e( 'All locations', 'dh-google-reviews' ); ?></option>
							<?php foreach ( $terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>">
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<!-- ── Display Options ──────────────────────────────────────────────── -->
			<h2><?php esc_html_e( 'Display Options', 'dh-google-reviews' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $display_toggles as $attr => $label ) : ?>
				<tr>
					<th scope="row">
						<label for="dh-gen-<?php echo esc_attr( $attr ); ?>"><?php echo esc_html( $label ); ?></label>
					</th>
					<td>
						<label class="dh-reviews-toggle">
							<input type="checkbox"
								id="dh-gen-<?php echo esc_attr( $attr ); ?>"
								data-attr="<?php echo esc_attr( $attr ); ?>"
								checked>
							<span class="dh-reviews-toggle__slider"></span>
						</label>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<!-- ── Google Branding ──────────────────────────────────────────────── -->
			<h2><?php esc_html_e( 'Google Branding', 'dh-google-reviews' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $branding_toggles as $attr => $label ) : ?>
				<tr>
					<th scope="row">
						<label for="dh-gen-<?php echo esc_attr( $attr ); ?>"><?php echo esc_html( $label ); ?></label>
					</th>
					<td>
						<label class="dh-reviews-toggle">
							<input type="checkbox"
								id="dh-gen-<?php echo esc_attr( $attr ); ?>"
								data-attr="<?php echo esc_attr( $attr ); ?>"
								checked>
							<span class="dh-reviews-toggle__slider"></span>
						</label>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr id="dh-gen-row-cta_text">
					<th scope="row">
						<label for="dh-gen-cta_text"><?php esc_html_e( 'CTA button text', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="text" id="dh-gen-cta_text" data-attr="cta_text"
							value="Review Us On Google" class="regular-text">
					</td>
				</tr>
			</table>

			<!-- ── Schema ───────────────────────────────────────────────────────── -->
			<h2><?php esc_html_e( 'Schema', 'dh-google-reviews' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="dh-gen-schema"><?php esc_html_e( 'Output JSON-LD schema', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<label class="dh-reviews-toggle">
							<input type="checkbox" id="dh-gen-schema" data-attr="schema" checked>
							<span class="dh-reviews-toggle__slider"></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dh-gen-class"><?php esc_html_e( 'Extra CSS class', 'dh-google-reviews' ); ?></label>
					</th>
					<td>
						<input type="text" id="dh-gen-class" data-attr="class" value=""
							class="regular-text" placeholder="my-custom-class">
						<p class="description"><?php esc_html_e( 'Additional class added to the review wrapper element.', 'dh-google-reviews' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- ── Generated Shortcode ──────────────────────────────────────────── -->
			<div class="dh-reviews-generator-output">
				<h2><?php esc_html_e( 'Generated Shortcode', 'dh-google-reviews' ); ?></h2>
				<div class="dh-reviews-generator-copy-row">
					<input type="text" id="dh-gen-output" class="large-text code" readonly value="[dh_reviews]">
					<button type="button" id="dh-gen-copy" class="button button-primary">
						<?php esc_html_e( 'Copy Shortcode', 'dh-google-reviews' ); ?>
					</button>
					<span id="dh-gen-copied" class="dh-reviews-generator-copied" style="display:none;">
						<?php esc_html_e( 'Copied!', 'dh-google-reviews' ); ?>
					</span>
				</div>
			</div>
		</div><!-- .wrap -->

		<script>
		( function () {
			'use strict';

			var DEFAULTS = {
				layout:                 'grid',
				count:                  '5',
				columns:                '3',
				visible_cards:          '3',
				min_rating:             '1',
				orderby:                'date',
				order:                  'DESC',
				excerpt_length:         '150',
				date_format:            'relative',
				location:               '',
				show_reply:             true,
				show_date:              true,
				show_photo:             true,
				show_stars:             true,
				show_aggregate:         true,
				show_dots:              true,
				show_google_icon:       true,
				show_google_attribution: true,
				show_cta:               true,
				cta_text:               'Review Us On Google',
				schema:                 true,
				'class':                ''
			};

			function getEl( attr ) {
				return document.getElementById( 'dh-gen-' + attr );
			}

			function val( attr ) {
				var el = getEl( attr );
				return el ? el.value : '';
			}

			function bool( attr ) {
				var el = getEl( attr );
				return el ? el.checked : true;
			}

			function maybe( parts, attr, current, def ) {
				if ( String( current ).trim() !== String( def ) ) {
					parts.push( attr + '="' + current + '"' );
				}
			}

			function maybeBool( parts, attr, current, def ) {
				if ( current !== def ) {
					parts.push( attr + '="' + ( current ? 'true' : 'false' ) + '"' );
				}
			}

			function buildShortcode() {
				var layout = val( 'layout' );
				var parts  = [];

				maybe( parts, 'layout',         layout,                    DEFAULTS.layout );
				maybe( parts, 'count',          val( 'count' ),            DEFAULTS.count );

				if ( layout === 'grid' ) {
					maybe( parts, 'columns', val( 'columns' ), DEFAULTS.columns );
				}
				if ( layout === 'slider' ) {
					maybe( parts, 'visible_cards', val( 'visible_cards' ), DEFAULTS.visible_cards );
				}

				maybe( parts, 'min_rating',     val( 'min_rating' ),      DEFAULTS.min_rating );
				maybe( parts, 'orderby',        val( 'orderby' ),         DEFAULTS.orderby );
				maybe( parts, 'order',          val( 'order' ),           DEFAULTS.order );
				maybe( parts, 'excerpt_length', val( 'excerpt_length' ),  DEFAULTS.excerpt_length );
				maybe( parts, 'date_format',    val( 'date_format' ),     DEFAULTS.date_format );
				maybe( parts, 'location',       val( 'location' ),        DEFAULTS.location );

				maybeBool( parts, 'show_reply',             bool( 'show_reply' ),             DEFAULTS.show_reply );
				maybeBool( parts, 'show_date',              bool( 'show_date' ),              DEFAULTS.show_date );
				maybeBool( parts, 'show_photo',             bool( 'show_photo' ),             DEFAULTS.show_photo );
				maybeBool( parts, 'show_stars',             bool( 'show_stars' ),             DEFAULTS.show_stars );
				maybeBool( parts, 'show_aggregate',         bool( 'show_aggregate' ),         DEFAULTS.show_aggregate );
				maybeBool( parts, 'show_dots',              bool( 'show_dots' ),              DEFAULTS.show_dots );
				maybeBool( parts, 'show_google_icon',       bool( 'show_google_icon' ),       DEFAULTS.show_google_icon );
				maybeBool( parts, 'show_google_attribution', bool( 'show_google_attribution' ), DEFAULTS.show_google_attribution );
				maybeBool( parts, 'show_cta',               bool( 'show_cta' ),               DEFAULTS.show_cta );
				maybe( parts, 'cta_text', val( 'cta_text' ), DEFAULTS.cta_text );
				maybeBool( parts, 'schema', bool( 'schema' ), DEFAULTS.schema );
				maybe( parts, 'class', val( 'class' ), DEFAULTS['class'] );

				getEl( 'output' ).value =
					'[dh_reviews' + ( parts.length ? ' ' + parts.join( ' ' ) : '' ) + ']';
			}

			function updateConditional() {
				var layout   = val( 'layout' );
				var colRow   = document.getElementById( 'dh-gen-row-columns' );
				var slideRow = document.getElementById( 'dh-gen-row-visible_cards' );
				var ctaRow   = document.getElementById( 'dh-gen-row-cta_text' );

				if ( colRow )   { colRow.style.display   = ( layout === 'grid'   ) ? '' : 'none'; }
				if ( slideRow ) { slideRow.style.display  = ( layout === 'slider' ) ? '' : 'none'; }
				if ( ctaRow ) {
					var showCta = getEl( 'show_cta' );
					ctaRow.style.display = ( showCta && showCta.checked ) ? '' : 'none';
				}
			}

			// Bind all controls.
			document.querySelectorAll( '[data-attr]' ).forEach( function ( el ) {
				el.addEventListener( 'change', function () { updateConditional(); buildShortcode(); } );
				el.addEventListener( 'input',  buildShortcode );
			} );

			// Copy button.
			document.getElementById( 'dh-gen-copy' ).addEventListener( 'click', function () {
				var output  = getEl( 'output' );
				var confirm = document.getElementById( 'dh-gen-copied' );

				output.select();
				try { document.execCommand( 'copy' ); } catch ( e ) {}
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( output.value );
				}

				confirm.style.display = 'inline';
				setTimeout( function () { confirm.style.display = 'none'; }, 2000 );
			} );

			// Initialise.
			updateConditional();
			buildShortcode();
		}() );
		</script>
		<?php
	}

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
	// Cache refresh handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the "Refresh" link that clears a cached API list.
	 *
	 * Triggered by GET ?dh_refresh_cache=accounts|locations with a nonce.
	 * Deletes the relevant transient then redirects back to the settings page
	 * so the next page load fetches fresh data from the GBP API.
	 *
	 * Hooked on admin_init.
	 *
	 * @return void
	 */
	public function handle_cache_refresh(): void {
		if ( ! isset( $_GET['dh_refresh_cache'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dh_refresh_cache' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'dh-google-reviews' ) );
		}

		$what = sanitize_key( wp_unslash( $_GET['dh_refresh_cache'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		if ( 'accounts' === $what ) {
			delete_transient( API::ACCOUNTS_CACHE );
		} elseif ( 'locations' === $what ) {
			$settings   = get_option( self::OPTION_NAME, array() );
			$account_id = $settings['google_account_id'] ?? '';
			if ( $account_id ) {
				$cache_key = API::LOCATIONS_CACHE_PREFIX
					. sanitize_key( str_replace( '/', '_', $account_id ) );
				delete_transient( $cache_key );
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// OAuth connect handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the "Connect Google Account" form POST.
	 *
	 * Saves the Client ID and Client Secret supplied in the POST body,
	 * then immediately redirects the browser to the Google OAuth
	 * authorisation URL — so the user never needs to click Save Changes
	 * before connecting.
	 *
	 * Hooked on admin_post_dh_reviews_connect_oauth.
	 *
	 * @return void
	 */
	public function handle_connect_oauth(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dh-google-reviews' ) );
		}

		if (
			! isset( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dh_reviews_connect_oauth' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'dh-google-reviews' ) );
		}

		$settings = get_option( self::OPTION_NAME, array() );

		// Persist Client ID if submitted and not locked by a wp-config constant.
		if ( ! defined( 'DH_REVIEWS_CLIENT_ID' ) ) {
			$client_id = sanitize_text_field( wp_unslash( $_POST['google_client_id'] ?? '' ) );
			if ( '' !== $client_id ) {
				$settings['google_client_id'] = $client_id;
			}
		}

		// Persist Client Secret if submitted and not locked by a wp-config constant.
		// An empty submission means the user left the field blank — keep the value
		// already stored (the field intentionally never echoes the stored secret).
		if ( ! defined( 'DH_REVIEWS_CLIENT_SECRET' ) ) {
			$client_secret = sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ?? '' ) );
			if ( '' !== $client_secret ) {
				$settings['google_client_secret'] = $client_secret;
			}
		}

		update_option( self::OPTION_NAME, $settings );

		// Verify both credentials are now in place (constant or stored).
		$has_id     = defined( 'DH_REVIEWS_CLIENT_ID' ) || ! empty( $settings['google_client_id'] );
		$has_secret = defined( 'DH_REVIEWS_CLIENT_SECRET' ) || ! empty( $settings['google_client_secret'] );

		if ( ! $has_id || ! $has_secret ) {
			// Surface the error through the same transient the API class reads.
			set_transient(
				'dh_reviews_api_error_' . get_current_user_id(),
				__( 'Please enter your Google Cloud Client ID and Client Secret before connecting.', 'dh-google-reviews' ),
				5 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings' ) );
			exit;
		}

		// Credentials saved — redirect to Google's OAuth consent screen.
		$api = new API();
		wp_redirect( $api->get_auth_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
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

		$redirect_uri = admin_url( 'admin.php?page=dh-reviews-oauth-callback' );
		?>
		<details class="dh-reviews-help-panel">
			<summary><?php esc_html_e( 'How to get your Google Cloud credentials', 'dh-google-reviews' ); ?></summary>
			<div class="dh-reviews-help-panel__body">
				<ol>
					<li><?php
						printf(
							/* translators: %s: URL */
							esc_html__( 'Go to Google Cloud Console: %s', 'dh-google-reviews' ),
							'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">https://console.cloud.google.com/</a>'
						);
					?></li>
					<li><?php esc_html_e( 'Create a new project or select an existing one.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Go to APIs & Services > Library.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Search for and enable these two APIs:', 'dh-google-reviews' ); ?>
						<ul>
							<li><strong>My Business Account Management API</strong></li>
							<li><strong>My Business Business Information API</strong></li>
						</ul>
					</li>
					<li><?php esc_html_e( 'Go to APIs & Services > Credentials.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Click Create Credentials > OAuth 2.0 Client ID.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'If prompted, configure the OAuth consent screen first:', 'dh-google-reviews' ); ?>
						<ul>
							<li><?php esc_html_e( 'User Type: External', 'dh-google-reviews' ); ?></li>
							<li><?php esc_html_e( 'App name: your business name', 'dh-google-reviews' ); ?></li>
							<li><?php esc_html_e( 'Support email: your email address', 'dh-google-reviews' ); ?></li>
							<li><?php esc_html_e( 'Authorized domains: your website domain', 'dh-google-reviews' ); ?></li>
							<li><?php esc_html_e( 'Save and continue through all steps.', 'dh-google-reviews' ); ?></li>
						</ul>
					</li>
					<li><?php esc_html_e( 'Back in Credentials, click Create Credentials > OAuth 2.0 Client ID.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Application type: Web application.', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Name: DH Google Reviews (or any name you prefer).', 'dh-google-reviews' ); ?></li>
					<li><?php esc_html_e( 'Under Authorized redirect URIs, click Add URI and enter:', 'dh-google-reviews' ); ?>
						<code class="dh-reviews-redirect-uri"><?php echo esc_html( $redirect_uri ); ?></code>
					</li>
					<li><?php esc_html_e( 'Click Create, then copy the Client ID and Client Secret into the fields below.', 'dh-google-reviews' ); ?></li>
				</ol>
			</div>
		</details>
		<?php
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
		$desc     = $args['description'] ?? '';

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
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
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

			$nonce = wp_create_nonce( 'dh_reviews_connect_oauth' );
			?>
			<button type="button" id="dh-reviews-connect-btn" class="button button-primary">
				<?php esc_html_e( 'Connect Google Account', 'dh-google-reviews' ); ?>
			</button>
			<script>
			( function () {
				var btn = document.getElementById( 'dh-reviews-connect-btn' );
				if ( ! btn ) { return; }

				btn.addEventListener( 'click', function () {
					var idField  = document.getElementById( 'google_client_id' );
					var secField = document.getElementById( 'google_client_secret' );

					var form = document.createElement( 'form' );
					form.method = 'POST';
					form.action = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;

					var fields = {
						'action'              : 'dh_reviews_connect_oauth',
						'_wpnonce'            : <?php echo wp_json_encode( $nonce ); ?>,
						'google_client_id'    : idField  ? idField.value  : '',
						'google_client_secret': secField ? secField.value : ''
					};

					Object.keys( fields ).forEach( function ( key ) {
						var input   = document.createElement( 'input' );
						input.type  = 'hidden';
						input.name  = key;
						input.value = fields[ key ];
						form.appendChild( input );
					} );

					document.body.appendChild( form );
					form.submit();
				} );
			}() );
			</script>
			<?php
		}
	}

	/**
	 * Render the Account selector dropdown, populated from the GBP API.
	 *
	 * Results are read from the ACCOUNTS_CACHE transient (1 h). A "Refresh"
	 * link next to the dropdown lets the user bust the cache on demand.
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

		$refresh_url = wp_nonce_url(
			admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_refresh_cache=accounts' ),
			'dh_refresh_cache'
		);

		$api      = new API();
		$accounts = $api->list_accounts();

		if ( false === $accounts ) {
			$err_key = API::ERROR_TRANSIENT . '_' . get_current_user_id();
			$error   = get_transient( $err_key );
			delete_transient( $err_key );
			echo '<div class="notice notice-error inline"><p>'
				. '<strong>' . esc_html__( 'Could not load Google accounts:', 'dh-google-reviews' ) . '</strong> '
				. ( $error ? esc_html( $error ) : esc_html__( 'Unknown error.', 'dh-google-reviews' ) )
				. '</p></div>';
			echo '<select disabled><option>' . esc_html__( 'Reload page to retry', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		if ( empty( $accounts ) ) {
			echo '<select disabled><option>' . esc_html__( 'No Google Business accounts found', 'dh-google-reviews' ) . '</option></select>';
			echo ' <a href="' . esc_url( $refresh_url ) . '" class="dh-reviews-cache-refresh">'
				. esc_html__( 'Refresh', 'dh-google-reviews' ) . '</a>';
			return;
		}

		printf( '<select id="%1$s" name="%2$s[%1$s]">', esc_attr( $field ), esc_attr( self::OPTION_NAME ) );
		echo '<option value="">' . esc_html__( 'Select account...', 'dh-google-reviews' ) . '</option>';

		foreach ( $accounts as $account ) {
			$name  = $account['name'] ?? '';
			$label = $account['accountName'] ?? $name;
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $name ),
				selected( $current, $name, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo ' <a href="' . esc_url( $refresh_url ) . '" class="dh-reviews-cache-refresh">'
			. esc_html__( 'Refresh', 'dh-google-reviews' ) . '</a>';
	}

	/**
	 * Render the Location selector dropdown, populated from the GBP API.
	 *
	 * Results are read from a per-account transient (1 h). A "Refresh" link
	 * next to the dropdown lets the user bust the cache on demand.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_location_selector( array $args ): void {
		$settings   = get_option( self::OPTION_NAME, array() );
		$field      = $args['field'];
		$current    = $settings[ $field ] ?? '';
		$connected  = ! empty( $settings['oauth_refresh_token'] ?? '' );
		$account_id = $settings['google_account_id'] ?? '';

		if ( ! $connected ) {
			echo '<select disabled><option>' . esc_html__( 'Connect Google Account first', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		if ( empty( $account_id ) ) {
			echo '<select disabled><option>' . esc_html__( 'Select and save an account first', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		$refresh_url = wp_nonce_url(
			admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=dh-reviews-settings&dh_refresh_cache=locations' ),
			'dh_refresh_cache'
		);

		$api       = new API();
		$locations = $api->list_locations( $account_id );

		if ( false === $locations ) {
			$err_key = API::ERROR_TRANSIENT . '_' . get_current_user_id();
			$error   = get_transient( $err_key );
			delete_transient( $err_key );
			echo '<div class="notice notice-error inline"><p>'
				. '<strong>' . esc_html__( 'Could not load locations:', 'dh-google-reviews' ) . '</strong> '
				. ( $error ? esc_html( $error ) : esc_html__( 'Unknown error.', 'dh-google-reviews' ) )
				. '</p></div>';
			echo '<select disabled><option>' . esc_html__( 'Reload page to retry', 'dh-google-reviews' ) . '</option></select>';
			return;
		}

		if ( empty( $locations ) ) {
			echo '<select disabled><option>' . esc_html__( 'No locations found for this account', 'dh-google-reviews' ) . '</option></select>';
			echo ' <a href="' . esc_url( $refresh_url ) . '" class="dh-reviews-cache-refresh">'
				. esc_html__( 'Refresh', 'dh-google-reviews' ) . '</a>';
			return;
		}

		printf( '<select id="%1$s" name="%2$s[%1$s]">', esc_attr( $field ), esc_attr( self::OPTION_NAME ) );
		echo '<option value="">' . esc_html__( 'Select location...', 'dh-google-reviews' ) . '</option>';

		foreach ( $locations as $location ) {
			$name  = $location['name'] ?? '';
			$label = $location['title'] ?? $name;
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $name ),
				selected( $current, $name, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo ' <a href="' . esc_url( $refresh_url ) . '" class="dh-reviews-cache-refresh">'
			. esc_html__( 'Refresh', 'dh-google-reviews' ) . '</a>';
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
