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

$log_entries = get_option( 'dh_reviews_sync_log', array() );
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( empty( $log_entries ) ) : ?>
		<p><?php esc_html_e( 'No syncs recorded yet.', 'dh-google-reviews' ); ?></p>
	<?php else : ?>
		<div class="dh-reviews-sync-log">
			<table class="widefat">
				<thead>
					<tr>
						<th class="col-time"><?php esc_html_e( 'Time', 'dh-google-reviews' ); ?></th>
						<th class="col-new"><?php esc_html_e( 'New', 'dh-google-reviews' ); ?></th>
						<th class="col-updated"><?php esc_html_e( 'Updated', 'dh-google-reviews' ); ?></th>
						<th class="col-trashed"><?php esc_html_e( 'Trashed', 'dh-google-reviews' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'dh-google-reviews' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_reverse( $log_entries ) as $entry ) : ?>
						<?php
						$ts      = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;
						$time    = $ts ? date_i18n( 'Y-m-d H:i:s', $ts ) : '—';
						$new     = (int) ( $entry['new'] ?? 0 );
						$updated = (int) ( $entry['updated'] ?? 0 );
						$trashed = (int) ( $entry['trashed'] ?? 0 );
						$errors  = $entry['errors'] ?? array();
						?>
						<tr>
							<td class="col-time"><?php echo esc_html( $time ); ?></td>
							<td class="col-new"><span class="sync-count"><?php echo esc_html( $new ); ?></span></td>
							<td class="col-updated"><span class="sync-count"><?php echo esc_html( $updated ); ?></span></td>
							<td class="col-trashed"><span class="sync-count"><?php echo esc_html( $trashed ); ?></span></td>
							<td class="col-errors">
								<?php if ( ! empty( $errors ) ) : ?>
									<ul style="margin:0;padding-left:1.2em;">
										<?php foreach ( $errors as $err ) : ?>
											<li><?php echo esc_html( $err ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<span style="color:#6b7280;"><?php esc_html_e( 'No errors', 'dh-google-reviews' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
