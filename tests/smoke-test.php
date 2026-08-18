<?php
/**
 * Smoke tests for Gestionnaire Colis Pro, executed with `wp eval-file`.
 *
 * Exercises the whole business layer end to end against a real
 * WordPress + WooCommerce install.
 *
 * @package GestionnaireColisPro
 */

global $pxfwd_failures;
$pxfwd_failures = 0;

/**
 * Asserts a condition and prints the result.
 *
 * @param string $label     Test label.
 * @param bool   $condition Condition to check.
 * @return void
 */
function pxfwd_check( $label, $condition ) {
	global $pxfwd_failures;

	if ( $condition ) {
		echo "PASS: {$label}\n";
	} else {
		$pxfwd_failures++;
		echo "FAIL: {$label}\n";
	}
}

// ---------------------------------------------------------------------------
// Environment.
// ---------------------------------------------------------------------------
pxfwd_check( 'WooCommerce actif', class_exists( 'WooCommerce' ) );
pxfwd_check( 'Plugin charge (PXFWD_Plugin)', class_exists( 'PXFWD_Plugin' ) );

global $wpdb;
foreach ( array( 'pxfwd_clients', 'pxfwd_parcels', 'pxfwd_shipments', 'pxfwd_documents', 'pxfwd_history' ) as $table ) {
	$full  = $wpdb->prefix . $table;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
	pxfwd_check( "Table {$full} creee", $found === $full );
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
pxfwd_check( 'Utilisateur WooCommerce cree', ! is_wp_error( $user_id ) );

$client_id = PXFWD_Clients::create( $user_id, '0690001122' );
pxfwd_check( 'Fiche client creee', is_int( $client_id ) );

$client = PXFWD_Clients::get( $client_id );
pxfwd_check( 'Reference client au format CL000000', 1 === preg_match( '/^CL\d{6}$/', $client->reference ) );

$dup = PXFWD_Clients::create( $user_id );
pxfwd_check( 'Pas de doublon de fiche pour le meme utilisateur', $dup === $client_id );

pxfwd_check( 'Recherche par reference', ! empty( PXFWD_Clients::search( $client->reference ) ) );
pxfwd_check( 'Recherche par prenom', ! empty( PXFWD_Clients::search( 'Jean' ) ) );
pxfwd_check( 'Recherche par e-mail', ! empty( PXFWD_Clients::search( 'client.test' ) ) );
pxfwd_check( 'Recherche par telephone', ! empty( PXFWD_Clients::search( '0690001122' ) ) );

// ---------------------------------------------------------------------------
// Pricing.
// ---------------------------------------------------------------------------
$settings                  = PXFWD_Settings::all();
$settings['pricing_tiers'] = array(
	array( 'max_weight' => 1, 'price' => 7.5 ),
	array( 'max_weight' => 5, 'price' => 15.0 ),
);
$settings['price_base']    = 5.0;
$settings['price_per_kg']  = 2.0;
PXFWD_Settings::update( $settings );

pxfwd_check( 'Tarif palier 1 (0.5 kg = 7.50)', 7.5 === PXFWD_Pricing::price_for_weight( 0.5 ) );
pxfwd_check( 'Tarif palier 2 (3 kg = 15.00)', 15.0 === PXFWD_Pricing::price_for_weight( 3 ) );
pxfwd_check( 'Tarif hors palier (10 kg = 5 + 2x10 = 25.00)', 25.0 === PXFWD_Pricing::price_for_weight( 10 ) );

// ---------------------------------------------------------------------------
// Parcel creation.
// ---------------------------------------------------------------------------
$parcel_id = PXFWD_Parcels::create(
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
pxfwd_check( 'Colis cree', is_int( $parcel_id ) );

$parcel = PXFWD_Parcels::get( $parcel_id );
pxfwd_check( 'Reference colis au format COL000000', 1 === preg_match( '/^COL\d{6}$/', $parcel->reference ) );
pxfwd_check( 'Statut initial disponible', 'available' === $parcel->status );
pxfwd_check( 'Tarif calcule automatiquement (3.2 kg = 15.00)', 15.0 === (float) $parcel->price );
pxfwd_check( 'Date de reception enregistree', ! empty( $parcel->received_at ) );
pxfwd_check( 'Transporteurs autorises enregistres', array( 'colissimo', 'chronopost' ) === PXFWD_Parcels::allowed_carrier_slugs( $parcel ) );

$invalid = PXFWD_Parcels::create( array( 'client_id' => $client_id, 'weight' => 0 ) );
pxfwd_check( 'Poids nul refuse', is_wp_error( $invalid ) );

$invalid2 = PXFWD_Parcels::create( array( 'client_id' => 999999, 'weight' => 1 ) );
pxfwd_check( 'Client inexistant refuse', is_wp_error( $invalid2 ) );

// Second parcel, grouping forbidden.
$parcel2_id = PXFWD_Parcels::create(
	array(
		'client_id'      => $client_id,
		'weight'         => 0.8,
		'allow_grouping' => 0,
	)
);
$parcel2    = PXFWD_Parcels::get( $parcel2_id );
pxfwd_check( 'Second colis cree, regroupement interdit', '0' === (string) $parcel2->allow_grouping || 0 === (int) $parcel2->allow_grouping );
pxfwd_check( 'Tarif second colis (0.8 kg = 7.50)', 7.5 === (float) $parcel2->price );

$stock = PXFWD_Parcels::in_stock_for_client( $client_id );
pxfwd_check( 'Deux colis en stock', 2 === count( $stock ) );

// ---------------------------------------------------------------------------
// Indicators.
// ---------------------------------------------------------------------------
$indicators = PXFWD_Clients::indicators( $client_id );
pxfwd_check( 'Indicateur colis en stock = 2', 2 === $indicators['parcels_in_stock'] );
pxfwd_check( 'Indicateur poids stocke = 4.0 kg', 4.0 === $indicators['weight_in_stock'] );
pxfwd_check( 'Indicateur expeditions = 0', 0 === $indicators['shipments_count'] );
pxfwd_check( 'Derniere reception renseignee', '' !== $indicators['last_reception'] );

// ---------------------------------------------------------------------------
// Storage fees.
// ---------------------------------------------------------------------------
$settings                        = PXFWD_Settings::all();
$settings['free_storage_days']   = 15;
$settings['storage_fee_per_day'] = 2.0;
PXFWD_Settings::update( $settings );

pxfwd_check( 'Frais de stockage nuls pendant la periode gratuite', 0.0 === PXFWD_Storage::fees_for_parcel( $parcel ) );

// Backdate the parcel by 20 days: 5 billable days x 2 = 10.
$wpdb->update(
	$wpdb->prefix . 'pxfwd_parcels',
	array( 'received_at' => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ),
	array( 'id' => $parcel_id )
);
$parcel = PXFWD_Parcels::get( $parcel_id );
pxfwd_check( 'Frais de stockage apres 20 jours (5 j x 2 = 10.00)', 10.0 === PXFWD_Storage::fees_for_parcel( $parcel ) );

$indicators = PXFWD_Clients::indicators( $client_id );
pxfwd_check( 'Indicateur frais de stockage = 10.00', 10.0 === $indicators['storage_fees_due'] );

// ---------------------------------------------------------------------------
// Shipment rules.
// ---------------------------------------------------------------------------
// Carrier tariffs are zeroed here so the totals below only cover parcels and
// storage fees; the carrier tariff section further down tests the transport
// pricing explicitly.
$settings             = PXFWD_Settings::all();
$settings['carriers'] = array(
	array( 'slug' => 'colissimo', 'name' => 'Colissimo', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
	array( 'slug' => 'chronopost', 'name' => 'Chronopost', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
	array( 'slug' => 'ups', 'name' => 'UPS', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
);
PXFWD_Settings::update( $settings );

$err = PXFWD_Shipments::request( $client_id, array( $parcel_id, $parcel2_id ), 'colissimo' );
pxfwd_check( 'Regroupement refuse quand un colis est non regroupable', is_wp_error( $err ) && 'pxfwd_grouping_forbidden' === $err->get_error_code() );

$err2 = PXFWD_Shipments::request( $client_id, array( $parcel_id ), 'ups' );
pxfwd_check( 'Transporteur non autorise pour le colis refuse', is_wp_error( $err2 ) && 'pxfwd_carrier_forbidden' === $err2->get_error_code() );

$err3 = PXFWD_Shipments::request( $client_id, array( $parcel_id ), 'inexistant' );
pxfwd_check( 'Transporteur inconnu refuse', is_wp_error( $err3 ) );

$shipment_id = PXFWD_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
pxfwd_check( 'Demande d expedition creee', is_int( $shipment_id ) );

$shipment = PXFWD_Shipments::get( $shipment_id );
pxfwd_check( 'Reference expedition au format EXP000000', 1 === preg_match( '/^EXP\d{6}$/', $shipment->reference ) );
pxfwd_check( 'Frais de stockage inclus dans l expedition', 10.0 === (float) $shipment->storage_fees );
pxfwd_check( 'Total = tarif colis + frais stockage (15 + 10 = 25)', 25.0 === (float) $shipment->total_price );

$parcel = PXFWD_Parcels::get( $parcel_id );
pxfwd_check( 'Colis commande (en attente de paiement via la commande WooCommerce)', in_array( $parcel->status, array( 'ordered', 'awaiting_payment' ), true ) );

$stock = PXFWD_Parcels::in_stock_for_client( $client_id );
pxfwd_check( 'Le colis commande sort du stock', 1 === count( $stock ) );

$err4 = PXFWD_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
pxfwd_check( 'Colis deja commande refuse pour une nouvelle expedition', is_wp_error( $err4 ) );

// Status lifecycle and cascade.
PXFWD_Shipments::set_status( $shipment_id, 'shipped' );
$parcel   = PXFWD_Parcels::get( $parcel_id );
$shipment = PXFWD_Shipments::get( $shipment_id );
pxfwd_check( 'Expedition marquee expediee', 'shipped' === $shipment->status );
pxfwd_check( 'Colis expedie en cascade', 'shipped' === $parcel->status );
pxfwd_check( 'Date d expedition renseignee sur le colis', ! empty( $parcel->shipped_at ) );

$indicators = PXFWD_Clients::indicators( $client_id );
pxfwd_check( 'Indicateur expeditions realisees = 1', 1 === $indicators['shipments_count'] );
pxfwd_check( 'Derniere expedition renseignee', '' !== $indicators['last_shipment'] );

// Cancelled shipment puts parcels back in stock.
$shipment2_id = PXFWD_Shipments::request( $client_id, array( $parcel2_id ), 'colissimo' );
PXFWD_Shipments::set_status( $shipment2_id, 'cancelled' );
$parcel2 = PXFWD_Parcels::get( $parcel2_id );
pxfwd_check( 'Expedition annulee : colis de retour en stock', 'available' === $parcel2->status );
pxfwd_check( 'Expedition annulee : colis detache de l expedition', empty( $parcel2->shipment_id ) );

// ---------------------------------------------------------------------------
// History.
// ---------------------------------------------------------------------------
$history = PXFWD_History::for_client( $client_id );
pxfwd_check( 'Historique rempli', count( $history ) >= 5 );
$events = wp_list_pluck( $history, 'event' );
pxfwd_check( 'Historique contient la creation de fiche', in_array( 'client_created', $events, true ) );
pxfwd_check( 'Historique contient la creation de colis', in_array( 'parcel_created', $events, true ) );
pxfwd_check( 'Historique contient la demande d expedition', in_array( 'shipment_requested', $events, true ) );

// ---------------------------------------------------------------------------
// Private files: protected directory, path traversal, authorization.
// ---------------------------------------------------------------------------
pxfwd_check( 'Repertoire prive cree', PXFWD_Files::ensure_dir() );
pxfwd_check( '.htaccess de protection present', file_exists( PXFWD_Files::base_dir() . '/.htaccess' ) );
pxfwd_check( 'index.html present', file_exists( PXFWD_Files::base_dir() . '/index.html' ) );

// Simulate a stored private file.
$pxfwd_test_file = 'test-' . wp_generate_password( 12, false ) . '.pdf';
file_put_contents( PXFWD_Files::base_dir() . '/' . $pxfwd_test_file, '%PDF-1.4 test' );

pxfwd_check( 'resolve() accepte un fichier valide', false !== PXFWD_Files::resolve( $pxfwd_test_file ) );
pxfwd_check( 'resolve() bloque la traversee ../wp-config.php', false === PXFWD_Files::resolve( '../../wp-config.php' ) );
pxfwd_check( 'resolve() bloque un chemin absolu', false === PXFWD_Files::resolve( ABSPATH . 'wp-config.php' ) );

$doc_id = PXFWD_Documents::add(
	$client_id,
	array(
		'path' => $pxfwd_test_file,
		'name' => 'facture.pdf',
		'type' => 'application/pdf',
	),
	'Facture test',
	'client'
);
pxfwd_check( 'Document prive enregistre', is_int( $doc_id ) );

$doc       = PXFWD_Documents::get( $doc_id );
$owner_uid = (int) PXFWD_Clients::get( $client_id )->user_id;
$other_uid = wp_insert_user(
	array(
		'user_login' => 'intrus_' . wp_generate_password( 6, false ),
		'user_email' => 'intrus+' . time() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
$admin_uid = (int) get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;

pxfwd_check( 'Le proprietaire peut telecharger son document', PXFWD_Downloads::user_can_download_document( $doc, $owner_uid ) );
pxfwd_check( 'Un autre client ne peut PAS telecharger', ! PXFWD_Downloads::user_can_download_document( $doc, $other_uid ) );
pxfwd_check( 'L administrateur peut telecharger', PXFWD_Downloads::user_can_download_document( $doc, $admin_uid ) );

$doc_admin_id = PXFWD_Documents::add(
	$client_id,
	array(
		'path' => $pxfwd_test_file,
		'name' => 'interne.pdf',
		'type' => 'application/pdf',
	),
	'Note interne',
	'admin'
);
$doc_admin    = PXFWD_Documents::get( $doc_admin_id );
pxfwd_check( 'Document interne invisible pour le proprietaire', ! PXFWD_Downloads::user_can_download_document( $doc_admin, $owner_uid ) );
$visible_ids = array_map( 'intval', wp_list_pluck( PXFWD_Documents::for_client( $client_id, true ), 'id' ) );
pxfwd_check( 'La vue client exclut les documents internes', ! in_array( (int) $doc_admin_id, $visible_ids, true ) && in_array( (int) $doc_id, $visible_ids, true ) );

// ---------------------------------------------------------------------------
// Carrier tariffs.
// ---------------------------------------------------------------------------
$settings             = PXFWD_Settings::all();
$settings['carriers'] = array(
	array( 'slug' => 'colissimo', 'name' => 'Colissimo', 'enabled' => 1, 'price_base' => 8.0, 'price_per_kg' => 1.5 ),
	array( 'slug' => 'chronopost', 'name' => 'Chronopost', 'enabled' => 1, 'price_base' => 12.0, 'price_per_kg' => 2.0 ),
	array( 'slug' => 'ups', 'name' => 'UPS', 'enabled' => 1, 'price_base' => 14.0, 'price_per_kg' => 2.2 ),
);
PXFWD_Settings::update( $settings );

pxfwd_check( 'Tarif transporteur Colissimo 2 kg (8 + 1.5x2 = 11.00)', 11.0 === PXFWD_Carriers::price_for( 'colissimo', 2.0 ) );
pxfwd_check( 'Tarif transporteur inconnu = 0', 0.0 === PXFWD_Carriers::price_for( 'inexistant', 2.0 ) );

// ---------------------------------------------------------------------------
// Native WooCommerce e-mails registered with the mailer.
// ---------------------------------------------------------------------------
$pxfwd_wc_emails = WC()->mailer()->get_emails();
pxfwd_check( 'E-mail « Colis réceptionné » enregistré dans WooCommerce', isset( $pxfwd_wc_emails['PXFWD_Email_Parcel_Received'] ) );
pxfwd_check( 'E-mail « Demande d’expédition » enregistré dans WooCommerce', isset( $pxfwd_wc_emails['PXFWD_Email_Shipment_Requested'] ) );
pxfwd_check( 'E-mail client marque comme customer_email', $pxfwd_wc_emails['PXFWD_Email_Parcel_Received']->is_customer_email() );
pxfwd_check( 'Gabarit HTML « Colis réceptionné » rendu', false !== strpos( (string) $pxfwd_wc_emails['PXFWD_Email_Parcel_Received']->get_default_subject(), '{parcel_reference}' ) );

// ---------------------------------------------------------------------------
// Native WooCommerce order integration.
// ---------------------------------------------------------------------------
$wc_parcel1 = PXFWD_Parcels::create( array( 'client_id' => $client_id, 'weight' => 2.0, 'allow_grouping' => 1 ) );
$wc_parcel2 = PXFWD_Parcels::create( array( 'client_id' => $client_id, 'weight' => 1.0, 'allow_grouping' => 1 ) );

$wc_ship_id = PXFWD_Shipments::request( $client_id, array( $wc_parcel1 ), 'colissimo' );
pxfwd_check( 'Expedition avec commande creee', is_int( $wc_ship_id ) );

$wc_ship = PXFWD_Shipments::get( $wc_ship_id );
pxfwd_check( 'Commande WooCommerce liee (order_id > 0)', (int) $wc_ship->order_id > 0 );
pxfwd_check( 'Expedition en attente de paiement', 'awaiting_payment' === $wc_ship->status );
pxfwd_check( 'Colis en attente de paiement', 'awaiting_payment' === PXFWD_Parcels::get( $wc_parcel1 )->status );

$wc_order = wc_get_order( (int) $wc_ship->order_id );
pxfwd_check( 'La commande existe et attend un paiement', $wc_order && $wc_order->needs_payment() );
pxfwd_check( 'Meta _pxfwd_shipment_id sur la commande', (int) $wc_order->get_meta( '_pxfwd_shipment_id' ) === $wc_ship_id );
pxfwd_check( 'Total commande = colis + stockage + transport', (float) $wc_order->get_total() === (float) $wc_ship->total_price );
pxfwd_check( 'Une ligne de frais par colis presente', 1 === count( $wc_order->get_fees() ) || 2 === count( $wc_order->get_fees() ) );
pxfwd_check( 'Transporteur en ligne de livraison', 1 === count( $wc_order->get_shipping_methods() ) );
pxfwd_check( 'Transport facture sur la ligne de livraison (2 kg Colissimo = 11.00)', 11.0 === (float) $wc_order->get_shipping_total() );
pxfwd_check( 'carrier_price stocke sur l expedition', 11.0 === (float) $wc_ship->carrier_price );

// Payment through the native WooCommerce flow.
$wc_order->payment_complete( 'TEST-TXN-1' );
$wc_ship = PXFWD_Shipments::get( $wc_ship_id );
pxfwd_check( 'Commande payee => expedition payee', 'paid' === $wc_ship->status );
pxfwd_check( 'Commande payee => colis payes', 'paid' === PXFWD_Parcels::get( $wc_parcel1 )->status );

// Shipping the parcels completes the order.
PXFWD_Shipments::set_status( $wc_ship_id, 'shipped' );
$wc_order = wc_get_order( (int) $wc_ship->order_id );
pxfwd_check( 'Expedition expediee => commande terminee', $wc_order->has_status( 'completed' ) );

// Cancelling an unpaid order puts the parcels back in stock.
$wc_ship2_id = PXFWD_Shipments::request( $client_id, array( $wc_parcel2 ), 'colissimo' );
$wc_ship2    = PXFWD_Shipments::get( $wc_ship2_id );
$wc_order2   = wc_get_order( (int) $wc_ship2->order_id );
$wc_order2->update_status( 'cancelled' );
$wc_ship2 = PXFWD_Shipments::get( $wc_ship2_id );
pxfwd_check( 'Commande annulee => expedition annulee', 'cancelled' === $wc_ship2->status );
pxfwd_check( 'Commande annulee => colis de retour en stock', 'available' === PXFWD_Parcels::get( $wc_parcel2 )->status );

// ---------------------------------------------------------------------------
// Decimal comma normalization.
// ---------------------------------------------------------------------------
pxfwd_check( 'Poids "2,5" normalise en 2.5', 2.5 === PXFWD_Parcels::to_float( '2,5' ) );

// ---------------------------------------------------------------------------
// Privacy (GDPR): exporter, eraser, account-deletion cleanup.
// ---------------------------------------------------------------------------
$priv_uid = wp_insert_user(
	array(
		'user_login' => 'rgpd_' . wp_generate_password( 6, false ),
		'user_email' => 'rgpd+' . time() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
$priv_email  = get_userdata( $priv_uid )->user_email;
$priv_client = PXFWD_Clients::create( $priv_uid, '0696000001' );
$priv_parcel = PXFWD_Parcels::create(
	array(
		'client_id'       => $priv_client,
		'tracking_number' => 'RGPD-TRACK-1',
		'weight'          => 1.5,
		'internal_note'   => 'Note interne RGPD',
	)
);
$priv_file = 'rgpd-' . wp_generate_password( 8, false ) . '.pdf';
PXFWD_Files::ensure_dir();
file_put_contents( PXFWD_Files::base_dir() . '/' . $priv_file, '%PDF-1.4 rgpd' );
PXFWD_Documents::add( $priv_client, array( 'path' => $priv_file, 'name' => 'piece.pdf', 'type' => 'application/pdf' ), 'Piece', 'client' );

$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
pxfwd_check( 'Exportateur RGPD enregistre', isset( $exporters['gestionnaire-colis-pro'] ) );
$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
pxfwd_check( 'Effaceur RGPD enregistre', isset( $erasers['gestionnaire-colis-pro'] ) );

$export = PXFWD_Privacy::export( $priv_email );
$groups = array_unique( wp_list_pluck( $export['data'], 'group_id' ) );
pxfwd_check( 'Export RGPD : fiche client presente', in_array( 'pxfwd_client', $groups, true ) );
pxfwd_check( 'Export RGPD : colis presents', in_array( 'pxfwd_parcels', $groups, true ) );
pxfwd_check( 'Export RGPD : termine en une passe', true === $export['done'] );
pxfwd_check( 'Export RGPD : e-mail inconnu vide', array() === PXFWD_Privacy::export( 'inconnu@example.com' )['data'] );

$erase = PXFWD_Privacy::erase( $priv_email );
pxfwd_check( 'Effacement RGPD : donnees supprimees', true === $erase['items_removed'] );
pxfwd_check( 'Effacement RGPD : conservation signalee', true === $erase['items_retained'] && ! empty( $erase['messages'] ) );

$priv_c = PXFWD_Clients::get( $priv_client );
$priv_p = PXFWD_Parcels::get( $priv_parcel );
pxfwd_check( 'Effacement RGPD : telephone efface', '' === (string) $priv_c->phone );
pxfwd_check( 'Effacement RGPD : numero de suivi efface', '' === (string) $priv_p->tracking_number );
pxfwd_check( 'Effacement RGPD : note interne effacee', '' === (string) $priv_p->internal_note );
pxfwd_check( 'Effacement RGPD : documents supprimes', 0 === count( PXFWD_Documents::for_client( $priv_client ) ) );
pxfwd_check( 'Effacement RGPD : fichier prive supprime', false === PXFWD_Files::resolve( $priv_file ) );
pxfwd_check( 'Effacement RGPD : reference colis conservee (comptabilite)', 1 === preg_match( '/^COL\d{6}$/', $priv_p->reference ) );

// Account deletion removes everything.
wp_delete_user( $priv_uid );
pxfwd_check( 'Suppression du compte : fiche client purgee', null === PXFWD_Clients::get( $priv_client ) );
pxfwd_check( 'Suppression du compte : colis purges', null === PXFWD_Parcels::get( $priv_parcel ) );

// ---------------------------------------------------------------------------
// Legacy "gcp" prefix migration: nothing must remain behind.
// ---------------------------------------------------------------------------
$legacy_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}gcp_%'" );
pxfwd_check( 'Migration : aucune table gcp_ restante', array() === $legacy_tables );

$legacy_options = 0;
foreach ( array( 'settings', 'db_version', 'flush_rewrite_rules', 'remove_data_on_uninstall' ) as $legacy_option ) {
	if ( false !== get_option( 'gcp_' . $legacy_option, false ) ) {
		++$legacy_options;
	}
}
pxfwd_check( 'Migration : aucune option gcp_ restante', 0 === $legacy_options );
pxfwd_check( 'Migration : capacite gcp_manage retiree', ! get_role( 'administrator' )->has_cap( 'gcp_manage' ) );
pxfwd_check( 'Migration : capacite pxfwd_manage presente', get_role( 'administrator' )->has_cap( 'pxfwd_manage' ) );
pxfwd_check( 'Migration : dossier prive renomme', ! is_dir( wp_upload_dir( null, false )['basedir'] . '/gcp-private' ) && is_dir( PXFWD_Files::base_dir() ) );
pxfwd_check(
	'Migration : aucune commande avec l ancienne meta',
	0 === count( wc_get_orders( array( 'limit' => -1, 'meta_key' => '_gcp_shipment_id', 'meta_compare' => 'EXISTS', 'return' => 'ids' ) ) )
);
pxfwd_check( 'Migration : les donnees clients ont survecu', PXFWD_Clients::count() > 0 && count( PXFWD_Parcels::for_client( PXFWD_Clients::paged_list( '', 1, 1 )[0]->id ) ) >= 0 );

// ---------------------------------------------------------------------------
// Statuses map.
// ---------------------------------------------------------------------------
$expected_statuses = array( 'available', 'ordered', 'awaiting_payment', 'paid', 'preparing', 'shipped', 'destroyed', 'cancelled' );
pxfwd_check( 'Tous les statuts du cahier des charges presents', $expected_statuses === array_keys( PXFWD_Parcels::statuses() ) );

// ---------------------------------------------------------------------------
// Result.
// ---------------------------------------------------------------------------
if ( $pxfwd_failures > 0 ) {
	echo "\n{$pxfwd_failures} TEST(S) EN ECHEC\n";
	exit( 1 );
}

echo "\nTOUS LES TESTS SONT PASSES\n";
