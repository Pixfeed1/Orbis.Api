/**
 * Gestionnaire Colis Pro — front scripts.
 *
 * Live estimate on the shipment request form: parcels + storage fees +
 * carrier tariff (base + per-kg). The server remains the authority; this is
 * a convenience preview only.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.colisly-request-form' );

	if ( ! form ) {
		return;
	}

	var boxes = Array.prototype.slice.call( form.querySelectorAll( 'input[name="colisly_parcels[]"]' ) );
	var select = form.querySelector( '#colisly-carrier' );
	var estimate = document.getElementById( 'colisly-estimate' );
	var amount = document.getElementById( 'colisly-estimate-amount' );

	if ( ! select || ! estimate || ! amount ) {
		return;
	}

	function formatPrice( value ) {
		var symbol = window.colislyFront && window.colislyFront.currencySymbol ? window.colislyFront.currencySymbol : '';
		var amount = value.toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

		return symbol ? amount + ' ' + symbol : amount;
	}

	function selectedBoxes() {
		return boxes.filter( function ( box ) {
			return box.checked;
		} );
	}

	function updateCarrierOptions() {
		var selected = selectedBoxes();

		Array.prototype.slice.call( select.options ).forEach( function ( option ) {
			if ( ! option.value ) {
				return;
			}

			// A carrier stays selectable only if every selected parcel allows it.
			var allowed = selected.every( function ( box ) {
				var list = ( box.getAttribute( 'data-carriers' ) || '' ).split( ',' ).filter( Boolean );

				return 0 === list.length || -1 !== list.indexOf( option.value );
			} );

			option.disabled = ! allowed;
			if ( ! allowed && option.selected ) {
				select.value = '';
			}
		} );
	}

	function updateEstimate() {
		var selected = selectedBoxes();
		var option = select.options[ select.selectedIndex ];

		if ( ! selected.length || ! option || ! option.value ) {
			estimate.hidden = true;
			return;
		}

		var weight = 0;
		var total = 0;

		selected.forEach( function ( box ) {
			weight += parseFloat( box.getAttribute( 'data-weight' ) || '0' );
			total += parseFloat( box.getAttribute( 'data-price' ) || '0' );
			total += parseFloat( box.getAttribute( 'data-storage' ) || '0' );
		} );

		total += parseFloat( option.getAttribute( 'data-base' ) || '0' );
		total += parseFloat( option.getAttribute( 'data-rate' ) || '0' ) * weight;

		amount.textContent = formatPrice( total );
		estimate.hidden = false;
	}

	boxes.forEach( function ( box ) {
		box.addEventListener( 'change', function () {
			updateCarrierOptions();
			updateEstimate();
		} );
	} );

	select.addEventListener( 'change', updateEstimate );
} )();
