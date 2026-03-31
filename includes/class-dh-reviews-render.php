<?php
/**
 * Frontend rendering engine.
 *
 * Registers the [dh_reviews] shortcode and the legacy WP Widget.
 * Queries review CPT posts, loads layout templates (grid, slider, list),
 * and handles conditional asset enqueuing.
 * See SPEC.md Section 6 for shortcode attributes, HTML structure,
 * and rendering behaviour.
 *
 * @package DH_Reviews
 */

namespace DH_Reviews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Render
 *
 * Handles shortcode registration, review queries, and template rendering.
 */
class Render {

	/**
	 * Avatar colour palette matching Google defaults.
	 * Index is selected via abs( crc32( $name ) ) % 8.
	 *
	 * @var string[]
	 */
	const AVATAR_COLORS = array(
		'#E67E22',
		'#3498DB',
		'#E74C3C',
		'#2ECC71',
		'#9B59B6',
		'#1ABC9C',
		'#F39C12',
		'#34495E',
	);

	/**
	 * Constructor.
	 *
	 * @param bool $register_hooks Pass false to skip hook registration when
	 *                             using Render purely as a rendering utility.
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		add_shortcode( 'dh_reviews', array( $this, 'render_shortcode' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Bust aggregate transients when review data changes.
		add_action( 'save_post_' . CPT::POST_TYPE, array( $this, 'handle_review_change' ) );
		add_action( 'trashed_post', array( $this, 'handle_review_change' ) );
		add_action( 'before_delete_post', array( $this, 'handle_review_change' ) );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	/**
	 * Render the [dh_reviews] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( $atts = array() ): string {
		$atts = $this->parse_attributes( $atts );

		// Ensure assets load even when wp_enqueue_scripts already fired
		// (e.g. shortcode inside a widget or dynamic block).
		wp_enqueue_style(
			'dh-reviews',
			DH_REVIEWS_URL . 'public/css/dh-reviews.css',
			array(),
			DH_REVIEWS_VERSION
		);
		wp_enqueue_script(
			'dh-reviews',
			DH_REVIEWS_URL . 'public/js/dh-reviews.js',
			array(),
			DH_REVIEWS_VERSION,
			array( 'strategy' => 'defer' )
		);

		do_action( 'dh_reviews_before_render', $atts );

		$reviews = $this->query_reviews( $atts );

		return $this->load_template( $atts['layout'], $reviews, $atts );
	}

	/**
	 * Parse and validate shortcode attributes with defaults.
	 *
	 * Normalises string booleans (true/false/1/0) and clamps integers.
	 *
	 * @param array|string $atts Raw shortcode attributes.
	 * @return array Validated attributes.
	 */
	public function parse_attributes( $atts ): array {
		$defaults = array(
			'count'                   => 5,
			'min_rating'              => 1,
			'layout'                  => 'grid',
			'columns'                 => 3,
			'show_reply'              => true,
			'show_date'               => true,
			'show_photo'              => true,
			'show_stars'              => true,
			'show_aggregate'          => true,
			'schema'                  => true,
			'location'                => '',
			'orderby'                 => 'date',
			'order'                   => 'DESC',
			'excerpt_length'          => 150,
			'show_google_icon'        => true,
			'show_google_attribution' => true,
			'show_cta'                => true,
			'cta_text'                => 'Review Us On Google',
			'show_dots'               => true,
			'visible_cards'           => 3,
			'date_format'             => 'relative',
			'class'                   => '',
		);

		$atts = shortcode_atts( $defaults, $atts, 'dh_reviews' );

		// Normalise boolean string values from shortcode attributes.
		$bool_keys = array(
			'show_reply', 'show_date', 'show_photo', 'show_stars',
			'show_aggregate', 'schema', 'show_google_icon',
			'show_google_attribution', 'show_cta', 'show_dots',
		);
		foreach ( $bool_keys as $key ) {
			if ( is_string( $atts[ $key ] ) ) {
				$atts[ $key ] = in_array( strtolower( $atts[ $key ] ), array( 'true', '1', 'yes', 'on' ), true );
			} else {
				$atts[ $key ] = (bool) $atts[ $key ];
			}
		}

		// Clamp integers.
		$atts['count']          = max( 1, min( 50, (int) $atts['count'] ) );
		$atts['min_rating']     = max( 1, min( 5, (int) $atts['min_rating'] ) );
		$atts['columns']        = max( 1, min( 4, (int) $atts['columns'] ) );
		$atts['excerpt_length'] = max( 0, (int) $atts['excerpt_length'] );
		$atts['visible_cards']  = max( 1, min( 4, (int) $atts['visible_cards'] ) );

		// Validate enum values.
		if ( ! in_array( $atts['layout'], array( 'grid', 'slider', 'list' ), true ) ) {
			$atts['layout'] = 'grid';
		}
		if ( ! in_array( $atts['orderby'], array( 'date', 'rating', 'random' ), true ) ) {
			$atts['orderby'] = 'date';
		}
		$atts['order'] = 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC';
		if ( ! in_array( $atts['date_format'], array( 'relative', 'absolute' ), true ) ) {
			$atts['date_format'] = 'relative';
		}

		return $atts;
	}

