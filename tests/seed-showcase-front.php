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
 * Gives one demo client a spread of parcel statuses and two shipments, so the
 * customer-facing screenshots show the account area doing real work.
 *
 * @package ColislyParcelForwarding
 */

global $wpdb;

$p_table = $wpdb->prefix . 'colisly_parcels';
$s_table = $wpdb->prefix . 'colisly_shipments';

// The front-office demo account.
$client = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}colisly_clients ORDER BY id ASC LIMIT 1" );
if ( ! $client ) {
	echo "no client\n";
	return;
}

$user = get_userdata( $client->user_id );
echo "compte vitrine : {$user->user_login} / {$client->reference} ({$user->display_name})\n";

$statuses = array( 'available', 'awaiting_payment', 'paid', 'shipped', 'cancelled' );
$existing = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p_table} WHERE client_id = %d ORDER BY id ASC", $client->id ) );

// Top the client up to five parcels.
while ( count( $existing ) < 5 ) {
	$n  = count( $existing );
	$id = COLISLY_Parcels::create(
		array(
			'client_id'       => $client->id,
			'tracking_number' => colisly_demo_tracking( 500 + $n ),
			'weight'          => round( 0.8 + $n * 0.7, 3 ),
			'allow_grouping'  => $n < 3 ? 1 : 0,
		)
	);
	if ( is_wp_error( $id ) ) {
		echo 'FAILED: ' . $id->get_error_message() . "\n";
		break;
	}
	$existing[] = $id;
}

foreach ( $existing as $i => $pid ) {
	if ( ! isset( $statuses[ $i ] ) ) {
		break;
	}
	$wpdb->update(
		$p_table,
		array(
			'status'      => $statuses[ $i ],
			'received_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 5 + $i * 6 ) . ' days' ) ),
		),
		array( 'id' => $pid )
	);
}

echo 'colis du compte vitrine : ' . count( $existing ) . "\n";
echo 'statuts : ' . implode( ', ', $wpdb->get_col( $wpdb->prepare( "SELECT status FROM {$p_table} WHERE client_id = %d ORDER BY id ASC", $client->id ) ) ) . "\n";
