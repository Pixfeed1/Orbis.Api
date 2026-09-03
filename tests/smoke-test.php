<?php
/**
 * Smoke tests for Colisly Parcel Forwarding, executed with `wp eval-file`.
 *
 * Exercises the whole business layer end to end against a real
 * WordPress + WooCommerce install.
 *
 * @package ColislyParcelForwarding
 */

global $colisly_failures;
$colisly_failures = 0;

/**
 * Asserts a condition and prints the result.
 *
 * @param string $label     Test label.
 * @param bool   $condition Condition to check.
 * @return void
 */
function colisly_check( $label, $condition ) {
	global $colisly_failures;

	if ( $condition ) {
		echo "PASS: {$label}\n";
	} else {
		$colisly_failures++;
		echo "FAIL: {$label}\n";
	}
}

// ---------------------------------------------------------------------------
// Environment.
// ---------------------------------------------------------------------------
colisly_check( 'WooCommerce actif', class_exists( 'WooCommerce' ) );
colisly_check( 'Plugin charge (COLISLY_Plugin)', class_exists( 'COLISLY_Plugin' ) );

global $wpdb;
foreach ( array( 'colisly_clients', 'colisly_parcels', 'colisly_shipments', 'colisly_documents', 'colisly_history' ) as $table ) {
	$full  = $wpdb->prefix . $table;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
	colisly_check( "Table {$full} creee", $found === $full );
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
colisly_check( 'Utilisateur WooCommerce cree', ! is_wp_error( $user_id ) );

$client_id = COLISLY_Clients::create( $user_id, '0690001122' );
colisly_check( 'Fiche client creee', is_int( $client_id ) );

$client = COLISLY_Clients::get( $client_id );
colisly_check( 'Reference client au format CL000000', 1 === preg_match( '/^CL\d{6}$/', $client->reference ) );

$dup = COLISLY_Clients::create( $user_id );
colisly_check( 'Pas de doublon de fiche pour le meme utilisateur', $dup === $client_id );

colisly_check( 'Recherche par reference', ! empty( COLISLY_Clients::search( $client->reference ) ) );
colisly_check( 'Recherche par prenom', ! empty( COLISLY_Clients::search( 'Jean' ) ) );
colisly_check( 'Recherche par e-mail', ! empty( COLISLY_Clients::search( 'client.test' ) ) );
colisly_check( 'Recherche par telephone', ! empty( COLISLY_Clients::search( '0690001122' ) ) );

// ---------------------------------------------------------------------------
// Pricing.
// ---------------------------------------------------------------------------
$settings                  = COLISLY_Settings::all();
$settings['pricing_tiers'] = array(
	array( 'max_weight' => 1, 'price' => 7.5 ),
	array( 'max_weight' => 5, 'price' => 15.0 ),
);
$settings['price_base']    = 5.0;
$settings['price_per_kg']  = 2.0;
COLISLY_Settings::update( $settings );

colisly_check( 'Tarif palier 1 (0.5 kg = 7.50)', 7.5 === COLISLY_Pricing::price_for_weight( 0.5 ) );
colisly_check( 'Tarif palier 2 (3 kg = 15.00)', 15.0 === COLISLY_Pricing::price_for_weight( 3 ) );
colisly_check( 'Tarif hors palier (10 kg = 5 + 2x10 = 25.00)', 25.0 === COLISLY_Pricing::price_for_weight( 10 ) );

// ---------------------------------------------------------------------------
// Parcel creation.
// ---------------------------------------------------------------------------
// Carriers are site settings, so the suite pins its own set instead of
// depending on whatever the site happens to hold. Reading the existing
// configuration made the scenarios below depend on install order.
$settings             = COLISLY_Settings::all();
$settings['carriers'] = array(
	array( 'slug' => 'colissimo', 'name' => 'Colissimo', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
	array( 'slug' => 'chronopost', 'name' => 'Chronopost', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
	array( 'slug' => 'ups', 'name' => 'UPS', 'enabled' => 1, 'price_base' => 0, 'price_per_kg' => 0 ),
);
COLISLY_Settings::update( $settings );

$colisly_two_carriers = array( 'colissimo', 'chronopost' );
colisly_check( 'Transporteurs de test en place', 3 === count( COLISLY_Carriers::all() ) );

$parcel_id = COLISLY_Parcels::create(
	array(
		'client_id'        => $client_id,
		'tracking_number'  => 'TRK123456789FR',
		'weight'           => 3.2,
		'length'           => 40,
		'width'            => 30,
		'height'           => 20,
		'internal_note'    => 'Emballage endommage a la reception',
		'allow_grouping'   => 1,
		'allowed_carriers' => $colisly_two_carriers,
	)
);
colisly_check( 'Colis cree', is_int( $parcel_id ) );

$parcel = COLISLY_Parcels::get( $parcel_id );
colisly_check( 'Reference colis au format COL000000', 1 === preg_match( '/^COL\d{6}$/', $parcel->reference ) );
colisly_check( 'Statut initial disponible', 'available' === $parcel->status );
colisly_check( 'Tarif calcule automatiquement (3.2 kg = 15.00)', 15.0 === (float) $parcel->price );
colisly_check( 'Date de reception enregistree', ! empty( $parcel->received_at ) );
colisly_check( 'Transporteurs autorises enregistres', $colisly_two_carriers === COLISLY_Parcels::allowed_carrier_slugs( $parcel ) );

$invalid = COLISLY_Parcels::create( array( 'client_id' => $client_id, 'weight' => 0 ) );
colisly_check( 'Poids nul refuse', is_wp_error( $invalid ) );

$invalid2 = COLISLY_Parcels::create( array( 'client_id' => 999999, 'weight' => 1 ) );
colisly_check( 'Client inexistant refuse', is_wp_error( $invalid2 ) );

// Second parcel, grouping forbidden.
$parcel2_id = COLISLY_Parcels::create(
	array(
		'client_id'      => $client_id,
		'weight'         => 0.8,
		'allow_grouping' => 0,
	)
);
$parcel2    = COLISLY_Parcels::get( $parcel2_id );
colisly_check( 'Second colis cree, regroupement interdit', '0' === (string) $parcel2->allow_grouping || 0 === (int) $parcel2->allow_grouping );
colisly_check( 'Tarif second colis (0.8 kg = 7.50)', 7.5 === (float) $parcel2->price );

$stock = COLISLY_Parcels::in_stock_for_client( $client_id );
colisly_check( 'Deux colis en stock', 2 === count( $stock ) );

// ---------------------------------------------------------------------------
// Indicators.
// ---------------------------------------------------------------------------
$indicators = COLISLY_Clients::indicators( $client_id );
colisly_check( 'Indicateur colis en stock = 2', 2 === $indicators['parcels_in_stock'] );
colisly_check( 'Indicateur poids stocke = 4.0 kg', 4.0 === $indicators['weight_in_stock'] );
colisly_check( 'Indicateur expeditions = 0', 0 === $indicators['shipments_count'] );
colisly_check( 'Derniere reception renseignee', '' !== $indicators['last_reception'] );

// ---------------------------------------------------------------------------
// Storage fees.
// ---------------------------------------------------------------------------
$settings                        = COLISLY_Settings::all();
$settings['free_storage_days']   = 15;
$settings['storage_fee_per_day'] = 2.0;
COLISLY_Settings::update( $settings );

colisly_check( 'Frais de stockage nuls pendant la periode gratuite', 0.0 === COLISLY_Storage::fees_for_parcel( $parcel ) );

// Backdate the parcel by 20 days: 5 billable days x 2 = 10.
$wpdb->update(
	$wpdb->prefix . 'colisly_parcels',
	array( 'received_at' => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ),
	array( 'id' => $parcel_id )
);
$parcel = COLISLY_Parcels::get( $parcel_id );
colisly_check( 'Frais de stockage apres 20 jours (5 j x 2 = 10.00)', 10.0 === COLISLY_Storage::fees_for_parcel( $parcel ) );

$indicators = COLISLY_Clients::indicators( $client_id );
colisly_check( 'Indicateur frais de stockage = 10.00', 10.0 === $indicators['storage_fees_due'] );

// ---------------------------------------------------------------------------
// Shipment rules.
// ---------------------------------------------------------------------------
// Carrier tariffs are zeroed here so the totals below only cover parcels and
// storage fees; the carrier tariff section further down tests the transport
// pricing explicitly.
$settings = COLISLY_Settings::all();
foreach ( $settings['carriers'] as $colisly_i => $colisly_carrier ) {
	$settings['carriers'][ $colisly_i ]['price_base']   = 0;
	$settings['carriers'][ $colisly_i ]['price_per_kg'] = 0;
}
COLISLY_Settings::update( $settings );

$err = COLISLY_Shipments::request( $client_id, array( $parcel_id, $parcel2_id ), 'colissimo' );
colisly_check( 'Regroupement refuse quand un colis est non regroupable', is_wp_error( $err ) && 'colisly_grouping_forbidden' === $err->get_error_code() );

$err2 = COLISLY_Shipments::request( $client_id, array( $parcel_id ), 'ups' );
colisly_check( 'Transporteur non autorise pour le colis refuse', is_wp_error( $err2 ) && 'colisly_carrier_forbidden' === $err2->get_error_code() );

$err3 = COLISLY_Shipments::request( $client_id, array( $parcel_id ), 'inexistant' );
colisly_check( 'Transporteur inconnu refuse', is_wp_error( $err3 ) );

$shipment_id = COLISLY_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
colisly_check( 'Demande d expedition creee', is_int( $shipment_id ) );

$shipment = COLISLY_Shipments::get( $shipment_id );
colisly_check( 'Reference expedition au format EXP000000', 1 === preg_match( '/^EXP\d{6}$/', $shipment->reference ) );
colisly_check( 'Frais de stockage inclus dans l expedition', 10.0 === (float) $shipment->storage_fees );
colisly_check( 'Total = tarif colis + frais stockage (15 + 10 = 25)', 25.0 === (float) $shipment->total_price );

$parcel = COLISLY_Parcels::get( $parcel_id );
colisly_check( 'Colis commande (en attente de paiement via la commande WooCommerce)', in_array( $parcel->status, array( 'ordered', 'awaiting_payment' ), true ) );

$stock = COLISLY_Parcels::in_stock_for_client( $client_id );
colisly_check( 'Le colis commande sort du stock', 1 === count( $stock ) );

$err4 = COLISLY_Shipments::request( $client_id, array( $parcel_id ), 'colissimo' );
colisly_check( 'Colis deja commande refuse pour une nouvelle expedition', is_wp_error( $err4 ) );

// Status lifecycle and cascade.
COLISLY_Shipments::set_status( $shipment_id, 'shipped' );
$parcel   = COLISLY_Parcels::get( $parcel_id );
$shipment = COLISLY_Shipments::get( $shipment_id );
colisly_check( 'Expedition marquee expediee', 'shipped' === $shipment->status );
colisly_check( 'Colis expedie en cascade', 'shipped' === $parcel->status );
colisly_check( 'Date d expedition renseignee sur le colis', ! empty( $parcel->shipped_at ) );

$indicators = COLISLY_Clients::indicators( $client_id );
colisly_check( 'Indicateur expeditions realisees = 1', 1 === $indicators['shipments_count'] );
colisly_check( 'Derniere expedition renseignee', '' !== $indicators['last_shipment'] );

// Cancelled shipment puts parcels back in stock.
$shipment2_id = COLISLY_Shipments::request( $client_id, array( $parcel2_id ), 'colissimo' );
COLISLY_Shipments::set_status( $shipment2_id, 'cancelled' );
$parcel2 = COLISLY_Parcels::get( $parcel2_id );
colisly_check( 'Expedition annulee : colis de retour en stock', 'available' === $parcel2->status );
colisly_check( 'Expedition annulee : colis detache de l expedition', empty( $parcel2->shipment_id ) );

// ---------------------------------------------------------------------------
// History.
// ---------------------------------------------------------------------------
$history = COLISLY_History::for_client( $client_id );
colisly_check( 'Historique rempli', count( $history ) >= 5 );
$events = wp_list_pluck( $history, 'event' );
colisly_check( 'Historique contient la creation de fiche', in_array( 'client_created', $events, true ) );
colisly_check( 'Historique contient la creation de colis', in_array( 'parcel_created', $events, true ) );
colisly_check( 'Historique contient la demande d expedition', in_array( 'shipment_requested', $events, true ) );

// ---------------------------------------------------------------------------
// Private files: protected directory, path traversal, authorization.
// ---------------------------------------------------------------------------
colisly_check( 'Repertoire prive cree', COLISLY_Files::ensure_dir() );
colisly_check( '.htaccess de protection present', file_exists( COLISLY_Files::base_dir() . '/.htaccess' ) );
colisly_check( 'index.html present', file_exists( COLISLY_Files::base_dir() . '/index.html' ) );

// Simulate a stored private file.
$colisly_test_file = 'test-' . wp_generate_password( 12, false ) . '.pdf';
file_put_contents( COLISLY_Files::base_dir() . '/' . $colisly_test_file, '%PDF-1.4 test' );

colisly_check( 'resolve() accepte un fichier valide', false !== COLISLY_Files::resolve( $colisly_test_file ) );
colisly_check( 'resolve() bloque la traversee ../wp-config.php', false === COLISLY_Files::resolve( '../../wp-config.php' ) );
colisly_check( 'resolve() bloque un chemin absolu', false === COLISLY_Files::resolve( ABSPATH . 'wp-config.php' ) );

$doc_id = COLISLY_Documents::add(
	$client_id,
	array(
		'path' => $colisly_test_file,
		'name' => 'facture.pdf',
		'type' => 'application/pdf',
	),
	'Facture test',
	'client'
);
colisly_check( 'Document prive enregistre', is_int( $doc_id ) );

$doc       = COLISLY_Documents::get( $doc_id );
$owner_uid = (int) COLISLY_Clients::get( $client_id )->user_id;
$other_uid = wp_insert_user(
	array(
		'user_login' => 'intrus_' . wp_generate_password( 6, false ),
		'user_email' => 'intrus+' . time() . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
$admin_uid = (int) get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;

colisly_check( 'Le proprietaire peut telecharger son document', COLISLY_Downloads::user_can_download_document( $doc, $owner_uid ) );
colisly_check( 'Un autre client ne peut PAS telecharger', ! COLISLY_Downloads::user_can_download_document( $doc, $other_uid ) );
colisly_check( 'L administrateur peut telecharger', COLISLY_Downloads::user_can_download_document( $doc, $admin_uid ) );

$doc_admin_id = COLISLY_Documents::add(
	$client_id,
	array(
		'path' => $colisly_test_file,
		'name' => 'interne.pdf',
		'type' => 'application/pdf',
	),
	'Note interne',
	'admin'
);
$doc_admin    = COLISLY_Documents::get( $doc_admin_id );
colisly_check( 'Document interne invisible pour le proprietaire', ! COLISLY_Downloads::user_can_download_document( $doc_admin, $owner_uid ) );
$visible_ids = array_map( 'intval', wp_list_pluck( COLISLY_Documents::for_client( $client_id, true ), 'id' ) );
colisly_check( 'La vue client exclut les documents internes', ! in_array( (int) $doc_admin_id, $visible_ids, true ) && in_array( (int) $doc_id, $visible_ids, true ) );

// ---------------------------------------------------------------------------
// Carrier tariffs.
// ---------------------------------------------------------------------------
$settings             = COLISLY_Settings::all();
$settings['carriers'] = array(
	array( 'slug' => 'colissimo', 'name' => 'Colissimo', 'enabled' => 1, 'price_base' => 8.0, 'price_per_kg' => 1.5 ),
	array( 'slug' => 'chronopost', 'name' => 'Chronopost', 'enabled' => 1, 'price_base' => 12.0, 'price_per_kg' => 2.0 ),
	array( 'slug' => 'ups', 'name' => 'UPS', 'enabled' => 1, 'price_base' => 14.0, 'price_per_kg' => 2.2 ),
);
COLISLY_Settings::update( $settings );

colisly_check( 'Tarif transporteur Colissimo 2 kg (8 + 1.5x2 = 11.00)', 11.0 === COLISLY_Carriers::price_for( 'colissimo', 2.0 ) );
colisly_check( 'Tarif transporteur inconnu = 0', 0.0 === COLISLY_Carriers::price_for( 'inexistant', 2.0 ) );

// ---------------------------------------------------------------------------
// Native WooCommerce e-mails registered with the mailer.
// ---------------------------------------------------------------------------
$colisly_wc_emails = WC()->mailer()->get_emails();
colisly_check( 'E-mail « Colis réceptionné » enregistré dans WooCommerce', isset( $colisly_wc_emails['COLISLY_Email_Parcel_Received'] ) );
colisly_check( 'E-mail « Demande d’expédition » enregistré dans WooCommerce', isset( $colisly_wc_emails['COLISLY_Email_Shipment_Requested'] ) );
colisly_check( 'E-mail client marque comme customer_email', $colisly_wc_emails['COLISLY_Email_Parcel_Received']->is_customer_email() );
colisly_check( 'Gabarit HTML « Colis réceptionné » rendu', false !== strpos( (string) $colisly_wc_emails['COLISLY_Email_Parcel_Received']->get_default_subject(), '{parcel_reference}' ) );

// ---------------------------------------------------------------------------
// Native WooCommerce order integration.
// ---------------------------------------------------------------------------
$wc_parcel1 = COLISLY_Parcels::create( array( 'client_id' => $client_id, 'weight' => 2.0, 'allow_grouping' => 1 ) );
$wc_parcel2 = COLISLY_Parcels::create( array( 'client_id' => $client_id, 'weight' => 1.0, 'allow_grouping' => 1 ) );

$wc_ship_id = COLISLY_Shipments::request( $client_id, array( $wc_parcel1 ), 'colissimo' );
colisly_check( 'Expedition avec commande creee', is_int( $wc_ship_id ) );

$wc_ship = COLISLY_Shipments::get( $wc_ship_id );
colisly_check( 'Commande WooCommerce liee (order_id > 0)', (int) $wc_ship->order_id > 0 );
colisly_check( 'Expedition en attente de paiement', 'awaiting_payment' === $wc_ship->status );
colisly_check( 'Colis en attente de paiement', 'awaiting_payment' === COLISLY_Parcels::get( $wc_parcel1 )->status );

$wc_order = wc_get_order( (int) $wc_ship->order_id );
colisly_check( 'La commande existe et attend un paiement', $wc_order && $wc_order->needs_payment() );
colisly_check( 'Meta _colisly_shipment_id sur la commande', (int) $wc_order->get_meta( '_colisly_shipment_id' ) === $wc_ship_id );
colisly_check( 'Total commande = colis + stockage + transport', (float) $wc_order->get_total() === (float) $wc_ship->total_price );
colisly_check( 'Une ligne de frais par colis presente', 1 === count( $wc_order->get_fees() ) || 2 === count( $wc_order->get_fees() ) );
colisly_check( 'Transporteur en ligne de livraison', 1 === count( $wc_order->get_shipping_methods() ) );
colisly_check( 'Transport facture sur la ligne de livraison (2 kg Colissimo = 11.00)', 11.0 === (float) $wc_order->get_shipping_total() );
colisly_check( 'carrier_price stocke sur l expedition', 11.0 === (float) $wc_ship->carrier_price );

// Payment through the native WooCommerce flow.
$wc_order->payment_complete( 'TEST-TXN-1' );
$wc_ship = COLISLY_Shipments::get( $wc_ship_id );
colisly_check( 'Commande payee => expedition payee', 'paid' === $wc_ship->status );
colisly_check( 'Commande payee => colis payes', 'paid' === COLISLY_Parcels::get( $wc_parcel1 )->status );

// Shipping the parcels completes the order.
COLISLY_Shipments::set_status( $wc_ship_id, 'shipped' );
$wc_order = wc_get_order( (int) $wc_ship->order_id );
colisly_check( 'Expedition expediee => commande terminee', $wc_order->has_status( 'completed' ) );

// Cancelling an unpaid order puts the parcels back in stock.
$wc_ship2_id = COLISLY_Shipments::request( $client_id, array( $wc_parcel2 ), 'colissimo' );
$wc_ship2    = COLISLY_Shipments::get( $wc_ship2_id );
$wc_order2   = wc_get_order( (int) $wc_ship2->order_id );
$wc_order2->update_status( 'cancelled' );
$wc_ship2 = COLISLY_Shipments::get( $wc_ship2_id );
colisly_check( 'Commande annulee => expedition annulee', 'cancelled' === $wc_ship2->status );
colisly_check( 'Commande annulee => colis de retour en stock', 'available' === COLISLY_Parcels::get( $wc_parcel2 )->status );

// ---------------------------------------------------------------------------
// Decimal comma normalization.
// ---------------------------------------------------------------------------
colisly_check( 'Poids "2,5" normalise en 2.5', 2.5 === COLISLY_Parcels::to_float( '2,5' ) );

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
$priv_client = COLISLY_Clients::create( $priv_uid, '0696000001' );
$priv_parcel = COLISLY_Parcels::create(
	array(
		'client_id'       => $priv_client,
		'tracking_number' => 'RGPD-TRACK-1',
		'weight'          => 1.5,
		'internal_note'   => 'Note interne RGPD',
	)
);
$priv_file = 'rgpd-' . wp_generate_password( 8, false ) . '.pdf';
COLISLY_Files::ensure_dir();
file_put_contents( COLISLY_Files::base_dir() . '/' . $priv_file, '%PDF-1.4 rgpd' );
COLISLY_Documents::add( $priv_client, array( 'path' => $priv_file, 'name' => 'piece.pdf', 'type' => 'application/pdf' ), 'Piece', 'client' );

$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
colisly_check( 'Exportateur RGPD enregistre', isset( $exporters['colisly'] ) );
$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
colisly_check( 'Effaceur RGPD enregistre', isset( $erasers['colisly'] ) );

$export = COLISLY_Privacy::export( $priv_email );
$groups = array_unique( wp_list_pluck( $export['data'], 'group_id' ) );
colisly_check( 'Export RGPD : fiche client presente', in_array( 'colisly_client', $groups, true ) );
colisly_check( 'Export RGPD : colis presents', in_array( 'colisly_parcels', $groups, true ) );
colisly_check( 'Export RGPD : termine en une passe', true === $export['done'] );
colisly_check( 'Export RGPD : e-mail inconnu vide', array() === COLISLY_Privacy::export( 'inconnu@example.com' )['data'] );

$erase = COLISLY_Privacy::erase( $priv_email );
colisly_check( 'Effacement RGPD : donnees supprimees', true === $erase['items_removed'] );
colisly_check( 'Effacement RGPD : conservation signalee', true === $erase['items_retained'] && ! empty( $erase['messages'] ) );

$priv_c = COLISLY_Clients::get( $priv_client );
$priv_p = COLISLY_Parcels::get( $priv_parcel );
colisly_check( 'Effacement RGPD : telephone efface', '' === (string) $priv_c->phone );
colisly_check( 'Effacement RGPD : numero de suivi efface', '' === (string) $priv_p->tracking_number );
colisly_check( 'Effacement RGPD : note interne effacee', '' === (string) $priv_p->internal_note );
colisly_check( 'Effacement RGPD : documents supprimes', 0 === count( COLISLY_Documents::for_client( $priv_client ) ) );
colisly_check( 'Effacement RGPD : fichier prive supprime', false === COLISLY_Files::resolve( $priv_file ) );
colisly_check( 'Effacement RGPD : reference colis conservee (comptabilite)', 1 === preg_match( '/^COL\d{6}$/', $priv_p->reference ) );

// Account deletion removes everything.
wp_delete_user( $priv_uid );
colisly_check( 'Suppression du compte : fiche client purgee', null === COLISLY_Clients::get( $priv_client ) );
colisly_check( 'Suppression du compte : colis purges', null === COLISLY_Parcels::get( $priv_parcel ) );

// ---------------------------------------------------------------------------
// Legacy "gcp" prefix migration: nothing must remain behind.
// ---------------------------------------------------------------------------
$legacy_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}gcp_%'" );
colisly_check( 'Migration : aucune table gcp_ restante', array() === $legacy_tables );

$legacy_options = 0;
foreach ( array( 'settings', 'db_version', 'flush_rewrite_rules', 'remove_data_on_uninstall' ) as $legacy_option ) {
	if ( false !== get_option( 'gcp_' . $legacy_option, false ) ) {
		++$legacy_options;
	}
}
colisly_check( 'Migration : aucune option gcp_ restante', 0 === $legacy_options );
colisly_check( 'Migration : capacite gcp_manage retiree', ! get_role( 'administrator' )->has_cap( 'gcp_manage' ) );
colisly_check( 'Migration : capacite colisly_manage presente', get_role( 'administrator' )->has_cap( 'colisly_manage' ) );
colisly_check( 'Migration : dossier prive renomme', ! is_dir( wp_upload_dir( null, false )['basedir'] . '/gcp-private' ) && is_dir( COLISLY_Files::base_dir() ) );
colisly_check(
	'Migration : aucune commande avec l ancienne meta',
	0 === count( wc_get_orders( array( 'limit' => -1, 'meta_key' => '_gcp_shipment_id', 'meta_compare' => 'EXISTS', 'return' => 'ids' ) ) )
);
colisly_check( 'Migration : les donnees clients ont survecu', COLISLY_Clients::count() > 0 && count( COLISLY_Parcels::for_client( COLISLY_Clients::paged_list( '', 1, 1 )[0]->id ) ) >= 0 );

// ---------------------------------------------------------------------------
// Statuses map.
// ---------------------------------------------------------------------------
$expected_statuses = array( 'available', 'ordered', 'awaiting_payment', 'paid', 'preparing', 'shipped', 'destroyed', 'cancelled' );
/*
 * Tranches de poids par transporteur.
 *
 * Les transporteurs ne facturent pas au kilo mais par tranche : un colis de
 * 6 kg a 45 EUR et un de 15 kg a 150 EUR ne tiennent sur aucune droite. On
 * verifie les bornes, la retombee sur base + tarif/kg au-dela de la grille,
 * et qu'un transporteur sans grille garde exactement l'ancien comportement.
 */
$colisly_saved_carriers = COLISLY_Settings::get( 'carriers', array() );
$colisly_grid_settings  = COLISLY_Settings::all();

$colisly_grid_settings['carriers'] = array(
	array(
		'slug'         => 'grille',
		'name'         => 'Grille',
		'enabled'      => 1,
		'price_base'   => 8.0,
		'price_per_kg' => 1.5,
		'tiers'        => array(
			array(
				'max_weight' => 6,
				'price'      => 45.0,
			),
			array(
				'max_weight' => 1,
				'price'      => 8.5,
			), // volontairement desordonne : le tri doit s'en charger.
			array(
				'max_weight' => 15,
				'price'      => 150.0,
			),
		),
	),
	array(
		'slug'         => 'lineaire',
		'name'         => 'Lineaire',
		'enabled'      => 1,
		'price_base'   => 12.0,
		'price_per_kg' => 2.0,
	),
);
COLISLY_Settings::update( $colisly_grid_settings );

colisly_check( 'Tranche : 1 kg sur la premiere borne', 8.5 === COLISLY_Carriers::price_for( 'grille', 1 ) );
colisly_check( 'Tranche : 0,5 kg dans la premiere tranche', 8.5 === COLISLY_Carriers::price_for( 'grille', 0.5 ) );
colisly_check( 'Tranche : 1,001 kg bascule dans la suivante', 45.0 === COLISLY_Carriers::price_for( 'grille', 1.001 ) );
colisly_check( 'Tranche : 6 kg sur la borne = 45', 45.0 === COLISLY_Carriers::price_for( 'grille', 6 ) );
colisly_check( 'Tranche : 6,01 kg = 150', 150.0 === COLISLY_Carriers::price_for( 'grille', 6.01 ) );
colisly_check( 'Tranche : 15 kg sur la derniere borne = 150', 150.0 === COLISLY_Carriers::price_for( 'grille', 15 ) );
colisly_check( 'Tranche : grille desordonnee triee correctement', 45.0 === COLISLY_Carriers::price_for( 'grille', 5 ) );
// 8 + 1,5 x 16 = 32, sous la derniere tranche : le plancher doit tenir.
colisly_check( 'Tranche : au-dela de la grille, jamais moins que la derniere', 150.0 === COLISLY_Carriers::price_for( 'grille', 16 ) );
// 8 + 1,5 x 100 = 158, au-dessus : la formule reprend la main.
colisly_check( 'Tranche : au-dela, la formule reprend quand elle depasse', 158.0 === COLISLY_Carriers::price_for( 'grille', 100 ) );
colisly_check( 'Sans grille : base + tarif/kg inchange', 22.0 === COLISLY_Carriers::price_for( 'lineaire', 5 ) );

$colisly_grid_settings['carriers'] = $colisly_saved_carriers;
COLISLY_Settings::update( $colisly_grid_settings );

/*
 * Libelles des tableaux de l'espace client.
 *
 * Depuis la 1.6.8 ces tableaux s'empilent quand la colonne du theme est trop
 * etroite, et l'en-tete disparait alors : chaque cellule doit porter son propre
 * data-title, sinon il ne reste que des valeurs nues. C'est exactement ce qui
 * est arrive au tableau de la demande d'expedition, oublie parce que les deux
 * autres etaient corrects. Le test lit le source plutot que le rendu, faute de
 * navigateur ici, mais il suffit a empecher la recidive.
 */
$colisly_account_src = file_get_contents( COLISLY_PLUGIN_DIR . 'includes/frontend/class-colisly-account.php' );
$colisly_tables_ok   = true;
$colisly_lines       = explode( "\n", $colisly_account_src );

foreach ( $colisly_lines as $colisly_i => $colisly_line ) {
	if ( false === strpos( $colisly_line, 'colisly-front-table' ) ) {
		continue;
	}

	$colisly_block = '';
	for ( $colisly_j = $colisly_i; $colisly_j < count( $colisly_lines ); $colisly_j++ ) {
		$colisly_block .= $colisly_lines[ $colisly_j ] . "\n";
		if ( false !== strpos( $colisly_lines[ $colisly_j ], '</table>' ) ) {
			break;
		}
	}

	$colisly_cells  = preg_match_all( '/<td[ >]/', $colisly_block );
	$colisly_titles = preg_match_all( '/data-title=/', $colisly_block );

	if ( $colisly_cells !== $colisly_titles ) {
		$colisly_tables_ok = false;
		echo "  tableau ligne " . ( $colisly_i + 1 ) . " : {$colisly_cells} cellules, {$colisly_titles} data-title\n";
	}
}

colisly_check( 'Espace client : chaque cellule de tableau porte son libelle', $colisly_tables_ok );

/*
 * Correction d'un colis deja recu.
 *
 * La reception se fait au comptoir, souvent vite : un poids faux ou un numero
 * de suivi mal tape n'avait aucun retour en arriere. Le poids fixant le prix,
 * la faute se facturait telle quelle. La correction s'arrete des que le colis
 * quitte le stock, sinon on changerait le prix d'une commande deja reglee.
 */
$colisly_edit_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'edit-test-' . wp_generate_password( 6, false ),
			'user_email' => 'edit-test-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);

$colisly_edit_parcel = COLISLY_Parcels::create(
	array(
		'client_id'       => $colisly_edit_client,
		'tracking_number' => 'AVANT123',
		'weight'          => 0.5,
		'allow_grouping'  => 1,
	)
);

$colisly_before = COLISLY_Parcels::get( $colisly_edit_parcel );
colisly_check( 'Correction : tarif initial sur 0,5 kg', 7.5 === (float) $colisly_before->price );

$colisly_edit_result = COLISLY_Parcels::update(
	$colisly_edit_parcel,
	array(
		'tracking_number' => 'APRES456',
		'weight'          => '12,5',
		'internal_note'   => 'Repese au comptoir.',
		'allow_grouping'  => 0,
	)
);
colisly_check( 'Correction : modification acceptee', true === $colisly_edit_result );

$colisly_after = COLISLY_Parcels::get( $colisly_edit_parcel );
colisly_check( 'Correction : numero de suivi remplace', 'APRES456' === $colisly_after->tracking_number );
colisly_check( 'Correction : virgule decimale acceptee', 12.5 === (float) $colisly_after->weight );
colisly_check( 'Correction : prix recalcule sur le nouveau poids', COLISLY_Pricing::price_for_weight( 12.5 ) === (float) $colisly_after->price );
colisly_check( 'Correction : note interne enregistree', 'Repese au comptoir.' === $colisly_after->internal_note );
colisly_check( 'Correction : groupage refuse enregistre', 0 === (int) $colisly_after->allow_grouping );

$colisly_edit_history = COLISLY_History::for_client( $colisly_edit_client );
colisly_check(
	'Correction : tracee dans l historique',
	! empty( $colisly_edit_history ) && false !== strpos( $colisly_edit_history[0]->message, 'COL' )
		&& 'parcel_updated' === $colisly_edit_history[0]->event
);

colisly_check(
	'Correction : poids nul refuse',
	is_wp_error( COLISLY_Parcels::update( $colisly_edit_parcel, array( 'weight' => '0' ) ) )
);
colisly_check(
	'Correction : colis inexistant refuse',
	is_wp_error( COLISLY_Parcels::update( 999999, array( 'weight' => '2' ) ) )
);

// Le colis quitte le stock : la correction doit etre refusee.
COLISLY_Parcels::set_status( $colisly_edit_parcel, 'awaiting_payment' );
$colisly_locked = COLISLY_Parcels::update( $colisly_edit_parcel, array( 'weight' => '3' ) );
colisly_check( 'Correction : refusee des que le colis est engage', is_wp_error( $colisly_locked ) );
colisly_check(
	'Correction : le poids n a pas bouge apres refus',
	12.5 === (float) COLISLY_Parcels::get( $colisly_edit_parcel )->weight
);

/*
 * L'estimation affichee au client est calculee en JavaScript. Elle ne peut
 * suivre la regle du serveur que si la grille de tranches accompagne l'option
 * du transporteur : sans elle, le JS retombe sur base + tarif/kg et annonce un
 * montant que le tunnel de paiement dementira. C'est arrive en 1.6.9, 17 EUR
 * affiches pour 6 kg factures 45. Le test verifie que l'attribut est bien emis
 * et que le calcul JS existe toujours.
 */
$colisly_account_markup = file_get_contents( COLISLY_PLUGIN_DIR . 'includes/frontend/class-colisly-account.php' );
$colisly_front_js       = file_get_contents( COLISLY_PLUGIN_DIR . 'assets/js/front.js' );

colisly_check(
	'Estimation : la grille accompagne l option transporteur',
	false !== strpos( $colisly_account_markup, 'data-tiers=' )
);
colisly_check(
	'Estimation : le calcul JS lit la grille',
	false !== strpos( $colisly_front_js, 'carrierPrice' ) && false !== strpos( $colisly_front_js, "data-tiers" )
);

/*
 * Assurance facultative.
 *
 * Le client choisit un niveau de couverture ; son prix n'est jamais lu dans le
 * formulaire mais relu dans les reglages, sinon un montant poste deciderait de
 * ce qui est facture. Une liste vide signifie simplement pas d'assurance.
 */
$colisly_ins_settings = COLISLY_Settings::all();
$colisly_ins_saved    = $colisly_ins_settings['insurance_options'];

$colisly_ins_settings['insurance_options'] = array();
COLISLY_Settings::update( $colisly_ins_settings );
colisly_check( 'Assurance : liste vide, non proposee', ! COLISLY_Insurance::offered() );

$colisly_ins_settings['insurance_options'] = array(
	array(
		'cover' => 100,
		'price' => 4,
	),
	array(
		'cover' => 50,
		'price' => 2,
	),
	array(
		'cover' => 0,
		'price' => 99,
	), // couverture nulle : doit disparaitre.
);
COLISLY_Settings::update( $colisly_ins_settings );

$colisly_ins_options = COLISLY_Insurance::options();
colisly_check( 'Assurance : proposee des qu un niveau existe', COLISLY_Insurance::offered() );
colisly_check( 'Assurance : niveau a couverture nulle ecarte', 2 === count( $colisly_ins_options ) );
colisly_check( 'Assurance : niveaux tries par couverture', 50.0 === $colisly_ins_options[0]['cover'] );
colisly_check( 'Assurance : montant inconnu refuse', null === COLISLY_Insurance::find( 999 ) );
colisly_check( 'Assurance : prix relu dans les reglages', 4.0 === COLISLY_Insurance::find( 100 )['price'] );

$colisly_ins_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'ins-' . wp_generate_password( 6, false ),
			'user_email' => 'ins-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);

$colisly_ins_p1 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_ins_client,
		'weight'    => 2,
	)
);
$colisly_ins_p2 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_ins_client,
		'weight'    => 2,
	)
);
$colisly_ins_p3 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_ins_client,
		'weight'    => 2,
	)
);