	// -------------------------------------------------------------------------
	// Query
	// -------------------------------------------------------------------------

	/**
	 * Query review posts based on shortcode attributes.
	 *
	 * @param array $atts Parsed shortcode attributes.
	 * @return \WP_Post[] Array of review posts.
	 */
	public function query_reviews( array $atts ): array {
		$args = array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $atts['count'],
			'no_found_rows'  => true,
		);

		// Min rating filter via meta query.
		if ( $atts['min_rating'] > 1 ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_dh_star_rating',
					'value'   => $atts['min_rating'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		}

		// Location taxonomy filter.
		if ( ! empty( $atts['location'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $atts['location'] ),
				),
			);
		}

		// Ordering.
		switch ( $atts['orderby'] ) {
			case 'rating':
				$args['meta_key'] = '_dh_star_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = $atts['order'];
				break;
			case 'random':
				$args['orderby'] = 'rand';
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = $atts['order'];
				break;
		}

		$args  = apply_filters( 'dh_reviews_query_args', $args );
		$query = new \WP_Query( $args );

		return $query->posts;
	}

	// -------------------------------------------------------------------------
	// Template loading
	// -------------------------------------------------------------------------

	/**
	 * Load a layout template (grid, slider, or list).
	 *
	 * Checks for theme override at theme/dh-google-reviews/layout-{layout}.php
	 * before falling back to the plugin templates directory.
	 *
	 * @param string     $layout  Layout type: grid, slider, or list.
	 * @param \WP_Post[] $reviews Array of review posts.
	 * @param array      $atts    Parsed shortcode attributes.
	 * @return string Rendered layout HTML.
	 */
	public function load_template( string $layout, array $reviews, array $atts ): string {
		$file_name  = 'layout-' . sanitize_key( $layout ) . '.php';
		$theme_file = locate_template( 'dh-google-reviews/' . $file_name );
		$path       = $theme_file ? $theme_file : DH_REVIEWS_PATH . 'templates/' . $file_name;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		// Allow themes and plugins to inject CSS custom property overrides.
		$css_vars = apply_filters( 'dh_reviews_css_vars', array() );
		$css_block = '';
		if ( ! empty( $css_vars ) && is_array( $css_vars ) ) {
			$props = '';
			foreach ( $css_vars as $prop => $value ) {
				$props .= '--' . sanitize_key( $prop ) . ':' . esc_attr( (string) $value ) . ';';
			}
			if ( $props ) {
				$css_block = '<style>.dh-reviews-wrap{' . $props . '}</style>';
			}
		}

		$render = $this;
		ob_start();
		include $path;
		return $css_block . (string) ob_get_clean();
	}

	/**
	 * Render a single review card.
	 *
	 * @param \WP_Post $review Review post object.
	 * @param array    $atts   Parsed shortcode attributes.
	 * @return string Card HTML.
	 */
	public function render_card( \WP_Post $review, array $atts ): string {
		$theme_file = locate_template( 'dh-google-reviews/review-card.php' );
		$path       = $theme_file ? $theme_file : DH_REVIEWS_PATH . 'templates/review-card.php';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$render = $this;
		ob_start();
		include $path;
		$html = (string) ob_get_clean();

		return apply_filters( 'dh_reviews_card_html', $html, $review, $atts );
	}

	/**
	 * Render the aggregate rating bar.
	 *
	 * @param array $atts Parsed shortcode attributes.
	 * @return string Aggregate bar HTML.
	 */
	public function render_aggregate( array $atts ): string {
		$theme_file = locate_template( 'dh-google-reviews/aggregate-bar.php' );
		$path       = $theme_file ? $theme_file : DH_REVIEWS_PATH . 'templates/aggregate-bar.php';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$aggregate = $this->get_aggregate( $atts['location'] );
		$render    = $this;
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Aggregate
	// -------------------------------------------------------------------------

	/**
	 * Get aggregate rating data for a location.
	 *
	 * Returns from transient if available, otherwise calculates and caches.
	 * Transient key: dh_reviews_aggregate_{location_slug} or dh_reviews_aggregate_all.
	 *
	 * @param string $location_slug Location taxonomy slug, or empty string for all.
	 * @return array Aggregate data: ratingValue, reviewCount, bestRating, worstRating.
	 */
	public function get_aggregate( string $location_slug = '' ): array {
		$cache_key = 'dh_reviews_aggregate_' . ( $location_slug ?: 'all' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$args = array(
			'post_type'      => CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => false,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_dh_star_rating',
					'compare' => 'EXISTS',
				),
			),
		);

		if ( $location_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $location_slug,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$post_ids = $query->posts;
		$total    = 0;
		$count    = 0;

		foreach ( $post_ids as $post_id ) {
			$rating = (int) get_post_meta( $post_id, '_dh_star_rating', true );
			if ( $rating >= 1 && $rating <= 5 ) {
				$total += $rating;
				$count++;
			}
		}

		$aggregate = array(
			'ratingValue' => $count > 0 ? round( $total / $count, 1 ) : 0,
			'reviewCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 1,
		);

		$aggregate = apply_filters( 'dh_reviews_aggregate_data', $aggregate );

		$settings  = get_option( 'dh_reviews_settings', array() );
		$freq_map  = array(
			'6h'  => 6 * HOUR_IN_SECONDS,
			'12h' => 12 * HOUR_IN_SECONDS,
			'24h' => DAY_IN_SECONDS,
		);
		$freq   = $settings['sync_frequency'] ?? '24h';
		$expiry = $freq_map[ $freq ] ?? 12 * HOUR_IN_SECONDS;

		set_transient( $cache_key, $aggregate, $expiry );

		return $aggregate;
	}

	/**
	 * Bust aggregate transients when a review is saved, trashed, or deleted.
	 *
	 * Fires on save_post_dh_review, trashed_post, and before_delete_post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_review_change( int $post_id ): void {
		if ( get_post_type( $post_id ) !== CPT::POST_TYPE ) {
			return;
		}

		delete_transient( 'dh_reviews_aggregate_all' );

		$terms = wp_get_post_terms( $post_id, CPT::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $slug ) {
				delete_transient( 'dh_reviews_aggregate_' . $slug );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Rendering helpers
	// -------------------------------------------------------------------------

	/**
	 * Render star rating SVG icons.
	 *
	 * Filled stars use .dh-star--filled, empty stars use .dh-star--empty.
	 * CSS custom properties control the colours.
	 *
	 * @param int $rating Star rating 1 through 5.
	 * @return string SVG stars HTML.
	 */
	public function render_stars( int $rating ): string {
		$rating = max( 1, min( 5, $rating ) );
		$output = '';
		$path   = 'M10 15.27L16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z';

		for ( $i = 1; $i <= 5; $i++ ) {
			$class   = $i <= $rating ? 'dh-star dh-star--filled' : 'dh-star dh-star--empty';
			$output .= sprintf(
				'<svg class="%s" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="18" height="18"><path d="%s"/></svg>',
				esc_attr( $class ),
				esc_attr( $path )
			);
		}

		return $output;
	}

	/**
	 * Format a review date as relative or absolute.
	 *
	 * Relative thresholds per SPEC.md Section 6.5:
	 * Less than 7 days = "X days ago"
	 * Less than 5 weeks = "X weeks ago"
	 * Less than 12 months = "X months ago"
	 * Otherwise = "X years ago"
	 *
	 * @param string $date        MySQL date string.
	 * @param string $date_format relative or absolute.
	 * @return string Formatted date string.
	 */
	public function format_date( string $date, string $date_format = 'relative' ): string {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return '';
		}

		if ( 'absolute' === $date_format ) {
			return date_i18n( get_option( 'date_format' ), $timestamp );
		}

		// Use current_time('timestamp') so the comparison is in the site's local timezone,
		// matching the local-time string stored in post_date.
		$diff   = current_time( 'timestamp' ) - $timestamp;
		$days   = (int) floor( $diff / DAY_IN_SECONDS );
		$weeks  = (int) floor( $diff / WEEK_IN_SECONDS );
		$months = (int) floor( $diff / MONTH_IN_SECONDS );
		$years  = (int) floor( $diff / YEAR_IN_SECONDS );

		if ( $days < 1 ) {
			return esc_html__( 'today', 'dh-google-reviews' );
		}
		if ( $days < 7 ) {
			/* translators: %d: number of days */
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'dh-google-reviews' ), $days );
		}
		if ( $weeks < 5 ) {
			/* translators: %d: number of weeks */
			return sprintf( _n( '%d week ago', '%d weeks ago', $weeks, 'dh-google-reviews' ), $weeks );
		}
		if ( $months < 12 ) {
			/* translators: %d: number of months */
			return sprintf( _n( '%d month ago', '%d months ago', max( 1, $months ), 'dh-google-reviews' ), max( 1, $months ) );
		}
		/* translators: %d: number of years */
		return sprintf( _n( '%d year ago', '%d years ago', max( 1, $years ), 'dh-google-reviews' ), max( 1, $years ) );
	}

	/**
	 * Get the avatar HTML for a reviewer.
	 *
	 * Shows the reviewer photo when available and show_photo is true.
	 * Falls back to an initial circle using a deterministic colour from the
	 * 8 colour palette, keyed by CRC32 hash of the reviewer name.
	 *
	 * @param int  $post_id    Review post ID.
	 * @param bool $show_photo Whether to attempt showing the photo.
	 * @return string Avatar HTML.
	 */
	public function get_avatar( int $post_id, bool $show_photo = true ): string {
		$name  = (string) get_post_meta( $post_id, '_dh_reviewer_name', true );
		$photo = $show_photo ? (string) get_post_meta( $post_id, '_dh_reviewer_photo', true ) : '';

		if ( $photo ) {
			// Route through local photo proxy if enabled in settings.
			$settings = get_option( 'dh_reviews_settings', array() );
			if ( ! empty( $settings['photo_proxy'] ) ) {
				$photo = add_query_arg( 'url', rawurlencode( $photo ), rest_url( 'dh-reviews/v1/photo/' ) );
			}
			return sprintf(
				'<img class="dh-review-card__photo" src="%s" alt="%s" loading="lazy" width="48" height="48" />',
				esc_url( $photo ),
				esc_attr( $name )
			);
		}

		$initial = $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '?';
		$color   = $this->get_avatar_color( $name );

		return sprintf(
			'<span class="dh-review-card__initial" style="background-color: %s" aria-hidden="true">%s</span>',
			esc_attr( $color ),
			esc_html( $initial )
		);
	}

	/**
	 * Determine the avatar background colour from the reviewer name.
	 *
	 * Uses CRC32 to map any name to one of 8 fixed colours matching
	 * Google's own avatar colour set.
	 *
	 * @param string $name Reviewer name.
	 * @return string Hex colour string.
	 */
	public function get_avatar_color( string $name ): string {
		$index = abs( crc32( $name ) ) % count( self::AVATAR_COLORS );
		return self::AVATAR_COLORS[ $index ];
	}

	/**
	 * Get the inline SVG for the Google G icon.
	 *
	 * @param string $class CSS class applied to the svg element.
	 * @return string SVG markup.
	 */
	public function get_google_g_icon( string $class = 'dh-review-card__google-icon' ): string {
		return sprintf(
			'<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-label="Google review" role="img" width="20" height="20">'
			. '<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>'
			. '<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>'
			. '<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>'
			. '<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>'
			. '</svg>',
			esc_attr( $class )
		);
	}

	/**
	 * Get the Google wordmark SVG for the powered by attribution line.
	 *
	 * @return string SVG markup.
	 */
	public function get_google_wordmark(): string {
		return '<svg class="dh-reviews-google-wordmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 74 24" aria-label="Google" role="img" height="16">'
			. '<path d="M9.24 8.19v2.46h5.88c-.18 1.38-.64 2.39-1.34 3.1-.86.86-2.2 1.8-4.54 1.8-3.62 0-6.45-2.92-6.45-6.54s2.83-6.54 6.45-6.54c1.95 0 3.38.77 4.43 1.76L15.4 2.5C13.94 1.08 11.98 0 9.24 0 4.28 0 .11 4.04.11 9s4.17 9 9.13 9c2.68 0 4.7-.88 6.28-2.52 1.62-1.62 2.13-3.91 2.13-5.75 0-.57-.04-1.1-.13-1.54H9.24z" fill="#4285F4"/>'
			. '<path d="M25 6.19c-3.21 0-5.83 2.44-5.83 5.81 0 3.34 2.62 5.81 5.83 5.81s5.83-2.46 5.83-5.81c0-3.37-2.62-5.81-5.83-5.81zm0 9.33c-1.76 0-3.28-1.45-3.28-3.52 0-2.09 1.52-3.52 3.28-3.52s3.28 1.43 3.28 3.52c0 2.07-1.52 3.52-3.28 3.52z" fill="#EA4335"/>'
			. '<path d="M38 6.19c-3.21 0-5.83 2.44-5.83 5.81 0 3.34 2.62 5.81 5.83 5.81s5.83-2.46 5.83-5.81c0-3.37-2.62-5.81-5.83-5.81zm0 9.33c-1.76 0-3.28-1.45-3.28-3.52 0-2.09 1.52-3.52 3.28-3.52s3.28 1.43 3.28 3.52c0 2.07-1.52 3.52-3.28 3.52z" fill="#FBBC05"/>'
			. '<path d="M53.58 7.49h-.09c-.57-.68-1.67-1.3-3.06-1.3C47.53 6.19 45 8.72 45 12c0 3.26 2.53 5.81 5.43 5.81 1.39 0 2.49-.62 3.06-1.32h.09v.81c0 2.22-1.19 3.41-3.1 3.41-1.56 0-2.53-1.13-2.93-2.08l-2.22.92c.64 1.54 2.33 3.46 5.15 3.46 2.99 0 5.52-1.76 5.52-6.05V6.49h-2.42v1zm-2.93 8.03c-1.76 0-3.1-1.5-3.1-3.52 0-2.05 1.34-3.52 3.1-3.52 1.74 0 3.1 1.5 3.1 3.54-.01 2.03-1.36 3.5-3.1 3.5z" fill="#4285F4"/>'
			. '<path d="M58 .24h2.51v17.57H58z" fill="#34A853"/>'
			. '<path d="M68.26 15.52c-1.3 0-2.22-.59-2.82-1.76l7.77-3.21-.26-.66c-.48-1.3-1.96-3.7-4.97-3.7-2.99 0-5.48 2.35-5.48 5.81 0 3.26 2.46 5.81 5.76 5.81 2.66 0 4.2-1.63 4.84-2.57l-1.98-1.32c-.66.96-1.56 1.6-2.86 1.6zm-.18-7.15c1.03 0 1.91.53 2.2 1.28l-5.25 2.17c0-2.44 1.73-3.45 3.05-3.45z" fill="#EA4335"/>'
			. '</svg>';
	}

	// -------------------------------------------------------------------------
	// Widget
	// -------------------------------------------------------------------------

	/**
	 * Register the classic WP Widget.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		register_widget( __NAMESPACE__ . '\\Reviews_Widget' );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Conditionally enqueue frontend CSS and JS.
	 *
	 * Only loads on singular pages/posts where the shortcode or block is present.
	 * The render_shortcode method also enqueues directly as a fallback for
	 * dynamic content (widgets, block patterns, etc.).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		global $post;

		if ( ! is_singular() || ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		if (
			! has_shortcode( $post->post_content, 'dh_reviews' ) &&
			! has_block( 'dh/google-reviews', $post )
		) {
			return;
		}

		wp_enqueue_style(
			'dh-reviews',
			DH_REVIEWS_URL . 'public/css/dh-reviews.css',
			array(),
			DH_REVIEWS_VERSION
		);

		wp_enqueue_script(
			'dh-reviews',
			DH_REVIEWS_URL . 'public/js/dh-reviews.js',
			array(),
			DH_REVIEWS_VERSION,
			array( 'strategy' => 'defer' )
		);
	}
}

/**
 * Class Reviews_Widget
 *
 * Classic WP Widget for sidebar display of reviews.
 * Simplified config: count, min_rating, show_stars, show_aggregate.
 * Always renders in list layout.
 * See SPEC.md Section 6.3.
 */
