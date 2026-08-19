<?php
/**
 * Replaces the randomly generated test identities with a realistic,
 * international demo set so the directory screenshots are presentable.
 *
 * Run with: wp eval-file /tests/seed-demo-data.php
 *
 * @package ColislyParcelForwarding
 */

global $wpdb;

$people = array(
	array( 'Marie', 'Martin', 'fr', '+33 6 12 45 78 90' ),
	array( 'Sophie', 'Bernard', 'fr', '+33 6 74 21 09 36' ),
	array( 'Lucas', 'Moreau', 'fr', '+33 7 68 33 51 24' ),
	array( 'Amelie', 'Rousseau', 'fr', '+33 6 09 87 14 62' ),
	array( 'David', 'Chen', 'sg', '+65 8123 4477' ),
	array( 'Priya', 'Sharma', 'in', '+91 98 2045 7731' ),
	array( 'James', 'Wilson', 'uk', '+44 7700 903214' ),
	array( 'Ana', 'Silva', 'pt', '+351 912 447 083' ),
	array( 'Marco', 'Rossi', 'it', '+39 340 118 9042' ),
	array( 'Yuki', 'Tanaka', 'jp', '+81 90 3312 5580' ),
	array( 'Emma', 'Johansson', 'se', '+46 70 412 88 03' ),
	array( 'Carlos', 'Mendes', 'br', '+55 21 99841 2270' ),
	array( 'Fatima', 'Zahra', 'ma', '+212 661 20 44 87' ),
	array( 'Thomas', 'Weber', 'de', '+49 151 2340 7791' ),
	array( 'Olivia', 'Brown', 'ca', '+1 604 555 0142' ),
	array( 'Minh', 'Nguyen', 'vn', '+84 90 227 6614' ),
	array( 'Sara', 'Lopez', 'es', '+34 655 30 18 27' ),
	array( 'Kwame', 'Mensah', 'gh', '+233 24 776 3310' ),
	array( 'Elena', 'Petrova', 'bg', '+359 88 412 7705' ),
	array( 'Hassan', 'Ali', 'ae', '+971 50 663 2214' ),
	array( 'Claire', 'Dubois', 'fr', '+33 6 51 30 22 78' ),
	array( 'Ryan', 'OConnor', 'ie', '+353 86 774 2018' ),
	array( 'Mei', 'Lin', 'tw', '+886 912 447 660' ),
	array( 'Paulo', 'Costa', 'br', '+55 11 98220 4471' ),
	array( 'Ingrid', 'Larsen', 'no', '+47 918 22 704' ),
	array( 'Omar', 'Haddad', 'tn', '+216 22 481 097' ),
	array( 'Julia', 'Novak', 'pl', '+48 601 337 220' ),
	array( 'Ben', 'Cohen', 'il', '+972 54 802 3317' ),
	array( 'Nadia', 'Belkacem', 'dz', '+213 550 21 74 39' ),
	array( 'Peter', 'Novotny', 'cz', '+420 776 210 448' ),
	array( 'Grace', 'Okafor', 'ng', '+234 803 447 2016' ),
	array( 'Diego', 'Fernandez', 'ar', '+54 9 11 6023 7714' ),
	array( 'Hanna', 'Virtanen', 'fi', '+358 40 552 8813' ),
	array( 'Samuel', 'Adeyemi', 'ng', '+234 815 220 6647' ),
	array( 'Laura', 'Bianchi', 'it', '+39 366 507 2218' ),
	array( 'Kenji', 'Sato', 'jp', '+81 80 4471 2260' ),
	array( 'Zoe', 'Lefevre', 'fr', '+33 7 82 14 60 35' ),
	array( 'Adam', 'Kowalski', 'pl', '+48 502 118 774' ),
	array( 'Rita', 'Gomes', 'pt', '+351 934 220 715' ),
	array( 'Tariq', 'Rahman', 'bd', '+880 171 224 8830' ),
	array( 'Chloe', 'Baker', 'au', '+61 412 776 205' ),
);

$clients = $wpdb->get_results( "SELECT id, user_id FROM {$wpdb->prefix}colisly_clients ORDER BY id ASC" );
$updated = 0;

foreach ( $clients as $i => $client ) {
	if ( ! isset( $people[ $i ] ) ) {
		break;
	}

	list( $first, $last, $cc, $phone ) = $people[ $i ];
	$email = strtolower( $first . '.' . $last ) . '@example.com';

	if ( $client->user_id && get_userdata( $client->user_id ) ) {
		wp_update_user(
			array(
				'ID'           => $client->user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => $first . ' ' . $last,
				'user_email'   => $email,
			)
		);
		update_user_meta( $client->user_id, 'billing_first_name', $first );
		update_user_meta( $client->user_id, 'billing_last_name', $last );
		update_user_meta( $client->user_id, 'billing_email', $email );
		update_user_meta( $client->user_id, 'billing_phone', $phone );
		update_user_meta( $client->user_id, 'billing_country', strtoupper( $cc ) );
	}

	$wpdb->update(
		$wpdb->prefix . 'colisly_clients',
		array( 'phone' => $phone ),
		array( 'id' => $client->id )
	);

	++$updated;
}

echo "Updated {$updated} client identities.\n";

/*
 * Enriches the parcels of one client so the directory screenshots show the
 * tracking, internal note and automatic storage fee features in action.
 */
$showcase = $wpdb->get_results(
	"SELECT id FROM {$wpdb->prefix}colisly_parcels WHERE client_id = 52 AND status = 'available' ORDER BY id ASC"
);

$details = array(
	array( 'LX419872265FR', 'Slightly dented corner, contents intact. Photographed on arrival.', 34 ),
	array( 'UPS1Z9W74E0357', '', 6 ),
);

foreach ( $showcase as $i => $parcel ) {
	if ( ! isset( $details[ $i ] ) ) {
		break;
	}

	list( $tracking, $note, $days_ago ) = $details[ $i ];

	$wpdb->update(
		$wpdb->prefix . 'colisly_parcels',
		array(
			'tracking_number' => $tracking,
			'internal_note'   => $note,
			'received_at'     => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
		),
		array( 'id' => $parcel->id )
	);
}

echo "Enriched " . count( $showcase ) . " showcase parcels.\n";

/*
 * Replaces the tracking numbers left over from the automated tests with
 * realistic carrier references on the customer-facing demo account.
 */
$customer_parcels = $wpdb->get_results(
	"SELECT id FROM {$wpdb->prefix}colisly_parcels WHERE client_id = 2 ORDER BY id DESC"
);

$trackings = array(
	'6A21874400391',
	'CC471029835FR',
	'1Z9W74E03579912',
	'JD0002280714553',
	'LX582146907FR',
);

foreach ( $customer_parcels as $i => $parcel ) {
	if ( ! isset( $trackings[ $i ] ) ) {
		break;
	}

	$wpdb->update(
		$wpdb->prefix . 'colisly_parcels',
		array( 'tracking_number' => $trackings[ $i ] ),
		array( 'id' => $parcel->id )
	);
}

echo "Rewrote " . min( count( $customer_parcels ), count( $trackings ) ) . " customer tracking numbers.\n";
