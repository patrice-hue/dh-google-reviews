/**
 * DH Google Reviews - Frontend Scripts
 *
 * Handles slider scroll snap navigation (prev/next arrows),
 * dot pagination sync via IntersectionObserver, and the
 * "Read more" text toggle for long reviews.
 * No jQuery dependency. Supports multiple instances per page.
 *
 * @package DH_Reviews
 * @version 1.0.0
 * @see     SPEC.md Sections 6.5 and 6.6
 */

( function () {
	'use strict';

	/**
	 * Initialise all slider instances on the page.
	 */
	function initSliders() {
		document.querySelectorAll( '.dh-reviews--slider' ).forEach( function ( wrap ) {
			var track   = wrap.querySelector( '.dh-reviews-cards' );
			var prev    = wrap.querySelector( '.dh-reviews-slider__prev' );
			var next    = wrap.querySelector( '.dh-reviews-slider__next' );
			var dots    = Array.from( wrap.querySelectorAll( '.dh-reviews-dots__dot' ) );
			var cards   = track ? Array.from( track.querySelectorAll( '.dh-review-card' ) ) : [];
			var visible = parseInt( wrap.dataset.visible, 10 ) || 3;

			if ( ! track ) {
				return;
			}

			// When there are fewer cards than visible_cards, centre them instead of
			// bunching them to the left.  CSS targets .dh-reviews--underflow.
			if ( cards.length > 0 && cards.length < visible ) {
				wrap.classList.add( 'dh-reviews--underflow' );
			}

			// Prev / next buttons scroll by one full viewport of the track.
			if ( prev ) {
				prev.addEventListener( 'click', function () {
					track.scrollBy( { left: -track.clientWidth, behavior: 'smooth' } );
				} );
			}

			if ( next ) {
				next.addEventListener( 'click', function () {
					track.scrollBy( { left: track.clientWidth, behavior: 'smooth' } );
				} );
			}

			// Dot pagination.
			if ( dots.length && cards.length ) {
				// Observe the first card of every page (every Nth card).
				var leaders = cards.filter( function ( _, i ) {
					return i % visible === 0;
				} );

				var observer = new IntersectionObserver(
					function ( entries ) {
						entries.forEach( function ( entry ) {
							if ( ! entry.isIntersecting ) {
								return;
							}
							var pageIndex = Math.floor( cards.indexOf( entry.target ) / visible );
							dots.forEach( function ( dot, i ) {
								var active = i === pageIndex;
								dot.classList.toggle( 'dh-reviews-dots__dot--active', active );
								dot.setAttribute( 'aria-selected', active ? 'true' : 'false' );
							} );
						} );
					},
					{ root: track, threshold: 0.5 }
				);

				leaders.forEach( function ( card ) {
					observer.observe( card );
				} );

				// Clicking a dot scrolls to that page.
				dots.forEach( function ( dot, i ) {
					dot.addEventListener( 'click', function () {
						track.scrollTo( { left: i * track.clientWidth, behavior: 'smooth' } );
					} );
				} );
			}
		} );
	}

	/**
	 * Initialise "Read more" toggles for truncated review bodies.
	 */
	function initReadMore() {
		document.querySelectorAll( '.dh-review-card__read-more' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var body = btn.closest( '.dh-review-card__body, .dh-review-card__reply' );
				if ( ! body ) {
					return;
				}

				var excerpt  = body.querySelector( '.dh-review-card__excerpt' );
				var full     = body.querySelector( '.dh-review-card__full' );
				var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';

				if ( excerpt ) {
					excerpt.hidden = ! expanded;
				}
				if ( full ) {
					full.hidden = expanded;
				}

				btn.textContent = expanded ? 'Read more' : 'Read less';
				btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			} );
		} );
	}

	// Boot after DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initSliders();
			initReadMore();
		} );
	} else {
		initSliders();
		initReadMore();
	}

}() );
