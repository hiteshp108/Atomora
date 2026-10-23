/**
 * Atomora - Toggle Style Tabs
 *
 * Injects a bar below the classes area in the Atomic Element panel:
 *   - A static search input placeholder (future feature)
 *   - An expand/collapse all toggle icon button
 *
 * Insertion point: right before the first MUI Accordion section
 * (Layout, Spacing, Size, etc.) which use .MuiAccordionSummary-root.
 *
 * @package Atomora
 * @since 1.0.0
 */

( function () {
	'use strict';

	var BAR_ID               = 'atp-tab-toggle-bar';
	var debounceTimer        = null;
	var allExpanded          = false;
	var searchExpandedBtns   = []; // sections auto-expanded by search
	var filterGeneration     = 0;  // incremented to cancel in-flight sequences
	var expandDebounceTimer  = null;

	/**
	 * Find the Style tab's MuiList-root, but only if the Style tab is
	 * currently active. Returns null if we're on any other tab.
	 */
	function getStyleList() {
		var panel = document.getElementById( 'elementor-panel' );
		if ( ! panel ) return null;

		// Find the Style tab button (role="tab" with "Style" label).
		var tabs      = Array.from( panel.querySelectorAll( '[role="tab"]' ) );
		var styleTab  = null;
		for ( var i = 0; i < tabs.length; i++ ) {
			if ( ( tabs[ i ].textContent || '' ).trim().toLowerCase() === 'style' ) {
				styleTab = tabs[ i ];
				break;
			}
		}
		if ( ! styleTab ) return null;
		if ( styleTab.getAttribute( 'aria-selected' ) !== 'true' ) return null;

		// Prefer the tab panel referenced by aria-controls.
		var tabPanel  = null;
		var controls  = styleTab.getAttribute( 'aria-controls' );
		if ( controls ) {
			tabPanel = document.getElementById( controls );
		}

		// Fallback: find the first visible tabpanel.
		if ( ! tabPanel ) {
			var panels = Array.from( panel.querySelectorAll( '[role="tabpanel"]' ) );
			for ( var j = 0; j < panels.length; j++ ) {
				if ( ! panels[ j ].hidden ) { tabPanel = panels[ j ]; break; }
			}
		}

		var root = tabPanel
			? tabPanel.querySelector( '.MuiList-root' )
			: panel.querySelector( '.MuiList-root' );

		return root || null;
	}

	/**
	 * Check if the panel is showing an Atomic Element on its Style tab.
	 */
	function isAtomicPanel() {
		return !! getStyleList();
	}

	/**
	 * Get all style section toggle buttons inside the MuiList.
	 * These are MuiListItemButton-root elements (not standard accordions —
	 * they have no aria-expanded; open state is on the MuiCollapse sibling).
	 */
	function getAccordions() {
		var list = getStyleList();
		if ( ! list ) return [];
		return Array.from( list.querySelectorAll( '.MuiListItemButton-root' ) );
	}

	/**
	 * Check if a section button is currently open.
	 * Open = its MuiCollapse sibling has the MuiCollapse-entered class,
	 * or the button itself has aria-controls pointing to an entered panel.
	 */
	function isOpen( btn ) {
		var controls = btn.getAttribute( 'aria-controls' );
		if ( controls ) {
			var panel = document.getElementById( controls );
			if ( panel ) return panel.classList.contains( 'MuiCollapse-entered' );
		}
		// Fallback: check next sibling
		var sibling = btn.nextElementSibling;
		if ( sibling && sibling.classList.contains( 'MuiCollapse-root' ) ) {
			return sibling.classList.contains( 'MuiCollapse-entered' );
		}
		return false;
	}

	// Lucide chevrons-down-up (pointing inward = collapse state)
	var ICON_COLLAPSE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 20 5-5 5 5"/><path d="m7 4 5 5 5-5"/></svg>';
	// Lucide chevrons-up-down (pointing outward = expand state)
	var ICON_EXPAND   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>';

	/**
	 * Update the toggle button icon based on current state.
	 */
	function updateToggleIcon( btn ) {
		if ( ! btn ) return;
		if ( allExpanded ) {
			btn.innerHTML = ICON_COLLAPSE;
			btn.title = 'Collapse all sections';
		} else {
			btn.innerHTML = ICON_EXPAND;
			btn.title = 'Expand all sections';
		}
	}

	/**
	 * Click each section button sequentially with a small delay,
	 * so React has time to re-render between each click.
	 *
	 * Captures the current filterGeneration so it can bail out if a
	 * newer filter run has started while this sequence is in progress.
	 *
	 * @param {Array}   elements  Buttons to process.
	 * @param {boolean} expand    True = open all, false = close all.
	 * @param {Element} toggleBtn The manual toggle button, or null for search-triggered calls.
	 */
	function clickSequentially( elements, expand, toggleBtn ) {
		var gen      = filterGeneration;
		var filtered = elements.filter( function ( el ) {
			return isOpen( el ) !== expand;
		} );

		var i = 0;
		function next() {
			// A newer filter has started — stop this sequence.
			if ( filterGeneration !== gen ) return;

			if ( i >= filtered.length ) {
				if ( ! expand ) {
					// Collapse finished — wait for any sections that were still
					// mid-animation when we started, then close stragglers.
					setTimeout( function () {
						if ( filterGeneration !== gen ) return;
						var stragglers = elements.filter( isOpen );
						stragglers.forEach( function ( el ) { el.click(); } );
						if ( toggleBtn ) {
							allExpanded = false;
							updateToggleIcon( toggleBtn );
						}
					}, 400 );
				} else {
					// Only update allExpanded/icon for manual toggle clicks, not search.
					if ( toggleBtn ) {
						allExpanded = expand;
						updateToggleIcon( toggleBtn );
					}
				}
				return;
			}
			filtered[ i ].click();
			i++;
			setTimeout( next, 80 );
		}
		next();
	}

	/**
	 * Toggle expand / collapse all sections.
	 */
	function toggleAll( btn ) {
		filterGeneration++;
		clearTimeout( expandDebounceTimer );
		var accordions = getAccordions();
		clickSequentially( accordions, ! allExpanded, btn );
	}

	/**
	 * Static field map for all Atomic widget style sections.
	 * All Atomic widgets share the same sections and fields, so we
	 * hardcode this once rather than relying on DOM content that may
	 * not be rendered when sections are collapsed.
	 */
	var SECTION_FIELDS = {
		'layout':     [ 'display', 'flex', 'direction', 'wrap', 'justify', 'justify content',
		                'align', 'align items', 'gap', 'column gap', 'row gap', 'overflow',
		                'flexbox', 'flex direction', 'flex wrap' ],
		'spacing':    [ 'margin', 'padding', 'top', 'right', 'bottom', 'left',
		                'margin top', 'margin right', 'margin bottom', 'margin left',
		                'padding top', 'padding right', 'padding bottom', 'padding left' ],
		'size':       [ 'width', 'height', 'min width', 'max width', 'min height', 'max height',
		                'min-width', 'max-width', 'min-height', 'max-height',
		                'fit content', 'fill', 'auto' ],
		'position':   [ 'position', 'static', 'relative', 'absolute', 'fixed', 'sticky',
		                'top', 'right', 'bottom', 'left', 'offset', 'z-index', 'z index' ],
		'typography': [ 'font', 'font family', 'font size', 'font weight', 'font style',
		                'line height', 'letter spacing', 'word spacing', 'text',
		                'text decoration', 'text transform', 'text shadow', 'text align',
		                'color', 'italic', 'bold', 'underline', 'uppercase', 'capitalize',
		                'strike', 'decoration', 'transform', 'family', 'size', 'weight' ],
		'background': [ 'background', 'background color', 'background image', 'gradient',
		                'color', 'image', 'position', 'attachment', 'repeat', 'size',
		                'cover', 'contain', 'overlay', 'linear', 'radial' ],
		'border':     [ 'border', 'border type', 'border width', 'border color', 'border radius',
		                'radius', 'shadow', 'box shadow', 'outline', 'solid', 'dashed',
		                'dotted', 'groove', 'inset', 'width', 'color' ],
		'effects':    [ 'opacity', 'blur', 'brightness', 'contrast', 'grayscale', 'saturate',
		                'hue', 'hue rotate', 'invert', 'sepia', 'filter', 'css filter',
		                'backdrop', 'backdrop filter', 'blend', 'blend mode', 'mix blend',
		                'transform', 'rotate', 'scale', 'skew', 'translate',
		                'transition', 'duration', 'animation' ],
		'custom css': [ 'css', 'custom', 'custom css', 'selector', 'style', 'code', 'class' ],
	};

	/**
	 * Get the label text for a section button.
	 */
	function getSectionLabel( btn ) {
		var stack = btn.querySelector( '.MuiStack-root' );
		var text  = stack ? stack.textContent : btn.textContent;
		return ( text || '' ).trim().toLowerCase();
	}

	/**
	 * Check if a section matches the search query — either by its own
	 * label or by any field in the static SECTION_FIELDS map.
	 *
	 * @param  {string}  label  Section label in lowercase.
	 * @param  {string}  q      Search query in lowercase.
	 * @return {boolean}
	 */
	function sectionMatches( label, q ) {
		if ( label.indexOf( q ) > -1 ) return true;

		var fields = SECTION_FIELDS[ label ] || [];
		for ( var i = 0; i < fields.length; i++ ) {
			if ( fields[ i ].indexOf( q ) > -1 ) return true;
		}
		return false;
	}

	/**
	 * Filter style sections by search query.
	 *
	 * Show/hide is instant. Auto-expanding matched sections is debounced
	 * by 200ms so rapid typing doesn't fire overlapping click sequences.
	 * A generation counter cancels any in-flight sequences when a new
	 * filter runs.
	 *
	 * @param {string} query
	 */
	function filterSections( query ) {
		var buttons = getAccordions();
		var q       = query.trim().toLowerCase();

		// Cancel any in-flight expand/collapse sequence.
		filterGeneration++;
		clearTimeout( expandDebounceTimer );

		// Restore all sections and collapse any that search had auto-expanded.
		if ( q === '' ) {
			buttons.forEach( function ( btn ) {
				btn.style.display = '';
				var collapse = btn.nextElementSibling;
				if ( collapse && collapse.classList.contains( 'MuiCollapse-root' ) ) {
					collapse.style.display = '';
				}
			} );

			var toClose        = searchExpandedBtns.slice();
			searchExpandedBtns = [];

			if ( toClose.length ) {
				var closeGen = filterGeneration;

				// First attempt: close sections already fully open.
				var nowOpen = toClose.filter( isOpen );
				if ( nowOpen.length ) {
					clickSequentially( nowOpen, false, null );
				}

				// Second attempt after the MuiCollapse animation completes (~305ms).
				// Catches sections that were still transitioning to open when we cleared.
				setTimeout( function () {
					if ( filterGeneration !== closeGen ) return;
					var stillOpen = toClose.filter( isOpen );
					if ( stillOpen.length ) {
						clickSequentially( stillOpen, false, null );
					}
				}, 400 );
			}
			return;
		}

		var toExpand = [];

		// Show/hide instantly — no delay.
		buttons.forEach( function ( btn ) {
			var label    = getSectionLabel( btn );
			var collapse = btn.nextElementSibling;
			var matched  = sectionMatches( label, q );

			btn.style.display = matched ? '' : 'none';
			if ( collapse && collapse.classList.contains( 'MuiCollapse-root' ) ) {
				collapse.style.display = matched ? '' : 'none';
			}

			if ( matched && ! isOpen( btn ) ) {
				toExpand.push( btn );
			}
		} );

		// Debounce the actual click-to-expand so rapid typing doesn't
		// fire multiple overlapping sequences.
		if ( toExpand.length ) {
			expandDebounceTimer = setTimeout( function () {
				searchExpandedBtns = searchExpandedBtns.concat( toExpand );
				clickSequentially( toExpand, true, null );
			}, 200 );
		}
	}

	/**
	 * Create the bar with a live search input and expand/collapse toggle icon.
	 */
	function createBar() {
		var bar       = document.createElement( 'div' );
		bar.id        = BAR_ID;
		bar.className = 'atp-tab-toggle-bar';

		// Search input
		var searchWrap       = document.createElement( 'div' );
		searchWrap.className = 'atp-search-wrap';

		var searchIcon       = document.createElement( 'span' );
		searchIcon.className = 'atp-search-icon';
		searchIcon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

		var searchInput         = document.createElement( 'input' );
		searchInput.type        = 'text';
		searchInput.placeholder = 'Search';
		searchInput.className   = 'atp-search-input';

		searchInput.addEventListener( 'input', function () {
			filterSections( searchInput.value );
		} );

		// Clear search on Escape
		searchInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				searchInput.value = '';
				filterSections( '' );
			}
		} );

		searchWrap.appendChild( searchIcon );
		searchWrap.appendChild( searchInput );

		// Expand/collapse toggle button
		var toggleBtn       = document.createElement( 'button' );
		toggleBtn.className = 'atp-toggle-btn';
		toggleBtn.innerHTML = ICON_EXPAND;
		toggleBtn.title     = 'Expand all sections';
		toggleBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			toggleAll( toggleBtn );
		} );

		bar.appendChild( searchWrap );
		bar.appendChild( toggleBtn );
		return bar;
	}

	/**
	 * Remove the bar from the panel.
	 */
	function removeBar() {
		var bar = document.getElementById( BAR_ID );
		if ( bar && bar.parentNode ) bar.parentNode.removeChild( bar );
	}

	/**
	 * Inject the bar right before the MuiList-root (style sections).
	 */
	function injectBar() {
		var styleList = getStyleList();
		if ( ! styleList ) { removeBar(); return; }
		if ( document.getElementById( BAR_ID ) ) return;

		var parent = styleList.parentElement;
		if ( ! parent ) return;

		// Reset state each time bar is freshly injected.
		allExpanded = false;
		filterSections( '' );

		parent.insertBefore( createBar(), styleList );
	}

	/**
	 * Debounced mutation handler.
	 */
	function onMutation() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( function () {
			var styleList = getStyleList();
			if ( ! styleList ) {
				removeBar();
			} else if ( ! document.getElementById( BAR_ID ) ) {
				injectBar();
			}
		}, 80 );
	}

	/**
	 * Initialize: wait for panel, then observe for changes.
	 */
	function init() {
		var pollTimer = setInterval( function () {
			var panel = document.getElementById( 'elementor-panel' );
			if ( ! panel ) return;
			clearInterval( pollTimer );

			injectBar();

			var observer = new MutationObserver( onMutation );
			observer.observe( panel, { childList: true, subtree: true } );
		}, 500 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();