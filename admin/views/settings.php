<?php
/**
 * Settings page template.
 *
 * Renders the plugin settings form with sections for API Connection,
 * Sync Configuration, Business Details, and Display Defaults.
 * See SPEC.md Section 7.2.
 *
 * @package DH_Reviews
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap dh-reviews-settings-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'dh_reviews_settings_group' );
		do_settings_sections( 'dh-reviews-settings' );
		submit_button( __( 'Save Settings', 'dh-google-reviews' ) );
		?>
	</form>
</div>
