<?php
/**
 * Smoke tests for Gestionnaire Colis Pro, executed with `wp eval-file`.
 *
 * Exercises the whole business layer end to end against a real
 * WordPress + WooCommerce install.
 *
 * @package GestionnaireColisPro
 */

$gcp_failures = 0;

/**
 * Asserts a condition and prints the result.
 *
 * @param string $label     Test label.
 * @param bool   $condition Condition to check.
 * @return void
 */
function gcp_check( $label, $condition ) {
	global $gcp_failures;

	if ( $condition ) {
		echo "PASS: {$label}\n";
	} else {
		$gcp_failures++;
		echo "FAIL: {$label}\n";
	}
}

// ---------------------------------------------------------------------------
// Environment.
// ---------------------------------------------------------------------------
gcp_check( 'WooCommerce actif', class_exists( 'WooCommerce' ) );
gcp_check( 'Plugin charge (GCP_Plugin)', class_exists( 'GCP_Plugin' ) );

global $wpdb;
foreach ( array( 'gcp_clients', 'gcp_parcels', 'gcp_shipments', 'gcp_documents', 'gcp_history' ) as $table ) {
	$full  = $wpdb->prefix . $table;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
	gcp_check( "Table {$full} creee", $found === $full );
}

// ---------------------------------------------------------------------------
// Client creation, reference and search.
// ---------------------------------------------------------------------------
$user_id = wp_insert_user(
	array(
		'user_login' => 'client_test_' . wp_generate_password( 6, false ),
		'user_email' => 'client.test+' . time() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'first_name' => 'Jean',
		'last_name'  => 'Dupont',
		'role'       => 'customer',
	)
);
gcp_check( 'Utilisateur WooCommerce cree', ! is_wp_error( $user_id ) );

$client_id = GCP_Clients::create( $user_id, '0690001122' );
gcp_check( 'Fiche client creee', is_int( $client_id ) );

$client = GCP_Clients::get( $client_id );
gcp_check( 'Reference client au format CL000000', 1 === preg_match( '/^CL\d{6}$/', $client->reference ) );

$dup = GCP_Clients::create( $user_id );
gcp_check( 'Pas de doublon de fiche pour le meme utilisateur', $dup === $client_id );

gcp_check( 'Recherche par reference', ! empty( GCP_Clients::search( $client->reference ) ) );
gcp_check( 'Recherche par prenom', ! empty( GCP_Clients::search( 'Jean' ) ) );
gcp_check( 'Recherche par e-mail', ! empty( GCP_Clients::search( 'client.test' ) ) );
gcp_check( 'Recherche par telephone', ! empty( GCP_Clients::search( '0690001122' ) ) );

// ---------------------------------------------------------------------------
// Pricing.
// ---------------------------------------------------------------------------
$settings                  = GCP_Settings::all();
$settings['pricing_tiers'] = array(
	array( 'max_weight' => 1, 'price' => 7.5 ),
	array( 'max_weight' => 5, 'price' => 15.0 ),
);
$settings['price_base']    = 5.0;
$settings['price_per_kg']  = 2.0;
GCP_Settings::update( $settings );

gcp_check( 'Tarif palier 1 (0.5 kg = 7.50)', 7.5 === GCP_Pricing::price_for_weight( 0.5 ) );
gcp_check( 'Tarif palier 2 (3 kg = 15.00)', 15.0 === GCP_Pricing::price_for_weight( 3 ) );
gcp_check( 'Tarif hors palier (10 kg = 5 + 2x10 = 25.00)', 25.0 === GCP_Pricing::price_for_weight( 10 ) );

// ---------------------------------------------------------------------------
// Parcel creation.
// ---------------------------------------------------------------------------
$parcel_id = GCP_Parcels::create(
	array(
		'client_id'        => $client_id,
		'tracking_number'  => 'TRK123456789FR',
		'weight'           => 3.2,
		'length'           => 40,
		'width'            => 30,
		'height'           => 20,
		'internal_note'    => 'Emballage endommage a la reception',
		'allow_grouping'   => 1,
		'allowed_carriers' => array( 'colissimo', 'chronopost' ),
	)
);
gcp_check( 'Colis cree', is_int( $parcel_id ) );

$parcel = GCP_Parcels::get( $parcel_id );
gcp_check( 'Reference colis au format COL000000', 1 === preg_match( '/^COL\d{6}$/', $parcel->reference ) );
gcp_check( 'Statut initial disponible', 'available' === $parcel->status );
gcp_check( 'Tarif calcule automatiquement (3.2 kg = 15.00)', 15.0 === (float) $parcel->price );
gcp_check( 'Date de reception enregistree', ! empty( $parcel->received_at ) );
gcp_check( 'Transporteurs autorises enregistres', array( 'colissimo', 'chronopost' ) === GCP_Parcels::allowed_carrier_slugs( $parcel ) );

$invalid = GCP_Parcels::create( array( 'client_id' => $client_id, 'weight' => 0 ) );
gcp_check( 'Poids nul refuse', is_wp_error( $invalid ) );

