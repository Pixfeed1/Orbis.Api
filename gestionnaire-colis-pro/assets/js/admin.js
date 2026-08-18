/**
 * Gestionnaire Colis Pro — admin scripts.
 *
 * Handles the client record tabs, the live client search on the parcel
 * creation form and the carriers settings checkboxes.
 */
( function ( $ ) {
	'use strict';

	// Tabs on the client record page.
	$( '.pxfwd-tabs .nav-tab' ).on( 'click', function ( e ) {
		e.preventDefault();

		var target = $( this ).attr( 'href' );

		$( '.pxfwd-tabs .nav-tab' ).removeClass( 'nav-tab-active' );
		$( this ).addClass( 'nav-tab-active' );

		$( '.pxfwd-tab-panel' ).removeClass( 'pxfwd-tab-active' );
		$( target ).addClass( 'pxfwd-tab-active' );
	} );

	// Carrier enabled checkboxes mirror their value into a hidden field so
	// unchecked rows still submit an explicit 0.
	$( document ).on( 'change', '.pxfwd-carrier-enabled', function () {
		$( this )
			.closest( 'td' )
			.find( '.pxfwd-carrier-enabled-value' )
			.val( this.checked ? '1' : '0' );
	} );

	// Live client search on the parcel creation form.
	var $search = $( '#pxfwd-client-search-input' );

	if ( ! $search.length || 'undefined' === typeof window.pxfwdAdmin ) {
		return;
	}

	var $results = $( '#pxfwd-client-results' );
	var $clientId = $( '#pxfwd-client-id' );
	var $stock = $( '#pxfwd-client-stock' );
	var timer = null;

	function esc( text ) {
		return $( '<span>' ).text( String( text ) ).html();
	}

	function renderStock( client ) {
		var i18n = window.pxfwdAdmin.i18n;
		var html = '<h3>' + esc( client.reference ) + ' — ' + esc( client.name ) + ' : ' + client.in_stock + ' ' + esc( i18n.inStock ) + '</h3>';

		html += '<table class="wp-list-table widefat fixed striped pxfwd-stock-table"><thead><tr>';
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
				$( '<span class="pxfwd-client-result" />' ).text( window.pxfwdAdmin.i18n.noResults )
			);
			$results.addClass( 'pxfwd-open' );
			return;
		}

		clients.forEach( function ( client ) {
			var $item = $( '<button type="button" class="pxfwd-client-result" role="option" />' );

			$item.append( $( '<span class="pxfwd-result-ref" />' ).text( client.reference + ' — ' + client.name ) );
			$item.append( $( '<span class="pxfwd-result-meta" />' ).text( client.email + ( client.phone ? ' · ' + client.phone : '' ) ) );

			$item.on( 'click', function () {
				$clientId.val( client.id );
				$search.val( client.reference + ' — ' + client.name );
				$results.removeClass( 'pxfwd-open' ).empty();
				renderStock( client );
			} );

			$results.append( $item );
		} );

		$results.addClass( 'pxfwd-open' );
	}

	$search.on( 'input', function () {
		var term = $.trim( $search.val() );

		$clientId.val( '' );
		window.clearTimeout( timer );

		if ( term.length < 2 ) {
			$results.removeClass( 'pxfwd-open' ).empty();
			return;
		}

		timer = window.setTimeout( function () {
			$.getJSON( window.pxfwdAdmin.ajaxUrl, {
				action: 'pxfwd_search_clients',
				nonce: window.pxfwdAdmin.nonce,
				term: term
			} ).done( function ( response ) {
				if ( response && response.success ) {
					renderResults( response.data );
				}
			} );
		}, 250 );
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#pxfwd-client-results, #pxfwd-client-search-input' ).length ) {
			$results.removeClass( 'pxfwd-open' );
		}
	} );
} )( jQuery );
