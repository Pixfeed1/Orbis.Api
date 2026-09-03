/**
 * Colisly Parcel Forwarding — front scripts.
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

			// And only if the whole shipment stays under what it takes: grouped
			// parcels leave in one carton, so the real weights add up.
			var maxWeight = parseFloat( option.getAttribute( 'data-max-weight' ) || '0' );
			if ( allowed && maxWeight > 0 ) {
				var real = 0;
				selected.forEach( function ( box ) {
					real += parseFloat( box.getAttribute( 'data-weight' ) || '0' );
				} );
				allowed = real <= maxWeight + 0.0005;
			}

			option.disabled = ! allowed;
			if ( ! allowed && option.selected ) {
				select.value = '';
			}
		} );
	}

	/*
	 * Same rule as COLISLY_Carriers::price_for on the server: the first bracket
	 * whose maximum weight covers the shipment sets the price, and past the last
	 * bracket the base + per-kg formula applies without ever charging less than
	 * that last bracket. Computing base + per-kg here regardless showed 17 for a
	 * 6 kg shipment the checkout then billed 45.
	 */
	function carrierPrice( option, weight ) {
		var base = parseFloat( option.getAttribute( 'data-base' ) || '0' );
		var rate = parseFloat( option.getAttribute( 'data-rate' ) || '0' );
		var tiers = [];

		try {
			tiers = JSON.parse( option.getAttribute( 'data-tiers' ) || '[]' ) || [];
		} catch ( e ) {
			tiers = [];
		}

		// A destination the carrier was priced for overrides its default grid,
		// the same way the server resolves the zone.
		var country = document.getElementById( 'colisly-country' );
		if ( country && country.value ) {
			try {
				var byCountry = JSON.parse( option.getAttribute( 'data-zone-tiers' ) || '{}' ) || {};
				if ( byCountry[ country.value ] ) {
					tiers = byCountry[ country.value ];
				}
			} catch ( e2 ) {}
		}

		for ( var i = 0; i < tiers.length; i++ ) {
			if ( weight <= tiers[ i ].w ) {
				return tiers[ i ].p;
			}
		}

		var price = base + rate * weight;

		if ( tiers.length ) {
			price = Math.max( price, tiers[ tiers.length - 1 ].p );
		}

		return price;
	}

	/*
	 * Same rule as COLISLY_Carriers::chargeable_weight: an express carrier
	 * bills whichever is greater, the real weight or the volume divided by
	 * its divisor, parcel by parcel. A parcel with no dimensions has no
	 * volume and keeps its real weight.
	 */
	function chargeableWeight( option, selected ) {
		var volumetric = '1' === option.getAttribute( 'data-volumetric' );
		var divisor = parseFloat( option.getAttribute( 'data-divisor' ) || '5000' );

		if ( ! ( divisor > 0 ) ) {
			divisor = 5000;
		}

		var weight = 0;

		selected.forEach( function ( box ) {
			var real = parseFloat( box.getAttribute( 'data-weight' ) || '0' );
			var volume = parseFloat( box.getAttribute( 'data-volume' ) || '0' );

			weight += volumetric ? Math.max( real, volume / divisor ) : real;
		} );

		return weight;
	}

	/*
	 * Every carrier shows what it would charge for the parcels ticked, so the
	 * client compares before choosing rather than trying them one by one.
	 * With nothing ticked the labels go back to what the server printed.
	 */
	function updateCarrierPrices() {
		var selected = selectedBoxes();

		Array.prototype.slice.call( select.options ).forEach( function ( option ) {
			if ( ! option.value ) {
				return;
			}

			if ( null === option.getAttribute( 'data-label' ) ) {
				option.setAttribute( 'data-label', option.textContent );
			}

			var label = option.getAttribute( 'data-label' );

			if ( ! selected.length ) {
				option.textContent = label;
				return;
			}

			var name = option.getAttribute( 'data-name' ) || label;

			option.textContent = name + ' — ' + formatPrice( carrierPrice( option, chargeableWeight( option, selected ) ) );
		} );
	}

	function updateEstimate() {
		var selected = selectedBoxes();
		var option = select.options[ select.selectedIndex ];

		if ( ! selected.length || ! option || ! option.value ) {
			estimate.hidden = true;
			return;
		}

		var total = 0;

		selected.forEach( function ( box ) {
			total += parseFloat( box.getAttribute( 'data-price' ) || '0' );
			total += parseFloat( box.getAttribute( 'data-storage' ) || '0' );
		} );

		total += carrierPrice( option, chargeableWeight( option, selected ) );

		// The cover level the client picked, when insurance is offered.
		var insurance = document.getElementById( 'colisly-insurance' );
		if ( insurance ) {
			var level = insurance.options[ insurance.selectedIndex ];
			total += parseFloat( ( level && level.getAttribute( 'data-price' ) ) || '0' );
		}

		amount.textContent = formatPrice( total );
		estimate.hidden = false;
	}

	var insuranceSelect = document.getElementById( 'colisly-insurance' );
	if ( insuranceSelect ) {
		insuranceSelect.addEventListener( 'change', updateEstimate );
	}

	boxes.forEach( function ( box ) {
		box.addEventListener( 'change', function () {
			updateCarrierOptions();
			updateCarrierPrices();
			updateEstimate();
		} );
	} );

	updateCarrierPrices();

	select.addEventListener( 'change', updateEstimate );
} )();

