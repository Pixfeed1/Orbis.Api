/**
 * Colisly Parcel Forwarding — admin scripts.
 *
 * Handles the client record tabs, the live client search on the parcel
 * creation form and the carriers settings checkboxes.
 */
( function ( $ ) {
	'use strict';

	// Tabs on the client record page.
	$( '.colisly-tabs .nav-tab' ).on( 'click', function ( e ) {
		e.preventDefault();

		var target = $( this ).attr( 'href' );

		$( '.colisly-tabs .nav-tab' ).removeClass( 'nav-tab-active' );
		$( this ).addClass( 'nav-tab-active' );

		$( '.colisly-tab-panel' ).removeClass( 'colisly-tab-active' );
		$( target ).addClass( 'colisly-tab-active' );
	} );

	// Carrier enabled checkboxes mirror their value into a hidden field so
	// unchecked rows still submit an explicit 0.
	/*
	 * The settings tables used to grow by exactly one blank row per save, so
	 * filling in six weight brackets meant saving six times, and nothing on
	 * screen said a seventh row was even possible. Cloning the last row keeps
	 * the whole grid enterable in one pass.
	 */
	$( document ).on( 'click', '.colisly-add-row', function () {
		var $table = $( this ).closest( 'p' ).prev( 'table' );
		var $last = $table.find( 'tbody tr' ).last();

		if ( ! $last.length ) {
			return;
		}

		var $row = $last.clone();

		$row.find( 'input' ).each( function () {
			var $input = $( this );

			if ( $input.is( ':checkbox' ) ) {
				$input.prop( 'checked', true );
			} else {
				$input.val( '' );
			}

			// Ids only serve the screen-reader labels; duplicating them would
			// point every label at the first row.
			$input.removeAttr( 'id' );
		} );
		$row.find( 'label' ).removeAttr( 'for' );

		$table.find( 'tbody' ).append( $row );
		$row.find( 'input' ).first().trigger( 'focus' );
	} );

	/*
	 * An unchecked checkbox is not posted at all, which would shift every
	 * carrier row by one. Each one is therefore paired with a hidden field
	 * that always is, and carries the real value.
	 */
	$( document ).on( 'change', '.colisly-toggle', function () {
		$( this )
			.closest( 'td' )
			.find( '.colisly-toggle-value' )
			.val( this.checked ? '1' : '0' );
	} );

	// Live client search on the parcel creation form.
	var $search = $( '#colisly-client-search-input' );

	if ( ! $search.length || 'undefined' === typeof window.colislyAdmin ) {
		return;
	}

	var $results = $( '#colisly-client-results' );
	var $clientId = $( '#colisly-client-id' );
	var $stock = $( '#colisly-client-stock' );
	var timer = null;

	function esc( text ) {
		return $( '<span>' ).text( String( text ) ).html();
	}

	function renderStock( client ) {
		var i18n = window.colislyAdmin.i18n;
		var html = '<h3>' + esc( client.reference ) + ' — ' + esc( client.name ) + ' : ' + client.in_stock + ' ' + esc( i18n.inStock ) + '</h3>';

		html += '<table class="wp-list-table widefat fixed striped colisly-stock-table"><thead><tr>';
		html += '<th>' + esc( i18n.refCol ) + '</th><th>' + esc( i18n.weightCol ) + '</th><th>' + esc( i18n.groupingCol ) + '</th><th>' + esc( i18n.noteCol ) + '</th>';
		html += '</tr></thead><tbody>';

		if ( ! client.parcels.length ) {
			html += '<tr><td colspan="4">' + esc( i18n.noParcels ) + '</td></tr>';
		} else {
			client.parcels.forEach( function ( parcel ) {
				html += '<tr><td>' + esc( parcel.reference ) + '</td><td>' + esc( parcel.weight ) + '</td><td>' +
					esc( parcel.allow_grouping ? i18n.yes : i18n.no ) + '</td><td>' +
					esc( parcel.internal_note || '—' ) + '</td></tr>';
			} );
		}

		html += '</tbody></table>';
		$stock.html( html );
	}

	function renderResults( clients ) {
		$results.empty();

		if ( ! clients.length ) {
			$results.append(
				$( '<span class="colisly-client-result" />' ).text( window.colislyAdmin.i18n.noResults )
			);
			$results.addClass( 'colisly-open' );
			return;
		}

		clients.forEach( function ( client ) {
			var $item = $( '<button type="button" class="colisly-client-result" role="option" />' );

			$item.append( $( '<span class="colisly-result-ref" />' ).text( client.reference + ' — ' + client.name ) );
			$item.append( $( '<span class="colisly-result-meta" />' ).text( client.email + ( client.phone ? ' · ' + client.phone : '' ) ) );

			$item.on( 'click', function () {
				$clientId.val( client.id );
				$search.val( client.reference + ' — ' + client.name );
				$results.removeClass( 'colisly-open' ).empty();
				renderStock( client );
			} );

			$results.append( $item );
		} );

		$results.addClass( 'colisly-open' );
	}

	$search.on( 'input', function () {
		var term = $.trim( $search.val() );

		$clientId.val( '' );
		window.clearTimeout( timer );

		if ( term.length < 2 ) {
			$results.removeClass( 'colisly-open' ).empty();
			return;
		}

		timer = window.setTimeout( function () {
			$.getJSON( window.colislyAdmin.ajaxUrl, {
				action: 'colisly_search_clients',
				nonce: window.colislyAdmin.nonce,
				term: term
			} ).done( function ( response ) {
				if ( response && response.success ) {
					renderResults( response.data );
				}
			} );
		}, 250 );
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#colisly-client-results, #colisly-client-search-input' ).length ) {
			$results.removeClass( 'colisly-open' );
		}
	} );
} )( jQuery );
