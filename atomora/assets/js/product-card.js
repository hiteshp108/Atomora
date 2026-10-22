/**
 * Atomora — Product Card frontend behaviour.
 * Vanilla JS for wishlist + countdown (no dependencies). Quick view's
 * Add to Cart form re-init uses jQuery only because WooCommerce's own
 * variation-form script is itself a jQuery plugin — there's no vanilla-JS
 * way to hook into it without reimplementing WooCommerce's variation
 * logic, which is exactly what calling its real template is meant to avoid.
 */
( function () {
	'use strict';

	var WISHLIST_KEY = 'atomora_wishlist';

	// ------------------------------------------------------------------
	// Wishlist — client-side only (localStorage). No server sync between
	// devices/browsers; this is a deliberate scope choice to avoid adding
	// a user-meta AJAX round trip for something purely cosmetic. If real
	// cross-device wishlist sync is ever needed, that's a separate,
	// larger feature (account storage + AJAX), not a CSS/JS-only add-on.
	// ------------------------------------------------------------------
	function getWishlist() {
		try {
			var raw = window.localStorage.getItem( WISHLIST_KEY );
			var parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	function saveWishlist( ids ) {
		try {
			window.localStorage.setItem( WISHLIST_KEY, JSON.stringify( ids ) );
		} catch ( e ) {
			// Storage unavailable (private browsing, quota, etc.) — the
			// heart still toggles visually for this page view, it just
			// won't persist. Not worth surfacing an error for.
		}
	}

	function initWishlistButton( button ) {
		var productId = button.getAttribute( 'data-product-id' );
		if ( ! productId ) {
			return;
		}

		var wishlist = getWishlist();
		if ( wishlist.indexOf( productId ) !== -1 ) {
			button.setAttribute( 'aria-pressed', 'true' );
		}

		button.addEventListener( 'click', function () {
			var current = getWishlist();
			var index = current.indexOf( productId );
			var isNowActive;

			if ( index === -1 ) {
				current.push( productId );
				isNowActive = true;
			} else {
				current.splice( index, 1 );
				isNowActive = false;
			}

			saveWishlist( current );
			button.setAttribute( 'aria-pressed', isNowActive ? 'true' : 'false' );
		} );
	}

	// ------------------------------------------------------------------
	// Sale countdown
	// ------------------------------------------------------------------
	function initCountdown( el ) {
		var target = parseInt( el.getAttribute( 'data-countdown-target' ), 10 );
		if ( ! target ) {
			return;
		}

		function render() {
			var diff = target - Date.now();

			if ( diff <= 0 ) {
				el.textContent = '';
				window.clearInterval( timer );
				return;
			}

			var days = Math.floor( diff / 86400000 );
			var hours = Math.floor( ( diff % 86400000 ) / 3600000 );
			var minutes = Math.floor( ( diff % 3600000 ) / 60000 );
			var seconds = Math.floor( ( diff % 60000 ) / 1000 );

			var parts = [];
			if ( days > 0 ) {
				parts.push( days + 'd' );
			}
			parts.push( ( hours < 10 ? '0' : '' ) + hours + 'h' );
			parts.push( ( minutes < 10 ? '0' : '' ) + minutes + 'm' );
			parts.push( ( seconds < 10 ? '0' : '' ) + seconds + 's' );

			el.textContent = parts.join( ' ' );
		}

		render();
		var timer = window.setInterval( render, 1000 );
	}

	// ------------------------------------------------------------------
	// Quick view — one shared modal for the whole page, reused by every
	// card, rather than one modal per card.
	// ------------------------------------------------------------------
	var quickViewModal = null;

	function ensureQuickViewModal() {
		if ( quickViewModal ) {
			return quickViewModal;
		}

		var modal = document.createElement( 'div' );
		modal.className = 'atomora-quick-view';
		modal.hidden = true;
		modal.innerHTML =
			'<div class="atomora-quick-view__dialog" role="dialog" aria-modal="true">' +
				'<button type="button" class="atomora-quick-view__close" aria-label="Close">&times;</button>' +
				'<div class="atomora-quick-view__body"></div>' +
			'</div>';

		document.body.appendChild( modal );

		var closeBtn = modal.querySelector( '.atomora-quick-view__close' );
		closeBtn.addEventListener( 'click', function () {
			closeQuickView();
		} );

		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				closeQuickView();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! modal.hidden ) {
				closeQuickView();
			}
		} );

		quickViewModal = modal;
		return modal;
	}

	function openQuickView() {
		var modal = ensureQuickViewModal();
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function closeQuickView() {
		if ( ! quickViewModal ) {
			return;
		}
		quickViewModal.hidden = true;
		document.body.style.overflow = '';
	}

	function loadQuickView( productId ) {
		var modal = ensureQuickViewModal();
		var body = modal.querySelector( '.atomora-quick-view__body' );
		body.innerHTML = '<p class="atomora-quick-view__loading">Loading…</p>';
		openQuickView();

		if ( 'undefined' === typeof window.atomoraProductCard ) {
			body.innerHTML = '<p class="atomora-quick-view__error">Quick view is not available right now.</p>';
			return;
		}

		var formData = new window.FormData();
		formData.append( 'action', 'atomora_quick_view' );
		formData.append( 'product_id', productId );
		formData.append( 'nonce', window.atomoraProductCard.nonce );

		window.fetch( window.atomoraProductCard.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success || ! result.data || ! result.data.html ) {
					var message = ( result && result.data && result.data.message ) || 'This product could not be loaded.';
					body.innerHTML = '<p class="atomora-quick-view__error"></p>';
					body.querySelector( '.atomora-quick-view__error' ).textContent = message;
					return;
				}

				body.innerHTML = result.data.html;

				// WooCommerce's variation-form.js is a jQuery plugin bound
				// on page load; since this form was just injected via
				// AJAX, it needs to be initialized manually the same way
				// WooCommerce itself does on a real product page.
				if ( window.jQuery ) {
					var $form = window.jQuery( body ).find( '.variations_form' );
					if ( $form.length && typeof $form.wc_variation_form === 'function' ) {
						$form.wc_variation_form();
					}
				}
			} )
			.catch( function () {
				body.innerHTML = '<p class="atomora-quick-view__error">This product could not be loaded. Please try again.</p>';
			} );
	}

	// ------------------------------------------------------------------
	// Init
	// ------------------------------------------------------------------
	function initCard( card ) {
		if ( card.dataset.atomoraPcInit === '1' ) {
			return;
		}
		card.dataset.atomoraPcInit = '1';

		var wishlistBtn = card.querySelector( '.atomora-product-card__wishlist' );
		if ( wishlistBtn ) {
			initWishlistButton( wishlistBtn );
		}

		var quickViewBtn = card.querySelector( '.atomora-product-card__quick-view' );
		if ( quickViewBtn ) {
			quickViewBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				loadQuickView( quickViewBtn.getAttribute( 'data-product-id' ) );
			} );
		}

		var countdownEl = card.querySelector( '.atomora-product-card__countdown' );
		if ( countdownEl ) {
			initCountdown( countdownEl );
		}
	}

	function initLoopWarning( scope ) {
		if ( window.atomoraLoopWarned || ! scope.querySelector( '[data-atomora-loop-warning]' ) ) {
			return;
		}
		window.atomoraLoopWarned = true;

		var modal = document.createElement( 'div' );
		modal.className = 'atomora-loop-warning-modal';
		modal.setAttribute( 'role', 'alertdialog' );

		var box = document.createElement( 'div' );
		box.className = 'atomora-loop-warning-modal__box';

		var title = document.createElement( 'strong' );
		title.textContent = 'Product Card can\u2019t be used here';

		var text = document.createElement( 'p' );
		text.textContent = 'Placed inside a Loop within a Single template, it corrupts the page, so it is not rendered. Move it outside the loop or into a Loop Item template.';

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.textContent = 'Got it';
		btn.addEventListener( 'click', function () {
			modal.remove();
		} );

		box.appendChild( title );
		box.appendChild( text );
		box.appendChild( btn );
		modal.appendChild( box );
		document.body.appendChild( modal );
	}

	function initAll( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		scope.querySelectorAll( '.atomora-product-card' ).forEach( initCard );
		initLoopWarning( scope );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
			initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
		} );
	}

	document.addEventListener( 'atomora/refresh', function ( e ) {
		initAll( e.detail && e.detail.context ? e.detail.context : document );
	} );
} )();