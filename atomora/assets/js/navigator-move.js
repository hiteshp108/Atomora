/* Atomora — Navigator: Move up / down / in / out (context menu + row buttons). */
( function () {
	'use strict';

	var DIRS = [
		{ key: 'up', arrow: 'Up', glyph: '\u2191', title: 'Move up', icon: 'eicon-arrow-up' },
		{ key: 'down', arrow: 'Down', glyph: '\u2193', title: 'Move down', icon: 'eicon-arrow-down' },
		{ key: 'in', arrow: 'Right', glyph: '\u2192', title: 'Move in (into the container above)', icon: 'eicon-arrow-right' },
		{ key: 'out', arrow: 'Left', glyph: '\u2190', title: 'Move out (to the parent level)', icon: 'eicon-arrow-left' }
	];

	function elementsOf( container ) {
		return container && container.model && container.model.get( 'elements' );
	}

	function indexOf( container ) {
		var list = container.parent && elementsOf( container.parent );
		return list ? list.indexOf( container.model ) : -1;
	}

	function locked( container ) {
		return !! ( container && container.isLocked && container.isLocked() );
	}

	function plan( container ) {
		var none = { up: null, down: null, in: null, out: null };
		if ( ! container || ! container.parent || 'document' === container.type || locked( container ) ) {
			return none;
		}

		var list = elementsOf( container.parent );
		var i = indexOf( container );
		if ( ! list || i < 0 ) {
			return none;
		}

		var result = { up: null, down: null, in: null, out: null };

		if ( i > 0 ) {
			result.up = { target: container.parent, at: i - 1 };
		}
		if ( i < list.length - 1 ) {
			result.down = { target: container.parent, at: i + 1 };
		}

		if ( i > 0 ) {
			var prevModel = list.at( i - 1 );
			var prev = window.elementor.getContainer( prevModel.get( 'id' ) );
			if ( prev && 'widget' !== prevModel.get( 'elType' ) && ! locked( prev ) ) {
				result.in = { target: prev, at: elementsOf( prev ).length };
			}
		}

		var parent = container.parent;
		if ( 'document' !== parent.type && parent.parent && ! locked( parent.parent ) ) {
			var grand = parent.parent;
			var pIndex = elementsOf( grand ).indexOf( parent.model );
			// Widgets cannot sit directly on the page (top level accepts containers only).
			var widgetToRoot = 'document' === grand.type && 'widget' === container.model.get( 'elType' );
			if ( pIndex >= 0 && ! widgetToRoot ) {
				result.out = { target: grand, at: pIndex + 1 };
			}
		}
		return result;
	}

	function run( container, dir ) {
		var step = plan( container )[ dir ];
		if ( ! step ) {
			return;
		}
		var id = container.model.get( 'id' );
		var ok = true;
		try {
			window.$e.run( 'document/elements/move', {
				container: container,
				target: step.target,
				options: { at: step.at }
			} );
		} catch ( err ) {
			ok = false;
		}
		// Safety net: Move deletes then re-creates the element; if the target refused it, undo so nothing is lost.
		if ( ! ok || ! window.elementor.getContainer( id ) ) {
			window.$e.run( 'document/history/undo' );
		}
	}

	function selected() {
		var list = window.elementor.selection && window.elementor.selection.getElements();
		return list && list.length ? list[ 0 ] : null;
	}

	// 1) Right-click menu (works in the Navigator and on the canvas).
	function registerMenu() {
		window.elementor.hooks.addFilter( 'elements/context-menu/groups', function ( groups ) {
			groups.push( {
				name: 'atomora-move',
				actions: DIRS.map( function ( dir ) {
					return {
						name: 'atomora-move-' + dir.key,
						icon: dir.icon,
						title: dir.title,
						callback: function () {
							run( selected(), dir.key );
						},
						isEnabled: function () {
							return !! plan( selected() )[ dir.key ];
						}
					};
				} )
			} );
			return groups;
		} );
	}

	// 2) Toolbar in the Structure panel header (acts on the selected element, no overlap with row text).
	function addToolbar( nav ) {
		var header = nav.querySelector( '#elementor-navigator__header' );
		if ( ! header || header.querySelector( '.atomora-nav-toolbar' ) ) {
			return;
		}
		var bar = document.createElement( 'span' );
		bar.className = 'atomora-nav-toolbar';
		DIRS.forEach( function ( dir ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'atomora-nav-toolbar__btn';
			btn.setAttribute( 'data-dir', dir.key );
			btn.title = dir.title + ' (Alt+Shift+' + dir.arrow + ')';
			btn.textContent = dir.glyph;
			btn.disabled = true;
			bar.appendChild( btn );
		} );
		var close = header.querySelector( '#elementor-navigator__close' );
		header.insertBefore( bar, close || null );
	}

	function updateToolbar( nav ) {
		var bar = nav.querySelector( '.atomora-nav-toolbar' );
		if ( ! bar ) {
			return;
		}
		var state = plan( selected() );
		bar.querySelectorAll( '.atomora-nav-toolbar__btn' ).forEach( function ( btn ) {
			btn.disabled = ! state[ btn.getAttribute( 'data-dir' ) ];
		} );
	}

	function bindNavigator() {
		var nav = document.getElementById( 'elementor-navigator' );
		if ( ! nav ) {
			return false;
		}
		if ( ! nav.getAttribute( 'data-ap-move' ) ) {
			nav.setAttribute( 'data-ap-move', '1' );

			new MutationObserver( function () {
				addToolbar( nav );
			} ).observe( nav, { childList: true, subtree: true } );

			nav.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.atomora-nav-toolbar__btn' );
				if ( ! btn ) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				if ( ! btn.disabled ) {
					run( selected(), btn.getAttribute( 'data-dir' ) );
					setTimeout( function () {
						updateToolbar( nav );
					}, 50 );
				}
			}, true );

			setInterval( function () {
				updateToolbar( nav );
			}, 300 );
		}
		addToolbar( nav );
		return true;
	}

	// 3) Keyboard: Alt+Shift+Arrow keys.
	function bindKeys() {
		var map = { ArrowUp: 'up', ArrowDown: 'down', ArrowRight: 'in', ArrowLeft: 'out' };
		document.addEventListener( 'keydown', function ( e ) {
			var dir = map[ e.key ];
			if ( ! dir || ! e.altKey || ! e.shiftKey || e.ctrlKey || e.metaKey ) {
				return;
			}
			var t = e.target;
			if ( t && ( /^(input|textarea|select)$/i.test( t.tagName ) || t.isContentEditable ) ) {
				return;
			}
			e.preventDefault();
			run( selected(), dir );
		} );
	}

	function init() {
		registerMenu();
		bindKeys();
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			if ( bindNavigator() || tries > 120 ) {
				clearInterval( timer );
			}
		}, 500 );
	}

	if ( window.elementor && window.elementor.hooks ) {
		init();
	} else if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor:init', init );
	}
}() );
