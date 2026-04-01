<?php
/**
 * Template: List layout wrapper.
 *
 * Renders reviews in a vertical stacked list.
 * Used as the default layout for the sidebar widget.
 * Overridable in theme at theme/dh-google-reviews/layout-list.php.
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

$wrapper_class = 'dh-reviews-wrap dh-reviews--list dh-reviews--aggregate-top';
if ( ! empty( $atts['class'] ) ) {
	$wrapper_class .= ' ' . sanitize_html_class( $atts['class'] );
}
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>"
	data-columns="1"
	data-visible="1">

	<?php if ( $atts['show_aggregate'] ) : ?>
		<?php echo $render->render_aggregate( $atts ); ?>
	<?php endif; ?>

	<?php if ( ! empty( $reviews ) ) : ?>
		<div class="dh-reviews-cards">
			<?php foreach ( $reviews as $review ) : ?>
				<?php echo $render->render_card( $review, $atts ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="dh-reviews-empty">No reviews found.</p>
	<?php endif; ?>

	<?php if ( $atts['show_cta'] && $cta_url ) : ?>
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
