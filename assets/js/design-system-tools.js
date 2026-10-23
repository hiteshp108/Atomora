/**
 * Atomora — Design System Quick Reorder (EXPERIMENTAL)
 *
 * Adds "Move to top / Move up / Move down / Move to bottom" actions to the
 * three-dot menu on each row of Elementor's Global Classes manager
 * (Design System panel → Classes tab).
 *
 * HOW THIS WORKS (and why it's fragile):
 * Elementor exposes no public API for this panel. This script dispatches
 * directly into Elementor's own internal Redux store — undocumented, but
 * exposed on window.elementorV2.store — using the exact same action
 * ('globalClasses/setOrder') Elementor's own drag-and-drop reordering
 * already dispatches. If it succeeds, it succeeds through Elementor's real
 * save path; nothing here reimplements or guesses at persistence.
 *
 * If Elementor ever renames this store, the slice, or the action shape,
 * verifyState() below detects the mismatch and this disables itself with a
 * clearly visible on-screen warning + console.warn — rather than silently
 * doing nothing, or worse, dispatching a malformed action.
 *
 * VARIABLES: originally handled via keyboard-drag simulation (Variables
 * has no Redux store like Classes does). CONFIRMED from source that
 * window.elementorV2.editorVariables.service is public and includes
 * variables() + batchSave() — the exact function Elementor's own panel
 * calls to persist a reorder. That's now the primary mechanism; keyboard-
 * drag simulation (see below) is kept only as a fallback if the service
 * is ever unavailable, mirroring how Classes falls back to it if its
 * Redux dispatch fails.
 */
