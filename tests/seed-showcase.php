<?php
/**
 * Builds a presentable demo data set for the plugin directory screenshots.
 *
 * Unlike seed-demo-data.php, which only renames whatever the smoke test left
 * behind, this one starts from a clean slate and creates the whole showcase,
 * so the screenshots can be reproduced exactly.
 *
 * Run with: wp eval-file /tests/seed-showcase.php
 *
 * @package ColislyParcelForwarding
 */

global $wpdb;

$c_table = $wpdb->prefix . 'colisly_clients';
$p_table = $wpdb->prefix . 'colisly_parcels';
$s_table = $wpdb->prefix . 'colisly_shipments';
$h_table = $wpdb->prefix . 'colisly_history';

// --- Clean slate -----------------------------------------------------------
foreach ( $wpdb->get_col( "SELECT user_id FROM {$c_table}" ) as $uid ) {
	wp_delete_user( (int) $uid );
}
foreach ( array( $p_table, $s_table, $h_table, $c_table ) as $t ) {
	$wpdb->query( "DELETE FROM {$t}" ); // phpcs:ignore
	$wpdb->query( "ALTER TABLE {$t} AUTO_INCREMENT = 1" ); // phpcs:ignore
}

// Settings back to the documented defaults.
update_option( 'colisly_settings', COLISLY_Settings::defaults() );

// --- Identities ------------------------------------------------------------
$people = array(
	array( 'Lise', 'Vandamme', 'BE', '+32 495 70 22 18' ),
	array( 'Benjamin', 'Roy', 'CA', '+1 438 555 0271' ),
	array( 'Grace', 'Sullivan', 'IE', '+353 87 220 4416' ),
	array( 'Jorge', 'Ramirez', 'ES', '+34 688 12 40 77' ),
	array( 'Sandrine', 'Faure', 'FR', '+33 6 30 74 12 05' ),
	array( 'Michael', 'Doyle', 'US', '+1 312 555 0184' ),
	array( 'Elena', 'Marchetti', 'IT', '+39 348 220 7715' ),
	array( 'Pierre', 'Caron', 'FR', '+33 7 12 60 45 88' ),
	array( 'Alice', 'Whitmore', 'GB', '+44 7802 447190' ),
	array( 'Stefan', 'Vogel', 'DE', '+49 160 2277 8104' ),
	array( 'Nathalie', 'Perrin', 'FR', '+33 6 88 30 27 41' ),
	array( 'Victor', 'Andersen', 'DK', '+45 51 20 74 33' ),
	array( 'Martina', 'Keller', 'CH', '+41 79 412 66 08' ),
	array( 'Antoine', 'Lambert', 'BE', '+32 476 21 88 40' ),
	array( 'Charlotte', 'Evans', 'GB', '+44 7911 224087' ),
	array( 'Daniel', 'Fischer', 'DE', '+49 170 8842 3306' ),
	array( 'Isabelle', 'Fontaine', 'FR', '+33 7 55 12 84 60' ),
	array( 'Nicolas', 'Girard', 'FR', '+33 6 44 71 08 52' ),
	array( 'Ryan', 'Callahan', 'IE', '+353 86 774 2018' ),
	array( 'Chloe', 'Baker', 'AU', '+61 412 776 205' ),
	array( 'Rita', 'Gomes', 'PT', '+351 966 401 238' ),
	array( 'Adam', 'Kowalski', 'PL', '+48 502 118 774' ),
	array( 'Hanna', 'Virtanen', 'FI', '+358 40 552 8813' ),
	array( 'Diego', 'Fernandez', 'ES', '+34 622 47 90 15' ),
	array( 'Laura', 'Bianchi', 'IT', '+39 366 507 2218' ),
	array( 'Paulo', 'Costa', 'PT', '+351 934 220 715' ),
	array( 'Ingrid', 'Larsen', 'NO', '+47 918 22 704' ),
	array( 'Peter', 'Novotny', 'CZ', '+420 776 210 448' ),
	array( 'Julia', 'Novak', 'PL', '+48 601 337 220' ),
	array( 'Sara', 'Lopez', 'ES', '+34 655 30 18 27' ),
	array( 'Olivia', 'Brown', 'CA', '+1 604 555 0142' ),
	array( 'Claire', 'Dubois', 'FR', '+33 6 51 30 22 78' ),
	array( 'Marco', 'Rossi', 'IT', '+39 340 118 9042' ),
	array( 'Ana', 'Silva', 'PT', '+351 912 447 083' ),
	array( 'James', 'Wilson', 'GB', '+44 7700 903214' ),
	array( 'Emma', 'Johansson', 'SE', '+46 70 412 88 03' ),
	array( 'Thomas', 'Weber', 'DE', '+49 151 2340 7791' ),
	array( 'Amelie', 'Rousseau', 'FR', '+33 6 09 87 14 62' ),
	array( 'Lucas', 'Moreau', 'FR', '+33 7 68 33 51 24' ),
	array( 'Sophie', 'Bernard', 'FR', '+33 6 74 21 09 36' ),
	array( 'Marie', 'Martin', 'FR', '+33 6 12 45 78 90' ),
);

