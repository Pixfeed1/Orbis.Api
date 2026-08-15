<?php
/**
 * E-mail notifications.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends transactional e-mails on parcel and shipment events.
 */
class GCP_Emails {

	/**
	 * Hooks into plugin events.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'gcp_parcel_created', array( __CLASS__, 'parcel_created' ), 10, 2 );
		add_action( 'gcp_shipment_requested', array( __CLASS__, 'shipment_requested' ), 10, 3 );
	}

	/**
	 * Notifies the client that a parcel has been received.
	 *
	 * @param int    $parcel_id Parcel ID.
	 * @param object $client    Client row.
	 * @return void
	 */
	public static function parcel_created( $parcel_id, $client ) {
		if ( ! GCP_Settings::get( 'notify_client_on_parcel', 1 ) ) {
			return;
		}

		$user   = get_userdata( (int) $client->user_id );
		$parcel = GCP_Parcels::get( $parcel_id );

		if ( ! $user || ! $parcel ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: parcel reference. */
			__( '[%1$s] Votre colis %2$s a bien été réceptionné', 'gestionnaire-colis-pro' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$parcel->reference
		);

		$message = sprintf(
			/* translators: 1: parcel reference, 2: weight, 3: tracking number, 4: free storage days. */
			__( "Bonjour,\n\nNous avons bien réceptionné votre colis %1\$s (poids : %2\$s kg, n° de suivi : %3\$s).\n\nIl est désormais disponible dans votre espace client, rubrique « Mes colis ». Vous bénéficiez de %4\$d jours de stockage gratuit.\n\nCordialement", 'gestionnaire-colis-pro' ),
			$parcel->reference,
			number_format_i18n( (float) $parcel->weight, 3 ),
			$parcel->tracking_number ? $parcel->tracking_number : '—',
			(int) GCP_Settings::get( 'free_storage_days', 15 )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Notifies the shop administrator that a shipment has been requested.
	 *
	 * @param int    $shipment_id Shipment ID.
	 * @param object $client      Client row.
	 * @param array  $parcel_ids  Parcel IDs.
	 * @return void
	 */
	public static function shipment_requested( $shipment_id, $client, $parcel_ids ) {
		if ( ! GCP_Settings::get( 'notify_admin_on_request', 1 ) ) {
			return;
		}

		$shipment = GCP_Shipments::get( $shipment_id );
		if ( ! $shipment ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: shipment reference, 2: client reference. */
			__( 'Nouvelle demande d’expédition %1$s (client %2$s)', 'gestionnaire-colis-pro' ),
			$shipment->reference,
			$client->reference
		);

		$message = sprintf(
			/* translators: 1: shipment reference, 2: client reference, 3: parcels count, 4: carrier. */
			__( "Une nouvelle demande d’expédition %1\$s vient d’être créée par le client %2\$s.\n\nNombre de colis : %3\$d\nTransporteur souhaité : %4\$s\n\nConnectez-vous à l’administration pour la traiter.", 'gestionnaire-colis-pro' ),
			$shipment->reference,
			$client->reference,
			count( $parcel_ids ),
			GCP_Carriers::name( $shipment->carrier )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $message );
	}
}
