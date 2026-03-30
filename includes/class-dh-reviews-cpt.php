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
	 * Constructor.
	 *
	 * Registers WordPress hooks for CPT, taxonomy, meta, and admin columns.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_filter( 'manage_dh_review_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_dh_review_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-dh_review_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	/**
	 * Register the dh_review custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		// Stub: register dh_review CPT per Section 4.1.
	}

	/**
	 * Register the dh_review_location taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		// Stub: register dh_review_location taxonomy per Section 4.3.
	}

	/**
	 * Register all meta fields for the dh_review CPT.
	 *
	 * @return void
	 */
	public function register_meta_fields(): void {
		// Stub: register_post_meta for all fields in Section 4.2.
	}

	/**
	 * Add custom columns to the dh_review admin list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( array $columns ): array {
		// Stub: add star rating, source, date, location columns.
		return $columns;
	}

	/**
	 * Render content for custom admin columns.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( string $column, int $post_id ): void {
		// Stub: output column content.
	}

	/**
	 * Define which custom columns are sortable.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		// Stub: make star rating and source sortable.
		return $columns;
	}
}
