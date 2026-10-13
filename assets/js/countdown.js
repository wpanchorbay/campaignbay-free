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

	function init() {
		scan();
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
