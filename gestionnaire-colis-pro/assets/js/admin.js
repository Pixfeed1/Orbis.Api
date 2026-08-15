/**
 * Gestionnaire Colis Pro — admin scripts.
 *
 * Handles the client record tabs, the live client search on the parcel
 * creation form and the carriers settings checkboxes.
 */
( function ( $ ) {
	'use strict';

	// Tabs on the client record page.
	$( '.gcp-tabs .nav-tab' ).on( 'click', function ( e ) {
		e.preventDefault();

		var target = $( this ).attr( 'href' );

		$( '.gcp-tabs .nav-tab' ).removeClass( 'nav-tab-active' );
		$( this ).addClass( 'nav-tab-active' );

		$( '.gcp-tab-panel' ).removeClass( 'gcp-tab-active' );
		$( target ).addClass( 'gcp-tab-active' );
	} );

	// Carrier enabled checkboxes mirror their value into a hidden field so
	// unchecked rows still submit an explicit 0.
	$( document ).on( 'change', '.gcp-carrier-enabled', function () {
		$( this )
			.closest( 'td' )
			.find( '.gcp-carrier-enabled-value' )
			.val( this.checked ? '1' : '0' );
	} );

	// Live client search on the parcel creation form.
	var $search = $( '#gcp-client-search-input' );

	if ( ! $search.length || 'undefined' === typeof window.gcpAdmin ) {
		return;
	}

	var $results = $( '#gcp-client-results' );
	var $clientId = $( '#gcp-client-id' );
	var $stock = $( '#gcp-client-stock' );
	var timer = null;

	function esc( text ) {
		return $( '<span>' ).text( String( text ) ).html();
	}

	function renderStock( client ) {
		var i18n = window.gcpAdmin.i18n;
		var html = '<h3>' + esc( client.reference ) + ' — ' + esc( client.name ) + ' : ' + client.in_stock + ' ' + esc( i18n.inStock ) + '</h3>';

		html += '<table class="wp-list-table widefat fixed striped gcp-stock-table"><thead><tr>';
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
				$( '<span class="gcp-client-result" />' ).text( window.gcpAdmin.i18n.noResults )
			);
			$results.addClass( 'gcp-open' );
			return;
		}

		clients.forEach( function ( client ) {
			var $item = $( '<button type="button" class="gcp-client-result" role="option" />' );

			$item.append( $( '<span class="gcp-result-ref" />' ).text( client.reference + ' — ' + client.name ) );
			$item.append( $( '<span class="gcp-result-meta" />' ).text( client.email + ( client.phone ? ' · ' + client.phone : '' ) ) );

			$item.on( 'click', function () {
				$clientId.val( client.id );
				$search.val( client.reference + ' — ' + client.name );
				$results.removeClass( 'gcp-open' ).empty();
				renderStock( client );
			} );

			$results.append( $item );
		} );

		$results.addClass( 'gcp-open' );
	}

	$search.on( 'input', function () {
		var term = $.trim( $search.val() );

		$clientId.val( '' );
		window.clearTimeout( timer );

		if ( term.length < 2 ) {
			$results.removeClass( 'gcp-open' ).empty();
			return;
		}

		timer = window.setTimeout( function () {
			$.getJSON( window.gcpAdmin.ajaxUrl, {
				action: 'gcp_search_clients',
				nonce: window.gcpAdmin.nonce,
				term: term
			} ).done( function ( response ) {
				if ( response && response.success ) {
					renderResults( response.data );
				}
			} );
		}, 250 );
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#gcp-client-results, #gcp-client-search-input' ).length ) {
			$results.removeClass( 'gcp-open' );
		}
	} );
} )( jQuery );