$colisly_ins_nothing = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_ins_client, array( $colisly_ins_p1 ), 'colissimo' ) );
$colisly_ins_taken   = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_ins_client, array( $colisly_ins_p2 ), 'colissimo', 100 ) );
$colisly_ins_forged  = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_ins_client, array( $colisly_ins_p3 ), 'colissimo', 99999 ) );

colisly_check( 'Assurance : sans choix, rien n est facture', 0.0 === (float) $colisly_ins_nothing->insurance_price && 0.0 === (float) $colisly_ins_nothing->insured_value );
colisly_check( 'Assurance : couverture retenue enregistree', 100.0 === (float) $colisly_ins_taken->insured_value );
colisly_check( 'Assurance : prix issu des reglages, pas du formulaire', 4.0 === (float) $colisly_ins_taken->insurance_price );
colisly_check(
	'Assurance : ajoutee au total de l expedition',
	abs( (float) $colisly_ins_taken->total_price - ( (float) $colisly_ins_nothing->total_price + 4.0 ) ) < 0.001
);
colisly_check( 'Assurance : montant fantaisiste ignore, rien facture', 0.0 === (float) $colisly_ins_forged->insurance_price );

if ( COLISLY_Orders::available() && $colisly_ins_taken->order_id ) {
	$colisly_ins_order = wc_get_order( (int) $colisly_ins_taken->order_id );
	colisly_check(
		'Assurance : total de la commande = total de l expedition',
		abs( (float) $colisly_ins_order->get_total() - (float) $colisly_ins_taken->total_price ) < 0.001
	);
	$colisly_ins_line = false;
	foreach ( $colisly_ins_order->get_items( 'fee' ) as $colisly_ins_item ) {
		if ( false !== strpos( $colisly_ins_item->get_name(), 'Insurance' ) ) {
			$colisly_ins_line = (float) $colisly_ins_item->get_total();
		}
	}
	colisly_check( 'Assurance : ligne dediee sur la commande', 4.0 === $colisly_ins_line );
}