( function () {
	'use strict';

	var SLICE_NAME = 'globalClasses';
	var ACTION_TYPE = SLICE_NAME + '/setOrder';
	var warned = false;
	var lastMenuTrigger = null;

	// Track the button that opened a menu, as a fallback for browsers/MUI
	// versions where aria-expanded isn't reliably set on the trigger.
	document.addEventListener(
		'pointerdown',
		function ( evt ) {
			var btn = evt.target.closest && evt.target.closest( 'button[aria-haspopup="true"]' );
			if ( btn ) {
				lastMenuTrigger = btn;
			}
		},
		true
	);

	function warn( message, err ) {
		if ( warned ) {
			return; // One banner per editor session is enough — don't spam.
		}
		warned = true;

		// eslint-disable-next-line no-console
		console.warn( '[Atomora] Design System quick-reorder disabled: ' + message, err || '' );

		var banner = document.createElement( 'div' );
		banner.textContent =
			'Atomora: the class reorder shortcut (Move to top/bottom) stopped working, ' +
			'likely because Elementor changed something internally. Normal dragging still works. ' +
			'Click to dismiss.';
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

	function getStore() {
		return ( window.elementorV2 && window.elementorV2.store ) || null;
	}

	/**
	 * Confirms the exact internal shape this script depends on still
	 * exists. Returns the current { items, order } for global classes,
	 * or null (after warning) if anything doesn't match.
	 */
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
		// on a freshly loaded page it can be `{}` even with classes
		// listed. `classLabels` (id -> label) is what's eagerly loaded
		// just to render the list, so it's the reliable source for
		// label lookups regardless of whether anything's been opened.
		return {
			items: slice.data.items,
			order: slice.data.order,
			classLabels: slice.classLabels,
		};
	}

	function dispatchNewOrder( newOrder ) {
		var store = getStore();
		if ( ! store ) {
			warn( 'Lost access to the store between reading and dispatching.' );
			return false;
		}
		try {
			// Same shared busy flag as simulateKeyboardReorder — a direct
			// dispatch still triggers Elementor's animated reorder
			// transition, so it needs the same protection against other
			// scripts' polling loops touching rows mid-transition.
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

	/**
	 * Finds a class's id by matching the row's visible label text against
	 * Redux state — deliberately NOT relying on any DOM id/data attribute,
	 * since the row markup doesn't expose one reliably.
	 */
	function findClassIdByLabel( label, classLabels ) {
		var ids = Object.keys( classLabels );
		for ( var i = 0; i < ids.length; i++ ) {
			if ( classLabels[ ids[ i ] ] === label ) {
				return ids[ i ];
			}
		}
		return null;
	}

	function computeNewOrder( order, id, action, targetPosition ) {
		var current = order.indexOf( id );
		if ( current === -1 ) {
			return null;
		}
		var next = order.slice();
		next.splice( current, 1 );

		if ( action === 'top' ) {
			next.unshift( id );
		} else if ( action === 'bottom' ) {
			next.push( id );
		} else if ( action === 'up' ) {
			next.splice( Math.max( 0, current - 1 ), 0, id );
		} else if ( action === 'down' ) {
			next.splice( Math.min( next.length, current + 1 ), 0, id );
		} else if ( action === 'position' ) {
			// targetPosition is 1-based, as shown to the user.
			var zeroBased = Math.max( 0, Math.min( next.length, targetPosition - 1 ) );
			next.splice( zeroBased, 0, id );
		} else {
			return null;
		}
		return next;
	}

	/**
	 * Builds one menu <li>, cloned from an existing item so it inherits
	 * the real menu's styling without needing to know its (dynamically
	 * hashed) class names. Shared by the move actions and Duplicate.
	 */
	function buildMenuItem( existingItem, text, onClick ) {
		var li = existingItem ? existingItem.cloneNode( true ) : document.createElement( 'li' );
		li.setAttribute( 'role', 'menuitem' );
		li.setAttribute( 'tabindex', '0' );
		li.classList.remove( 'Mui-focusVisible', 'Mui-selected' );

		var textNode = li.querySelector( 'span span' ) || li.querySelector( 'span' ) || li;
		textNode.textContent = text;

		li.addEventListener( 'click', function ( evt ) {
			evt.stopPropagation();
			onClick();
		} );

		return li;
	}

	/**
	 * Appends "-copy", or "-copy-2", "-copy-3", etc., until the result
	 * isn't already in `existingLabels` — avoids silently colliding with
	 * another item (Classes' API rejects duplicate labels outright;
	 * Variables doesn't enforce it, but a collision there would just be
	 * confusing).
	 */
	function makeCopyLabel( baseLabel, existingLabels ) {
		var candidate = baseLabel + '-copy';
		var n = 2;
		while ( existingLabels.indexOf( candidate ) !== -1 ) {
			candidate = baseLabel + '-copy-' + n;
			n++;
		}
		return candidate;
	}

	function getMenuTriggerButton() {
		return (
			document.querySelector( 'button[aria-haspopup="true"][aria-expanded="true"]' ) ||
			lastMenuTrigger
		);
	}

	function getRowLabel( button ) {
		// Classes rows are div.MuiListItemButton-root (confirmed from
		// Elementor 4.2.4 markup); Variables rows are <tr> — support both
		// rather than assuming one shape.
		var row =
			button.closest( '.MuiListItemButton-root' ) ||
			button.closest( 'tr' ) ||
			button.closest( '[role="button"]' );
		if ( ! row ) {
			return null;
		}
		var labelEl =
			row.querySelector( '[style*="text-overflow"]' ) || row.querySelector( '.MuiBox-root' );
		return labelEl ? labelEl.textContent.trim() : null;
	}

	/**
	 * We can't tell "this menu belongs to a Classes row" from the menu's
	 * own markup — it's a generic, shared component. Instead we look up
	 * the row's label in the Classes Redux state; if it isn't found (e.g.
	 * this is actually a Variables row), we do nothing — a safe no-op,
	 * not an error.
	 */
	function tryAugmentMenu( menuEl ) {
		if ( menuEl.__atomoraAugmented ) {
			return;
		}

		var triggerButton = getMenuTriggerButton();
		if ( ! triggerButton ) {
			return;
		}

		var label = getRowLabel( triggerButton );
		if ( ! label ) {
			return;
		}

		var classesState = readClassesState();
		var classId = classesState ? findClassIdByLabel( label, classesState.classLabels ) : null;

		if ( ! classId ) {
			// Not a matched class — try the Variables path instead (separate
			// mechanism, since Variables has no exposed Redux store).
			var row = triggerButton.closest( 'tr' );
			if ( row && 'sortable' === row.getAttribute( 'aria-roledescription' ) ) {
				augmentVariablesMenu( menuEl, row, label );
			}
			return;
		}

		menuEl.__atomoraAugmented = true;

		// Captured for the keyboard-sensor fallback below — used only if
		// the direct Redux dispatch fails.
		var classRow = triggerButton.closest( '.MuiListItemButton-root' );

		var existingItem = menuEl.querySelector( '[role="menuitem"]' );
		var actions = [
			[ 'top', 'Move to top' ],
			[ 'up', 'Move up' ],
			[ 'down', 'Move down' ],
			[ 'bottom', 'Move to bottom' ],
			[ 'position', 'Move to position…' ],
		];

		actions.forEach( function ( pair ) {
			var action = pair[ 0 ];
			var text = pair[ 1 ];

			var li = existingItem ? existingItem.cloneNode( true ) : document.createElement( 'li' );
			li.setAttribute( 'role', 'menuitem' );
			li.setAttribute( 'tabindex', '0' );
			// Clear any cloned inline state (e.g. hover/focus classes) that
			// could make the copy look selected/highlighted on insertion.
			li.classList.remove( 'Mui-focusVisible', 'Mui-selected' );

			var textNode = li.querySelector( 'span span' ) || li.querySelector( 'span' ) || li;
			textNode.textContent = text;

			li.addEventListener( 'click', function ( evt ) {
				evt.stopPropagation();

				var freshState = readClassesState();
				if ( ! freshState ) {
					return;
				}

				var currentIndex = freshState.order.indexOf( classId );
				var newOrder;

				if ( action === 'position' ) {
					var total = freshState.order.length;
					var input = window.prompt(
						'Move "' + label + '" to position (1-' + total + '):',
						String( currentIndex + 1 )
					);
					if ( input === null ) {
						return; // Cancelled.
					}
					var target = parseInt( input, 10 );
					if ( isNaN( target ) ) {
						window.alert( 'Please enter a valid number.' );
						return;
					}
					target = Math.max( 1, Math.min( total, target ) );
					newOrder = computeNewOrder( freshState.order, classId, action, target );
				} else {
					newOrder = computeNewOrder( freshState.order, classId, action );
				}

				if ( ! newOrder ) {
					warn( 'Could not compute a new position for "' + label + '".' );
					return;
				}

				var closeMenu = function () {
					document.dispatchEvent(
						new KeyboardEvent( 'keydown', { key: 'Escape', code: 'Escape', bubbles: true } )
					);
				};

				var dispatched = dispatchNewOrder( newOrder );
				if ( dispatched ) {
					closeMenu();
					return;
				}

				// FALLBACK: the direct Redux dispatch failed (e.g. a future
				// Elementor update changed the store/slice/action shape —
				// warn() already fired inside dispatchNewOrder/readClassesState
				// for that). Rather than give up, try the same keyboard-
				// sensor simulation used for Variables, using the class
				// row's own drag handle.
				//
				// CONFIRMED (manual test, Elementor 4.2.4): this currently
				// cannot succeed. Variables' drag handle is a real <button>
				// with working native Tab+Space+Arrow dragging; Classes'
				// handle is a <div role="button"> with no tabindex, and
				// native keyboard dragging does not work on it either — so
				// Elementor never wired dnd-kit's keyboard sensor into the
				// Classes list at all. This isn't a bug in the simulation;
				// there is nothing to replay a keypress into. Left in
				// deliberately (dormant, harmless) in case Elementor adds
				// keyboard support to Classes in a future release.
				var targetIndex = newOrder.indexOf( classId );
				var delta = targetIndex - currentIndex;

				if ( 0 === delta ) {
					return;
				}

				var handle = classRow && classRow.querySelector( '.class-item-sortable-trigger' );
				if ( ! handle ) {
					warn( 'Fallback also unavailable: could not find the drag handle for "' + label + '".' );
					return;
				}

				simulateKeyboardReorder( handle, Math.abs( delta ), delta > 0 ? 'ArrowDown' : 'ArrowUp', closeMenu );
			} );

			menuEl.appendChild( li );
		} );

		menuEl.appendChild(
			buildMenuItem( existingItem, 'Duplicate', function () {
				var freshState = readClassesState();
				if ( ! freshState ) {
					return;
				}

				var original = freshState.items[ classId ];
				if ( ! original || ! Array.isArray( original.variants ) ) {
					// CONFIRMED (live testing): `items[id]` is only
					// populated once a class has actually been opened —
					// `classLabels` alone (always loaded) isn't enough to
					// copy a class's actual styles. Rather than create an
					// empty, broken duplicate, refuse with a clear reason.
					window.alert(
						'Can\'t duplicate "' + label + '" yet — open it once (click into its styles) ' +
						'this session, then try Duplicate again.'
					);
					return;
				}

				var newLabel = makeCopyLabel( label, Object.keys( freshState.classLabels ).map( function ( id ) {
					return freshState.classLabels[ id ];
				} ) );
				var newId = 'g-' + Math.random().toString( 16 ).slice( 2, 9 );

				var store = getStore();
				if ( ! store ) {
					warn( 'Lost access to the store — could not duplicate "' + label + '".' );
					return;
				}

				try {
					window.__atomoraDSBusy = ( window.__atomoraDSBusy || 0 ) + 1;
					setTimeout( function () {
						window.__atomoraDSBusy = Math.max( 0, ( window.__atomoraDSBusy || 1 ) - 1 );
					}, 350 );
					store.__dispatch( {
						type: SLICE_NAME + '/add',
						payload: {
							id: newId,
							label: newLabel,
							type: original.type,
							variants: original.variants,
						},
					} );
				} catch ( e ) {
					warn( 'Duplicating "' + label + '" threw an error.', e );
					return;
				}

				document.dispatchEvent(
					new KeyboardEvent( 'keydown', { key: 'Escape', code: 'Escape', bubbles: true } )
				);
			} )
		);
	}

	// ---- Variables tab: keyboard-drag simulation -----------------------
	//
	// Variables has no exposed Redux store (confirmed from Elementor
	// 4.2.4 source — its reorder callback is local component state,
	// persisted to the server only on Save). The only way to move a row
	// is to make Elementor's own drag-and-drop actually do it.
	//
	// Rather than simulating pointer/mouse drag (fragile: requires
	// faking coordinates and continuous collision detection), this uses
	// dnd-kit's built-in KEYBOARD sensor, confirmed present in
	// Elementor's bundled dnd-kit build: Space picks up the focused
	// drag handle, Arrow Up/Down moves exactly one row per press, Space
	// again drops it, Escape cancels. This is dispatching the same
	// input events a real keyboard user would send — not reverse-
	// engineering internal state.
	//
	// IMPORTANT: Escape is dnd-kit's cancel key. The three-dot menu is
	// normally closed with Escape too, but firing it WHILE a keyboard-
	// drag is in progress would cancel the move instead of just closing
	// the menu. So Escape is only ever dispatched from onDone, after
	// the drop step has already completed.

	function dispatchKey( target, code ) {
		var key = 'Space' === code ? ' ' : code;
		target.dispatchEvent(
			new KeyboardEvent( 'keydown', { code: code, key: key, bubbles: true, cancelable: true } )
		);
	}

	function simulateKeyboardReorder( handle, steps, direction, onDone ) {
		// Shared across every Design System script (see
		// design-system-row-numbers.js / design-system-groups.js): while
		// this flag is true, their polling loops skip a tick rather than
		// touch a row. Confirmed necessary — a badge/header insertion
		// landing mid-drag (Elementor animates each move with a 200ms CSS
		// transition) can make React lose track of which DOM node belongs
		// to which row, visible as two labels merging into one.
		window.__atomoraDSBusy = ( window.__atomoraDSBusy || 0 ) + 1;

		function finish() {
			window.__atomoraDSBusy = Math.max( 0, ( window.__atomoraDSBusy || 1 ) - 1 );
			if ( onDone ) {
				onDone();
			}
		}

		handle.focus();
		dispatchKey( handle, 'Space' ); // Pick up — must target the handle itself.

		var i = 0;
		function nextStep() {
			if ( i >= steps ) {
				setTimeout( function () {
					dispatchKey( document, 'Space' ); // Drop.
					// Elementor's own reorder transition runs after drop —
					// stay "busy" a little past it before releasing the flag.
					setTimeout( finish, 250 );
				}, 80 );
				return;
			}
			// Once picked up, dnd-kit listens on the document — no need
			// to target the handle for subsequent moves.
			dispatchKey( document, direction );
			i++;
			setTimeout( nextStep, 80 );
		}
		setTimeout( nextStep, 80 );
	}

	// ---- Variables tab: direct service call (primary mechanism) ---------
	//
	// CONFIRMED from Elementor 4.2.4 source: window.elementorV2.editorVariables
	// .service is public and includes variables() (current state, each
	// entry carrying its own `order`), and batchSave(original, current,
	// deletedIds) — the exact function Elementor's own panel calls, which
	// diffs `order` per variable and persists it immediately. This is the
	// same technique now used for delete (see design-system-bulk-actions.js
	// for the fuller writeup of why the public service beats a raw REST
	// call: it also keeps Elementor's own panel in sync).
	//
	// This replaces keyboard-drag simulation as the PRIMARY mechanism.
	// simulateKeyboardReorder() below is kept only as a fallback for if
	// this service is ever unavailable — mirroring how Classes falls back
	// to it if its Redux dispatch fails.

	function getVariablesService() {
		var pkg = window.elementorV2 && window.elementorV2.editorVariables;
		var svc = pkg && pkg.service;
		if ( svc && 'function' === typeof svc.variables && 'function' === typeof svc.batchSave ) {
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

	function findVariableIdByLabel( variables, label ) {
		var ids = Object.keys( variables );
		for ( var i = 0; i < ids.length; i++ ) {
			if ( ! variables[ ids[ i ] ].deleted && variables[ ids[ i ] ].label === label ) {
				return ids[ i ];
			}
		}
		return null;
	}

	/**
	 * Applies a full target order for every variable in one call — the
	 * same shape as Elementor's own handleReorder(): rewrite each
	 * variable's `order` to its index in targetIds, then batchSave. Calls
	 * onDone(true) on success, onDone(false) if the service is missing or
	 * the save fails (caller decides whether to fall back).
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

	function getSortableRowsContainer( row ) {
		return row.closest( 'table' ) || row.parentElement;
	}

	function getAllSortableRows( container ) {
		return Array.prototype.slice.call(
			container.querySelectorAll( '[aria-roledescription="sortable"]' )
		);
	}

	function getRowDragHandle( row ) {
		return row.querySelector( 'button[draggable="true"]' );
	}

	function augmentVariablesMenu( menuEl, row, label ) {
		menuEl.__atomoraAugmented = true;

		var existingItem = menuEl.querySelector( '[role="menuitem"]' );
		var actions = [
			[ 'top', 'Move to top' ],
			[ 'up', 'Move up' ],
			[ 'down', 'Move down' ],
			[ 'bottom', 'Move to bottom' ],
			[ 'position', 'Move to position…' ],
		];

		actions.forEach( function ( pair ) {
			var action = pair[ 0 ];
			var text = pair[ 1 ];

			var li = existingItem ? existingItem.cloneNode( true ) : document.createElement( 'li' );
			li.setAttribute( 'role', 'menuitem' );
			li.setAttribute( 'tabindex', '0' );
			li.classList.remove( 'Mui-focusVisible', 'Mui-selected' );

			var textNode = li.querySelector( 'span span' ) || li.querySelector( 'span' ) || li;
			textNode.textContent = text;

			li.addEventListener( 'click', function ( evt ) {
				evt.stopPropagation();

				var closeMenu = function () {
					document.dispatchEvent(
						new KeyboardEvent( 'keydown', { key: 'Escape', code: 'Escape', bubbles: true } )
					);
				};

				var svc = getVariablesService();
				var variables = null;
				var variableId = null;
				var orderedIds = null;
				var currentIndex = -1;

				if ( svc ) {
					try {
						variables = svc.variables();
					} catch ( e ) {
						warn( 'Reading Elementor\'s variables service threw an error.', e );
					}
				}
				if ( variables ) {
					variableId = findVariableIdByLabel( variables, label );
					orderedIds = getOrderedVariableIds( variables );
					currentIndex = variableId ? orderedIds.indexOf( variableId ) : -1;
				}

				// Fall back to DOM-based row counting only if the service
				// path above didn't work out (missing service, threw, or
				// this row's variable couldn't be matched).
				var row2, handle, total, useFallback = -1 === currentIndex;
				if ( useFallback ) {
					row2 = row;
					handle = getRowDragHandle( row2 );
					if ( ! handle ) {
						warn( 'Could not find the drag handle for variable "' + label + '".' );
						return;
					}
					var container = getSortableRowsContainer( row2 );
					var rows = getAllSortableRows( container );
					currentIndex = rows.indexOf( row2 );
					if ( -1 === currentIndex ) {
						warn( 'Lost track of "' + label + '"\'s row position.' );
						return;
					}
					total = rows.length;
				} else {
					total = orderedIds.length;
				}

				var targetIndex;
				if ( 'top' === action ) {
					targetIndex = 0;
				} else if ( 'bottom' === action ) {
					targetIndex = total - 1;
				} else if ( 'up' === action ) {
					targetIndex = Math.max( 0, currentIndex - 1 );
				} else if ( 'down' === action ) {
					targetIndex = Math.min( total - 1, currentIndex + 1 );
				} else if ( 'position' === action ) {
					var input = window.prompt(
						'Move "' + label + '" to position (1-' + total + '):',
						String( currentIndex + 1 )
					);
					if ( null === input ) {
						return; // Cancelled.
					}
					var target = parseInt( input, 10 );
					if ( isNaN( target ) ) {
						window.alert( 'Please enter a valid number.' );
						return;
					}
					targetIndex = Math.max( 1, Math.min( total, target ) ) - 1;
				} else {
					return;
				}

				var delta = targetIndex - currentIndex;
				if ( 0 === delta ) {
					return; // Already there — nothing to do.
				}

				if ( ! useFallback ) {
					var newOrderIds = orderedIds.slice();
					newOrderIds.splice( currentIndex, 1 );
					newOrderIds.splice( targetIndex, 0, variableId );

					applyVariablesOrderDirect( newOrderIds, function ( success ) {
						if ( success ) {
							closeMenu();
							return;
						}
						// Direct save failed after all — fall back to
						// keyboard-sim using the same delta we already
						// computed, rather than give up.
						var fallbackHandle = getRowDragHandle( row );
						if ( ! fallbackHandle ) {
							warn( 'Direct save failed and no fallback drag handle was found for "' + label + '".' );
							return;
						}
						simulateKeyboardReorder(
							fallbackHandle,
							Math.abs( delta ),
							delta > 0 ? 'ArrowDown' : 'ArrowUp',
							closeMenu
						);
					} );
					return;
				}

				simulateKeyboardReorder( handle, Math.abs( delta ), delta > 0 ? 'ArrowDown' : 'ArrowUp', closeMenu );
			} );

			menuEl.appendChild( li );
		} );

		menuEl.appendChild(
			buildMenuItem( existingItem, 'Duplicate', function () {
				var svc = getVariablesService();
				if ( ! svc ) {
					warn( 'Could not duplicate "' + label + '": variables service unavailable.' );
					return;
				}

				var variables;
				try {
					variables = svc.variables();
				} catch ( e ) {
					warn( 'Reading Elementor\'s variables service threw an error.', e );
					return;
				}

				var originalId = findVariableIdByLabel( variables, label );
				var original = originalId ? variables[ originalId ] : null;
				if ( ! original ) {
					warn( 'Could not find "' + label + '" to duplicate.' );
					return;
				}

				var existingLabels = Object.keys( variables )
					.filter( function ( id ) {
						return ! variables[ id ].deleted;
					} )
					.map( function ( id ) {
						return variables[ id ].label;
					} );
				var newLabel = makeCopyLabel( label, existingLabels );

				svc.create( { type: original.type, label: newLabel, value: original.value } ).catch( function ( e ) {
					warn( 'Duplicating "' + label + '" failed.', e );
				} );

				document.dispatchEvent(
					new KeyboardEvent( 'keydown', { key: 'Escape', code: 'Escape', bubbles: true } )
				);
			} )
		);
	}

	var observer = new MutationObserver( function ( mutations ) {
		// `let` (not `var`) is required in both loop bodies below: a menu
		// opens by inserting several sibling nodes at once (portal wrapper,
		// backdrop, the menu itself), all in the same mutation batch. With
		// `var`, every deferred setTimeout callback would share one
		// variable and all fire against whichever node was last in the
		// batch — silently doing nothing for every real menu. `let`
		// creates a fresh binding per iteration so each callback gets its
		// own node.
		for ( let m = 0; m < mutations.length; m++ ) {
			const added = mutations[ m ].addedNodes;
			for ( let i = 0; i < added.length; i++ ) {
				const node = added[ i ];
				if ( node.nodeType !== 1 ) {
					continue;
				}
				const menu =
					node.matches && node.matches( '[role="menu"]' )
						? node
						: node.querySelector && node.querySelector( '[role="menu"]' );
				if ( menu ) {
					// Menu items can render a tick after the container in
					// some MUI versions — defer a frame so the existing
					// item we clone from is already there.
					setTimeout( function () {
						tryAugmentMenu( menu );
					}, 0 );
				}
			}
		}
	} );

	function start() {
		if ( ! document.body ) {
			setTimeout( start, 50 );
			return;
		}
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	start();
} )();