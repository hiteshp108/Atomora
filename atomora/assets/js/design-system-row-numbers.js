/**
 * Atomora — Design System Row Numbers (EXPERIMENTAL)
 *
 * Adds a small "Sr. No." badge next to each row's drag handle in
 * Elementor's Design System panel (both the Classes and Variables tabs),
 * showing its current position in the list. Purely cosmetic — it never
 * reads or writes any Elementor state, only overlays a number based on
 * each row's live DOM position, so it stays correct through drags,
 * searches/filters, and this plugin's own reorder actions alike.
 *
 * Independent of design-system-tools.js: this has its own on/off switch
 * and works whether or not the reorder feature is enabled.
 *
 * IMPLEMENTATION NOTE: a MutationObserver-based approach (matching
 * design-system-tools.js) was considered, but a plain polling loop is
 * used instead. This is deliberate, not a shortcut — a badge insertion
 * itself mutates the DOM, which would re-trigger a mutation observer
 * watching the same subtree, and getting that feedback loop provably
 * right (batched mutations, closure-per-node correctness) already took
 * real debugging effort for the reorder feature. For a purely cosmetic
 * overlay, a 400ms poll is simpler to reason about, cheap enough to run
 * indefinitely in an editor-only context, and can't get into a loop
 * with itself.
 */
( function () {
	'use strict';

	var POLL_INTERVAL_MS = 400;
	var BADGE_CLASS = 'atomora-row-number';

	function ensureBadgeAfter( handle, position ) {
		// Scoped to the immediate parent rather than strictly
		// nextElementSibling: another injected element (e.g. a bulk-select
		// checkbox from a different script) can legitimately sit between
		// the handle and this badge. Checking existence anywhere in the
		// parent — not a fixed position — avoids the two scripts each
		// repeatedly reinserting past each other on every poll tick.
		var badge = handle.parentNode.querySelector( ':scope > .' + BADGE_CLASS );

		if ( ! badge ) {
			badge = document.createElement( 'span' );
			badge.className = BADGE_CLASS;
			badge.setAttribute( 'aria-hidden', 'true' );
			badge.style.cssText =
				'display:inline-block;min-width:16px;margin:0 6px;text-align:right;' +
				'font:11px/1.4 monospace;color:rgba(255,255,255,0.4);' +
				'pointer-events:none;user-select:none;vertical-align:middle;';
			handle.insertAdjacentElement( 'afterend', badge );
		}

		var text = String( position );
		if ( badge.textContent !== text ) {
			badge.textContent = text; // Text-only update — no further mutation.
		}
	}

	function renumberClasses() {
		var handles = document.querySelectorAll( '.class-item-sortable-trigger' );
		handles.forEach( function ( handle, i ) {
			ensureBadgeAfter( handle, i + 1 );
		} );
	}

	function renumberVariables() {
		// Grouped by container so numbering restarts at 1 per list, in
		// case more than one such table is ever rendered at once.
		var groups = [];
		var rows = document.querySelectorAll( 'tr[aria-roledescription="sortable"]' );

		rows.forEach( function ( row ) {
			var handle = row.querySelector( 'button[draggable="true"]' );
			if ( ! handle ) {
				return;
			}
			var container = row.closest( 'table' ) || row.parentElement;
			var group = null;
			for ( var g = 0; g < groups.length; g++ ) {
				if ( groups[ g ].container === container ) {
					group = groups[ g ];
					break;
				}
			}
			if ( ! group ) {
				group = { container: container, handles: [] };
				groups.push( group );
			}
			group.handles.push( handle );
		} );

		groups.forEach( function ( group ) {
			group.handles.forEach( function ( handle, idx ) {
				ensureBadgeAfter( handle, idx + 1 );
			} );
		} );
	}

	function tick() {
		// Shared flag set by design-system-tools.js / design-system-groups.js
		// while a reorder (direct dispatch or simulated keyboard-drag) is
		// in flight. Confirmed necessary: inserting/moving a badge while
		// Elementor is mid-transition on a row can make React lose track
		// of which DOM node belongs to which list item — observed as two
		// labels visually merging into one.
		if ( window.__atomoraDSBusy ) {
			return;
		}
		renumberClasses();
		renumberVariables();
	}

	setInterval( tick, POLL_INTERVAL_MS );
} )();