$colisly_ins_settings['insurance_options'] = $colisly_ins_saved;
COLISLY_Settings::update( $colisly_ins_settings );

/*
 * Zones de destination.
 *
 * Un reexpediteur ne facture pas le meme transport vers la metropole, vers
 * l'outre-mer et vers Madagascar. Une grille unique par transporteur ne pouvait
 * donc pas porter de vrais tarifs. Un pays sans zone, ou une zone pour laquelle
 * le transporteur n'a pas ete tarife, retombe sur la grille par defaut.
 */
$colisly_zone_settings = COLISLY_Settings::all();
$colisly_zone_saved    = array( $colisly_zone_settings['zones'], $colisly_zone_settings['carriers'] );

$colisly_zone_settings['zones'] = array(
	array(
		'slug'      => 'metropole',
		'name'      => 'Metropole',
		'countries' => 'FR',
	),
	array(
		'slug'      => 'outremer',
		'name'      => 'Outre-mer',
		'countries' => 'RE, YT; GP',
	),
);
$colisly_zone_settings['carriers'] = array(
	array(
		'slug'         => 'zone-test',
		'name'         => 'Zone test',
		'enabled'      => 1,
		'price_base'   => 5.0,
		'price_per_kg' => 1.0,
		'tiers'        => array(
			array(
				'max_weight' => 10,
				'price'      => 25.0,
			),
		),
		'zone_tiers'   => array(
			'outremer' => array(
				array(
					'max_weight' => 10,
					'price'      => 80.0,
				),
			),
		),
	),
);
COLISLY_Settings::update( $colisly_zone_settings );

