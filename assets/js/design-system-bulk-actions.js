/**
 * Atomora — Design System Bulk Select + Bulk Move (EXPERIMENTAL)
 *
 * Adds a checkbox to each Classes/Variables row and a floating toolbar
 * ("N selected" + Move to top / Move to bottom / Clear) once anything is
 * checked.
 *
 * Reuses the mechanisms proven for single-item moves rather than
 * inventing anything new:
 *  - Classes: one direct dispatch into Elementor's exposed Redux store
 *    (window.elementorV2.store), computing the whole target order in one
 *    shot — same reliability tier as the quick-reorder feature.
 *  - Variables: window.elementorV2.editorVariables.service.batchSave() —
 *    the same function Elementor's own panel calls to persist a reorder.
 *    Rewrites every variable's `order` to its target index in one call;
 *    no dragging, no timing. Falls back to the older keyboard-sensor
 *    simulation (planned as a sequence, run serialized) only if the
 *    direct save fails. That keyboard-sim path is the same mechanism the
 *    removed Groups/folders feature used, and the same one that
 *    corrupted rows when called concurrently — this file avoids that by
 *    only ever running one bulk-move sequence at a time (a single
 *    toolbar click, not several independent per-row triggers).
 *
 * Delete is NOT included here by design (built and tested separately,
 * once bulk move is confirmed solid) — this file only ever reorders,
 * never removes anything.
 *
 * Selection state is in-memory only (resets on reload) — this is a
 * one-off action tool, not a saved preference.
 */
