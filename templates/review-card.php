<?php
/**
 * Template: Single review card.
 *
 * Renders a single review card with avatar, name, date, star rating,
 * review body with excerpt truncation, and optional owner reply.
 * Overridable in theme at theme/dh-google-reviews/review-card.php.
 *
 * Available variables:
 *
 * @var \WP_Post          $review Review post object.
 * @var array             $atts   Shortcode/block attributes (normalised).
 * @var \DH_Reviews\Render $render Render instance for helpers.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 * @see     SPEC.md Section 10 for template override instructions
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reviewer_name  = (string) get_post_meta( $review->ID, '_dh_reviewer_name', true );
$star_rating    = max( 1, min( 5, (int) get_post_meta( $review->ID, '_dh_star_rating', true ) ) );
$owner_reply    = (string) get_post_meta( $review->ID, '_dh_owner_reply', true );
$reply_date     = (string) get_post_meta( $review->ID, '_dh_reply_date', true );
$review_date    = $review->post_date;
$review_body    = get_the_content( null, false, $review );

$formatted_date = $render->format_date( $review_date, $atts['date_format'] );
$datetime_attr  = gmdate( 'Y-m-d', (int) strtotime( $review_date ) );

// Excerpt truncation.
$excerpt_length = $atts['excerpt_length'];
$plain_body     = wp_strip_all_tags( $review_body );
$needs_truncate = $excerpt_length > 0 && mb_strlen( $plain_body ) > $excerpt_length;
$excerpt        = '';

if ( $needs_truncate ) {
	$excerpt    = mb_substr( $plain_body, 0, $excerpt_length );
	$last_space = mb_strrpos( $excerpt, ' ' );
	if ( false !== $last_space ) {
		$excerpt = mb_substr( $excerpt, 0, $last_space );
	}
	$excerpt .= '...';
}
?>
<div class="dh-review-card">

	<div class="dh-review-card__header">

		<div class="dh-review-card__avatar">
			<?php echo $render->get_avatar( $review->ID, $atts['show_photo'] ); // Avatar HTML built with esc_url/esc_attr/esc_html. ?>
		</div>

		<?php if ( $atts['show_google_icon'] ) : ?>
			<?php echo $render->get_google_g_icon(); // SVG markup built with esc_attr. ?>
		<?php endif; ?>

		<div class="dh-review-card__meta">
			<span class="dh-review-card__name"><?php echo esc_html( $reviewer_name ); ?></span>
			<?php if ( $atts['show_date'] && $formatted_date ) : ?>
				<time class="dh-review-card__date" datetime="<?php echo esc_attr( $datetime_attr ); ?>">
					<?php echo esc_html( $formatted_date ); ?>
				</time>
			<?php endif; ?>
		</div>

	</div>

	<?php if ( $atts['show_stars'] ) : ?>
		<span class="dh-review-card__stars" aria-label="<?php echo esc_attr( $star_rating . ' out of 5 stars' ); ?>">
			<?php echo $render->render_stars( $star_rating ); // SVG built with esc_attr. ?>
		</span>
	<?php endif; ?>

	<div class="dh-review-card__body">
		<?php if ( $needs_truncate ) : ?>
			<p class="dh-review-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<p class="dh-review-card__full" hidden><?php echo wp_kses_post( $review_body ); ?></p>
			<button class="dh-review-card__read-more" type="button" aria-expanded="false">Read more</button>
		<?php else : ?>
			<?php echo wp_kses_post( $review_body ); ?>
		<?php endif; ?>
	</div>

	<?php if ( $atts['show_reply'] && $owner_reply ) : ?>
		<div class="dh-review-card__reply">
			<div class="dh-review-card__reply-header">
				<span class="dh-review-card__reply-label">Response from the owner</span>
				<?php if ( $reply_date ) : ?>
					<time class="dh-review-card__reply-date">
						<?php echo esc_html( $render->format_date( $reply_date, $atts['date_format'] ) ); ?>
					</time>
				<?php endif; ?>
			</div>
			<p><?php echo wp_kses_post( $owner_reply ); ?></p>
		</div>
	<?php endif; ?>

</div>