colisly_check( 'Zone : pays reconnu', 'outremer' === COLISLY_Zones::for_country( 'GP' )['slug'] );
colisly_check( 'Zone : casse ignoree', 'outremer' === COLISLY_Zones::for_country( 'gp' )['slug'] );
colisly_check( 'Zone : separateurs melanges acceptes', 3 === count( COLISLY_Zones::for_country( 'YT' )['countries'] ) );
colisly_check( 'Zone : pays hors zone', null === COLISLY_Zones::for_country( 'US' ) );
colisly_check( 'Zone : pays vide', null === COLISLY_Zones::for_country( '' ) );

colisly_check( 'Zone : grille de la zone appliquee', 80.0 === COLISLY_Carriers::price_for( 'zone-test', 2, 'GP' ) );
colisly_check( 'Zone : pays hors zone garde la grille par defaut', 25.0 === COLISLY_Carriers::price_for( 'zone-test', 2, 'US' ) );
colisly_check( 'Zone : zone sans grille garde la grille par defaut', 25.0 === COLISLY_Carriers::price_for( 'zone-test', 2, 'FR' ) );
colisly_check( 'Zone : sans destination, grille par defaut', 25.0 === COLISLY_Carriers::price_for( 'zone-test', 2 ) );
// 5 + 1 x 12 = 17, sous la derniere tranche de la zone : le plancher tient.
colisly_check( 'Zone : plancher de la derniere tranche respecte', 80.0 === COLISLY_Carriers::price_for( 'zone-test', 12, 'GP' ) );

$colisly_zone_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'zone-' . wp_generate_password( 6, false ),
			'user_email' => 'zone-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_zone_row = COLISLY_Clients::get( $colisly_zone_client );
update_user_meta( (int) $colisly_zone_row->user_id, 'shipping_country', 'GP' );

