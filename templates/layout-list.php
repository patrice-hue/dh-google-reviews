<?php
/**
 * Template: List layout wrapper.
 *
 * Renders reviews in a vertical stacked list.
 * Used as the default layout for the sidebar widget.
 * Overridable in theme at theme/dh-google-reviews/layout-list.php.
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

$wrapper_classes = 'dh-reviews-wrap dh-reviews--list';
if ( ! empty( $atts['class'] ) ) {
	$wrapper_classes .= ' ' . $atts['class'];
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>">

	<?php if ( $atts['show_aggregate'] ) : ?>
		<?php echo $renderer->render_aggregate( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template. ?>
	<?php endif; ?>

	<div class="dh-reviews-cards">
		<?php foreach ( $reviews as $review ) : ?>
			<?php echo $renderer->render_card( $review, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template. ?>
		<?php endforeach; ?>
	</div>

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