( function () {
	'use strict';

	var CHECKBOX_CLASS = 'atomora-row-checkbox';
	var TOOLBAR_ID = 'atomora-bulk-toolbar';
	var SLICE_NAME = 'globalClasses';
	var ACTION_TYPE = SLICE_NAME + '/setOrder';

	var warned = false;
	var selectedClassIds = new Set();
	var selectedVariableIds = new Set();
	var variableIdByLabel = {};
	var actionInProgress = false;

	function warn( message, err ) {
		if ( warned ) {
			return;
		}
		warned = true;
		// eslint-disable-next-line no-console
		console.warn( '[Atomora] Bulk actions disabled: ' + message, err || '' );

		var banner = document.createElement( 'div' );
		banner.textContent = 'Atomora: bulk move stopped working (' + message + '). Click to dismiss.';
		banner.setAttribute( 'role', 'alert' );
		banner.style.cssText =
			'position:fixed;top:8px;right:8px;z-index:999999;max-width:340px;' +
			'background:#7a1f1f;color:#fff;padding:10px 14px;border-radius:6px;' +
			'font:12px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;' +
			'box-shadow:0 2px 10px rgba(0,0,0,.35);cursor:pointer;';
		banner.addEventListener( 'click', function () {
			banner.remove();
		} );
		document.body.appendChild( banner );
		setTimeout( function () {
			if ( banner.parentNode ) {
				banner.remove();
			}
		}, 15000 );
	}

	// ---- Variables: Elementor's own public service ------------------------
	//
	// CONFIRMED from Elementor 4.2.4 source: the editor-variables package
	// publicly exports its `service` on window.elementorV2.editorVariables
	// (service.variables(), service.delete(id), service.load(), ...). Using
	// it instead of raw REST calls fixes two real problems found in live
	// testing:
	//  - id/label lookup no longer depends on a network fetch (which failed
	//    transiently with ERR_CONNECTION_CLOSED and needed its own nonce).
	//  - delete now goes through the same code path as Elementor's own
	//    delete button, which also updates the editor's in-browser
	//    variables storage and fires its `variables:updated` event. A raw
	//    REST delete skipped that step: the server deleted the variable,
	//    but the panel never learned of it and kept showing the row until
	//    a full page refresh.

	function getVariablesService() {
		var pkg = window.elementorV2 && window.elementorV2.editorVariables;
		var svc = pkg && pkg.service;
		if (
			svc &&
			'function' === typeof svc.variables &&
			'function' === typeof svc.delete &&
			'function' === typeof svc.batchSave
		) {
			return svc;
		}
		return null;
	}

	function getOrderedVariableIds( variables ) {
		return Object.keys( variables )
			.filter( function ( id ) {
				return ! variables[ id ].deleted;
			} )
			.sort( function ( a, b ) {
				return ( variables[ a ].order || 0 ) - ( variables[ b ].order || 0 );
			} );
	}

	/**
	 * Primary mechanism for bulk-moving Variables (mirrors
	 * design-system-tools.js's single-item version): rewrite `order` on
	 * every variable to its index in targetIds, then batchSave — the same
	 * function Elementor's own panel calls to persist a reorder. No
	 * dragging, no timing, no focus dependency.
	 */
	function applyVariablesOrderDirect( targetIds, onDone ) {
		var svc = getVariablesService();
		if ( ! svc ) {
			onDone( false );
			return;
		}
		var original;
		try {
			original = svc.variables();
		} catch ( e ) {
			warn( 'Reading Elementor\'s variables service threw an error.', e );
			onDone( false );
			return;
		}
		var updated = {};
		Object.keys( original ).forEach( function ( id ) {
			updated[ id ] = original[ id ];
		} );
		targetIds.forEach( function ( id, index ) {
			if ( updated[ id ] ) {
				updated[ id ] = Object.assign( {}, updated[ id ], { order: index + 1 } );
			}
		} );

		var result;
		try {
			result = svc.batchSave( original, updated, [] );
		} catch ( e ) {
			warn( 'Saving the new variable order threw an error.', e );
			onDone( false );
			return;
		}
		Promise.resolve( result )
			.then( function () {
				onDone( true );
			} )
			.catch( function ( e ) {
				warn( 'Saving the new variable order failed.', e );
				onDone( false );
			} );
	}

	// Rebuilds label -> id from the live service each call. Cheap (a small
	// localStorage read) and always current, including brand-new or
	// renamed variables, so there's no stale-cache window to manage.
	// Soft-deleted entries (flagged `deleted`) are excluded, matching how
	// Elementor's own panel filters its list.
	var serviceMissingTicks = 0;

	function refreshVariableIdMap() {
		var svc = getVariablesService();
		if ( ! svc ) {
			// Runs every 400ms: give Elementor's variables package a few
			// seconds to finish loading before treating "missing" as a
			// real problem, so a slow page load doesn't trigger a false
			// warning banner.
			serviceMissingTicks++;
			if ( 15 === serviceMissingTicks ) {
				warn( 'window.elementorV2.editorVariables.service is missing or has a different shape than expected.' );
			}
			return false;
		}
		serviceMissingTicks = 0;
		var all;
		try {
			all = svc.variables();
		} catch ( e ) {
			warn( 'Reading Elementor\'s variables service threw an error.', e );
			return false;
		}
		var map = {};
		Object.keys( all || {} ).forEach( function ( id ) {
			var entry = all[ id ];
			if ( entry && entry.label && ! entry.deleted ) {
				map[ entry.label ] = id;
			}
		} );
		variableIdByLabel = map;
		return true;
	}

	// ---- Classes: Redux store (duplicated from design-system-tools.js) --

	function getStore() {
		return ( window.elementorV2 && window.elementorV2.store ) || null;
	}

	function readClassesState() {
		var store = getStore();
		if ( ! store || typeof store.__getState !== 'function' || typeof store.__dispatch !== 'function' ) {
			warn( 'window.elementorV2.store is missing or has a different shape than expected.' );
			return null;
		}
		var state;
		try {
			state = store.__getState();
		} catch ( e ) {
			warn( 'store.__getState() threw an error.', e );
			return null;
		}
		var slice = state && state[ SLICE_NAME ];
		if (
			! slice ||
			! slice.data ||
			! Array.isArray( slice.data.order ) ||
			typeof slice.data.items !== 'object' ||
			typeof slice.classLabels !== 'object'
		) {
			warn( 'Redux state no longer has the expected "' + SLICE_NAME + '" shape.' );
			return null;
		}
		// CONFIRMED (live site testing): `data.items` is only lazily
		// populated once a class has actually been opened for editing —
		// `classLabels` (id -> label) is eagerly loaded just to render
		// the list, so it's the reliable source for label lookups.
		return {
			items: slice.data.items,
			order: slice.data.order,
			classLabels: slice.classLabels,
		};
	}

	function findClassIdByLabel( label, classLabels ) {
		var ids = Object.keys( classLabels );
		for ( var i = 0; i < ids.length; i++ ) {
			if ( classLabels[ ids[ i ] ] === label ) {
				return ids[ i ];
			}
		}
		return null;
	}

	function dispatchNewOrder( newOrder ) {
		var store = getStore();
		if ( ! store ) {
			return false;
		}
		try {
			window.__atomoraDSBusy = ( window.__atomoraDSBusy || 0 ) + 1;
			setTimeout( function () {
				window.__atomoraDSBusy = Math.max( 0, ( window.__atomoraDSBusy || 1 ) - 1 );
			}, 350 );
			store.__dispatch( { type: ACTION_TYPE, payload: newOrder } );
			return true;
		} catch ( e ) {
			warn( 'Dispatching the reorder action threw an error.', e );
			return false;
		}
	}

	// ---- Variables: keyboard-drag simulation (duplicated) ---------------

	function dispatchKey( target, code ) {
		var key = 'Space' === code ? ' ' : code;
		target.dispatchEvent(
			new KeyboardEvent( 'keydown', { code: code, key: key, bubbles: true, cancelable: true } )
		);
	}

	function simulateKeyboardReorder( handle, steps, direction, onDone ) {
		window.__atomoraDSBusy = ( window.__atomoraDSBusy || 0 ) + 1;
		function finish() {
			window.__atomoraDSBusy = Math.max( 0, ( window.__atomoraDSBusy || 1 ) - 1 );
			if ( onDone ) {
				onDone();
			}
		}
		handle.focus();
		dispatchKey( handle, 'Space' );
		var i = 0;
		function nextStep() {
			if ( i >= steps ) {
				setTimeout( function () {
					dispatchKey( document, 'Space' );
					setTimeout( finish, 250 );
				}, 80 );
				return;
			}
			dispatchKey( document, direction );
			i++;
			setTimeout( nextStep, 80 );
		}
		setTimeout( nextStep, 80 );
	}

	function getVisibleVariableRows() {
		return Array.prototype.slice.call( document.querySelectorAll( 'tr[aria-roledescription="sortable"]' ) );
	}

	function getVariableRowLabel( row ) {
		var el = row.querySelector( '[style*="text-overflow"]' ) || row.querySelector( '.MuiBox-root' );
		return el ? el.textContent.trim() : null;
	}

	function getVariableRowHandle( row ) {
		return row.querySelector( 'button[draggable="true"]' );
	}

	function planMoves( currentIds, targetIds ) {
		var moves = [];
		var working = currentIds.slice();
		for ( var i = 0; i < targetIds.length; i++ ) {
			var id = targetIds[ i ];
			var curIdx = working.indexOf( id );
			if ( -1 === curIdx || curIdx === i ) {
				continue;
			}
			moves.push( { id: id, from: curIdx, to: i } );
			working.splice( curIdx, 1 );
			working.splice( i, 0, id );
		}
		return moves;
	}

	function runMovesSequentially( moves, variableLabelById, onAllDone ) {
		var i = 0;
		function next() {
			if ( i >= moves.length ) {
				onAllDone();
				return;
			}
			var move = moves[ i ];
			var label = variableLabelById[ move.id ];
			var row = getVisibleVariableRows().filter( function ( r ) {
				return getVariableRowLabel( r ) === label;
			} )[ 0 ];
			var handle = row && getVariableRowHandle( row );
			if ( ! handle ) {
				warn( 'Lost track of a variable during bulk move ("' + label + '").' );
				i++;
				next();
				return;
			}
			var delta = move.to - move.from;
			simulateKeyboardReorder( handle, Math.abs( delta ), delta > 0 ? 'ArrowDown' : 'ArrowUp', function () {
				i++;
				next();
			} );
		}
		next();
	}

	// ---- Shared: computing a "selected block to an edge" order ----------

	function computeBulkOrder( order, selectedIds, edge ) {
		var selected = order.filter( function ( id ) {
			return selectedIds.has( id );
		} );
		var rest = order.filter( function ( id ) {
			return ! selectedIds.has( id );
		} );
		return 'top' === edge ? selected.concat( rest ) : rest.concat( selected );
	}

	/**
	 * Moves the whole selected set up or down by exactly one step, as a
	 * block, preserving relative order within the selection — the usual
	 * "move selected rows" behavior (spreadsheet/file-manager convention).
	 * Each selected item swaps past its nearest unselected neighbor in
	 * the given direction; already-adjacent-to-the-edge selected items
	 * simply stop moving rather than wrapping or erroring.
	 */
	function moveSelectionByOne( order, selectedIds, direction ) {
		var arr = order.slice();
		if ( 'up' === direction ) {
			for ( var i = 1; i < arr.length; i++ ) {
				if ( selectedIds.has( arr[ i ] ) && ! selectedIds.has( arr[ i - 1 ] ) ) {
					var tmp = arr[ i - 1 ];
					arr[ i - 1 ] = arr[ i ];
					arr[ i ] = tmp;
				}
			}
		} else {
			for ( var j = arr.length - 2; j >= 0; j-- ) {
				if ( selectedIds.has( arr[ j ] ) && ! selectedIds.has( arr[ j + 1 ] ) ) {
					var tmp2 = arr[ j + 1 ];
					arr[ j + 1 ] = arr[ j ];
					arr[ j ] = tmp2;
				}
			}
		}
		return arr;
	}

	/**
	 * Inserts the whole selected block (relative order preserved) so its
	 * first item lands at 1-based `targetPosition` among the full list —
	 * counted the same way the single-item "Move to position" feature
	 * counts it (position among the list as it stands after the moving
	 * items are pulled out).
	 */
	function computeBulkOrderAtPosition( order, selectedIds, targetPosition ) {
		var selected = order.filter( function ( id ) {
			return selectedIds.has( id );
		} );
		var rest = order.filter( function ( id ) {
			return ! selectedIds.has( id );
		} );
		var idx = Math.max( 0, Math.min( rest.length, targetPosition - 1 ) );
		return rest.slice( 0, idx ).concat( selected, rest.slice( idx ) );
	}

	function computeBulkTargetOrder( order, selectedIds, action, targetPosition ) {
		if ( 'top' === action || 'bottom' === action ) {
			return computeBulkOrder( order, selectedIds, action );
		}
		if ( 'up' === action || 'down' === action ) {
			return moveSelectionByOne( order, selectedIds, action );
		}
		if ( 'position' === action ) {
			return computeBulkOrderAtPosition( order, selectedIds, targetPosition );
		}
		return order;
	}

	// ---- Checkboxes -------------------------------------------------------

	function ensureCheckboxAfter( handle, checked, onToggle ) {
		var box = handle.parentNode.querySelector( ':scope > .' + CHECKBOX_CLASS );
		if ( ! box ) {
			box = document.createElement( 'input' );
			box.type = 'checkbox';
			box.className = CHECKBOX_CLASS;
			// Default unstyled checkboxes render as a thin gray-on-gray
			// square that's nearly invisible on Elementor's dark panel —
			// confirmed present via getComputedStyle but not something a
			// person would actually notice. Explicit sizing/color/border
			// makes it unmistakable regardless of OS/browser defaults.
			box.style.cssText =
				'appearance:auto;width:16px;height:16px;margin:0 6px;' +
				'vertical-align:middle;cursor:pointer;accent-color:#6c5ce7;' +
				'border:1px solid rgba(255,255,255,0.6);border-radius:3px;' +
				'background:rgba(255,255,255,0.08);flex-shrink:0;';
			handle.insertAdjacentElement( 'afterend', box );
			box.addEventListener( 'change', function () {
				onToggle( box.checked );
			} );
		}
		if ( box.checked !== checked ) {
			box.checked = checked;
		}
	}

	function tickClassCheckboxes() {
		var state = readClassesState();
		if ( ! state ) {
			return;
		}
		var handles = document.querySelectorAll( '.class-item-sortable-trigger' );
		handles.forEach( function ( handle ) {
			var row = handle.closest( '.MuiListItemButton-root' );
			if ( ! row ) {
				return;
			}
			var labelEl = row.querySelector( '[style*="text-overflow"]' ) || row.querySelector( '.MuiBox-root' );
			var label = labelEl ? labelEl.textContent.trim() : null;
			var id = label ? findClassIdByLabel( label, state.classLabels ) : null;
			if ( ! id ) {
				return;
			}
			ensureCheckboxAfter( handle, selectedClassIds.has( id ), function ( checked ) {
				if ( checked ) {
					selectedClassIds.add( id );
				} else {
					selectedClassIds.delete( id );
				}
				refreshToolbar();
			} );
		} );
	}

	function tickVariableCheckboxes() {
		getVisibleVariableRows().forEach( function ( row ) {
			var handle = getVariableRowHandle( row );
			if ( ! handle ) {
				return;
			}
			var label = getVariableRowLabel( row );
			var id = label ? variableIdByLabel[ label ] : null;
			if ( ! id ) {
				return;
			}
			ensureCheckboxAfter( handle, selectedVariableIds.has( id ), function ( checked ) {
				if ( checked ) {
					selectedVariableIds.add( id );
				} else {
					selectedVariableIds.delete( id );
				}
				refreshToolbar();
			} );
		} );
	}

	function tick() {
		if ( window.__atomoraDSBusy ) {
			return; // Same shared flag design-system-tools.js sets during a reorder.
		}
		// Sync and cheap, so it runs every tick — the map is never stale
		// after a variable is added, renamed or deleted.
		refreshVariableIdMap();
		tickClassCheckboxes();
		tickVariableCheckboxes();
	}

	// ---- Toolbar ----------------------------------------------------------

	function getToolbar() {
		return document.getElementById( TOOLBAR_ID );
	}

	function buildToolbar() {
		var bar = document.createElement( 'div' );
		bar.id = TOOLBAR_ID;
		bar.style.cssText =
			'position:fixed;right:16px;top:80px;z-index:999998;' +
			'background:#1e1e1e;color:#fff;border-radius:8px;' +
			'box-shadow:0 4px 16px rgba(0,0,0,.4);padding:8px 10px;' +
			'display:none;align-items:center;gap:8px;' +
			'font:12px -apple-system,BlinkMacSystemFont,sans-serif;';

		var label = document.createElement( 'span' );
		label.className = 'atomora-bulk-count';
		label.style.cssText = 'cursor:move;user-select:none;padding:2px 4px;';
		label.title = 'Drag to move';

		( function makeDraggable() {
			var dragging = false;
			var offsetX = 0;
			var offsetY = 0;

			label.addEventListener( 'pointerdown', function ( evt ) {
				dragging = true;
				var rect = bar.getBoundingClientRect();
				offsetX = evt.clientX - rect.left;
				offsetY = evt.clientY - rect.top;
				label.setPointerCapture( evt.pointerId );
			} );
			label.addEventListener( 'pointermove', function ( evt ) {
				if ( ! dragging ) {
					return;
				}
				bar.style.left = evt.clientX - offsetX + 'px';
				bar.style.top = evt.clientY - offsetY + 'px';
				bar.style.bottom = 'auto';
			} );
			label.addEventListener( 'pointerup', function ( evt ) {
				dragging = false;
				label.releasePointerCapture( evt.pointerId );
			} );
		} )();

		function makeButton( text, handler ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.textContent = text;
			btn.style.cssText =
				'background:#333;color:#fff;border:1px solid #555;border-radius:4px;' +
				'padding:4px 8px;font:inherit;cursor:pointer;';
			btn.addEventListener( 'click', handler );
			return btn;
		}

		var topBtn = makeButton( 'Move to top', function () {
			runBulkMove( 'top' );
		} );
		var upBtn = makeButton( 'Move up', function () {
			runBulkMove( 'up' );
		} );
		var downBtn = makeButton( 'Move down', function () {
			runBulkMove( 'down' );
		} );
		var bottomBtn = makeButton( 'Move to bottom', function () {
			runBulkMove( 'bottom' );
		} );
		var positionBtn = makeButton( 'Move to position…', function () {
			runBulkMove( 'position' );
		} );
		var clearBtn = makeButton( 'Clear', function () {
			selectedClassIds.clear();
			selectedVariableIds.clear();
			refreshToolbar();
		} );
		var deleteBtn = makeButton( 'Delete selected', function () {
			deleteSelected();
		} );
		deleteBtn.style.background = '#5a1f1f';
		deleteBtn.style.borderColor = '#8a3a3a';

		bar.appendChild( label );
		bar.appendChild( topBtn );
		bar.appendChild( upBtn );
		bar.appendChild( downBtn );
		bar.appendChild( bottomBtn );
		bar.appendChild( positionBtn );
		bar.appendChild( deleteBtn );
		bar.appendChild( clearBtn );
		bar.style.flexWrap = 'wrap';
		bar.style.maxWidth = '260px';
		document.body.appendChild( bar );
		return bar;
	}

	function refreshToolbar() {
		var bar = getToolbar() || buildToolbar();
		var total = selectedClassIds.size + selectedVariableIds.size;
		bar.style.display = total > 0 ? 'flex' : 'none';
		var label = bar.querySelector( '.atomora-bulk-count' );
		if ( label ) {
			label.textContent = total + ' selected';
		}
		var buttons = bar.querySelectorAll( 'button' );
		buttons.forEach( function ( btn ) {
			btn.disabled = actionInProgress;
			btn.style.opacity = actionInProgress ? '0.5' : '1';
		} );
	}

	function runBulkMove( edge ) {
		if ( actionInProgress ) {
			return;
		}
		actionInProgress = true;
		refreshToolbar();

		var classesDone = false;
		var variablesDone = false;

		function maybeFinish() {
			if ( classesDone && variablesDone ) {
				actionInProgress = false;
				selectedClassIds.clear();
				selectedVariableIds.clear();
				refreshToolbar();
			}
		}

		if ( selectedClassIds.size > 0 ) {
			var state = readClassesState();
			if ( state ) {
				var targetPos = null;
				if ( 'position' === edge ) {
					targetPos = promptForPosition( 'classes', state.order.length );
					if ( null === targetPos ) {
						classesDone = true;
						variablesDone = true;
						actionInProgress = false;
						refreshToolbar();
						return;
					}
				}
				var newOrder = computeBulkTargetOrder( state.order, selectedClassIds, edge, targetPos );
				dispatchNewOrder( newOrder );
			}
		}
		classesDone = true;

		if ( selectedVariableIds.size > 0 ) {
			var svc = getVariablesService();
			var variables = svc ? safeGetVariables( svc ) : null;

			// currentIds now comes from the service's own `order` field,
			// not from scanning DOM rows — more direct and no dependency
			// on what's currently scrolled into view.
			var currentIds = variables ? getOrderedVariableIds( variables ) : [];
			// Only ids actually selected (a checkbox could be stale if a
			// variable was deleted elsewhere since it was checked).
			var relevantSelected = new Set(
				currentIds.filter( function ( id ) {
					return selectedVariableIds.has( id );
				} )
			);

			var varTargetPos = null;
			if ( 'position' === edge ) {
				varTargetPos = promptForPosition( 'variables', currentIds.length );
				if ( null === varTargetPos ) {
					variablesDone = true;
					maybeFinish();
					return;
				}
			}

			var targetIds = currentIds.length
				? computeBulkTargetOrder( currentIds, relevantSelected, edge, varTargetPos )
				: [];

			if ( targetIds.length > 0 ) {
				applyVariablesOrderDirect( targetIds, function ( success ) {
					if ( success ) {
						variablesDone = true;
						maybeFinish();
						return;
					}
					// Direct save failed — fall back to the old
					// keyboard-sim approach using DOM row order, which
					// was the only mechanism before this was added.
					var variableLabelById = {};
					Object.keys( variableIdByLabel ).forEach( function ( label ) {
						variableLabelById[ variableIdByLabel[ label ] ] = label;
					} );
					var fallbackCurrentIds = getVisibleVariableRows()
						.map( function ( row ) {
							return variableIdByLabel[ getVariableRowLabel( row ) ];
						} )
						.filter( Boolean );
					var fallbackTargetIds = computeBulkTargetOrder(
						fallbackCurrentIds,
						relevantSelected,
						edge,
						varTargetPos
					);
					var moves = planMoves( fallbackCurrentIds, fallbackTargetIds );
					if ( 0 === moves.length ) {
						variablesDone = true;
						maybeFinish();
						return;
					}
					runMovesSequentially( moves, variableLabelById, function () {
						variablesDone = true;
						maybeFinish();
					} );
				} );
				return; // maybeFinish() called from the async callback above.
			}
		}
		variablesDone = true;
		maybeFinish();
	}

	function safeGetVariables( svc ) {
		try {
			return svc.variables();
		} catch ( e ) {
			warn( 'Reading Elementor\'s variables service threw an error.', e );
			return null;
		}
	}

	// ---- Delete ------------------------------------------------------------
	//
	// Classes: dispatches Elementor's own `delete` reducer on the same
	// globalClasses slice used for reorder — a real, confirmed action
	// (found in Elementor 4.2.4 source), not something invented here. It
	// stages the deletion the same way reordering does: the panel's own
	// "Save changes" button still has to be clicked to persist it.
	//
	// Variables: calls Elementor's own public `service.delete(id)`. That
	// makes the REST call AND updates Elementor's in-browser variables
	// storage, which fires `variables:updated` — the Variables panel
	// listens for that and re-syncs itself, so the row disappears
	// immediately. (An earlier version called the REST endpoint directly:
	// the server deleted the variable, but the panel was never told, so
	// the row stayed on screen until a full page refresh.) Takes effect on
	// the server immediately — unlike Classes, it is NOT staged behind the
	// panel's "Save changes" button.
	//
	// Side effect worth knowing: the panel re-syncing from storage also
	// discards any *unsaved* staged edits in the Variables panel, so save
	// those first.

	function deleteVariablesSequentially( ids, onDone ) {
		var svc = getVariablesService();
		if ( ! svc ) {
			warn( 'Could not delete variables: Elementor\'s variables service is unavailable.' );
			onDone();
			return;
		}
		var i = 0;
		function next() {
			if ( i >= ids.length ) {
				onDone();
				return;
			}
			var id = ids[ i ];
			var result;
			try {
				result = svc.delete( id );
			} catch ( e ) {
				warn( 'Deleting a variable threw an error (' + i + ' of ' + ids.length + ' done).', e );
				i++;
				next();
				return;
			}
			Promise.resolve( result )
				.then( function () {
					i++;
					next();
				} )
				.catch( function ( e ) {
					warn( 'Deleting a variable failed (' + i + ' of ' + ids.length + ' done).', e );
					i++;
					next();
				} );
		}
		next();
	}

	function deleteSelected() {
		if ( actionInProgress ) {
			return;
		}
		var total = selectedClassIds.size + selectedVariableIds.size;
		if ( 0 === total ) {
			return;
		}

		var names = [];
		var classesState = selectedClassIds.size > 0 ? readClassesState() : null;
		if ( classesState ) {
			selectedClassIds.forEach( function ( id ) {
				names.push( classesState.classLabels[ id ] || id );
			} );
		}
		var variableLabelById = {};
		Object.keys( variableIdByLabel ).forEach( function ( l ) {
			variableLabelById[ variableIdByLabel[ l ] ] = l;
		} );
		selectedVariableIds.forEach( function ( id ) {
			names.push( variableLabelById[ id ] || id );
		} );

		var confirmed = window.confirm(
			'Permanently delete ' + total + ' item(s)?\n\n' +
			names.join( ', ' ) +
			'\n\nClasses: staged — click "Save changes" in the panel afterwards to persist this.\n' +
			'Variables: deleted immediately (not staged). Any unsaved edits in the Variables panel will be discarded — save those first.'
		);
		if ( ! confirmed ) {
			return;
		}

		actionInProgress = true;
		refreshToolbar();

		if ( classesState ) {
			var store = getStore();
			selectedClassIds.forEach( function ( id ) {
				try {
					window.__atomoraDSBusy = ( window.__atomoraDSBusy || 0 ) + 1;
					store.__dispatch( { type: SLICE_NAME + '/delete', payload: id } );
				} catch ( e ) {
					warn( 'Deleting a class threw an error.', e );
				} finally {
					setTimeout( function () {
						window.__atomoraDSBusy = Math.max( 0, ( window.__atomoraDSBusy || 1 ) - 1 );
					}, 350 );
				}
			} );
		}

		var variableIds = Array.from( selectedVariableIds );
		deleteVariablesSequentially( variableIds, function () {
			selectedClassIds.clear();
			selectedVariableIds.clear();
			actionInProgress = false;
			refreshToolbar();
		} );
	}

	function promptForPosition( kind, total ) {
		var input = window.prompt( 'Move selected ' + kind + ' so the first one lands at position (1-' + total + '):' );
		if ( null === input ) {
			return null;
		}
		var n = parseInt( input, 10 );
		if ( isNaN( n ) ) {
			window.alert( 'Please enter a valid number.' );
			return null;
		}
		return Math.max( 1, Math.min( total, n ) );
	}

	// ---- Boot ---------------------------------------------------------------

	setInterval( tick, 400 );
} )();