colisly_check( 'Zone : destination lue sur l adresse du compte', 'GP' === COLISLY_Shipments::client_country( $colisly_zone_row ) );

$colisly_zone_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_zone_client,
		'weight'    => 2,
	)
);
$colisly_zone_ship = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_zone_client, array( $colisly_zone_parcel ), 'zone-test' ) );
colisly_check( 'Zone : destination enregistree sur l expedition', 'GP' === $colisly_zone_ship->destination_country );
colisly_check( 'Zone : transport facture sur la grille de la zone', 80.0 === (float) $colisly_zone_ship->carrier_price );

$colisly_zone_parcel2 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_zone_client,
		'weight'    => 2,
	)
);
$colisly_zone_ship2 = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_zone_client, array( $colisly_zone_parcel2 ), 'zone-test', 0, 'US' ) );
colisly_check( 'Zone : destination choisie prime sur l adresse', 'US' === $colisly_zone_ship2->destination_country );
colisly_check( 'Zone : tarif suit la destination choisie', 25.0 === (float) $colisly_zone_ship2->carrier_price );

list( $colisly_zone_settings['zones'], $colisly_zone_settings['carriers'] ) = $colisly_zone_saved;
COLISLY_Settings::update( $colisly_zone_settings );

/*
 * Poids volumetrique.
 *
 * Les transporteurs express facturent l'encombrement, mais pas a la place du
 * poids reel : ils retiennent le plus grand des deux, colis par colis. Un
 * carton dense de 20 kg dans 20x20x20 serait sinon facture 1,6 kg. Un colis
 * dont les dimensions n'ont pas ete saisies garde son poids reel, sinon un
 * oubli de saisie ferait voyager le colis gratuitement.
 */
$colisly_vol_settings = COLISLY_Settings::all();
$colisly_vol_saved    = $colisly_vol_settings['carriers'];

$colisly_vol_settings['carriers'] = array(
	array(
		'slug'               => 'reel',
		'name'               => 'Reel',
		'enabled'            => 1,
		'price_base'         => 0,
		'price_per_kg'       => 1,
		'volumetric'         => 0,
	),
	array(
		'slug'               => 'volume',
		'name'               => 'Volume',
		'enabled'            => 1,
		'price_base'         => 0,
		'price_per_kg'       => 1,
		'volumetric'         => 1,
		'volumetric_divisor' => 5000,
	),
	array(
		'slug'               => 'volume4000',
		'name'               => 'Volume 4000',
		'enabled'            => 1,
		'price_base'         => 0,
		'price_per_kg'       => 1,
		'volumetric'         => 1,
		'volumetric_divisor' => 4000,
	),
);
COLISLY_Settings::update( $colisly_vol_settings );

// 60x40x40 = 96000 cm3, soit 19,2 kg a 5000 et 24 kg a 4000, pour 3 kg reels.
$colisly_vol_leger = (object) array(
	'weight' => 3.0,
	'length' => 60,
	'width'  => 40,
	'height' => 40,
);
// 20x20x20 = 8000 cm3, soit 1,6 kg, pour 20 kg reels.
$colisly_vol_dense = (object) array(
	'weight' => 20.0,
	'length' => 20,
	'width'  => 20,
	'height' => 20,
);
$colisly_vol_sans  = (object) array(
	'weight' => 5.0,
	'length' => null,
	'width'  => null,
	'height' => null,
);

colisly_check( 'Volumetrique : transporteur au reel ignore les dimensions', 3.0 === COLISLY_Carriers::chargeable_weight( 'reel', array( $colisly_vol_leger ) ) );
colisly_check( 'Volumetrique : colis leger et volumineux facture au volume', 19.2 === COLISLY_Carriers::chargeable_weight( 'volume', array( $colisly_vol_leger ) ) );
colisly_check( 'Volumetrique : diviseur du contrat respecte', 24.0 === COLISLY_Carriers::chargeable_weight( 'volume4000', array( $colisly_vol_leger ) ) );
colisly_check( 'Volumetrique : colis dense garde son poids reel', 20.0 === COLISLY_Carriers::chargeable_weight( 'volume', array( $colisly_vol_dense ) ) );
colisly_check( 'Volumetrique : sans dimensions, poids reel', 5.0 === COLISLY_Carriers::chargeable_weight( 'volume', array( $colisly_vol_sans ) ) );
colisly_check(
	'Volumetrique : cumul colis par colis',
	44.2 === COLISLY_Carriers::chargeable_weight( 'volume', array( $colisly_vol_leger, $colisly_vol_dense, $colisly_vol_sans ) )
);
colisly_check(
	'Volumetrique : jamais moins qu au poids reel',
	COLISLY_Carriers::chargeable_weight( 'volume', array( $colisly_vol_dense ) ) >= COLISLY_Carriers::chargeable_weight( 'reel', array( $colisly_vol_dense ) )
);
colisly_check( 'Volumetrique : transporteur inconnu, poids reel', 3.0 === COLISLY_Carriers::chargeable_weight( 'inexistant', array( $colisly_vol_leger ) ) );

// Le tarif de l'expedition doit suivre le poids taxable, pas celui de la balance.
$colisly_vol_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'vol-' . wp_generate_password( 6, false ),
			'user_email' => 'vol-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_vol_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_vol_client,
		'weight'    => 3,
		'length'    => 60,
		'width'     => 40,
		'height'    => 40,
	)
);
$colisly_vol_ship = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_vol_client, array( $colisly_vol_parcel ), 'volume' ) );
colisly_check( 'Volumetrique : transport facture sur 19,2 kg', 19.2 === (float) $colisly_vol_ship->carrier_price );
colisly_check( 'Volumetrique : le poids reel reste enregistre sur l expedition', 3.0 === (float) $colisly_vol_ship->total_weight );

$colisly_vol_settings['carriers'] = $colisly_vol_saved;
COLISLY_Settings::update( $colisly_vol_settings );

/*
 * Declaration douaniere.
 *
 * Le plugin ne devine pas quelles destinations en exigent une : reexpedier de
 * metropole vers la Guadeloupe en demande une, vers la Belgique non, alors que
 * les deux sont dans l'UE. Le reexpediteur marque donc les zones concernees.
 */
$colisly_cu_settings = COLISLY_Settings::all();
$colisly_cu_saved    = $colisly_cu_settings['zones'];

$colisly_cu_settings['zones'] = array(
	array(
		'slug'      => 'metropole',
		'name'      => 'Metropole',
		'countries' => 'FR',
		'customs'   => 0,
	),
	array(
		'slug'      => 'outremer',
		'name'      => 'Outre-mer',
		'countries' => 'GP, MQ, RE',
		'customs'   => 1,
	),
);
COLISLY_Settings::update( $colisly_cu_settings );

colisly_check( 'Douane : exigee sur une zone marquee', COLISLY_Customs::required_for( 'GP' ) );
colisly_check( 'Douane : non exigee sur une zone non marquee', ! COLISLY_Customs::required_for( 'FR' ) );
colisly_check( 'Douane : non exigee hors de toute zone', ! COLISLY_Customs::required_for( 'BE' ) );

$colisly_cu_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'cu-' . wp_generate_password( 6, false ),
			'user_email' => 'cu-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_cu_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cu_client,
		'weight'    => 4,
	)
);

colisly_check( 'Douane : colis non declare au depart', ! COLISLY_Customs::declared( $colisly_cu_parcel ) );

$colisly_cu_saved_lines = COLISLY_Customs::save(
	$colisly_cu_parcel,
	array(
		array(
			'description'    => 'T-shirts',
			'quantity'       => 3,
			'unit_weight'    => '0,2',
			'unit_value'     => '15',
			'origin_country' => 'fr',
		),
		array(
			'description' => '   ',
			'quantity'    => 9,
		),
		array(
			'description' => 'Baskets',
			'quantity'    => 2,
			'unit_weight' => '0,9',
			'unit_value'  => '40',
		),
	)
);
colisly_check( 'Douane : lignes vides ecartees', 2 === $colisly_cu_saved_lines );
colisly_check( 'Douane : colis declare', COLISLY_Customs::declared( $colisly_cu_parcel ) );

$colisly_cu_items = COLISLY_Customs::items( $colisly_cu_parcel );
colisly_check( 'Douane : virgule decimale acceptee', 0.2 === (float) $colisly_cu_items[0]->unit_weight );
colisly_check( 'Douane : code pays normalise', 'FR' === $colisly_cu_items[0]->origin_country );

$colisly_cu_totals = COLISLY_Customs::totals( $colisly_cu_parcel );
colisly_check( 'Douane : quantite totale', 5 === $colisly_cu_totals['quantity'] );
colisly_check( 'Douane : poids net total', 2.4 === $colisly_cu_totals['weight'] );
colisly_check( 'Douane : valeur totale', 125.0 === $colisly_cu_totals['value'] );

COLISLY_Customs::save( $colisly_cu_parcel, array( array( 'description' => 'Livres' ) ) );
colisly_check( 'Douane : declaration remplacee et non cumulee', 1 === count( COLISLY_Customs::items( $colisly_cu_parcel ) ) );

// Le blocage a l'expedition.
$colisly_cu_p2 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cu_client,
		'weight'    => 2,
	)
);
colisly_check(
	'Douane : expedition refusee vers une zone exigeante sans declaration',
	is_wp_error( COLISLY_Shipments::request( $colisly_cu_client, array( $colisly_cu_p2 ), 'colissimo', 0, 'GP' ) )
);
colisly_check(
	'Douane : expedition acceptee vers une zone sans exigence',
	! is_wp_error( COLISLY_Shipments::request( $colisly_cu_client, array( $colisly_cu_p2 ), 'colissimo', 0, 'FR' ) )
);

$colisly_cu_p3 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cu_client,
		'weight'    => 2,
	)
);
COLISLY_Customs::save( $colisly_cu_p3, array( array( 'description' => 'Vetements' ) ) );
colisly_check(
	'Douane : expedition acceptee une fois le colis declare',
	! is_wp_error( COLISLY_Shipments::request( $colisly_cu_client, array( $colisly_cu_p3 ), 'colissimo', 0, 'GP' ) )
);

// RGPD : ce que l'effacement supprime, l'export doit le montrer.
$colisly_cu_p4 = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cu_client,
		'weight'    => 1,
	)
);
COLISLY_Customs::save( $colisly_cu_p4, array( array( 'description' => 'Objet declare' ) ) );
$colisly_cu_user  = get_userdata( (int) COLISLY_Clients::get( $colisly_cu_client )->user_id );
$colisly_cu_export = COLISLY_Privacy::export( $colisly_cu_user->user_email, 1 );
$colisly_cu_found  = false;
foreach ( $colisly_cu_export['data'] as $colisly_cu_group ) {
	foreach ( $colisly_cu_group['data'] as $colisly_cu_field ) {
		if ( false !== strpos( (string) $colisly_cu_field['value'], 'Objet declare' ) ) {
			$colisly_cu_found = true;
		}
	}
}
colisly_check( 'Douane : declaration presente dans l export RGPD', $colisly_cu_found );

