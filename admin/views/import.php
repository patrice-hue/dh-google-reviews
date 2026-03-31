<?php
/**
 * Import/Export page template.
 *
 * Renders the CSV import form and JSON export button.
 * See SPEC.md Sections 7.3 and 7.4.
 *
 * @package DH_Reviews
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Retrieve and clear any result transient stored by Import::handle_csv_upload().
$import_result = get_transient( 'dh_reviews_import_result_' . get_current_user_id() );
if ( $import_result ) {
	delete_transient( 'dh_reviews_import_result_' . get_current_user_id() );
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( $import_result ) : ?>
		<?php if ( ! empty( $import_result['error'] ) ) : ?>
			<div class="dh-reviews-import-result dh-reviews-import-result--error">
				<strong><?php esc_html_e( 'Import failed:', 'dh-google-reviews' ); ?></strong>
				<?php echo esc_html( $import_result['error'] ); ?>
			</div>
		<?php else : ?>
			<div class="dh-reviews-import-result dh-reviews-import-result--success">
				<strong><?php esc_html_e( 'Import complete.', 'dh-google-reviews' ); ?></strong>
				<?php
				printf(
					/* translators: 1: created count, 2: skipped count, 3: error count */
					esc_html__( '%1$d created, %2$d skipped, %3$d errors.', 'dh-google-reviews' ),
					(int) ( $import_result['created'] ?? 0 ),
					(int) ( $import_result['skipped'] ?? 0 ),
					(int) ( $import_result['errors'] ?? 0 )
				);
				?>
				<?php if ( ! empty( $import_result['error_details'] ) ) : ?>
					<ul style="margin:8px 0 0 1.2em;">
						<?php foreach ( $import_result['error_details'] as $detail ) : ?>
							<li><?php echo esc_html( $detail ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div style="display:flex;gap:48px;align-items:flex-start;flex-wrap:wrap;">

		<!-- ======================================================
		     CSV Import
		     ====================================================== -->
		<div style="flex:1;min-width:320px;max-width:580px;">
			<h2><?php esc_html_e( 'Import Reviews from CSV', 'dh-google-reviews' ); ?></h2>

			<div class="dh-reviews-admin-notice">
				<p><strong><?php esc_html_e( 'Expected CSV format:', 'dh-google-reviews' ); ?></strong></p>
				<p><code>reviewer_name, star_rating, review_text, review_date, owner_reply, location</code></p>
				<p><?php esc_html_e( 'Required columns: reviewer_name, star_rating. All others are optional.', 'dh-google-reviews' ); ?></p>
				<p><?php esc_html_e( 'Max file size: 5 MB. Date format: YYYY-MM-DD. Location must be a slug (e.g. perth-cbd).', 'dh-google-reviews' ); ?></p>
			</div>

			<form method="post"
			      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			      enctype="multipart/form-data"
			      class="dh-reviews-import-form">

				<?php wp_nonce_field( 'dh_reviews_csv_import', 'dh_reviews_import_nonce' ); ?>
				<input type="hidden" name="action" value="dh_reviews_csv_import" />

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dh-reviews-csv-file">
									<?php esc_html_e( 'CSV File', 'dh-google-reviews' ); ?>
								</label>
							</th>
							<td>
								<input type="file"
								       id="dh-reviews-csv-file"
								       name="dh_reviews_csv"
								       accept=".csv,text/csv"
								       required />
								<p class="description">
									<?php esc_html_e( 'Select a .csv file to import. Maximum 5 MB.', 'dh-google-reviews' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dh-reviews-import-status">
									<?php esc_html_e( 'Post Status', 'dh-google-reviews' ); ?>
								</label>
							</th>
							<td>
								<select id="dh-reviews-import-status" name="dh_reviews_import_status">
									<option value="publish"><?php esc_html_e( 'Published', 'dh-google-reviews' ); ?></option>
									<option value="draft"><?php esc_html_e( 'Draft', 'dh-google-reviews' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Status applied to all imported reviews.', 'dh-google-reviews' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Import CSV', 'dh-google-reviews' ), 'primary', 'dh_reviews_import_submit' ); ?>
			</form>
		</div>

		<!-- ======================================================
		     JSON Export
		     ====================================================== -->
		<div style="flex:1;min-width:280px;max-width:420px;">
			<h2><?php esc_html_e( 'Export Reviews', 'dh-google-reviews' ); ?></h2>

			<p>
				<?php esc_html_e( 'Download all reviews as a JSON file for backup or migration. Includes all meta fields, taxonomy terms, and aggregate statistics.', 'dh-google-reviews' ); ?>
			</p>

			<form method="post"
			      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

				<?php wp_nonce_field( 'dh_reviews_json_export', 'dh_reviews_export_nonce' ); ?>
				<input type="hidden" name="action" value="dh_reviews_json_export" />

				<?php
				$count = wp_count_posts( \DH_Reviews\CPT::POST_TYPE );
				$total = (int) ( $count->publish ?? 0 ) + (int) ( $count->draft ?? 0 );
				?>
				<p>
					<strong>
						<?php
						printf(
							/* translators: %d: total review count */
							esc_html( _n( '%d review in database.', '%d reviews in database.', $total, 'dh-google-reviews' ) ),
							$total
						);
						?>
					</strong>
				</p>

				<?php submit_button( __( 'Export as JSON', 'dh-google-reviews' ), 'secondary', 'dh_reviews_export_submit' ); ?>
			</form>
		</div>

	</div><!-- /flex row -->
</div>