/**
 * Confirmation before a client withdraws his own shipment request.
 *
 * Its own closure: the estimate above bails out on any page without the
 * request form, and the shipments list is exactly such a page.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-colisly-confirm]' ) : null;

		if ( ! button ) {
			return;
		}

		if ( ! window.confirm( button.getAttribute( 'data-colisly-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
} )();

/**
 * One more declaration line, when the forwarder set no limit.
 *
 * A capped declaration is rendered with all its lines at once, so the button
 * only exists when there is no cap and no number would have been right.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.colisly-add-customs-line' ) : null;

		if ( ! button ) {
			return;
		}

		var wrap = button.closest( 'p' ).previousElementSibling;
		var body = wrap ? wrap.querySelector( 'tbody' ) : null;

		if ( ! body || ! body.rows.length ) {
			return;
		}

		var index = body.rows.length;
		var row = body.rows[ index - 1 ].cloneNode( true );

		Array.prototype.forEach.call( row.querySelectorAll( 'input, select' ), function ( field ) {
			// Every line posts under its own index; keeping the cloned one
			// would have overwritten the line it was copied from.
			field.name = field.name.replace( /\[(\d+)\](\[[a-z_]+\])$/, '[' + index + ']$2' );

			if ( 'number' === field.type ) {
				field.value = '1';
			} else {
				field.value = '';
			}
		} );

		body.appendChild( row );

		var first = row.querySelector( 'input, select' );
		if ( first ) {
			first.focus();
		}
	} );
} )();

/**
 * A declared line needs its value: the browser asks for it as soon as the
 * contents are filled, and the server refuses the line without it anyway.
 */
( function () {
	'use strict';

	function syncRow( field ) {
		var row = field.closest ? field.closest( 'tr' ) : null;

		if ( ! row ) {
			return;
		}

		var description = row.querySelector( '[name$="[description]"]' );
		var value = row.querySelector( '[name$="[unit_value]"]' );

		if ( ! description || ! value ) {
			return;
		}

		var filled = '' !== String( description.value || '' ).trim();

		value.required = filled;
		if ( filled ) {
			value.setAttribute( 'min', '0.01' );
			value.setAttribute( 'inputmode', 'decimal' );
		} else {
			value.removeAttribute( 'min' );
		}
	}

	document.addEventListener( 'input', function ( event ) {
		if ( event.target && /\[description\]$/.test( event.target.name || '' ) ) {
			syncRow( event.target );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( event.target && /\[description\]$/.test( event.target.name || '' ) ) {
			syncRow( event.target );
		}
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '[name$="[description]"]' ), syncRow );
} )();
