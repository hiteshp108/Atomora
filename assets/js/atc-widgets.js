/**
 * Atomora - Add to Cart / Buy Now / Quantity / Variations (WooCom family)
 *
 * All widgets in this family are fully independent (no shared parent
 * container). They coordinate purely by matching data-product-id: a
 * button, quantity control, or variation selector looks elsewhere on
 * the SAME page for others sharing the same product ID and reads their
 * live values — no nesting requirement.
 *
 * Posts directly to WooCommerce's own core AJAX endpoint
 * (?wc-ajax=add_to_cart), using the exact field contract verified
 * against WooCommerce's own source: WC_AJAX::add_to_cart() never reads
 * a separate 'variation_id' field — it expects the VARIATION's own ID
 * sent AS 'product_id' directly, detecting "this is a variation" by
 * checking wc_get_product(product_id)->get_type().
 *
 * @package Atomora
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	ready( function () {
		initVariationForms();
		initVariationButtonWidgets();
		initButtons();
		initQuantitySteppers();
		document.addEventListener( 'input', onQtyChange );
		document.addEventListener( 'change', onQtyChange );
	} );

	// ── Variations: initialize WooCommerce's own matching plugin ──────
	// WooCommerce's auto-init only binds forms inside its own expected
	// template structure (.summary) — it never finds ours placed
	// elsewhere, so each Variations form is bound explicitly.

	function initVariationForms() {
		if ( ! window.jQuery || typeof window.jQuery.fn.wc_variation_form === 'undefined' ) {
			return;
		}

		document.querySelectorAll( '.atomora-atc-variations' ).forEach( function ( form ) {
			var productId = form.getAttribute( 'data-product-id' );
			var variationIdInput = form.querySelector( 'input.variation_id' );

			window.jQuery( form ).wc_variation_form();
			window.jQuery( form )
				.on( 'found_variation', function ( event, variation ) {
					if ( variationIdInput ) {
						variationIdInput.value = variation.variation_id;
					}
					updatePriceFor( productId, variation );
					refreshButtonsFor( productId );
				} )
				.on( 'reset_data hide_variation', function () {
					if ( variationIdInput ) {
						variationIdInput.value = 0;
					}
					updatePriceFor( productId, null );
					refreshButtonsFor( productId );
				} );
		} );
	}

	// ── Product Price widget ───────────────────────────────────────
	// Unit price comes from the server (simple products) or the selected
	// variation. With quantity > 1 and a known unit price, the total is
	// computed; otherwise WooCommerce's own price HTML is shown.

	function formatMoney( n ) {
		var f = window.atomoraPrice || { symbol: '', format: '%1$s%2$s', decimals: 2, decimalSep: '.', thousandSep: ',' };
		var parts = Math.abs( n ).toFixed( f.decimals ).split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, f.thousandSep );
		var num = parts.join( f.decimalSep );
		var out = f.format.replace( '%1$s', '<span class="woocommerce-Price-currencySymbol">' + f.symbol + '</span>' ).replace( '%2$s', num );
		return '<span class="woocommerce-Price-amount amount"><bdi>' + out + '</bdi></span>';
	}

	function renderPrice( el ) {
		var amt = el.querySelector( '.atomora-atc-price__amount' );
		if ( ! amt ) {
			return;
		}
		if ( undefined === el._defaultHtml ) {
			el._defaultHtml = amt.innerHTML;
			var up = parseFloat( el.getAttribute( 'data-unit-price' ) );
			if ( ! isNaN( up ) ) {
				el._unit = { price: up, regular: parseFloat( el.getAttribute( 'data-regular-price' ) ) || up };
			}
		}
		var qty = '1' === el.getAttribute( 'data-multiply' ) ? getQuantityFor( el.getAttribute( 'data-product-id' ) ) : 1;
		var unit = el._unit;

		if ( ! unit || qty <= 1 ) {
			amt.innerHTML = el._variationHtml || el._defaultHtml;
			return;
		}
		var html = unit.regular > unit.price
			? '<del>' + formatMoney( unit.regular * qty ) + '</del> <ins>' + formatMoney( unit.price * qty ) + '</ins>'
			: formatMoney( unit.price * qty );
		amt.innerHTML = '<span class="price">' + html + '</span>';
	}

	// variation: the found_variation object, or null on reset.
	function updatePriceFor( productId, variation ) {
		document.querySelectorAll( '.atomora-atc-price[data-product-id="' + productId + '"]' ).forEach( function ( el ) {
			if ( undefined === el._defaultHtml ) {
				renderPrice( el );
			}
			if ( variation && variation.display_price !== undefined ) {
				el._unit = {
					price: parseFloat( variation.display_price ),
					regular: parseFloat( variation.display_regular_price ) || parseFloat( variation.display_price )
				};
				el._variationHtml = variation.price_html || '';
			} else {
				el._unit = null;
				el._variationHtml = '';
			}
			renderPrice( el );
		} );
	}

	function refreshPricesFor( productId ) {
		document.querySelectorAll( '.atomora-atc-price[data-product-id="' + productId + '"]' ).forEach( renderPrice );
	}

	function onQtyChange( e ) {
		var t = e.target;
		if ( t && t.classList && t.classList.contains( 'atomora-atc-qty-input' ) ) {
			refreshPricesFor( t.getAttribute( 'data-product-id' ) );
		}
	}

	function refreshButtonsFor( productId ) {
		document.querySelectorAll( '[data-product-id="' + productId + '"][data-atc-action]' ).forEach( updateButtonState );
	}

	// ── Variation: Buttons — transform a real <select> into custom
	// button/pill options, without touching WooCommerce's own matching.
	// The select stays in the DOM (visually hidden, not display:none)
	// so WooCommerce's delegated 'change' listener on '.variations
	// select' keeps working exactly as with a plain dropdown — verified
	// directly against WooCommerce's add-to-cart-variation.js.

	function initVariationButtonWidgets() {
		document.querySelectorAll( '[data-atc-variation-style="buttons"]' ).forEach( function ( form ) {
			form.querySelectorAll( '.variations select' ).forEach( function ( select ) {
				buildButtonRowFor( select );
			} );
		} );
	}

	function buildButtonRowFor( select ) {
		var wrap = select.closest( '.atomora-atc-option-control' );
		if ( ! wrap || wrap.querySelector( '.atomora-atc-variation-buttons-row' ) ) {
			return;
		}

		select.classList.add( 'atomora-atc-visually-hidden' );

		var row = document.createElement( 'div' );
		row.className = 'atomora-atc-variation-buttons-row';

		Array.prototype.forEach.call( select.options, function ( opt ) {
			if ( ! opt.value ) {
				return; // Skip the "Choose an option" placeholder.
			}

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'atomora-atc-variation-btn';
			btn.textContent = opt.textContent;
			btn.setAttribute( 'data-value', opt.value );
			if ( select.value === opt.value ) {
				btn.classList.add( 'is-selected' );
			}

			btn.addEventListener( 'click', function () {
				select.value = opt.value;
				row.querySelectorAll( '.atomora-atc-variation-btn' ).forEach( function ( b ) {
					b.classList.remove( 'is-selected' );
				} );
				btn.classList.add( 'is-selected' );

				if ( window.jQuery ) {
					window.jQuery( select ).trigger( 'change' );
				} else {
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );

			row.appendChild( btn );
		} );

		wrap.appendChild( row );
	}

	// ── Buttons (Add to Cart / Buy Now) ────────────────────────────

	function initButtons() {
		document.querySelectorAll( '[data-atc-action]' ).forEach( function ( btn ) {
			updateButtonState( btn );
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleClick( btn );
			} );
		} );
	}

	function findVariationInputFor( productId ) {
		var form = document.querySelector( '.atomora-atc-variations[data-product-id="' + productId + '"]' );
		return form ? form.querySelector( 'input.variation_id' ) : null;
	}

	function findQuantityInputFor( productId ) {
		return document.querySelector( '.atomora-atc-qty-input[data-product-id="' + productId + '"]' );
	}

	function isButtonReady( btn ) {
		if ( btn.getAttribute( 'data-is-variable' ) !== '1' ) {
			return true;
		}
		var variationInput = findVariationInputFor( btn.getAttribute( 'data-product-id' ) );
		// No Variations widget present for a variable product: nothing
		// can ever be selected, so stay disabled rather than silently
		// adding the parent (unpurchasable) product.
		if ( ! variationInput ) {
			return false;
		}
		return parseInt( variationInput.value, 10 ) > 0;
	}

	function updateButtonState( btn ) {
		btn.disabled = ! isButtonReady( btn );
	}

	function getQuantityFor( productId ) {
		var input = findQuantityInputFor( productId );
		if ( ! input ) {
			return 1;
		}
		var v = parseFloat( input.value );
		return isNaN( v ) || v < 1 ? 1 : v;
	}

	// Toast for the success case only — see conversation: a message
	// inserted right after the button has no reliable width to wrap in,
	// and looks cramped regardless of button size. A fixed-position toast
	// sidesteps that, and is also the pattern WooCommerce shoppers already
	// expect. Error messages (e.g. "select options") stay inline near the
	// button via showMessageNear() below, since those need context right
	// where the problem is, not a generic corner notification.
	var toastEl = null;
	var toastTimer = null;

	function hideToast() {
		if ( toastEl ) {
			toastEl.classList.remove( 'is-visible' );
		}
		if ( toastTimer ) {
			window.clearTimeout( toastTimer );
			toastTimer = null;
		}
	}

	function showAddedToCartToast( text, cartUrl, viewCartText ) {
		if ( ! toastEl ) {
			toastEl = document.createElement( 'div' );
			toastEl.className = 'atomora-atc-toast';
			toastEl.setAttribute( 'role', 'status' );
			toastEl.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( toastEl );
		}

		toastEl.innerHTML = '';

		var textEl = document.createElement( 'span' );
		textEl.className = 'atomora-atc-toast__text';
		textEl.textContent = text;
		toastEl.appendChild( textEl );

		if ( cartUrl ) {
			var link = document.createElement( 'a' );
			link.className = 'atomora-atc-toast__link';
			link.href = cartUrl;
			link.textContent = viewCartText || 'View cart';
			toastEl.appendChild( link );
		}

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'atomora-atc-toast__close';
		closeBtn.setAttribute( 'aria-label', 'Dismiss' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', hideToast );
		toastEl.appendChild( closeBtn );

		toastEl.classList.add( 'is-visible' );

		if ( toastTimer ) {
			window.clearTimeout( toastTimer );
		}
		toastTimer = window.setTimeout( hideToast, 5000 );
	}

	function showMessageNear( btn, text, type ) {
		var existing = btn.parentElement ? btn.parentElement.querySelector( '.atomora-atc-message' ) : null;
		var box = existing;
		if ( ! box ) {
			box = document.createElement( 'p' );
			box.className = 'atomora-atc-message';
			btn.insertAdjacentElement( 'afterend', box );
		}
		box.textContent = text;
		box.className = 'atomora-atc-message is-' + type;
		box.hidden = false;
	}

	function handleClick( btn ) {
		var productId          = btn.getAttribute( 'data-product-id' );
		var isVariable          = btn.getAttribute( 'data-is-variable' ) === '1';
		var redirectToCheckout = btn.getAttribute( 'data-atc-action' ) === 'buy';

		if ( ! productId || ! window.atomoraAtc || ! window.atomoraAtc.ajaxUrl ) {
			showMessageNear( btn, window.atomoraAtc ? window.atomoraAtc.errorText : 'Something went wrong.', 'error' );
			return;
		}

		if ( isVariable && ! isButtonReady( btn ) ) {
			showMessageNear( btn, atomoraAtc.selectOptionsText, 'error' );
			return;
		}

		var variationInput = isVariable ? findVariationInputFor( productId ) : null;

		// WC_AJAX::add_to_cart() expects the variation's own ID sent AS
		// product_id when adding a variation — see file docblock.
		var idToSend = ( isVariable && variationInput && variationInput.value && '0' !== variationInput.value )
			? variationInput.value
			: productId;

		var formData = new FormData();
		formData.append( 'product_id', idToSend );
		formData.append( 'quantity', getQuantityFor( productId ) );

		if ( isVariable ) {
			var form = document.querySelector( '.atomora-atc-variations[data-product-id="' + productId + '"]' );
			if ( form ) {
				form.querySelectorAll( 'select[name^="attribute_"]' ).forEach( function ( sel ) {
					formData.append( sel.name, sel.value );
				} );
			}
		}

		btn.classList.add( 'is-loading' );
		btn.disabled = true;

		fetch( atomoraAtc.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				btn.classList.remove( 'is-loading' );
				updateButtonState( btn );

				if ( ! data || data.error ) {
					showMessageNear( btn, ( data && data.error_message ) || atomoraAtc.errorText, 'error' );
					if ( data && data.product_url ) {
						window.location.href = data.product_url;
					}
					return;
				}

				if ( redirectToCheckout ) {
					window.location.href = atomoraAtc.checkoutUrl;
					return;
				}

				showAddedToCartToast( atomoraAtc.addedText, btn.getAttribute( 'data-show-view-cart' ) === '0' ? '' : atomoraAtc.cartUrl, atomoraAtc.viewCartText );

				if ( data.fragments ) {
					Object.keys( data.fragments ).forEach( function ( selector ) {
						document.querySelectorAll( selector ).forEach( function ( el ) {
							el.outerHTML = data.fragments[ selector ];
						} );
					} );
				}

				document.body.dispatchEvent( new CustomEvent( 'added_to_cart', { detail: data } ) );
			} )
			.catch( function () {
				btn.classList.remove( 'is-loading' );
				updateButtonState( btn );
				showMessageNear( btn, atomoraAtc.errorText, 'error' );
			} );
	}

	// ── Quantity: Plus / Minus (standalone widgets) ────────────────
	// No shared wrapper anymore — matched purely by data-product-id,
	// same convention as everything else in this family.

	function initQuantitySteppers() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-atc-qty-action]' );
			if ( ! btn ) {
				return;
			}

			var input = findQuantityInputFor( btn.getAttribute( 'data-product-id' ) );
			if ( ! input ) {
				return;
			}

			var min  = parseFloat( input.min ) || 1;
			var max  = input.max !== '' ? parseFloat( input.max ) : null;
			var step = parseFloat( input.step ) || 1;
			var v    = parseFloat( input.value ) || min;

			v = 'minus' === btn.getAttribute( 'data-atc-qty-action' ) ? v - step : v + step;
			if ( v < min ) {
				v = min;
			}
			if ( null !== max && v > max ) {
				v = max;
			}
			input.value = v;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	}
} )();