class Reviews_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'dh_reviews_widget',
			'Google Reviews',
			array( 'description' => 'Display Google Business Profile reviews in a sidebar widget area.' )
		);
	}

	/**
	 * Front end output.
	 *
	 * @param array $args     Widget display arguments.
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$count          = max( 1, (int) ( $instance['count'] ?? 3 ) );
		$min_rating     = max( 1, min( 5, (int) ( $instance['min_rating'] ?? 1 ) ) );
		$show_stars     = ! empty( $instance['show_stars'] ) ? 'true' : 'false';
		$show_aggregate = ! empty( $instance['show_aggregate'] ) ? 'true' : 'false';

		echo do_shortcode( sprintf(
			'[dh_reviews layout="list" count="%d" min_rating="%d" show_stars="%s" show_aggregate="%s"]',
			$count,
			$min_rating,
			esc_attr( $show_stars ),
			esc_attr( $show_aggregate )
		) );

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back end form.
	 *
	 * @param array $instance Current widget settings.
	 * @return void
	 */
	public function form( $instance ): void {
		$title          = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$count          = ! empty( $instance['count'] ) ? (int) $instance['count'] : 3;
		$min_rating     = ! empty( $instance['min_rating'] ) ? (int) $instance['min_rating'] : 1;
		$show_stars     = isset( $instance['show_stars'] ) ? (bool) $instance['show_stars'] : true;
		$show_aggregate = isset( $instance['show_aggregate'] ) ? (bool) $instance['show_aggregate'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title:</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>">Number of Reviews:</label>
			<input class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				type="number"
				value="<?php echo esc_attr( $count ); ?>"
				min="1"
				max="20" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'min_rating' ) ); ?>">Minimum Rating:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'min_rating' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'min_rating' ) ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $min_rating, $i ); ?>>
						<?php echo esc_html( $i ); ?> Star<?php echo esc_html( $i > 1 ? 's' : '' ); ?>+
					</option>
				<?php endfor; ?>
			</select>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_stars' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_stars' ) ); ?>"
				value="1"
				<?php checked( $show_stars ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_stars' ) ); ?>">Show Star Ratings</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_aggregate' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_aggregate' ) ); ?>"
				value="1"
				<?php checked( $show_aggregate ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_aggregate' ) ); ?>">Show Aggregate Rating</label>
		</p>
		<?php
	}

	/**
	 * Sanitize widget settings on save.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Previous settings.
	 * @return array Sanitised settings.
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance                   = array();
		$instance['title']          = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['count']          = max( 1, min( 20, (int) ( $new_instance['count'] ?? 3 ) ) );
		$instance['min_rating']     = max( 1, min( 5, (int) ( $new_instance['min_rating'] ?? 1 ) ) );
		$instance['show_stars']     = ! empty( $new_instance['show_stars'] );
		$instance['show_aggregate'] = ! empty( $new_instance['show_aggregate'] );
		return $instance;
	}
}
