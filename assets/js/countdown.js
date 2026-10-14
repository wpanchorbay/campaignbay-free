/**
 * CampaignBay countdown timers.
 * Counts down client-side from the server-provided end/now epoch pair so the
 * visitor's clock skew does not matter. Initialises timers added later too.
 */
( function () {
	'use strict';

	var SELECTOR = '.campaignbay-countdown[data-end]';
	var timers = [];
	var ticking = false;

	function pad( n ) {
		return n < 10 ? '0' + n : String( n );
	}

	function format( seconds ) {
		var d = Math.floor( seconds / 86400 );
		var h = Math.floor( ( seconds % 86400 ) / 3600 );
		var m = Math.floor( ( seconds % 3600 ) / 60 );
		var s = seconds % 60;
		var clock = pad( h ) + ':' + pad( m ) + ':' + pad( s );
		return d > 0 ? d + 'd ' + clock : clock;
	}

	function register( el ) {
		if ( el.getAttribute( 'data-cb-init' ) ) {
			return;
		}
		el.setAttribute( 'data-cb-init', '1' );
		var end = parseInt( el.getAttribute( 'data-end' ), 10 );
		var now = parseInt( el.getAttribute( 'data-now' ), 10 );
		if ( ! end ) {
			return;
		}
		// Offset between server and visitor clock, measured when first seen.
		var offset = now ? now * 1000 - Date.now() : 0;
		timers.push( { el: el, end: end * 1000, offset: offset, time: el.querySelector( '.campaignbay-countdown__time' ) } );
	}

	function scan( root ) {
		var nodes = ( root || document ).querySelectorAll( SELECTOR );
		for ( var i = 0; i < nodes.length; i++ ) {
			register( nodes[ i ] );
		}
		if ( timers.length && ! ticking ) {
			ticking = true;
			window.setInterval( tick, 1000 );
		}
		tick();
	}

	function expire( t ) {
		var msg = t.el.getAttribute( 'data-expired' ) || '';
		if ( msg ) {
			t.el.textContent = msg;
			t.el.classList.add( 'is-expired' );
		} else {
			t.el.style.display = 'none';
		}
	}

	function tick() {
		for ( var i = timers.length - 1; i >= 0; i-- ) {
			var t = timers[ i ];
			if ( ! document.body.contains( t.el ) ) {
				timers.splice( i, 1 );
				continue;
			}
			var left = Math.ceil( ( t.end - ( Date.now() + t.offset ) ) / 1000 );
			if ( left <= 0 ) {
				expire( t );
				timers.splice( i, 1);
			} else if ( t.time ) {
				t.time.textContent = format( left );
			}
		}
	}

	/**
	 * Block cart / checkout: React renders item names from the product title, so the timer
	 * is injected from the Store API extension data (extensions.campaignbay) found in the
	 * wc/store/cart data store.
	 */
	function blockItems() {
		try {
			if ( ! window.wp || ! window.wp.data ) {
				return null;
			}
			var store = window.wp.data.select( 'wc/store/cart' );
			if ( ! store || ! store.getCartData ) {
				return null;
			}
			var data = store.getCartData();
			return data && data.items ? data.items : null;
		} catch ( e ) {
			return null;
		}
	}

	function syncBlocks() {
		var items = blockItems();
		var rows = document.querySelectorAll( '.wc-block-cart-items__row, .wc-block-components-order-summary-item' );
		if ( ! items || ! rows.length ) {
			return;
		}
		// Match rows to cart items by product permalink (never by array index: free BOGO lines and the
		// order summary can reorder or add rows). Rows sharing a permalink (e.g. a paid line plus its
		// free BOGO line) are paired in order within that permalink group; rows with no match get no timer.
		function normUrl( url ) {
			try {
				var u = new URL( url, window.location.href );
				return u.origin + u.pathname + u.search;
			} catch ( e ) {
				return '';
			}
		}
		var groups = {};
		items.forEach( function ( item ) {
			var cd = item.extensions && item.extensions.campaignbay;
			var key = normUrl( ( cd && cd.permalink ) || item.permalink || '' );
			if ( ! key ) {
				return;
			}
			( groups[ key ] = groups[ key ] || [] ).push( item );
		} );
		var seen = {};
		Array.prototype.forEach.call( rows, function ( row ) {
			var link = row.querySelector( 'a.wc-block-components-product-name' ) || row.querySelector( 'a[href]' );
			var key = link ? normUrl( link.getAttribute( 'href' ) || '' ) : '';
			var scope = row.classList.contains( 'wc-block-cart-items__row' ) ? 'cart' : 'summary';
			var item = null;
			if ( key && groups[ key ] ) {
				var n = seen[ scope + key ] || 0;
				seen[ scope + key ] = n + 1;
				item = groups[ key ][ n ] || null;
			}
			var cd = item && item.extensions && item.extensions.campaignbay;
			if ( cd && ! ( cd.end && cd.text ) ) {
				cd = null;
			}
			var holder = row.querySelector( '.wc-block-components-product-name' );
			var existing = row.querySelector( '.campaignbay-countdown--block' );
			if ( ! cd || ! holder ) {
				if ( existing ) {
					existing.remove();
				}
				return;
			}
			if ( existing && existing.getAttribute( 'data-campaign' ) === String( cd.campaign ) && existing.getAttribute( 'data-end' ) === String( cd.end ) ) {
				return;
			}
			if ( existing ) {
				existing.remove();
			}
			var el = document.createElement( 'div' );
			el.className = 'campaignbay-countdown campaignbay-countdown--cart campaignbay-countdown--block';
			el.setAttribute( 'data-end', cd.end );
			el.setAttribute( 'data-now', cd.now );
			el.setAttribute( 'data-campaign', cd.campaign );
			el.setAttribute( 'data-expired', cd.expired || '' );
			var parts = String( cd.text ).split( '{countdown}' );
			parts.forEach( function ( part, i ) {
				if ( i > 0 ) {
					var t = document.createElement( 'span' );
					t.className = 'campaignbay-countdown__time';
					el.appendChild( t );
				}
				el.appendChild( document.createTextNode( part ) );
			} );
			holder.parentNode.insertBefore( el, holder.nextSibling );
			register( el );
		} );
		tick();
	}

	function init() {
		scan();
		syncBlocks();
		if ( 'MutationObserver' in window ) {
			var pending = false;
			new MutationObserver( function () {
				if ( pending ) {
					return;
				}
				pending = true;
				window.setTimeout( function () {
					pending = false;
					scan();
					syncBlocks();
				}, 100 );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
