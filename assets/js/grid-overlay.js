/* Atomora — Layout grid overlay: top bar toggle + settings popover (editor only). */
( function () {
	'use strict';

	var STORE = 'atomoraGridOverlay';
	var CFG_STORE = 'atomoraGridOverlayCfg';
	var POS_STORE = 'atomoraGridOverlayPos';
	var ID = 'atomora-grid-overlay';
	var MAX = 24;
	var on = false;
	var cfg = { d: 12, t: 8, m: 4, gap: 24, margin: 0, w: 0, w_t: 0, w_m: 0, h: 0, h_t: 0, h_m: 0 };

	function load() {
		try {
			on = '1' === window.localStorage.getItem( STORE );
			var saved = JSON.parse( window.localStorage.getItem( CFG_STORE ) || '{}' );
			Object.keys( cfg ).forEach( function ( k ) {
				if ( 'number' === typeof saved[ k ] ) {
					cfg[ k ] = saved[ k ];
				}
			} );
		} catch ( e ) {}
	}

	function save() {
		try {
			window.localStorage.setItem( STORE, on ? '1' : '0' );
			window.localStorage.setItem( CFG_STORE, JSON.stringify( cfg ) );
		} catch ( e ) {}
	}

	function hideFrom( n ) {
		return n < MAX ? '#' + ID + ' i:nth-child(n+' + ( n + 1 ) + '){display:none}' : '';
	}

	// Whole-grid size for one breakpoint (0 = fill the canvas).
	function box( w, h ) {
		return 'top:0;' + ( w > 0
			? 'left:50%;right:auto;width:' + w + 'px;max-width:100%;transform:translateX(-50%);'
			: 'left:0;right:0;width:auto;max-width:none;transform:none;' ) +
			( h > 0 ? 'bottom:auto;height:' + h + 'px;max-height:100%;' : 'bottom:0;height:auto;max-height:none;' );
	}

	function css() {
		return '#' + ID + '{position:fixed;' + box( cfg.w, cfg.h ) + 'z-index:2147483000;pointer-events:none;display:grid;box-sizing:border-box;' +
			'padding-inline:' + cfg.margin + 'px;column-gap:' + cfg.gap + 'px;' +
			'grid-template-columns:repeat(' + cfg.d + ',minmax(0,1fr))}' +
			'#' + ID + ' i{background:rgba(255,0,0,.1)}' + hideFrom( cfg.d ) +
			'@media (max-width:1024px){#' + ID + '{' + box( cfg.w_t, cfg.h_t ) + 'grid-template-columns:repeat(' + cfg.t + ',minmax(0,1fr))}' + hideFrom( cfg.t ) + '}' +
			'@media (max-width:767px){#' + ID + '{' + box( cfg.w_m, cfg.h_m ) + 'grid-template-columns:repeat(' + cfg.m + ',minmax(0,1fr))}' + hideFrom( cfg.m ) + '}';
	}

	function previewDoc() {
		var frame = window.elementor && window.elementor.$preview && window.elementor.$preview[ 0 ];
		return frame && frame.contentDocument;
	}

	function apply() {
		var doc = previewDoc();
		if ( ! doc || ! doc.body ) {
			return;
		}
		var overlay = doc.getElementById( ID );
		var style = doc.getElementById( ID + '-css' );

		if ( ! on ) {
			if ( overlay ) {
				overlay.remove();
			}
			if ( style ) {
				style.remove();
			}
			return;
		}

		if ( ! style ) {
			style = doc.createElement( 'style' );
			style.id = ID + '-css';
			doc.head.appendChild( style );
		}
		var text = css();
		if ( style.textContent !== text ) {
			style.textContent = text;
		}
		if ( ! overlay ) {
			overlay = doc.createElement( 'div' );
			overlay.id = ID;
			for ( var i = 0; i < MAX; i++ ) {
				overlay.appendChild( doc.createElement( 'i' ) );
			}
			doc.body.appendChild( overlay );
		}
	}

	function notify() {
		window.dispatchEvent( new CustomEvent( 'atomora-grid-overlay' ) );
	}

	function toggle() {
		on = ! on;
		if ( ! on ) {
			closePanel();
		}
		save();
		apply();
		notify();
	}

	// Settings popover.
	var panel = null;

	function closePanel() {
		if ( panel ) {
			panel.remove();
			panel = null;
		}
	}

	function field( key, label, min, max ) {
		return '<label style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin:6px 0">' + label +
			'<input type="number" data-k="' + key + '" min="' + min + '" max="' + max + '" value="' + cfg[ key ] + '" style="width:64px;padding:2px 4px"></label>';
	}

	function openPanel() {
		if ( panel ) {
			closePanel();
			return;
		}
		panel = document.createElement( 'div' );
		panel.id = 'atomora-grid-panel';
		panel.style.cssText = 'position:fixed;top:56px;right:16px;z-index:100000;width:230px;padding:12px 14px;border-radius:6px;' +
			'background:var(--e-a-bg-default,#fff);color:var(--e-a-color-txt,#515962);box-shadow:0 4px 16px rgba(0,0,0,.25);font-size:12px';
		panel.innerHTML = '<div data-drag style="cursor:move;user-select:none;touch-action:none;display:flex;justify-content:space-between;align-items:center;margin:-4px -6px 6px;padding:4px 6px"><strong>Layout grid</strong><span data-close style="cursor:pointer;padding:0 4px;font-size:16px;line-height:1">&times;</span></div>' +
			field( 'd', 'Columns (desktop)', 1, MAX ) +
			field( 't', 'Columns (tablet)', 1, MAX ) +
			field( 'm', 'Columns (mobile)', 1, MAX ) +
			field( 'gap', 'Gutter (px)', 0, 200 ) +
			field( 'margin', 'Margin (px)', 0, 400 ) +
			field( 'w', 'Grid width (desktop, 0 = full)', 0, 4000 ) +
			field( 'w_t', 'Grid width (tablet)', 0, 4000 ) +
			field( 'w_m', 'Grid width (mobile)', 0, 4000 ) +
			field( 'h', 'Grid height (desktop, 0 = full)', 0, 4000 ) +
			field( 'h_t', 'Grid height (tablet)', 0, 4000 ) +
			field( 'h_m', 'Grid height (mobile)', 0, 4000 );
		panel.addEventListener( 'input', function ( e ) {
			var key = e.target.getAttribute( 'data-k' );
			var val = parseInt( e.target.value, 10 );
			if ( ! key || isNaN( val ) ) {
				return;
			}
			var min = parseInt( e.target.min, 10 );
			var max = parseInt( e.target.max, 10 );
			cfg[ key ] = Math.min( max, Math.max( min, val ) );
			save();
			apply();
		} );
		document.body.appendChild( panel );
		placePanel();
		makeDraggable( panel );
	}

	function placePanel() {
		var pos = null;
		try {
			pos = JSON.parse( window.localStorage.getItem( POS_STORE ) || 'null' );
		} catch ( e ) {}
		if ( pos && 'number' === typeof pos.x && 'number' === typeof pos.y ) {
			moveTo( pos.x, pos.y );
		}
	}

	function moveTo( x, y ) {
		var maxX = Math.max( 0, window.innerWidth - panel.offsetWidth );
		var maxY = Math.max( 0, window.innerHeight - panel.offsetHeight );
		panel.style.right = 'auto';
		panel.style.left = Math.min( maxX, Math.max( 0, x ) ) + 'px';
		panel.style.top = Math.min( maxY, Math.max( 0, y ) ) + 'px';
	}

	function makeDraggable( el ) {
		var handle = el.querySelector( '[data-drag]' );
		var start = null;

		el.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-close]' ) ) {
				closePanel();
			}
		} );

		handle.addEventListener( 'pointerdown', function ( e ) {
			if ( e.target.closest( '[data-close]' ) ) {
				return;
			}
			var rect = el.getBoundingClientRect();
			start = { dx: e.clientX - rect.left, dy: e.clientY - rect.top };
			handle.setPointerCapture( e.pointerId );
			e.preventDefault();
		} );
		handle.addEventListener( 'pointermove', function ( e ) {
			if ( start ) {
				moveTo( e.clientX - start.dx, e.clientY - start.dy );
			}
		} );
		handle.addEventListener( 'pointerup', function ( e ) {
			if ( ! start ) {
				return;
			}
			start = null;
			handle.releasePointerCapture( e.pointerId );
			var rect = el.getBoundingClientRect();
			try {
				window.localStorage.setItem( POS_STORE, JSON.stringify( { x: rect.left, y: rect.top } ) );
			} catch ( err ) {}
		} );
	}

	function svg( d ) {
		return function Icon( props ) {
			var R = window.React;
			return R.createElement( window.elementorV2.ui.SvgIcon, props, R.createElement( 'path', { d: d } ) );
		};
	}

	var GridIcon = svg( 'M3 3h4v18H3zM10 3h4v18h-4zM17 3h4v18h-4z' );
	var GearIcon = svg( 'M19.14 12.94a7.07 7.07 0 000-1.88l2.03-1.58-1.92-3.32-2.39.96a7 7 0 00-1.63-.94L14.87 3h-3.84l-.36 2.18a7 7 0 00-1.63.94l-2.39-.96-1.92 3.32 2.03 1.58a7.07 7.07 0 000 1.88l-2.03 1.58 1.92 3.32 2.39-.96c.5.38 1.04.7 1.63.94l.36 2.18h3.84l.36-2.18a7 7 0 001.63-.94l2.39.96 1.92-3.32zM12.95 15.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z' );

	function useSync() {
		var R = window.React;
		var state = R.useState( on );
		R.useEffect( function () {
			function sync() {
				state[ 1 ]( on );
			}
			window.addEventListener( 'atomora-grid-overlay', sync );
			return function () {
				window.removeEventListener( 'atomora-grid-overlay', sync );
			};
		}, [] );
		return state[ 0 ];
	}

	function useToggleProps() {
		return { title: 'Layout grid', icon: GridIcon, selected: useSync(), onClick: toggle };
	}

	function useSettingsProps() {
		return { title: 'Layout grid settings', icon: GearIcon, selected: false, visible: useSync(), onClick: openPanel };
	}

	function init() {
		var bar = window.elementorV2 && window.elementorV2.editorAppBar;
		if ( ! bar || ! bar.utilitiesMenu ) {
			return false;
		}
		bar.utilitiesMenu.registerToggleAction( { id: 'atomora-grid-overlay', priority: 25, useProps: useToggleProps } );
		bar.utilitiesMenu.registerToggleAction( { id: 'atomora-grid-overlay-settings', priority: 26, useProps: useSettingsProps } );
		setInterval( apply, 1000 );
		return true;
	}

	load();

	// The app bar package may load after this script, so retry for a few seconds.
	if ( ! init() ) {
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			if ( init() || tries > 40 ) {
				clearInterval( timer );
			}
		}, 250 );
	}
}() );