<?php
/**
 * Template: Single review card.
 *
 * Renders a single review card with avatar, name, date, star rating,
 * review body, and optional owner reply. Overridable in theme at
 * theme/dh-google-reviews/review-card.php.
 *
 * Available variables:
 * @var WP_Post               $review   Review post object.
 * @var array                  $atts     Shortcode/block attributes.
 * @var \DH_Reviews\Render     $renderer Render class instance.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 * @see     SPEC.md Section 10 for template override instructions
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reviewer_name = get_post_meta( $review->ID, '_dh_reviewer_name', true );
if ( empty( $reviewer_name ) ) {
	$reviewer_name = get_the_title( $review->ID );
}
$star_rating = (int) get_post_meta( $review->ID, '_dh_star_rating', true );
$star_rating = max( 1, min( 5, $star_rating ) );
$owner_reply = get_post_meta( $review->ID, '_dh_owner_reply', true );
$reply_date  = get_post_meta( $review->ID, '_dh_reply_date', true );
$review_date = $review->post_date;
?>
<div class="dh-review-card">
	<div class="dh-review-card__header">
		<div class="dh-review-card__avatar">
			<?php echo $renderer->get_avatar( $review, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_avatar(). ?>
		</div>
		<?php if ( $atts['show_google_icon'] ) : ?>
			<?php echo $renderer->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<?php endif; ?>
		<div class="dh-review-card__meta">
			<span class="dh-review-card__name"><?php echo esc_html( $reviewer_name ); ?></span>
			<?php if ( $atts['show_date'] ) : ?>
				<time class="dh-review-card__date" datetime="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $review_date ) ) ); ?>">
					<?php echo esc_html( $renderer->format_date( $review_date, $atts['date_format'] ) ); ?>
				</time>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $atts['show_stars'] ) : ?>
		<span class="dh-review-card__stars" aria-label="<?php echo esc_attr( $star_rating . ' out of 5 stars' ); ?>">
			<?php echo $renderer->render_stars( $star_rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</span>
	<?php endif; ?>
	<div class="dh-review-card__body">
		<?php echo $renderer->get_review_body( $review, $atts['excerpt_length'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_review_body(). ?>
	</div>
	<?php if ( $atts['show_reply'] && ! empty( $owner_reply ) ) : ?>
		<div class="dh-review-card__reply">
			<div class="dh-review-card__reply-header">
				<span class="dh-review-card__reply-label"><?php echo esc_html( 'Response from the owner' ); ?></span>
				<?php if ( ! empty( $reply_date ) ) : ?>
					<time class="dh-review-card__reply-date" datetime="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $reply_date ) ) ); ?>">
						<?php echo esc_html( $renderer->format_date( $reply_date, $atts['date_format'] ) ); ?>
					</time>
				<?php endif; ?>
			</div>
			<p><?php echo wp_kses_post( $owner_reply ); ?></p>
		</div>
	<?php endif; ?>
</div>
