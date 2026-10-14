( function () {
	'use strict';

	function findQty( wrap ) {
		var scope = wrap.closest( 'form.cart' );
		var qty = scope ? scope.querySelector( 'input.qty' ) : null;
		if ( qty ) {
			return qty;
		}
		// Not inside the add-to-cart form: use the nearest ancestor that holds a quantity input.
		scope = wrap.closest( '.product, li.product, .wc-block-grid__product, [id^="product-"]' );
		qty = scope ? scope.querySelector( 'input.qty' ) : null;
		if ( qty ) {
			return qty;
		}
		scope = wrap.parentElement;
		while ( scope && scope !== document.body ) {
			qty = scope.querySelector( 'input.qty' );
			if ( qty ) {
				return qty;
			}
			scope = scope.parentElement;
		}
		return document.querySelector( 'form.cart input.qty' );
	}

	function setup( wrap ) {
		if ( wrap.getAttribute( 'data-cb-bound' ) ) {
			return;
		}
		var cards = Array.prototype.slice.call( wrap.querySelectorAll( '.campaignbay-discount-card' ) );
		var qty = findQty( wrap );
		if ( ! qty || ! cards.length ) {
			return;
		}
		wrap.setAttribute( 'data-cb-bound', '1' );

		function num( v, d ) {
			var n = parseFloat( v );
			return isNaN( n ) ? d : n;
		}

		function highlight( value ) {
			var active = null;
			cards.forEach( function ( c ) {
				var min = num( c.getAttribute( 'data-min' ), 0 );
				if ( value >= min && ( ! active || min >= num( active.getAttribute( 'data-min' ), 0 ) ) ) {
					active = c;
				}
			} );
			cards.forEach( function ( c ) {
				var on = c === active;
				c.classList.toggle( 'is-selected', on );
				c.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}

		function select( card ) {
			var target = num( card.getAttribute( 'data-min' ), 1 );
			var step = num( qty.getAttribute( 'step' ), 1 );
			var min = num( qty.getAttribute( 'min' ), 0 );
			var max = num( qty.getAttribute( 'max' ), Infinity );
			if ( step > 0 ) {
				target = min + Math.ceil( ( target - min ) / step ) * step;
			}
			target = Math.max( target, min );
			if ( target > max ) {
				target = max;
			}
			qty.value = target;
			qty.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			qty.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			highlight( target );
		}

		cards.forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				select( card );
			} );
			card.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key || 'Spacebar' === e.key ) {
					e.preventDefault();
					select( card );
				}
			} );
		} );

		function sync() {
			highlight( num( qty.value, 0 ) );
		}
		qty.addEventListener( 'input', sync );
		qty.addEventListener( 'change', sync );
		sync();
	}

	function init() {
		var groups = document.querySelectorAll( '.campaignbay-discount-cards' );
		Array.prototype.forEach.call( groups, setup );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
