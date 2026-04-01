<?php
/**
 * Template: Slider layout wrapper.
 *
 * Renders reviews in a horizontal CSS scroll snap slider with
 * prev/next arrow buttons, dot pagination, and CTA button.
 * Overridable in theme at theme/dh-google-reviews/layout-slider.php.
 *
 * Available variables:
 *
 * @var \WP_Post[]         $reviews Array of review post objects.
 * @var array              $atts    Shortcode/block attributes (normalised).
 * @var \DH_Reviews\Render $render  Render instance for helpers.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings  = get_option( 'dh_reviews_settings', array() );
$place_id  = ! empty( $settings['google_place_id'] ) ? $settings['google_place_id'] : '';
$cta_url   = ! empty( $settings['cta_url_override'] ) ? $settings['cta_url_override'] : '';
if ( ! $cta_url && $place_id ) {
	$cta_url = 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place_id );
}
if ( ! $cta_url ) {
	$cta_url = '#';
}

$total_cards = count( $reviews );
$visible     = max( 1, (int) $atts['visible_cards'] );
$dot_count   = (int) ceil( $total_cards / $visible );

$wrapper_class = 'dh-reviews-wrap dh-reviews--slider';
if ( ! empty( $atts['class'] ) ) {
	$wrapper_class .= ' ' . sanitize_html_class( $atts['class'] );
}
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>"
	data-columns="<?php echo esc_attr( $atts['columns'] ); ?>"
	data-visible="<?php echo esc_attr( $visible ); ?>">

	<div class="dh-reviews-slider-layout">

		<?php if ( $atts['show_aggregate'] ) : ?>
			<div class="dh-reviews-slider-layout__sidebar">
				<?php echo $render->render_aggregate( $atts ); ?>
			</div>
		<?php endif; ?>

		<div class="dh-reviews-slider-main">

	<div class="dh-reviews-slider">

		<button class="dh-reviews-slider__prev" aria-label="Previous reviews" type="button">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
				<path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z" fill="currentColor"/>
			</svg>
		</button>

		<div class="dh-reviews-cards">
			<?php if ( ! empty( $reviews ) ) : ?>
				<?php foreach ( $reviews as $review ) : ?>
					<?php echo $render->render_card( $review, $atts ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="dh-reviews-empty">No reviews found.</p>
			<?php endif; ?>
		</div>

		<button class="dh-reviews-slider__next" aria-label="Next reviews" type="button">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
				<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z" fill="currentColor"/>
			</svg>
		</button>

	</div>

	<?php if ( $atts['show_dots'] && $dot_count > 1 ) : ?>
		<div class="dh-reviews-dots" role="tablist" aria-label="Review pages">
			<?php for ( $i = 0; $i < $dot_count; $i++ ) : ?>
				<button
					class="dh-reviews-dots__dot<?php echo 0 === $i ? ' dh-reviews-dots__dot--active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( 'Page ' . ( $i + 1 ) ); ?>"
					type="button"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

		</div><!-- .dh-reviews-slider-main -->

	</div><!-- .dh-reviews-slider-layout -->

	<?php if ( $atts['show_cta'] ) : ?>
		<div class="dh-reviews-cta">
			<a class="dh-reviews-cta__button"
				href="<?php echo esc_url( $cta_url ); ?>"
				target="_blank"
				rel="noopener noreferrer">
				<?php echo $render->get_google_g_icon( 'dh-reviews-cta__google-icon' ); ?>
				<span><?php echo esc_html( $atts['cta_text'] ); ?></span>
			</a>
		</div>
	<?php endif; ?>

</div>
