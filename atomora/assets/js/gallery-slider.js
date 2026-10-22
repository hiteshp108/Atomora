/**
 * Atomora — Gallery Slider frontend behaviour.
 * Vanilla JS, no dependencies, cache-plugin friendly (nothing here is
 * server-rendered per-request; all state comes from data-* attributes
 * already baked into the cached HTML).
 */
( function () {
	'use strict';

	function initSlider( root ) {
		if ( root.dataset.atomoraInit === '1' ) {
			return; // Already wired up (e.g. Elementor editor re-render).
		}
		root.dataset.atomoraInit = '1';

		var slides = Array.prototype.slice.call( root.querySelectorAll( '.atomora-gallery-slider__slide' ) );
		var thumbs = Array.prototype.slice.call( root.querySelectorAll( '.atomora-gallery-slider__thumb' ) );
		var prevBtn = root.querySelector( '.atomora-gallery-slider__arrow--prev' );
		var nextBtn = root.querySelector( '.atomora-gallery-slider__arrow--next' );
		var lightbox = root.querySelector( '.atomora-gallery-slider__lightbox' );
		var lightboxImg = lightbox ? lightbox.querySelector( '.atomora-gallery-slider__lightbox-image' ) : null;
		var lightboxClose = lightbox ? lightbox.querySelector( '.atomora-gallery-slider__lightbox-close' ) : null;
		var lightboxPrevBtn = lightbox ? lightbox.querySelector( '.atomora-gallery-slider__lightbox-arrow--prev' ) : null;
		var lightboxNextBtn = lightbox ? lightbox.querySelector( '.atomora-gallery-slider__lightbox-arrow--next' ) : null;

		// CONFIRMED bug: the lightbox was rendered as a child of this
		// widget's own wrapper. `position: fixed` + a very high z-index
		// only wins against OTHER elements within the same stacking
		// context — if any ancestor (an Elementor section with a sticky
		// effect, a transform, etc.) creates its own stacking context,
		// the lightbox is trapped inside it regardless of its z-index
		// value, and anything outside that ancestor with its own stacking
		// order (a sticky header, for instance) can still render above
		// it. Moving the lightbox to be a direct child of <body> — the
		// standard "portal" pattern every modal/lightbox library uses —
		// removes it from any ancestor's stacking context entirely,
		// fixing this at the actual cause rather than just raising the
		// z-index further (which wouldn't have helped).
		if ( lightbox && lightbox.parentElement !== document.body ) {
			document.body.appendChild( lightbox );
		}

		if ( slides.length < 2 && ! root.dataset.lightbox ) {
			return; // Single static image, nothing to wire up.
		}

		var loop = '1' === root.getAttribute( 'data-loop' );
		var autoplay = '1' === root.getAttribute( 'data-autoplay' );
		var autoplaySpeed = parseInt( root.getAttribute( 'data-autoplay-speed' ), 10 ) || 4000;
		var lightboxEnabled = '1' === root.getAttribute( 'data-lightbox' );
		var current = 0;
		var timer = null;

		function goTo( index ) {
			if ( ! slides.length ) {
				return;
			}
			if ( index < 0 ) {
				index = loop ? slides.length - 1 : 0;
			}
			if ( index >= slides.length ) {
				index = loop ? 0 : slides.length - 1;
			}

			slides[ current ].classList.remove( 'is-active' );
			if ( thumbs[ current ] ) {
				thumbs[ current ].classList.remove( 'is-active' );
			}

			current = index;

			slides[ current ].classList.add( 'is-active' );
			if ( thumbs[ current ] ) {
				thumbs[ current ].classList.add( 'is-active' );
			}
		}

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				goTo( parseInt( thumb.getAttribute( 'data-index' ), 10 ) || 0 );
				resetAutoplay();
			} );
		} );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				goTo( current - 1 );
				resetAutoplay();
			} );
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				goTo( current + 1 );
				resetAutoplay();
			} );
		}

		// Basic touch swipe support.
		var touchStartX = null;
		var mainEl = root.querySelector( '.atomora-gallery-slider__main' );
		if ( mainEl ) {
			mainEl.addEventListener( 'touchstart', function ( e ) {
				touchStartX = e.touches[ 0 ].clientX;
			}, { passive: true } );

			mainEl.addEventListener( 'touchend', function ( e ) {
				if ( null === touchStartX ) {
					return;
				}
				var delta = e.changedTouches[ 0 ].clientX - touchStartX;
				if ( Math.abs( delta ) > 40 ) {
					goTo( delta > 0 ? current - 1 : current + 1 );
					resetAutoplay();
				}
				touchStartX = null;
			}, { passive: true } );
		}

		// Height sync for Left/Right thumbnail layouts.
		//
		// CSS alone can't do this: capping .thumbs' height to match .main
		// would need a percentage max-height, but percentages only resolve
		// against an ancestor with an explicit height — and this widget's
		// wrapper is intentionally auto-height (so it works at any ratio).
		// Without a real measurement, the browser instead lets whichever
		// element has more natural content (usually the thumbnail list)
		// dictate the row's height, which both distorts .main away from
		// its own aspect-ratio and lets .thumbs overhang below it.
		//
		// This measures .main's actual rendered height and applies it to
		// .thumbs directly, so .main stays a correct ratio box and .thumbs
		// is capped to exactly match it (scrolling internally beyond that
		// via the existing overflow-y: auto). Only applies in row layout
		// (Left/Right); Bottom/Hidden modes clear the inline override and
		// let the normal horizontal-strip CSS handle sizing.
		var thumbsEl = root.querySelector( '.atomora-gallery-slider__thumbs' );
		if ( mainEl && thumbsEl ) {
			var syncThumbsHeight = function () {
				var isRowLayout = 'row' === window.getComputedStyle( root ).flexDirection;
				if ( isRowLayout ) {
					thumbsEl.style.maxHeight = mainEl.getBoundingClientRect().height + 'px';
				} else {
					thumbsEl.style.maxHeight = '';
				}
			};

			if ( 'undefined' !== typeof ResizeObserver ) {
				new ResizeObserver( syncThumbsHeight ).observe( mainEl );
			} else {
				// Fallback for browsers without ResizeObserver: re-sync on
				// window resize, which covers breakpoint/orientation
				// changes even if it misses some other resize causes.
				window.addEventListener( 'resize', syncThumbsHeight );
			}

			syncThumbsHeight();
		}

		function startAutoplay() {
			if ( ! autoplay || slides.length < 2 ) {
				return;
			}
			timer = window.setInterval( function () {
				goTo( current + 1 );
			}, autoplaySpeed );
		}

		function stopAutoplay() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function resetAutoplay() {
			stopAutoplay();
			startAutoplay();
		}

		if ( autoplay ) {
			root.addEventListener( 'mouseenter', stopAutoplay );
			root.addEventListener( 'mouseleave', startAutoplay );
			startAutoplay();
		}

		// Lightbox.
		if ( lightboxEnabled && lightbox && lightboxImg ) {
			// One lightbox-capable image per slide, in slide order — lets
			// prev/next inside the lightbox work from a plain index
			// instead of re-deriving position from whichever thumbnail
			// was clicked.
			var lightboxSources = slides.map( function ( slide ) {
				var img = slide.querySelector( '[data-lightbox-src]' );
				return img
					? { src: img.getAttribute( 'data-lightbox-src' ), alt: img.getAttribute( 'alt' ) || '' }
					: null;
			} );
			var lightboxIndex = 0;

			function showInLightbox( index ) {
				if ( index < 0 ) {
					index = loop ? lightboxSources.length - 1 : 0;
				}
				if ( index >= lightboxSources.length ) {
					index = loop ? 0 : lightboxSources.length - 1;
				}
				var entry = lightboxSources[ index ];
				if ( ! entry ) {
					return;
				}
				lightboxIndex = index;
				lightboxImg.src = entry.src;
				lightboxImg.alt = entry.alt;
				// Keeps the main slider on the same image, so closing the
				// lightbox doesn't leave you back where you started if
				// you navigated while it was open.
				goTo( index );
			}

			function openLightbox( index ) {
				showInLightbox( index );
				lightbox.hidden = false;
				stopAutoplay();
			}

			function closeLightbox() {
				lightbox.hidden = true;
				lightboxImg.src = '';
				resetAutoplay();
			}

			root.querySelectorAll( '[data-lightbox-src]' ).forEach( function ( img, index ) {
				img.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					openLightbox( index );
				} );
			} );

			if ( lightboxClose ) {
				lightboxClose.addEventListener( 'click', closeLightbox );
			}
			if ( lightboxPrevBtn ) {
				lightboxPrevBtn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					showInLightbox( lightboxIndex - 1 );
				} );
			}
			if ( lightboxNextBtn ) {
				lightboxNextBtn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					showInLightbox( lightboxIndex + 1 );
				} );
			}
			lightbox.addEventListener( 'click', function ( e ) {
				if ( e.target === lightbox ) {
					closeLightbox();
				}
			} );
			lightboxImg.addEventListener( 'click', closeLightbox );

			document.addEventListener( 'keydown', function ( e ) {
				if ( lightbox.hidden ) {
					return;
				}
				if ( 'Escape' === e.key ) {
					closeLightbox();
				} else if ( 'ArrowLeft' === e.key ) {
					showInLightbox( lightboxIndex - 1 );
				} else if ( 'ArrowRight' === e.key ) {
					showInLightbox( lightboxIndex + 1 );
				}
			} );

			// Swipe support inside the open lightbox — previously only the
			// main (non-lightbox) slider view had this.
			var lightboxTouchStartX = null;
			lightboxImg.addEventListener( 'touchstart', function ( e ) {
				lightboxTouchStartX = e.touches[ 0 ].clientX;
			}, { passive: true } );
			lightboxImg.addEventListener( 'touchend', function ( e ) {
				if ( null === lightboxTouchStartX ) {
					return;
				}
				var delta = e.changedTouches[ 0 ].clientX - lightboxTouchStartX;
				if ( Math.abs( delta ) > 40 ) {
					showInLightbox( delta > 0 ? lightboxIndex - 1 : lightboxIndex + 1 );
				}
				lightboxTouchStartX = null;
			}, { passive: true } );
		}

		// Hover zoom (WooCommerce-style): scales the active slide's image
		// up, with the zoom's focal point following the cursor, via CSS
		// custom properties the stylesheet reads (see gallery-slider.css).
		// Scoped to .main so it only ever affects the currently-visible
		// image, not every slide stacked underneath it.
		if ( '1' === root.getAttribute( 'data-hover-zoom' ) && mainEl ) {
			var zoomScale = parseFloat( root.getAttribute( 'data-hover-zoom-scale' ) ) || 1.8;
			root.style.setProperty( '--atomora-zoom-scale', zoomScale );

			mainEl.addEventListener( 'mousemove', function ( e ) {
				var activeImg = mainEl.querySelector( '.atomora-gallery-slider__slide.is-active .atomora-gallery-slider__image' );
				if ( ! activeImg ) {
					return;
				}
				var rect = activeImg.getBoundingClientRect();
				var x = ( ( e.clientX - rect.left ) / rect.width ) * 100;
				var y = ( ( e.clientY - rect.top ) / rect.height ) * 100;
				root.style.setProperty( '--atomora-zoom-x', Math.max( 0, Math.min( 100, x ) ) + '%' );
				root.style.setProperty( '--atomora-zoom-y', Math.max( 0, Math.min( 100, y ) ) + '%' );
			} );
		}
	}

	function initAll( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		scope.querySelectorAll( '.atomora-gallery-slider' ).forEach( initSlider );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}

	// Re-init when Elementor (re)renders widgets in the editor preview,
	// and when content is swapped in via AJAX/popups on the frontend.
	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
			initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
		} );
	}

	document.addEventListener( 'atomora/refresh', function ( e ) {
		initAll( e.detail && e.detail.context ? e.detail.context : document );
	} );
} )();