COLISLY_Privacy::erase( $colisly_cu_user->user_email, 1 );
colisly_check( 'Douane : declaration effacee par l effaceur RGPD', ! COLISLY_Customs::declared( $colisly_cu_p4 ) );

$colisly_cu_settings['zones'] = $colisly_cu_saved;
COLISLY_Settings::update( $colisly_cu_settings );

/*
 * Toute table creee doit etre supprimee a la desinstallation, sinon une table
 * de donnees personnelles survit a la suppression de l'extension.
 */
$colisly_created = array();
preg_match_all( '/CREATE TABLE \{\$wpdb->prefix\}(\w+)/', file_get_contents( COLISLY_PLUGIN_DIR . 'includes/class-colisly-install.php' ), $colisly_created );
preg_match_all( "/'(colisly_\w+)'/", file_get_contents( COLISLY_PLUGIN_DIR . 'uninstall.php' ), $colisly_dropped );
colisly_check(
	'Desinstallation : aucune table creee n est oubliee',
	array() === array_diff( $colisly_created[1], $colisly_dropped[1] )
);

/*
 * Categories de contenu et plafond de lignes.
 *
 * Les categories sont celles du reexpediteur, pas de l'extension : un
 * reexpediteur de vetements et un de materiel agricole ne decrivent pas leurs
 * colis avec les memes mots. Reglage vide, le champ reste un texte libre.
 */
$colisly_cat_settings = COLISLY_Settings::all();
$colisly_cat_saved    = array( $colisly_cat_settings['customs_categories'], $colisly_cat_settings['customs_max_lines'] );

$colisly_cat_settings['customs_categories'] = '';
$colisly_cat_settings['customs_max_lines']  = 0;
COLISLY_Settings::update( $colisly_cat_settings );
colisly_check( 'Categories : aucune par defaut', array() === COLISLY_Customs::categories() );
colisly_check( 'Categories : aucun plafond par defaut', 0 === COLISLY_Customs::max_lines() );

$colisly_cat_settings['customs_categories'] = "Vetements\nChaussures\n\n  Livres  \nVetements";
$colisly_cat_settings['customs_max_lines']  = 3;
COLISLY_Settings::update( $colisly_cat_settings );

$colisly_cat_list = COLISLY_Customs::categories();
colisly_check( 'Categories : lignes vides et doublons ecartes', 3 === count( $colisly_cat_list ) );
colisly_check( 'Categories : espaces superflus retires', 'Livres' === $colisly_cat_list[2] );

$colisly_cat_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'cat-' . wp_generate_password( 6, false ),
			'user_email' => 'cat-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_cat_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cat_client,
		'weight'    => 2,
	)
);

$colisly_cat_n = COLISLY_Customs::save(
	$colisly_cat_parcel,
	array(
		array(
			'description' => 'Vetements',
			'unit_value'  => 50,
		),
		array(
			'description' => 'Chaussures',
			'unit_value'  => 80,
		),
		array(
			'description' => 'Livres',
			'unit_value'  => 20,
		),
		array(
			'description' => 'Jouets',
			'unit_value'  => 10,
		),
		array(
			'description' => 'Montres',
			'unit_value'  => 200,
		),
	)
);
colisly_check( 'Plafond : 5 lignes envoyees, 3 retenues', 3 === $colisly_cat_n );
colisly_check( 'Plafond : les premieres lignes sont gardees', 'Vetements' === COLISLY_Customs::items( $colisly_cat_parcel )[0]->description );

$colisly_cat_settings['customs_max_lines'] = 0;
COLISLY_Settings::update( $colisly_cat_settings );
colisly_check(
	'Plafond : sans limite, tout est garde',
	5 === COLISLY_Customs::save(
		$colisly_cat_parcel,
		array(
			array( 'description' => 'A' ),
			array( 'description' => 'B' ),
			array( 'description' => 'C' ),
			array( 'description' => 'D' ),
			array( 'description' => 'E' ),
		)
	)
);

// Une valeur par ligne, et le total qui suit : le choix A.
COLISLY_Customs::save(
	$colisly_cat_parcel,
	array(
		array(
			'description' => 'Vetements',
			'quantity'    => 2,
			'unit_value'  => 45,
		),
		array(
			'description' => 'Livres',
			'quantity'    => 3,
			'unit_value'  => 10,
		),
	)
);
colisly_check( 'Valeur : total = somme des lignes', 120.0 === COLISLY_Customs::totals( $colisly_cat_parcel )['value'] );

list( $colisly_cat_settings['customs_categories'], $colisly_cat_settings['customs_max_lines'] ) = $colisly_cat_saved;
COLISLY_Settings::update( $colisly_cat_settings );

/*
 * Adresse de livraison.
 *
 * Le formulaire ne demandait qu'un pays de destination : une demande pouvait
 * donc arriver chez le reexpediteur sans aucune rue ou envoyer le colis. Pire,
 * le pays choisi servait au tarif pendant que l'etiquette portait l'adresse du
 * compte, et seule l'etiquette disait vrai. La destination est desormais
 * l'adresse elle-meme.
 */
$colisly_addr_user = wp_insert_user(
	array(
		'user_login' => 'addr-' . wp_generate_password( 6, false ),
		'user_email' => 'addr-' . wp_generate_password( 6, false ) . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
$colisly_addr_client = COLISLY_Clients::create( $colisly_addr_user );
$colisly_addr_row    = COLISLY_Clients::get( $colisly_addr_client );

$colisly_addr_empty = COLISLY_Shipments::address_missing_fields( COLISLY_Shipments::client_address( $colisly_addr_row ) );
colisly_check( 'Adresse : un compte vide est signale incomplet', ! empty( $colisly_addr_empty ) );

// Compte ne portant qu'une adresse de facturation, le cas le plus frequent
// quand la boutique ne demande pas d'adresse de livraison distincte.
foreach (
	array(
		'first_name' => 'Jean',
		'last_name'  => 'Dupont',
		'address_1'  => '12 rue des Lilas',
		'city'       => 'Paris',
		'postcode'   => '75011',
		'country'    => 'FR',
	) as $colisly_addr_k => $colisly_addr_v
) {
	update_user_meta( $colisly_addr_user, 'billing_' . $colisly_addr_k, $colisly_addr_v );
}

$colisly_addr = COLISLY_Shipments::client_address( $colisly_addr_row );
colisly_check( 'Adresse : la facturation sert de repli', '12 rue des Lilas' === $colisly_addr['address_1'] );
colisly_check( 'Adresse : pays lu sur le repli', 'FR' === $colisly_addr['country'] );
colisly_check( 'Adresse : complete, plus rien ne manque', array() === COLISLY_Shipments::address_missing_fields( $colisly_addr ) );

// Une adresse de livraison renseignee prime, et les deux jeux ne se melangent
// pas : une rue d'un cote et une ville de l'autre donneraient une adresse qui
// n'existe nulle part.
update_user_meta( $colisly_addr_user, 'shipping_address_1', '8 allee des Manguiers' );
update_user_meta( $colisly_addr_user, 'shipping_city', 'Antananarivo' );
update_user_meta( $colisly_addr_user, 'shipping_postcode', '101' );
update_user_meta( $colisly_addr_user, 'shipping_country', 'MG' );

$colisly_addr2 = COLISLY_Shipments::client_address( $colisly_addr_row );
colisly_check( 'Adresse : la livraison prime sur la facturation', '8 allee des Manguiers' === $colisly_addr2['address_1'] );
colisly_check( 'Adresse : aucun melange entre les deux jeux', 'Antananarivo' === $colisly_addr2['city'] );
colisly_check( 'Adresse : le nom est repris de la facturation', 'Dupont' === $colisly_addr2['last_name'] );

// Ce qu'un transporteur exige depend du pays : Madagascar reclame une region,
// la France non. La regle est lue dans WooCommerce, pas devinee ici.
$colisly_addr_mg = COLISLY_Shipments::address_missing_fields( $colisly_addr2 );
colisly_check( 'Adresse : exigence propre au pays de destination', in_array( 'MG', array( $colisly_addr2['country'] ), true ) && ! empty( $colisly_addr_mg ) );

update_user_meta( $colisly_addr_user, 'shipping_state', 'Analamanga' );
$colisly_addr3 = COLISLY_Shipments::client_address( $colisly_addr_row );
colisly_check( 'Adresse : region renseignee, plus rien ne manque', array() === COLISLY_Shipments::address_missing_fields( $colisly_addr3 ) );

// La commande WooCommerce doit porter une destination. Elle etait construite a
// partir des seuls champs de livraison : un compte sans adresse de livraison
// produisait une commande sans destination du tout.
$colisly_addr_user2 = wp_insert_user(
	array(
		'user_login' => 'addr2-' . wp_generate_password( 6, false ),
		'user_email' => 'addr2-' . wp_generate_password( 6, false ) . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
foreach (
	array(
		'first_name' => 'Marie',
		'last_name'  => 'Martin',
		'address_1'  => '4 quai Saint-Antoine',
		'city'       => 'Lyon',
		'postcode'   => '69002',
		'country'    => 'FR',
	) as $colisly_addr_k => $colisly_addr_v
) {
	update_user_meta( $colisly_addr_user2, 'billing_' . $colisly_addr_k, $colisly_addr_v );
}

$colisly_addr_client2 = COLISLY_Clients::create( $colisly_addr_user2 );
$colisly_addr_parcel  = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_addr_client2,
		'weight'    => 1,
	)
);
$colisly_addr_ship  = COLISLY_Shipments::get( COLISLY_Shipments::request( $colisly_addr_client2, array( $colisly_addr_parcel ), 'colissimo' ) );
$colisly_addr_order = wc_get_order( (int) $colisly_addr_ship->order_id );
colisly_check( 'Commande : une destination est toujours portee', '4 quai Saint-Antoine' === $colisly_addr_order->get_shipping_address_1() );
colisly_check( 'Commande : ville de destination portee', 'Lyon' === $colisly_addr_order->get_shipping_city() );

/*
 * Annulation par le client.
 *
 * Une demande faite par erreur n'offrait qu'une issue : la payer. Elle restait
 * dans les commandes a regler sans moyen de la retirer. Le client la retire
 * tant qu'elle n'est pas payee, ce qui remet les colis en stock et annule la
 * commande impayee avec elle.
 */
$colisly_cancel_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'annul-' . wp_generate_password( 6, false ),
			'user_email' => 'annul-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_cancel_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_cancel_client,
		'weight'    => 1,
	)
);
$colisly_cancel_id   = COLISLY_Shipments::request( $colisly_cancel_client, array( $colisly_cancel_parcel ), 'colissimo' );
$colisly_cancel_ship = COLISLY_Shipments::get( $colisly_cancel_id );

