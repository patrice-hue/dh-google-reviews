<?php
/**
 * Template: Aggregate rating bar.
 *
 * Renders the aggregate rating display with business name, overall
 * score, star icons, review count, and optional Google attribution.
 * Overridable in theme at theme/dh-google-reviews/aggregate-bar.php.
 *
 * Available variables:
 * @var array                  $aggregate Aggregate data (rating_value, review_count).
 * @var array                  $atts      Shortcode/block attributes.
 * @var \DH_Reviews\Render     $renderer  Render class instance.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$business_name = $renderer->get_business_name();
$rating_value  = $aggregate['rating_value'];
$review_count  = $aggregate['review_count'];
$full_stars    = (int) floor( $rating_value );
?>
<div class="dh-reviews-aggregate">
	<?php if ( ! empty( $business_name ) ) : ?>
		<span class="dh-reviews-aggregate__name"><?php echo esc_html( $business_name ); ?></span>
	<?php endif; ?>
	<div class="dh-reviews-aggregate__rating">
		<span class="dh-reviews-aggregate__score"><?php echo esc_html( number_format( $rating_value, 1 ) ); ?></span>
		<span class="dh-reviews-aggregate__stars" aria-label="<?php echo esc_attr( $rating_value . ' out of 5 stars' ); ?>">
			<?php echo $renderer->render_stars( $full_stars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</span>
	</div>
	<span class="dh-reviews-aggregate__count">
		<?php
		/* translators: %d: number of reviews */
		echo esc_html( sprintf( _n( 'Based on %d review', 'Based on %d reviews', $review_count, 'dh-google-reviews' ), $review_count ) );
		?>
	</span>
	<?php if ( $atts['show_google_attribution'] ) : ?>
		<span class="dh-reviews-aggregate__powered">
			<?php echo esc_html( 'powered by' ); ?>
			<?php echo $renderer->get_google_wordmark_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</span>
	<?php endif; ?>
</div>
