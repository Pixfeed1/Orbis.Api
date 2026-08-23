<?php
/**
 * Creates a large data set to check the admin screens still hold up.
 *
 * @package ColislyParcelForwarding
 */

global $wpdb;

$clients  = 400;
$per      = 5;
$now      = current_time( 'mysql', true );
$c_table  = $wpdb->prefix . 'colisly_clients';
$p_table  = $wpdb->prefix . 'colisly_parcels';
$start_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$c_table}" );

for ( $i = 1; $i <= $clients; $i++ ) {
	$uid = wp_insert_user(
		array(
			'user_login' => 'charge' . $i,
			'user_email' => 'charge' . $i . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
			'first_name' => 'Charge',
			'last_name'  => 'Client' . $i,
		)
	);
	if ( is_wp_error( $uid ) ) {
		continue;
	}

	$wpdb->insert(
		$c_table,
		array(
			'user_id'    => $uid,
			'reference'  => '',
			'phone'      => '+33 6 00 00 ' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT ),
			'created_at' => $now,
			'updated_at' => $now,
		)
	);
	$cid = (int) $wpdb->insert_id;
	$wpdb->update( $c_table, array( 'reference' => COLISLY_Clients::format_reference( $cid ) ), array( 'id' => $cid ) );

	for ( $j = 0; $j < $per; $j++ ) {
		$wpdb->insert(
			$p_table,
			array(
				'reference'       => '',
				'client_id'       => $cid,
				'tracking_number' => 'CHG' . $i . '-' . $j,
				'weight'          => 0.5 + $j,
				'price'           => 7.5,
				'status'          => 'available',
				'allow_grouping'  => 1,
				'received_at'     => $now,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		$pid = (int) $wpdb->insert_id;
		$wpdb->update( $p_table, array( 'reference' => COLISLY_Parcels::format_reference( $pid ) ), array( 'id' => $pid ) );
	}
}

echo 'clients: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c_table}" ) . "\n";
echo 'colis: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p_table}" ) . "\n";
