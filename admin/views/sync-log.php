<?php
/**
 * Sync log display template.
 *
 * Renders a table of the last 10 sync results with timestamp,
 * review counts, and error details.
 * See SPEC.md Section 3.3 (sync logging) and Section 7.1.
 *
 * @package DH_Reviews
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<!-- Stub: sync log table will be rendered here -->
</div>
