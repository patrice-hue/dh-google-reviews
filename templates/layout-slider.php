<?php
/**
 * Template: Slider layout wrapper.
 *
 * Renders reviews in a horizontal scroll snap slider with
 * prev/next arrows and dot pagination.
 * Overridable in theme at theme/dh-google-reviews/layout-slider.php.
 *
 * Available variables:
 * @var WP_Post[]              $reviews  Array of review post objects.
 * @var array                  $atts     Shortcode/block attributes.
 * @var \DH_Reviews\Render     $renderer Render class instance.
 *
 * @package DH_Reviews
 * @see     SPEC.md Section 6.4 for HTML structure
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_classes = 'dh-reviews-wrap dh-reviews--slider';
if ( ! empty( $atts['class'] ) ) {
	$wrapper_classes .= ' ' . $atts['class'];
}

$total_cards   = count( $reviews );
$visible_cards = $atts['visible_cards'];
$total_pages   = max( 1, (int) ceil( $total_cards / $visible_cards ) );
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>" data-visible="<?php echo esc_attr( $visible_cards ); ?>">

	<?php if ( $atts['show_aggregate'] ) : ?>
		<?php echo $renderer->render_aggregate( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template. ?>
	<?php endif; ?>

	<div class="dh-reviews-slider">

		<button class="dh-reviews-slider__prev" aria-label="<?php echo esc_attr( 'Previous reviews' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
		</button>

		<div class="dh-reviews-cards">
			<?php foreach ( $reviews as $review ) : ?>
				<?php echo $renderer->render_card( $review, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template. ?>
			<?php endforeach; ?>
		</div>

		<button class="dh-reviews-slider__next" aria-label="<?php echo esc_attr( 'Next reviews' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
		</button>

	</div>

	<?php if ( $atts['show_dots'] && $total_pages > 1 ) : ?>
		<div class="dh-reviews-dots" role="tablist" aria-label="<?php echo esc_attr( 'Review pages' ); ?>">
			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
				<button class="dh-reviews-dots__dot<?php echo 1 === $i ? ' dh-reviews-dots__dot--active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo esc_attr( 1 === $i ? 'true' : 'false' ); ?>"
					aria-label="<?php echo esc_attr( 'Page ' . $i ); ?>"
					data-page="<?php echo esc_attr( $i ); ?>"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

	<?php
	if ( $atts['show_cta'] ) :
		$cta_url = $renderer->get_cta_url();
		if ( ! empty( $cta_url ) ) :
			?>
			<div class="dh-reviews-cta">
				<a class="dh-reviews-cta__button" href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo $renderer->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $atts['cta_text'] ); ?></span>
				</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>

</div>
