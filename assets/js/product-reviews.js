/* Atomora — Reviews List: "Load more" and numbered pagination (admin-ajax). */
( function () {
	'use strict';

	var cfg = window.atomoraReviews;
	if ( ! cfg ) {
		return;
	}

	function fetchPage( root, page ) {
		var body = new URLSearchParams();
		body.set( 'action', 'atomora_load_reviews' );
		body.set( 'nonce', cfg.nonce );
		body.set( 'product_id', root.getAttribute( 'data-product-id' ) || '0' );
		body.set( 'doc_id', root.getAttribute( 'data-doc-id' ) || '0' );
		body.set( 'element_id', root.getAttribute( 'data-id' ) || '' );
		body.set( 'page', String( page ) );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function setActive( root, page ) {
		root.setAttribute( 'data-page', String( page ) );
		root.querySelectorAll( '.atomora-reviews__page' ).forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', Number( btn.getAttribute( 'data-page' ) ) === page );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var more = e.target.closest( '.atomora-reviews__more, [data-atomora-load-more]' );
		var num  = e.target.closest( '.atomora-reviews__page' );
		var btn  = more || num;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();

		var root  = btn.closest( '[data-atomora-reviews-list]' );
		var items = root && root.querySelector( '.atomora-reviews-items' );
		if ( ! root || ! items || btn.hasAttribute( 'data-loading' ) ) {
			return;
		}

		var current = Number( root.getAttribute( 'data-page' ) ) || 1;
		var target  = more ? current + 1 : Number( btn.getAttribute( 'data-page' ) ) || 1;
		if ( ! more && target === current ) {
			return;
		}

		btn.setAttribute( 'data-loading', '1' );
		fetchPage( root, target ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				return;
			}
			var data = res.data;
			if ( more ) {
				items.insertAdjacentHTML( 'beforeend', data.html );
				root.setAttribute( 'data-page', String( data.page ) );
				if ( data.page >= data.total ) {
					( btn.closest( '.atomora-review' ) || btn ).remove();
				}
			} else {
				items.innerHTML = data.html;
				setActive( root, data.page );
			}
		} ).catch( function () {} ).then( function () {
			btn.removeAttribute( 'data-loading' );
		} );
	} );
}() );