$invalid2 = GCP_Parcels::create( array( 'client_id' => 999999, 'weight' => 1 ) );
gcp_check( 'Client inexistant refuse', is_wp_error( $invalid2 ) );

// Second parcel, grouping forbidden.
$parcel2_id = GCP_Parcels::create(
	array(
		'client_id'      => $client_id,
		'weight'         => 0.8,
		'allow_grouping' => 0,
	)
);
$parcel2    = GCP_Parcels::get( $parcel2_id );
gcp_check( 'Second colis cree, regroupement interdit', '0' === (string) $parcel2->allow_grouping || 0 === (int) $parcel2->allow_grouping );
gcp_check( 'Tarif second colis (0.8 kg = 7.50)', 7.5 === (float) $parcel2->price );

$stock = GCP_Parcels::in_stock_for_client( $client_id );
gcp_check( 'Deux colis en stock', 2 === count( $stock ) );

// ---------------------------------------------------------------------------
// Indicators.
// ---------------------------------------------------------------------------
$indicators = GCP_Clients::indicators( $client_id );
gcp_check( 'Indicateur colis en stock = 2', 2 === $indicators['parcels_in_stock'] );
gcp_check( 'Indicateur poids stocke = 4.0 kg', 4.0 === $indicators['weight_in_stock'] );
gcp_check( 'Indicateur expeditions = 0', 0 === $indicators['shipments_count'] );
gcp_check( 'Derniere reception renseignee', '' !== $indicators['last_reception'] );

// ---------------------------------------------------------------------------
// Storage fees.
// ---------------------------------------------------------------------------
$settings                        = GCP_Settings::all();
$settings['free_storage_days']   = 15;
$settings['storage_fee_per_day'] = 2.0;
GCP_Settings::update( $settings );

gcp_check( 'Frais de stockage nuls pendant la periode gratuite', 0.0 === GCP_Storage::fees_for_parcel( $parcel ) );

// Backdate the parcel by 20 days: 5 billable days x 2 = 10.
$wpdb->update(
	$wpdb->prefix . 'gcp_parcels',
	array( 'received_at' => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ),
	array( 'id' => $parcel_id )
);
$parcel = GCP_Parcels::get( $parcel_id );
gcp_check( 'Frais de stockage apres 20 jours (5 j x 2 = 10.00)', 10.0 === GCP_Storage::fees_for_parcel( $parcel ) );

$indicators = GCP_Clients::indicators( $client_id );
gcp_check( 'Indicateur frais de stockage = 10.00', 10.0 === $indicators['storage_fees_due'] );

// ---------------------------------------------------------------------------
// Shipment rules.
// ---------------------------------------------------------------------------
$err = GCP_Shipments::request( $client_id, array( $parcel_id, $parcel2_id ), 'colissimo' );
gcp_check( 'Regroupement refuse quand un colis est non regroupable', is_wp_error( $err ) && 'gcp_grouping_forbidden' === $err->get_error_code() );

$err2 = GCP_Shipments::request( $client_id, array( $parcel_id ), 'ups' );
gcp_check( 'Transporteur non autorise pour le colis refuse', is_wp_error( $err2 ) && 'gcp_carrier_forbidden' === $err2->get_error_code() );

$err3 = GCP_Shipments::request( $client_id, array( $parcel_id ), 'inexistant' );
gcp_check( 'Transporteur inconnu refuse', is_wp_error( $err3 ) );

$shipment_id = GCP_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
gcp_check( 'Demande d expedition creee', is_int( $shipment_id ) );

$shipment = GCP_Shipments::get( $shipment_id );
gcp_check( 'Reference expedition au format EXP000000', 1 === preg_match( '/^EXP\d{6}$/', $shipment->reference ) );
gcp_check( 'Frais de stockage inclus dans l expedition', 10.0 === (float) $shipment->storage_fees );
gcp_check( 'Total = tarif colis + frais stockage (15 + 10 = 25)', 25.0 === (float) $shipment->total_price );

$parcel = GCP_Parcels::get( $parcel_id );
gcp_check( 'Colis passe au statut commande', 'ordered' === $parcel->status );

$stock = GCP_Parcels::in_stock_for_client( $client_id );
gcp_check( 'Le colis commande sort du stock', 1 === count( $stock ) );

$err4 = GCP_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
gcp_check( 'Colis deja commande refuse pour une nouvelle expedition', is_wp_error( $err4 ) );

// Status lifecycle and cascade.
GCP_Shipments::set_status( $shipment_id, 'shipped' );
$parcel   = GCP_Parcels::get( $parcel_id );
$shipment = GCP_Shipments::get( $shipment_id );
gcp_check( 'Expedition marquee expediee', 'shipped' === $shipment->status );
gcp_check( 'Colis expedie en cascade', 'shipped' === $parcel->status );
gcp_check( 'Date d expedition renseignee sur le colis', ! empty( $parcel->shipped_at ) );

$indicators = GCP_Clients::indicators( $client_id );
gcp_check( 'Indicateur expeditions realisees = 1', 1 === $indicators['shipments_count'] );
gcp_check( 'Derniere expedition renseignee', '' !== $indicators['last_shipment'] );