colisly_check( 'Annulation : une demande en attente de paiement est retirable', COLISLY_Shipments::client_can_cancel( $colisly_cancel_ship ) );

$colisly_cancel_order_id = (int) $colisly_cancel_ship->order_id;
COLISLY_Shipments::set_status( $colisly_cancel_id, 'cancelled' );

$colisly_cancel_after  = COLISLY_Shipments::get( $colisly_cancel_id );
$colisly_cancel_parcel_after = COLISLY_Parcels::get( $colisly_cancel_parcel );
colisly_check( 'Annulation : le colis revient en stock', 'available' === $colisly_cancel_parcel_after->status );
colisly_check( 'Annulation : le colis est detache de l expedition', empty( $colisly_cancel_parcel_after->shipment_id ) );
colisly_check( 'Annulation : la commande impayee est annulee', 'cancelled' === wc_get_order( $colisly_cancel_order_id )->get_status() );
colisly_check( 'Annulation : une demande annulee ne l est plus une seconde fois', ! COLISLY_Shipments::client_can_cancel( $colisly_cancel_after ) );

$colisly_cancel_paid = (object) array( 'status' => 'paid' );
colisly_check( 'Annulation : une expedition payee echappe au client', ! COLISLY_Shipments::client_can_cancel( $colisly_cancel_paid ) );
colisly_check( 'Annulation : une expedition partie echappe au client', ! COLISLY_Shipments::client_can_cancel( (object) array( 'status' => 'shipped' ) ) );
colisly_check( 'Annulation : une demande fraiche est retirable', COLISLY_Shipments::client_can_cancel( (object) array( 'status' => 'requested' ) ) );

/*
 * Gardes de source.
 *
 * Le pays de destination ne doit plus etre un choix libre a cote de l'adresse :
 * les deux pouvaient se contredire, le tarif suivant l'un et l'etiquette
 * l'autre. Et une demande retiree doit rester atteignable depuis la liste.
 */
$colisly_guard_src = file_get_contents( COLISLY_PLUGIN_DIR . 'includes/frontend/class-colisly-account.php' );
colisly_check(
	'Garde : le pays de destination n est plus un menu libre',
	false === strpos( $colisly_guard_src, '<select name="colisly_country"' )
);
colisly_check(
	'Garde : la destination est portee par l adresse du compte',
	false !== strpos( $colisly_guard_src, 'name="colisly_country" id="colisly-country"' )
);
colisly_check(
	'Garde : la demande refuse une adresse incomplete',
	false !== strpos( $colisly_guard_src, 'address_missing_fields' )
);
colisly_check(
	'Garde : le retrait d une demande est propose au client',
	false !== strpos( $colisly_guard_src, 'cancel_shipment' )
);

/*
 * Formulaire de declaration : autant de lignes que la limite en promet.
 *
 * Le formulaire n'offrait qu'une seule ligne vierge. Une limite de trois
 * lignes reglee dans les reglages etait donc une promesse jamais tenue : le
 * client ne pouvait declarer qu'un article par envoi, et sur la demande
 * d'expedition il n'y a pas de second envoi. Le rendu est teste ici, pas
 * seulement l'enregistrement, parce que c'est le rendu qui etait faux.
 */
$colisly_form_settings = COLISLY_Settings::all();
$colisly_form_saved    = array(
	$colisly_form_settings['customs_max_lines'],
	$colisly_form_settings['customs_categories'],
	isset( $colisly_form_settings['customs_ask_quantity'] ) ? $colisly_form_settings['customs_ask_quantity'] : 1,
	isset( $colisly_form_settings['customs_ask_weight'] ) ? $colisly_form_settings['customs_ask_weight'] : 1,
	isset( $colisly_form_settings['customs_ask_origin'] ) ? $colisly_form_settings['customs_ask_origin'] : 1,
);

