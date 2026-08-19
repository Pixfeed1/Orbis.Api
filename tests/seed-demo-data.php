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
	array( 'Thomas', 'Weber', 'de', '+49 151 2340 7791' ),
	array( 'Emma', 'Johansson', 'se', '+46 70 412 88 03' ),
	array( 'James', 'Wilson', 'gb', '+44 7700 903214' ),
	array( 'Ana', 'Silva', 'pt', '+351 912 447 083' ),
	array( 'Marco', 'Rossi', 'it', '+39 340 118 9042' ),
	array( 'Claire', 'Dubois', 'fr', '+33 6 51 30 22 78' ),
	array( 'Olivia', 'Brown', 'ca', '+1 604 555 0142' ),
	array( 'Sara', 'Lopez', 'es', '+34 655 30 18 27' ),
	array( 'Julia', 'Novak', 'pl', '+48 601 337 220' ),
	array( 'Peter', 'Novotny', 'cz', '+420 776 210 448' ),
	array( 'Ingrid', 'Larsen', 'no', '+47 918 22 704' ),
	array( 'Paulo', 'Costa', 'pt', '+351 934 220 715' ),
	array( 'Laura', 'Bianchi', 'it', '+39 366 507 2218' ),
	array( 'Diego', 'Fernandez', 'es', '+34 622 47 90 15' ),
	array( 'Hanna', 'Virtanen', 'fi', '+358 40 552 8813' ),
	array( 'Adam', 'Kowalski', 'pl', '+48 502 118 774' ),
	array( 'Rita', 'Gomes', 'pt', '+351 966 401 238' ),
	array( 'Chloe', 'Baker', 'au', '+61 412 776 205' ),
	array( 'Ryan', 'Callahan', 'ie', '+353 86 774 2018' ),
	array( 'Nicolas', 'Girard', 'fr', '+33 6 44 71 08 52' ),
	array( 'Isabelle', 'Fontaine', 'fr', '+33 7 55 12 84 60' ),
	array( 'Daniel', 'Fischer', 'de', '+49 170 8842 3306' ),
	array( 'Charlotte', 'Evans', 'gb', '+44 7911 224087' ),
	array( 'Antoine', 'Lambert', 'be', '+32 476 21 88 40' ),
	array( 'Martina', 'Keller', 'ch', '+41 79 412 66 08' ),
	array( 'Victor', 'Andersen', 'dk', '+45 51 20 74 33' ),
	array( 'Nathalie', 'Perrin', 'fr', '+33 6 88 30 27 41' ),
	array( 'Stefan', 'Vogel', 'de', '+49 160 2277 8104' ),
	array( 'Alice', 'Whitmore', 'gb', '+44 7802 447190' ),
	array( 'Pierre', 'Caron', 'fr', '+33 7 12 60 45 88' ),
	array( 'Elena', 'Marchetti', 'it', '+39 348 220 7715' ),
	array( 'Michael', 'Doyle', 'us', '+1 312 555 0184' ),
	array( 'Sandrine', 'Faure', 'fr', '+33 6 30 74 12 05' ),
	array( 'Jorge', 'Ramirez', 'es', '+34 688 12 40 77' ),
	array( 'Grace', 'Sullivan', 'ie', '+353 87 220 4416' ),
	array( 'Benjamin', 'Roy', 'ca', '+1 438 555 0271' ),
	array( 'Lise', 'Vandamme', 'be', '+32 495 70 22 18' ),
);

$clients = $wpdb->get_results( "SELECT id, user_id FROM {$wpdb->prefix}colisly_clients ORDER BY id ASC" );

/*
 * Pass 1: park every address on a unique placeholder. Without this, reusing an
 * e-mail that another seeded user still holds makes wp_update_user() fail
 * silently and that client keeps its previous identity.
 */
foreach ( $clients as $i => $client ) {
	if ( ! isset( $people[ $i ] ) || ! $client->user_id || ! get_userdata( $client->user_id ) ) {
		continue;
	}

	$wpdb->update(
		$wpdb->users,
		array( 'user_email' => 'colisly-seed-' . (int) $client->user_id . '@example.com' ),
		array( 'ID' => (int) $client->user_id )
	);
	clean_user_cache( (int) $client->user_id );
}

// Pass 2: apply the final identities.
$updated = 0;

foreach ( $clients as $i => $client ) {
	if ( ! isset( $people[ $i ] ) ) {
		break;
	}

	list( $first, $last, $cc, $phone ) = $people[ $i ];
	$email = strtolower( $first . '.' . $last ) . '@example.com';

	if ( $client->user_id && get_userdata( $client->user_id ) ) {
		$result = wp_update_user(
			array(
				'ID'           => $client->user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => $first . ' ' . $last,
				'user_email'   => $email,
			)
		);

		if ( is_wp_error( $result ) ) {
			echo "FAILED user {$client->user_id}: " . $result->get_error_message() . "\n";
			continue;
		}

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
