<?php
/**
 * Template: Aggregate rating bar.
 *
 * Renders the aggregate rating display with business name, overall
 * score, star icons, review count, and optional Google attribution.
 * Overridable in theme at theme/dh-google-reviews/aggregate-bar.php.
 *
 * Available variables:
 *
 * @var array             $aggregate Aggregate data: ratingValue, reviewCount, bestRating, worstRating.
 * @var array             $atts      Shortcode/block attributes (normalised).
 * @var \DH_Reviews\Render $render    Render instance for helpers.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings      = get_option( 'dh_reviews_settings', array() );
$business_name = ! empty( $settings['business_name'] ) ? $settings['business_name'] : '';
$rating_value  = number_format( (float) $aggregate['ratingValue'], 1 );
$review_count  = (int) $aggregate['reviewCount'];
$star_rating   = (int) round( (float) $aggregate['ratingValue'] );
$star_rating   = max( 1, min( 5, $star_rating ) );
?>
<div class="dh-reviews-aggregate">

	<?php if ( $business_name ) : ?>
		<span class="dh-reviews-aggregate__name"><?php echo esc_html( $business_name ); ?></span>
	<?php endif; ?>

	<div class="dh-reviews-aggregate__rating">
		<span class="dh-reviews-aggregate__score"><?php echo esc_html( $rating_value ); ?></span>
		<span class="dh-reviews-aggregate__stars" aria-label="<?php echo esc_attr( $rating_value . ' out of 5 stars' ); ?>">
			<?php echo $render->render_stars( $star_rating ); // SVG built with esc_attr. ?>
		</span>
	</div>

	<span class="dh-reviews-aggregate__count">
		<?php echo esc_html( sprintf( 'Based on %d %s', $review_count, 1 === $review_count ? 'review' : 'reviews' ) ); ?>
	</span>

	<?php if ( $atts['show_google_attribution'] ) : ?>
		<span class="dh-reviews-aggregate__powered">
			powered by
			<?php echo $render->get_google_wordmark(); // SVG markup, no user data. ?>
		</span>
	<?php endif; ?>

</div>