/**
 * Deterministic pseudo-random so the data set is reproducible.
 *
 * @param int $n Sequence position.
 * @param int $m Modulus.
 * @return int
 */
function colisly_demo_rand( $n, $m ) {
	return ( ( $n * 1103515245 + 12345 ) >> 8 ) % $m;
}

/**
 * Builds a plausible carrier tracking number.
 *
 * @param int $n Sequence position.
 * @return string
 */
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

$notes = array(
	'Slightly dented corner, contents intact. Photographed on arrival.',
	'Oversized box, needs a dedicated pallet slot.',
	'Fragile sticker from the sender, handled separately.',
	'',
	'',
	'',
);

$seq     = 0;
$created = 0;

foreach ( $people as $i => $person ) {
	list( $first, $last, $country, $phone ) = $person;
	$login = strtolower( $first . '.' . str_replace( ' ', '', $last ) );
	$email = $login . '@example.com';

	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => 'demo-password',
			'role'         => 'customer',
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => $first . ' ' . $last,
		)
	);
	if ( is_wp_error( $uid ) ) {
		echo "FAILED user {$login}: " . $uid->get_error_message() . "\n";
		continue;
	}

	update_user_meta( $uid, 'billing_first_name', $first );
	update_user_meta( $uid, 'billing_last_name', $last );
	update_user_meta( $uid, 'billing_phone', $phone );
	update_user_meta( $uid, 'billing_country', $country );

	$client_id = COLISLY_Clients::create( $uid, $phone );
	if ( is_wp_error( $client_id ) ) {
		echo "FAILED client {$login}: " . $client_id->get_error_message() . "\n";
		continue;
	}
	++$created;

	// Two parcels in stock per client.
	for ( $j = 0; $j < 2; $j++ ) {
		++$seq;
		$weight   = round( 0.4 + ( colisly_demo_rand( $seq, 90 ) / 10 ), 3 );
		$days_ago = 2 + colisly_demo_rand( $seq + 7, 38 );
		$note     = $notes[ $seq % count( $notes ) ];

		$parcel_id = COLISLY_Parcels::create(
			array(
				'client_id'       => $client_id,
				'tracking_number' => colisly_demo_tracking( $seq ),
				'weight'          => $weight,
				'length'          => 20 + colisly_demo_rand( $seq + 3, 40 ),
				'width'           => 15 + colisly_demo_rand( $seq + 5, 25 ),
				'height'          => 10 + colisly_demo_rand( $seq + 11, 20 ),
				'internal_note'   => $note,
				'allow_grouping'  => ( 0 === $seq % 7 ) ? 0 : 1,
			)
		);
		if ( is_wp_error( $parcel_id ) ) {
			echo "FAILED parcel: " . $parcel_id->get_error_message() . "\n";
			continue;
		}

		$wpdb->update(
			$p_table,
			array( 'received_at' => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ) ),
			array( 'id' => $parcel_id )
		);
	}
}

echo "clients: {$created}\n";
echo 'colis: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p_table}" ) . "\n";