$colisly_form_client = COLISLY_Clients::create(
	wp_insert_user(
		array(
			'user_login' => 'decl-' . wp_generate_password( 6, false ),
			'user_email' => 'decl-' . wp_generate_password( 6, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		)
	)
);
$colisly_form_parcel = COLISLY_Parcels::get(
	COLISLY_Parcels::create(
		array(
			'client_id' => $colisly_form_client,
			'weight'    => 2,
		)
	)
);

$colisly_render = new ReflectionMethod( 'COLISLY_Account', 'customs_lines_table' );
$colisly_render->setAccessible( true );

/**
 * Rend le tableau de declaration d'un colis et renvoie son HTML.
 *
 * @param ReflectionMethod $method Methode privee de rendu.
 * @param object           $parcel Colis.
 * @return string
 */
function colisly_render_declaration( $method, $parcel ) {
	ob_start();
	$method->invoke( null, $parcel, 'customs' );
	return (string) ob_get_clean();
}

/**
 * Compte les lignes du corps du tableau rendu.
 *
 * @param string $html HTML rendu.
 * @return int
 */
function colisly_count_rows( $html ) {
	if ( ! preg_match( '/<tbody>(.*)<\/tbody>/s', $html, $m ) ) {
		return 0;
	}
	return preg_match_all( '/<tr>/', $m[1] );
}

$colisly_form_settings['customs_max_lines']    = 3;
$colisly_form_settings['customs_categories']   = "Vetements\nChaussures\nLivres";
$colisly_form_settings['customs_ask_quantity'] = 1;
$colisly_form_settings['customs_ask_weight']   = 1;
$colisly_form_settings['customs_ask_origin']   = 1;
COLISLY_Settings::update( $colisly_form_settings );

$colisly_html = colisly_render_declaration( $colisly_render, $colisly_form_parcel );
colisly_check( 'Declaration : une limite de 3 offre 3 lignes', 3 === colisly_count_rows( $colisly_html ) );
colisly_check( 'Declaration : sous limite, aucun bouton d ajout', false === strpos( $colisly_html, 'colisly-add-customs-line' ) );

// Deux lignes deja declarees : le formulaire complete jusqu'a la limite au
// lieu de repartir de zero ou d'en offrir une de trop.
COLISLY_Customs::save(
	(int) $colisly_form_parcel->id,
	array(
		array( 'description' => 'Vetements', 'unit_value' => 20 ),
		array( 'description' => 'Livres', 'unit_value' => 10 ),
	)
);
$colisly_html = colisly_render_declaration( $colisly_render, $colisly_form_parcel );
colisly_check( 'Declaration : 2 lignes saisies + 1 vierge = 3', 3 === colisly_count_rows( $colisly_html ) );

// Sans limite, quelques lignes et un bouton, puisqu aucun nombre n est juste
// pour tous les colis.
$colisly_form_settings['customs_max_lines'] = 0;
COLISLY_Settings::update( $colisly_form_settings );
$colisly_html = colisly_render_declaration( $colisly_render, $colisly_form_parcel );
colisly_check( 'Declaration : sans limite, les lignes saisies plus des vierges', colisly_count_rows( $colisly_html ) > 3 );
colisly_check( 'Declaration : sans limite, un bouton d ajout', false !== strpos( $colisly_html, 'colisly-add-customs-line' ) );

/*
 * Colonnes facultatives.
 *
 * Quantite, poids unitaire et pays d'origine sont ce qu'un CN23 reclame ligne
 * par ligne. Un reexpediteur qui recopie ensuite le formulaire de son
 * transporteur n'en a aucun besoin, et trois colonnes remplies pour rien sont
 * trois colonnes remplies n'importe comment. Elles restent demandees par
 * defaut : un site qui les collecte continue de les collecter.
 */
colisly_check( 'Colonnes : la quantite est demandee par defaut', COLISLY_Customs::asks( 'quantity' ) );
colisly_check( 'Colonnes : le poids est demande par defaut', COLISLY_Customs::asks( 'weight' ) );
colisly_check( 'Colonnes : l origine est demandee par defaut', COLISLY_Customs::asks( 'origin' ) );

$colisly_form_settings['customs_ask_quantity'] = 0;
$colisly_form_settings['customs_ask_weight']   = 0;
$colisly_form_settings['customs_ask_origin']   = 0;
COLISLY_Settings::update( $colisly_form_settings );

colisly_check( 'Colonnes : la quantite peut etre retiree', ! COLISLY_Customs::asks( 'quantity' ) );
colisly_check( 'Colonnes : le poids peut etre retire', ! COLISLY_Customs::asks( 'weight' ) );
colisly_check( 'Colonnes : l origine peut etre retiree', ! COLISLY_Customs::asks( 'origin' ) );

$colisly_html = colisly_render_declaration( $colisly_render, $colisly_form_parcel );
colisly_check( 'Colonnes : la quantite disparait du formulaire', false === strpos( $colisly_html, '[quantity]' ) );
colisly_check( 'Colonnes : le poids disparait du formulaire', false === strpos( $colisly_html, '[unit_weight]' ) );
colisly_check( 'Colonnes : l origine disparait du formulaire', false === strpos( $colisly_html, '[origin_country]' ) );
colisly_check( 'Colonnes : le contenu reste demande', false !== strpos( $colisly_html, '[description]' ) );
colisly_check( 'Colonnes : la valeur reste demandee', false !== strpos( $colisly_html, '[unit_value]' ) );

// Chaque cellule garde son libelle, colonnes retirees comprises : c est ce qui
// permet au tableau de s empiler proprement sur mobile.
if ( preg_match( '/<tbody>(.*)<\/tbody>/s', $colisly_html, $colisly_body ) ) {
	colisly_check(
		'Colonnes : chaque cellule restante porte son libelle',
		preg_match_all( '/<td[ >]/', $colisly_body[1] ) === preg_match_all( '/data-title=/', $colisly_body[1] )
	);
}

// Une declaration posee sans quantite ni origine reste valide, les valeurs
// manquantes prenant leur defaut plutot que de faire echouer l enregistrement.
COLISLY_Customs::save(
	(int) $colisly_form_parcel->id,
	array(
		array( 'description' => 'Chaussures', 'unit_value' => 30 ),
	)
);
$colisly_form_items = COLISLY_Customs::items( (int) $colisly_form_parcel->id );
colisly_check( 'Colonnes : une ligne sans quantite vaut 1', 1 === (int) $colisly_form_items[0]->quantity );
colisly_check( 'Colonnes : une ligne sans origine reste vide', '' === (string) $colisly_form_items[0]->origin_country );
colisly_check( 'Colonnes : la valeur est bien enregistree', 30.0 === (float) $colisly_form_items[0]->unit_value );

list(
	$colisly_form_settings['customs_max_lines'],
	$colisly_form_settings['customs_categories'],
	$colisly_form_settings['customs_ask_quantity'],
	$colisly_form_settings['customs_ask_weight'],
	$colisly_form_settings['customs_ask_origin']
) = $colisly_form_saved;
COLISLY_Settings::update( $colisly_form_settings );

/*
 * Recherche de clients.
 *
 * Elle ne lisait que le prenom et le nom WordPress. Un client cree par
 * WooCommerce, a la commande ou depuis son ecran Clients, porte un nom de
 * facturation et le plus souvent aucun nom WordPress : l'operateur tapait le
 * nom qu'il voyait sur chaque commande et ne trouvait personne, alors que le
 * compte figurait bien dans l'onglet Clients.
 */
$colisly_srch_user = wp_insert_user(
	array(
		'user_login' => 'srch-' . wp_generate_password( 6, false ),
		'user_email' => 'srch-' . wp_generate_password( 6, false ) . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
update_user_meta( $colisly_srch_user, 'billing_first_name', 'Fabrice' );
update_user_meta( $colisly_srch_user, 'billing_last_name', 'Ravalomanana' );
update_user_meta( $colisly_srch_user, 'billing_company', 'Import Tana' );
update_user_meta( $colisly_srch_user, 'billing_phone', '+261 34 05 123 45' );
update_user_meta( $colisly_srch_user, 'shipping_last_name', 'Rakoto' );

$colisly_srch_client = COLISLY_Clients::create( $colisly_srch_user );
$colisly_srch_row    = COLISLY_Clients::get( $colisly_srch_client );
$colisly_srch_parcel = COLISLY_Parcels::create(
	array(
		'client_id' => $colisly_srch_client,
		'weight'    => 1,
	)
);

/**
 * Indique si une recherche de clients renvoie le client de test.
 *
 * @param string $term      Terme.
 * @param int    $client_id Client attendu.
 * @return bool
 */
function colisly_finds_client( $term, $client_id ) {
	foreach ( COLISLY_Clients::search( $term ) as $row ) {
		if ( (int) $row->id === (int) $client_id ) {
			return true;
		}
	}
	return false;
}

/**
 * Indique si une recherche de colis renvoie le colis de test.
 *
 * @param string $term      Terme.
 * @param int    $parcel_id Colis attendu.
 * @return bool
 */
function colisly_finds_parcel( $term, $parcel_id ) {
	foreach ( COLISLY_Parcels::paged_list( array( 'search' => $term ) )['items'] as $row ) {
		if ( (int) $row->id === (int) $parcel_id ) {
			return true;
		}
	}
	return false;
}

colisly_check( 'Recherche : nom de facturation', colisly_finds_client( 'Ravalomanana', $colisly_srch_client ) );
colisly_check( 'Recherche : prenom de facturation', colisly_finds_client( 'fabrice', $colisly_srch_client ) );
colisly_check( 'Recherche : societe de facturation', colisly_finds_client( 'Tana', $colisly_srch_client ) );
colisly_check( 'Recherche : telephone de facturation', colisly_finds_client( '34 05', $colisly_srch_client ) );
colisly_check( 'Recherche : nom du destinataire (livraison)', colisly_finds_client( 'Rakoto', $colisly_srch_client ) );
colisly_check( 'Recherche : identifiant WordPress', colisly_finds_client( 'srch-', $colisly_srch_client ) );
colisly_check( 'Recherche : reference client', colisly_finds_client( $colisly_srch_row->reference, $colisly_srch_client ) );
colisly_check( 'Recherche : prenom et nom tapes ensemble', colisly_finds_client( 'Fabrice Rav', $colisly_srch_client ) );
colisly_check( 'Recherche : nom puis prenom', colisly_finds_client( 'ravalomanana fabrice', $colisly_srch_client ) );
colisly_check( 'Recherche : un mot faux suffit a ecarter', ! colisly_finds_client( 'Fabrice Durand', $colisly_srch_client ) );
colisly_check( 'Recherche : aucun homonyme inventé', ! colisly_finds_client( 'Zzxq', $colisly_srch_client ) );

// La liste des colis cherche le client de la meme facon, pour que les deux
// ecrans ne se contredisent jamais sur un nom.
colisly_check( 'Recherche colis : par nom de facturation du client', colisly_finds_parcel( 'Ravalomanana', $colisly_srch_parcel ) );
colisly_check( 'Recherche colis : par prenom et nom', colisly_finds_parcel( 'fabrice rav', $colisly_srch_parcel ) );
colisly_check( 'Recherche colis : par numero de colis', colisly_finds_parcel( COLISLY_Parcels::get( $colisly_srch_parcel )->reference, $colisly_srch_parcel ) );

/*
 * Nom affiche.
 *
 * WordPress donne a un compte son identifiant comme nom affiche tant que
 * personne n'en tape un vrai, et WooCommerce ne le fait presque jamais. Le nom
 * de facturation est prefere quand le nom affiche n'est que l'identifiant, le
 * nom affiche quand c'est un vrai nom.
 */
colisly_check( 'Nom : facturation quand le nom affiche est l identifiant', 'Fabrice Ravalomanana' === COLISLY_Clients::name( $colisly_srch_row ) );
colisly_check( 'Nom : depuis la seule ligne client', 'Fabrice Ravalomanana' === COLISLY_Clients::name( (object) array( 'user_id' => $colisly_srch_user ) ) );

wp_update_user(
	array(
		'ID'           => $colisly_srch_user,
		'display_name' => 'F. Ravalomanana',
	)
);
colisly_check( 'Nom : un vrai nom affiche prime', 'F. Ravalomanana' === COLISLY_Clients::name( COLISLY_Clients::get( $colisly_srch_client ) ) );

$colisly_srch_bare = wp_insert_user(
	array(
		'user_login' => 'bare-' . wp_generate_password( 6, false ),
		'user_email' => 'bare-' . wp_generate_password( 6, false ) . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
colisly_check( 'Nom : l identifiant quand rien d autre n existe', get_userdata( $colisly_srch_bare )->user_login === COLISLY_Clients::name( (object) array( 'user_id' => $colisly_srch_bare ) ) );

/*
 * Clients sans fiche.
 *
 * Un client inscrit sur la boutique devient un client du reexpediteur au
 * moment ou un colis arrive pour lui, et c'est au comptoir qu'on l'apprend.
 * Le formulaire de colis ne connaissait que les comptes ayant deja une fiche :
 * chaque nouveau client devait etre cree a la main ailleurs avant que son
 * premier colis puisse etre enregistre. Les comptes sans fiche sont proposes
 * avec les autres, et la fiche nait avec le premier colis.
 */
$colisly_nf_user = wp_insert_user(
	array(
		'user_login' => 'nofiche-' . wp_generate_password( 6, false ),
		'user_email' => 'nofiche-' . wp_generate_password( 6, false ) . '@example.com',
		'user_pass'  => wp_generate_password(),
		'role'       => 'customer',
	)
);
update_user_meta( $colisly_nf_user, 'billing_first_name', 'Nadia' );
update_user_meta( $colisly_nf_user, 'billing_last_name', 'Andriamasy' );

/**
 * Indique si un compte sans fiche est propose pour un terme.
 *
 * @param string $term    Terme.
 * @param int    $user_id Compte attendu.
 * @return bool
 */
function colisly_offers_user( $term, $user_id ) {
	foreach ( COLISLY_Clients::search_users_without_record( $term ) as $row ) {
		if ( (int) $row->user_id === (int) $user_id ) {
			return true;
		}
	}
	return false;
}

colisly_check( 'Sans fiche : absent de la recherche des clients', ! colisly_finds_client( 'Andriamasy', 0 ) && 0 === count( array_filter( COLISLY_Clients::search( 'Andriamasy' ), static function ( $r ) use ( $colisly_nf_user ) { return (int) $r->user_id === (int) $colisly_nf_user; } ) ) );
colisly_check( 'Sans fiche : propose par son nom de facturation', colisly_offers_user( 'Andriamasy', $colisly_nf_user ) );
colisly_check( 'Sans fiche : propose par son e-mail', colisly_offers_user( 'nofiche-', $colisly_nf_user ) );
colisly_check( 'Sans fiche : nomme par sa facturation', 'Nadia Andriamasy' === COLISLY_Clients::name( (object) array( 'user_id' => $colisly_nf_user ) ) );
colisly_check( 'Sans fiche : un client avec fiche n y figure pas', ! colisly_offers_user( 'Ravalomanana', $colisly_srch_user ) );
colisly_check( 'Sans fiche : terme vide, rien', array() === COLISLY_Clients::search_users_without_record( '' ) );

// La fiche nait avec le premier colis, et une seconde creation renvoie la
// meme fiche plutot que d en ouvrir une autre.
$colisly_nf_client = COLISLY_Clients::create( $colisly_nf_user );
colisly_check( 'Sans fiche : la fiche est creee', is_int( $colisly_nf_client ) && $colisly_nf_client > 0 );
colisly_check( 'Sans fiche : creer deux fois rend la meme fiche', $colisly_nf_client === COLISLY_Clients::create( $colisly_nf_user ) );
colisly_check( 'Sans fiche : une fois la fiche creee, il n est plus propose comme nouveau', ! colisly_offers_user( 'Andriamasy', $colisly_nf_user ) );
colisly_check( 'Sans fiche : une fois la fiche creee, il est trouve comme client', colisly_finds_client( 'Andriamasy', $colisly_nf_client ) );

// Garde : le formulaire de colis porte bien le compte choisi, et le
// traitement cree la fiche a partir de lui.
$colisly_nf_src = file_get_contents( COLISLY_PLUGIN_DIR . 'includes/admin/class-colisly-admin-parcels.php' );
colisly_check( 'Garde : le formulaire de colis transmet le compte sans fiche', false !== strpos( $colisly_nf_src, 'name="client_user_id"' ) );
colisly_check( 'Garde : le traitement cree la fiche avec le premier colis', false !== strpos( $colisly_nf_src, 'COLISLY_Clients::create( $user_id )' ) );

colisly_check( 'Tous les statuts du cahier des charges presents', $expected_statuses === array_keys( COLISLY_Parcels::statuses() ) );

// ---------------------------------------------------------------------------
// Result.
// ---------------------------------------------------------------------------
if ( $colisly_failures > 0 ) {
	echo "\n{$colisly_failures} TEST(S) EN ECHEC\n";
	exit( 1 );
}

echo "\nTOUS LES TESTS SONT PASSES\n";
