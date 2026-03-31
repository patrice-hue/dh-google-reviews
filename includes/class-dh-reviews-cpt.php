<?php
/**
 * Custom Post Type and taxonomy registration.
 *
 * Registers the dh_review CPT and dh_review_location taxonomy.
 * Handles meta field registration with register_post_meta() and
 * admin list table column customisation.
 * See SPEC.md Section 4 for full data model specification.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPT
 *
 * Registers the dh_review custom post type, the dh_review_location taxonomy,
 * all associated meta fields, and customises the admin list table.
 */
class CPT {

	/**
	 * Post type identifier.
	 *
	 * @var string
	 */
	const POST_TYPE = 'dh_review';

	/**
	 * Taxonomy identifier.
	 *
	 * @var string
	 */
	const TAXONOMY = 'dh_review_location';

	/**
	 * Valid review source values.
	 *
	 * @var string[]
	 */
	const VALID_SOURCES = array( 'gbp_api', 'manual', 'csv_import' );

	/**
	 * Constructor.
	 *
	 * Registers the CPT, taxonomy, and meta immediately (this class is
	 * instantiated during the init action) and hooks admin column and
	 * meta box callbacks.
	 */
	public function __construct() {
		// Register data model immediately since we are already inside init.
		$this->register_post_type();
		$this->register_taxonomy();
		$this->register_meta_fields();

		// Admin list table columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );

		// Meta box for manual review entry.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Register the dh_review custom post type.
	 *
	 * Section 4.1: public false, show_ui true, supports title + editor,
	 * has_archive false, exclude_from_search true, dashicons-star-filled.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => 'Reviews',
			'singular_name'         => 'Review',
			'menu_name'             => 'Reviews',
			'add_new'               => 'Add Manual Review',
			'add_new_item'          => 'Add Manual Review',
			'edit_item'             => 'Edit Review',
			'new_item'              => 'New Review',
			'view_item'             => 'View Review',
			'search_items'          => 'Search Reviews',
			'not_found'             => 'No reviews found',
			'not_found_in_trash'    => 'No reviews found in Trash',
			'all_items'             => 'All Reviews',
			'filter_items_list'     => 'Filter reviews list',
			'items_list_navigation' => 'Reviews list navigation',
			'items_list'            => 'Reviews list',
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-star-filled',
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'rewrite'             => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the dh_review_location taxonomy.
	 *
	 * Section 4.3: non-hierarchical taxonomy for tagging reviews by location.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => 'Locations',
			'singular_name'              => 'Location',
			'menu_name'                  => 'Locations',
			'search_items'               => 'Search Locations',
			'popular_items'              => 'Popular Locations',
			'all_items'                  => 'All Locations',
			'edit_item'                  => 'Edit Location',
			'update_item'                => 'Update Location',
			'add_new_item'               => 'Add New Location',
			'new_item_name'              => 'New Location Name',
			'separate_items_with_commas' => 'Separate locations with commas',
			'add_or_remove_items'        => 'Add or remove locations',
			'choose_from_most_used'      => 'Choose from the most used locations',
			'not_found'                  => 'No locations found',
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		);

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, $args );
	}

	/**
	 * Register all meta fields for the dh_review CPT.
	 *
	 * Section 4.2: each field registered with proper type, sanitize callback,
	 * and REST API visibility.
	 *
	 * @return void
	 */
	public function register_meta_fields(): void {
		register_post_meta(
			self::POST_TYPE,
			'_dh_star_rating',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_star_rating' ),
				'default'           => 5,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_reviewer_name',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_reviewer_photo',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_review_source',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_review_source' ),
				'default'           => 'manual',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_gbp_review_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_review_updated',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_owner_reply',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_kses_post',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_reply_date',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_review_location',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_dh_review_verified',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitize star rating to an integer between 1 and 5.
	 *
	 * @param mixed $value Raw input value.
	 * @return int Sanitised rating clamped to 1 through 5.
	 */
	public function sanitize_star_rating( $value ): int {
		$rating = absint( $value );
		return max( 1, min( 5, $rating ) );
	}

	/**
	 * Sanitize review source to one of the allowed enum values.
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitised source string.
	 */
	public function sanitize_review_source( $value ): string {
		$value = sanitize_text_field( $value );
		if ( in_array( $value, self::VALID_SOURCES, true ) ) {
			return $value;
		}
		return 'manual';
	}

	/**
	 * Add custom columns to the dh_review admin list table.
	 *
	 * Columns: checkbox, title (reviewer name), star rating, source,
	 * location, date.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( array $columns ): array {
		$new_columns = array();

		// Keep checkbox.
		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		$new_columns['title']          = 'Reviewer Name';
		$new_columns['dh_star_rating'] = 'Rating';
		$new_columns['dh_source']      = 'Source';

		// Taxonomy column is added automatically via show_admin_column.
		if ( isset( $columns[ 'taxonomy-' . self::TAXONOMY ] ) ) {
			$new_columns[ 'taxonomy-' . self::TAXONOMY ] = $columns[ 'taxonomy-' . self::TAXONOMY ];
		}

		$new_columns['date'] = 'Date';

		return $new_columns;
	}

	/**
	 * Render content for custom admin columns.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'dh_star_rating':
				$rating = (int) get_post_meta( $post_id, '_dh_star_rating', true );
				$rating = max( 1, min( 5, $rating ) );
				$output = '';
				for ( $i = 1; $i <= 5; $i++ ) {
					if ( $i <= $rating ) {
						$output .= '<span style="color:#FBBC04;" aria-hidden="true">&#9733;</span>';
					} else {
						$output .= '<span style="color:#E0E0E0;" aria-hidden="true">&#9733;</span>';
					}
				}
				$output .= '<span class="screen-reader-text">' . esc_html( $rating ) . ' out of 5 stars</span>';
				echo wp_kses(
					$output,
					array(
						'span' => array(
							'style'       => array(),
							'aria-hidden' => array(),
							'class'       => array(),
						),
					)
				);
				break;

			case 'dh_source':
				$source = get_post_meta( $post_id, '_dh_review_source', true );
				$labels = array(
					'gbp_api'    => 'Google API',
					'manual'     => 'Manual',
					'csv_import' => 'CSV Import',
				);
				echo esc_html( $labels[ $source ] ?? 'Unknown' );
				break;
		}
	}

	/**
	 * Define which custom columns are sortable.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		$columns['dh_star_rating'] = 'dh_star_rating';
		$columns['date']           = 'date';
		return $columns;
	}

	/**
	 * Handle sorting by custom meta columns in the admin list table.
	 *
	 * @param \WP_Query $query The current query.
	 * @return void
	 */
	public function handle_column_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== self::POST_TYPE ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'dh_star_rating' === $orderby ) {
			$query->set( 'meta_key', '_dh_star_rating' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Register the review details meta box on the edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'dh_review_details',
			'Review Details',
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the review details meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'dh_review_meta_box', 'dh_review_meta_nonce' );

		$star_rating    = (int) get_post_meta( $post->ID, '_dh_star_rating', true );
		$reviewer_name  = get_post_meta( $post->ID, '_dh_reviewer_name', true );
		$reviewer_photo = get_post_meta( $post->ID, '_dh_reviewer_photo', true );
		$review_source  = get_post_meta( $post->ID, '_dh_review_source', true );
		$gbp_review_id  = get_post_meta( $post->ID, '_dh_gbp_review_id', true );
		$review_updated = get_post_meta( $post->ID, '_dh_review_updated', true );
		$owner_reply    = get_post_meta( $post->ID, '_dh_owner_reply', true );
		$reply_date     = get_post_meta( $post->ID, '_dh_reply_date', true );
		$review_loc     = get_post_meta( $post->ID, '_dh_review_location', true );
		$verified       = (bool) get_post_meta( $post->ID, '_dh_review_verified', true );

		if ( ! $star_rating ) {
			$star_rating = 5;
		}
		if ( ! $review_source ) {
			$review_source = 'manual';
		}
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="dh_star_rating">Star Rating</label>
				</th>
				<td>
					<select name="dh_star_rating" id="dh_star_rating">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $star_rating, $i ); ?>>
								<?php echo esc_html( $i ); ?> <?php echo esc_html( 1 === $i ? 'Star' : 'Stars' ); ?>
							</option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_reviewer_name">Reviewer Name</label>
				</th>
				<td>
					<input type="text" name="dh_reviewer_name" id="dh_reviewer_name"
						value="<?php echo esc_attr( $reviewer_name ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_reviewer_photo">Reviewer Photo URL</label>
				</th>
				<td>
					<input type="url" name="dh_reviewer_photo" id="dh_reviewer_photo"
						value="<?php echo esc_attr( $reviewer_photo ); ?>" class="regular-text" />
					<p class="description">Profile photo URL. Leave blank for initial circle fallback.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_review_source">Source</label>
				</th>
				<td>
					<select name="dh_review_source" id="dh_review_source">
						<option value="manual" <?php selected( $review_source, 'manual' ); ?>>Manual</option>
						<option value="gbp_api" <?php selected( $review_source, 'gbp_api' ); ?>>Google API</option>
						<option value="csv_import" <?php selected( $review_source, 'csv_import' ); ?>>CSV Import</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_gbp_review_id">Google Review ID</label>
				</th>
				<td>
					<input type="text" name="dh_gbp_review_id" id="dh_gbp_review_id"
						value="<?php echo esc_attr( $gbp_review_id ); ?>" class="regular-text" />
					<p class="description">Set automatically for API sourced reviews.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_review_updated">Review Updated</label>
				</th>
				<td>
					<input type="datetime-local" name="dh_review_updated" id="dh_review_updated"
						value="<?php echo esc_attr( $review_updated ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_owner_reply">Owner Reply</label>
				</th>
				<td>
					<textarea name="dh_owner_reply" id="dh_owner_reply" rows="4"
						class="large-text"><?php echo esc_textarea( $owner_reply ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_reply_date">Reply Date</label>
				</th>
				<td>
					<input type="datetime-local" name="dh_reply_date" id="dh_reply_date"
						value="<?php echo esc_attr( $reply_date ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dh_review_location">GBP Location Resource</label>
				</th>
				<td>
					<input type="text" name="dh_review_location" id="dh_review_location"
						value="<?php echo esc_attr( $review_loc ); ?>" class="regular-text" />
					<p class="description">Google Business Profile location resource name.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Verified</th>
				<td>
					<label for="dh_review_verified">
						<input type="checkbox" name="dh_review_verified" id="dh_review_verified"
							value="1" <?php checked( $verified ); ?> />
						Mark this review as verified
					</label>
					<p class="description">Automatically set for API sourced reviews.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save meta box field values when the review is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['dh_review_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dh_review_meta_nonce'] ) ), 'dh_review_meta_box' ) ) {
			return;
		}

		// Skip autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Star rating.
		if ( isset( $_POST['dh_star_rating'] ) ) {
			$rating = $this->sanitize_star_rating( $_POST['dh_star_rating'] );
			update_post_meta( $post_id, '_dh_star_rating', $rating );
		}

		// Reviewer name.
		if ( isset( $_POST['dh_reviewer_name'] ) ) {
			update_post_meta( $post_id, '_dh_reviewer_name', sanitize_text_field( wp_unslash( $_POST['dh_reviewer_name'] ) ) );
		}

		// Reviewer photo.
		if ( isset( $_POST['dh_reviewer_photo'] ) ) {
			update_post_meta( $post_id, '_dh_reviewer_photo', esc_url_raw( wp_unslash( $_POST['dh_reviewer_photo'] ) ) );
		}

		// Review source.
		if ( isset( $_POST['dh_review_source'] ) ) {
			update_post_meta( $post_id, '_dh_review_source', $this->sanitize_review_source( $_POST['dh_review_source'] ) );
		}

		// GBP review ID.
		if ( isset( $_POST['dh_gbp_review_id'] ) ) {
			update_post_meta( $post_id, '_dh_gbp_review_id', sanitize_text_field( wp_unslash( $_POST['dh_gbp_review_id'] ) ) );
		}

		// Review updated datetime — also sync to post_date so relative date display is correct.
		// The template uses $review->post_date for the card date; if we only save to meta,
		// post_date stays as the time the post was created (today), showing "today" forever.
		if ( isset( $_POST['dh_review_updated'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['dh_review_updated'] ) );
			update_post_meta( $post_id, '_dh_review_updated', $raw );

			if ( $raw ) {
				$ts = strtotime( $raw );
				if ( $ts ) {
					// Remove hook before calling wp_update_post to prevent infinite recursion.
					remove_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10 );
					wp_update_post( array(
						'ID'            => $post_id,
						'post_date'     => date( 'Y-m-d H:i:s', $ts ),
						'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
						'edit_date'     => true,
					) );
					add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
				}
			}
		}

		// Owner reply.
		if ( isset( $_POST['dh_owner_reply'] ) ) {
			update_post_meta( $post_id, '_dh_owner_reply', wp_kses_post( wp_unslash( $_POST['dh_owner_reply'] ) ) );
		}

		// Reply date.
		if ( isset( $_POST['dh_reply_date'] ) ) {
			update_post_meta( $post_id, '_dh_reply_date', sanitize_text_field( wp_unslash( $_POST['dh_reply_date'] ) ) );
		}

		// Review location resource name.
		if ( isset( $_POST['dh_review_location'] ) ) {
			update_post_meta( $post_id, '_dh_review_location', sanitize_text_field( wp_unslash( $_POST['dh_review_location'] ) ) );
		}

		// Verified flag.
		$verified = isset( $_POST['dh_review_verified'] ) ? true : false;
		update_post_meta( $post_id, '_dh_review_verified', $verified );
	}
}
