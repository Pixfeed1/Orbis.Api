<?php
function colisly_demo_rand( $n, $m ) {
	return ( ( $n * 1103515245 + 12345 ) >> 8 ) % $m;
}
function colisly_demo_tracking( $n ) {
	$digits = str_pad( (string) ( colisly_demo_rand( $n, 900000000 ) + 100000000 ), 9, '0', STR_PAD_LEFT );
	switch ( $n % 4 ) {
		case 0:
			return 'LX' . $digits . 'FR';
		case 1:
			return '1Z9W74E0' . substr( $digits, 0, 7 );
		case 2:
			return 'JD00022' . $digits;
		default:
			return 'CC' . $digits . 'FR';
	}
}
/**
 * Final pass on the showcase data: spreads the registration dates, varies how
 * many parcels each client holds, and gives the client used for the directory
 * screenshot a real history — an old parcel that has started accruing storage
 * fees, and two shipments already sent.
 *
 * @package ColislyParcelForwarding
 */

global $wpdb;

$c_table = $wpdb->prefix . 'colisly_clients';
$p_table = $wpdb->prefix . 'colisly_parcels';
$s_table = $wpdb->prefix . 'colisly_shipments';

$clients = $wpdb->get_results( "SELECT id, reference FROM {$c_table} ORDER BY id ASC" );

// Registration dates spread over the last two months rather than all today.
foreach ( $clients as $i => $client ) {
	$days = 62 - (int) floor( $i * 1.4 );
	$wpdb->update(
		$c_table,
		array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ) ),
		array( 'id' => $client->id )
	);
}

// A warehouse never holds the same number of parcels for everyone.
$extra = array( 3 => 2, 7 => 1, 11 => 2, 15 => 1, 19 => 3, 23 => 1, 27 => 2, 31 => 1, 35 => 2 );
foreach ( $clients as $i => $client ) {
	if ( isset( $extra[ $i ] ) ) {
		for ( $k = 0; $k < $extra[ $i ]; $k++ ) {
			$id = COLISLY_Parcels::create(
				array(
					'client_id'       => $client->id,
					'tracking_number' => colisly_demo_tracking( 900 + $i * 5 + $k ),
					'weight'          => round( 0.6 + ( ( $i + $k ) % 9 ) * 0.8, 3 ),
					'allow_grouping'  => 1,
				)
			);
			if ( ! is_wp_error( $id ) ) {
				$wpdb->update(
					$p_table,
					array( 'received_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 3 + ( $i % 25 ) ) . ' days' ) ) ),
					array( 'id' => $id )
				);
			}
		}
	}
	// A few clients have nothing left in stock.
	if ( in_array( $i, array( 5, 17, 29 ), true ) ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$p_table} SET status = 'shipped' WHERE client_id = %d", $client->id ) ); // phpcs:ignore
	}
}

// --- The client shown on the directory screenshot --------------------------
$showcase = $wpdb->get_row( "SELECT * FROM {$c_table} ORDER BY id DESC LIMIT 1 OFFSET 2" );
$user     = get_userdata( $showcase->user_id );

$parcels = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p_table} WHERE client_id = %d ORDER BY id ASC", $showcase->id ) );

// One parcel stored well past the 15 free days, so the storage fee is real.
if ( isset( $parcels[0] ) ) {
	$wpdb->update(
		$p_table,
		array(
			'received_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-34 days' ) ),
			'internal_note' => 'Slightly dented corner, contents intact. Photographed on arrival.',
		),
		array( 'id' => $parcels[0] )
	);
}
if ( isset( $parcels[1] ) ) {
	$wpdb->update(
		$p_table,
		array( 'received_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-6 days' ) ) ),
		array( 'id' => $parcels[1] )
	);
}

// Two shipments already sent, so the record is not that of a brand-new client.
$now = current_time( 'mysql', true );
foreach ( array( array( 'colissimo', 21, 2, 4.300, 41.80 ), array( 'ups', 47, 3, 6.100, 63.50 ) ) as $s ) {
	list( $carrier, $days_ago, $count, $weight, $total ) = $s;
	$wpdb->insert(
		$s_table,
		array(
			'reference'     => '',
			'client_id'     => $showcase->id,
			'carrier'       => $carrier,
			'status'        => 'shipped',
			'total_weight'  => $weight,
			'total_price'   => $total,
			'storage_fees'  => 0,
			'carrier_price' => 0,
			'requested_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $days_ago + 1 ) . ' days' ) ),
			'shipped_at'    => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
			'created_at'    => $now,
			'updated_at'    => $now,
		)
	);
	$sid = (int) $wpdb->insert_id;
	$wpdb->update( $s_table, array( 'reference' => COLISLY_Shipments::format_reference( $sid ) ), array( 'id' => $sid ) );
}

echo "client vitrine : {$showcase->reference} — {$user->display_name} (id {$showcase->id})\n";
echo 'colis totaux : ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p_table}" ) . "\n";
echo 'en stock : ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p_table} WHERE status = 'available'" ) . "\n";