// Cancelled shipment puts parcels back in stock.
$shipment2_id = GCP_Shipments::request( $client_id, array( $parcel2_id ), 'colissimo' );
GCP_Shipments::set_status( $shipment2_id, 'cancelled' );
$parcel2 = GCP_Parcels::get( $parcel2_id );
gcp_check( 'Expedition annulee : colis de retour en stock', 'available' === $parcel2->status );
gcp_check( 'Expedition annulee : colis detache de l expedition', empty( $parcel2->shipment_id ) );

// ---------------------------------------------------------------------------
// History.
// ---------------------------------------------------------------------------
$history = GCP_History::for_client( $client_id );
gcp_check( 'Historique rempli', count( $history ) >= 5 );
$events = wp_list_pluck( $history, 'event' );
gcp_check( 'Historique contient la creation de fiche', in_array( 'client_created', $events, true ) );
gcp_check( 'Historique contient la creation de colis', in_array( 'parcel_created', $events, true ) );
gcp_check( 'Historique contient la demande d expedition', in_array( 'shipment_requested', $events, true ) );

// ---------------------------------------------------------------------------
// Private files: protected directory, path traversal, authorization.
// ---------------------------------------------------------------------------
gcp_check( 'Repertoire prive cree', GCP_Files::ensure_dir() );
gcp_check( '.htaccess de protection present', file_exists( GCP_Files::base_dir() . '/.htaccess' ) );
gcp_check( 'index.html present', file_exists( GCP_Files::base_dir() . '/index.html' ) );

// Simulate a stored private file.
$gcp_test_file = 'test-' . wp_generate_password( 12, false ) . '.pdf';
file_put_contents( GCP_Files::base_dir() . '/' . $gcp_test_file, '%PDF-1.4 test' );

gcp_check( 'resolve() accepte un fichier valide', false !== GCP_Files::resolve( $gcp_test_file ) );
gcp_check( 'resolve() bloque la traversee ../wp-config.php', false === GCP_Files::resolve( '../../wp-config.php' ) );
gcp_check( 'resolve() bloque un chemin absolu', false === GCP_Files::resolve( ABSPATH . 'wp-config.php' ) );

$doc_id = GCP_Documents::add(
	$client_id,
	array(
		'path' => $gcp_test_file,
		'name' => 'facture.pdf',
		'type' => 'application/pdf',
	),
	'Facture test',
	'client'
);
gcp_check( 'Document prive enregistre', is_int( $doc_id ) );

$doc       = GCP_Documents::get( $doc_id );
$owner_uid = (int) GCP_Clients::get( $client_id )->user_id;
$other_uid = wp_insert_user(
	array(
		'user_login' => 'intrus_' . wp_generate_password( 6, false ),
		'user_email' => 'intrus+' . time() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
$admin_uid = (int) get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;

gcp_check( 'Le proprietaire peut telecharger son document', GCP_Downloads::user_can_download_document( $doc, $owner_uid ) );
gcp_check( 'Un autre client ne peut PAS telecharger', ! GCP_Downloads::user_can_download_document( $doc, $other_uid ) );
gcp_check( 'L administrateur peut telecharger', GCP_Downloads::user_can_download_document( $doc, $admin_uid ) );

$doc_admin_id = GCP_Documents::add(
	$client_id,
	array(
		'path' => $gcp_test_file,
		'name' => 'interne.pdf',
		'type' => 'application/pdf',
	),
	'Note interne',
	'admin'
);
$doc_admin    = GCP_Documents::get( $doc_admin_id );
gcp_check( 'Document interne invisible pour le proprietaire', ! GCP_Downloads::user_can_download_document( $doc_admin, $owner_uid ) );
$visible_ids = array_map( 'intval', wp_list_pluck( GCP_Documents::for_client( $client_id, true ), 'id' ) );
gcp_check( 'La vue client exclut les documents internes', ! in_array( (int) $doc_admin_id, $visible_ids, true ) && in_array( (int) $doc_id, $visible_ids, true ) );

// ---------------------------------------------------------------------------
// Decimal comma normalization.
// ---------------------------------------------------------------------------
gcp_check( 'Poids "2,5" normalise en 2.5', 2.5 === GCP_Parcels::to_float( '2,5' ) );

// ---------------------------------------------------------------------------
// Statuses map.
// ---------------------------------------------------------------------------
$expected_statuses = array( 'available', 'ordered', 'awaiting_payment', 'paid', 'preparing', 'shipped', 'destroyed', 'cancelled' );
gcp_check( 'Tous les statuts du cahier des charges presents', $expected_statuses === array_keys( GCP_Parcels::statuses() ) );

// ---------------------------------------------------------------------------
// Result.
// ---------------------------------------------------------------------------
if ( $gcp_failures > 0 ) {
	echo "\n{$gcp_failures} TEST(S) EN ECHEC\n";
	exit( 1 );
}

echo "\nTOUS LES TESTS SONT PASSES\n";
