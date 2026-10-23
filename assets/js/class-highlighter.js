/**
 * Atomora - Class Highlighter
 *
 * Adds a "Highlight on page" item to the three-dots dropdown menu on
 * class chips in the Atomic Element panel, under a "Atomora"
 * section header. Menu item and header are cloned from real native
 * MUI elements so styling and hover behavior match Elementor exactly.
 *
 * @package Atomora
 * @since 1.0.0
 */

( function () {
	'use strict';

	var STYLE_ID      = 'atp-class-highlight';
	var ITEM_CLASS    = 'atp-highlight-menu-item';
	var HEADER_CLASS  = 'atp-menu-section-header';
	var DIVIDER_CLASS = 'atp-menu-section-divider';
	var SECTION_LABEL = "Atomora";
	var activeClass = null;

	// ── Chip label detection ──────────────────────────────────────

	function getChipLabel( chip ) {
		var labelEl = chip.querySelector( '[class*="MuiChip-label"] div, [class*="MuiChip-label"]' );
		return labelEl ? labelEl.textContent.trim() : '';
	}

	function findActiveChipLabel( menuList ) {
		var popup = menuList.closest( '[id^="eui-popup"]' );
		if ( ! popup ) return null;

		var trigger = document.querySelector( '[aria-controls="' + popup.id + '"]' );
		if ( ! trigger ) return null;

		// Primary: read from [role="group"][aria-label="Edit button-soft"]
		var group = trigger.closest( '[role="group"]' );
		if ( group ) {
			var ariaLabel = group.getAttribute( 'aria-label' ) || '';
			var match = ariaLabel.match( /^Edit\s+(.+)$/ );
			if ( match ) return match[ 1 ];
		}

		// Fallback: previous sibling chip label
		var el = trigger.previousElementSibling;
		while ( el ) {
			if ( el.className && typeof el.className === 'string' && el.className.indexOf( 'MuiChip' ) > -1 ) {
				var label = getChipLabel( el );
				if ( label ) return label;
			}
			el = el.previousElementSibling;
		}

		return null;
	}

	// ── Iframe highlight logic ────────────────────────────────────

	function getIframeDoc() {
		var iframe = document.getElementById( 'elementor-preview-iframe' );
		if ( ! iframe ) return null;
		return iframe.contentDocument || ( iframe.contentWindow && iframe.contentWindow.document );
	}

	function clearHighlights() {
		var doc = getIframeDoc();
		if ( ! doc ) return;
		var el = doc.getElementById( STYLE_ID );
		if ( el && el.parentNode ) el.parentNode.removeChild( el );
		activeClass = null;
	}

	function highlightClass( className ) {
		clearHighlights();
		var doc = getIframeDoc();
		if ( ! doc || ! doc.head ) return;

		var style       = doc.createElement( 'style' );
		style.id        = STYLE_ID;
		style.textContent =
			'.' + className + ' { ' +
			'outline: 2px solid #7C3AED !important; ' +
			'outline-offset: 2px !important; ' +
			'box-shadow: 0 0 8px 2px rgba(124,58,237,0.35), 0 0 20px 4px rgba(124,58,237,0.15) !important; ' +
			'}';
		doc.head.appendChild( style );
		activeClass = className;
	}

	// ── Menu helpers ──────────────────────────────────────────────

	function isClassChipMenu( menuList ) {
		var headers = menuList.querySelectorAll( '[class*="MuiListSubheader"]' );
		for ( var i = 0; i < headers.length; i++ ) {
			if ( headers[ i ].textContent.trim().toLowerCase() === 'class' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Locate the "States" subheader so we can insert our own section
	 * right above it — keeping our item in the same visual band as
	 * Rename/Duplicate/Remove but under our own branded header.
	 */
	function findStatesHeader( menuList ) {
		var headers = menuList.querySelectorAll( '[class*="MuiListSubheader"]' );
		for ( var i = 0; i < headers.length; i++ ) {
			if ( headers[ i ].textContent.trim().toLowerCase() === 'states' ) {
				return headers[ i ];
			}
		}
		return null;
	}

	/**
	 * Find a plain, non-selected, non-disabled native menu item to clone.
	 * Cloning a real item guarantees pixel-perfect style and hover parity.
	 */
	function findReferenceMenuItem( menuList ) {
		var items = menuList.querySelectorAll( '[role="menuitem"]' );
		for ( var i = 0; i < items.length; i++ ) {
			var it = items[ i ];
			if ( it.getAttribute( 'aria-disabled' ) === 'true' ) continue;
			if ( it.className && it.className.indexOf( 'Mui-selected' ) > -1 ) continue;
			return it;
		}
		return items[ 0 ] || null;
	}

	/**
	 * Clone a native menu item, swap its text, strip any inherited
	 * icons/secondary text/selected/focus states.
	 */
	function cloneMenuItemAs( reference, text, extraClass ) {
		var li = reference.cloneNode( true );

		// Clean state classes from the clone.
		if ( li.className ) {
			li.className = li.className
				.replace( /\s*Mui-focusVisible/g, '' )
				.replace( /\s*Mui-selected/g, '' )
				.replace( /\s*Mui-disabled/g, '' )
				.trim();
		}
		if ( extraClass ) li.classList.add( extraClass );
		li.setAttribute( 'tabindex', '-1' );
		li.removeAttribute( 'aria-selected' );
		li.removeAttribute( 'aria-disabled' );

		// Drop any icons or secondary text the source item may have carried.
		var junk = li.querySelectorAll(
			'[class*="MuiListItemIcon"],' +
			'[class*="MuiListItemText-secondary"],' +
			'[class*="MuiSvgIcon"],' +
			'svg'
		);
		junk.forEach( function ( el ) {
			if ( el.parentNode ) el.parentNode.removeChild( el );
		} );

		// Swap the primary text.
		var primary = li.querySelector( '[class*="MuiListItemText-primary"]' )
			|| li.querySelector( '[class*="MuiTypography"]' );
		if ( primary ) {
			primary.textContent = text;
		} else {
			li.textContent = text;
		}

		return li;
	}

	/**
	 * Clone the "States" subheader to build our own section header
	 * with matching class list and structure.
	 */
	function cloneHeaderAs( reference, text, extraClass ) {
		var li = reference.cloneNode( false );
		if ( extraClass ) li.classList.add( extraClass );
		li.textContent = text;
		return li;
	}

	/**
	 * Find any existing MUI divider in the menu list so we can clone it —
	 * Elementor drops one between each section (e.g. between Remove and States).
	 * Cloning guarantees the divider matches whatever styles Elementor uses.
	 */
	function findReferenceDivider( menuList ) {
		return menuList.querySelector( '[class*="MuiDivider"]' );
	}

	function cloneDivider( reference, extraClass ) {
		var el = reference.cloneNode( false );
		if ( extraClass ) el.classList.add( extraClass );
		return el;
	}

	/**
	 * Close the MUI dropdown by clicking its backdrop.
	 * This closes only the popover — NOT the Elementor panel.
	 */
	function closeMenu() {
		var backdrop = document.querySelector( '.MuiBackdrop-root, [class*="MuiBackdrop"]' );
		if ( backdrop ) {
			backdrop.click();
		}
	}

	// ── Injection ─────────────────────────────────────────────────

	function injectMenuItem( menuList ) {
		if ( menuList.querySelector( '.' + ITEM_CLASS ) ) return;
		if ( ! isClassChipMenu( menuList ) ) return;

		var className = findActiveChipLabel( menuList );
		if ( ! className ) return;

		var referenceItem = findReferenceMenuItem( menuList );
		if ( ! referenceItem ) return;

		var isActive = ( activeClass === className );
		var label    = isActive ? 'Remove highlight' : 'Highlight on page';
		var item     = cloneMenuItemAs( referenceItem, label, ITEM_CLASS );

		item.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			e.preventDefault();
			if ( isActive ) {
				clearHighlights();
			} else {
				highlightClass( className );
			}
			closeMenu();
		} );

		var statesHeader = findStatesHeader( menuList );
		if ( statesHeader ) {
			// Insert branded header + item + matching divider just above the States section.
			var header  = cloneHeaderAs( statesHeader, SECTION_LABEL, HEADER_CLASS );
			menuList.insertBefore( header, statesHeader );
			menuList.insertBefore( item, statesHeader );

			var refDivider = findReferenceDivider( menuList );
			if ( refDivider ) {
				var divider = cloneDivider( refDivider, DIVIDER_CLASS );
				menuList.insertBefore( divider, statesHeader );
			}
		} else {
			menuList.appendChild( item );
		}
	}

	// ── Observer ──────────────────────────────────────────────────

	var observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( mutation ) {
			mutation.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) return;
				var menuList = null;
				if ( node.getAttribute && node.getAttribute( 'role' ) === 'menu' ) {
					menuList = node;
				} else if ( node.querySelector ) {
					menuList = node.querySelector( '[role="menu"]' );
				}
				if ( menuList ) {
					setTimeout( function () { injectMenuItem( menuList ); }, 50 );
				}
			} );
		} );
	} );

	observer.observe( document.body, { childList: true } );
} )();