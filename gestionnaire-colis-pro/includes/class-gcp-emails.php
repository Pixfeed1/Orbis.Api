<?php
/**
 * E-mail notifications.
 *
 * With WooCommerce active, notifications are real WooCommerce e-mails
 * (registered in WooCommerce → Settings → E-mails, using the shop's
 * templates and colors). Without WooCommerce, a plain wp_mail() fallback
 * keeps the notifications working.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes plugin events to WooCommerce e-mails (or a wp_mail fallback).
 */
class GCP_Emails {

	/**
	 * Hooks into plugin events.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email_classes' ) );
		add_action( 'gcp_parcel_created', array( __CLASS__, 'parcel_created' ), 10, 2 );
		add_action( 'gcp_shipment_requested', array( __CLASS__, 'shipment_requested' ), 10, 3 );
	}

	/**
	 * Registers the plugin e-mails with the WooCommerce mailer.
	 *
	 * @param array $emails Registered e-mail classes.
	 * @return array
	 */
	public static function register_email_classes( $emails ) {
		require_once GCP_PLUGIN_DIR . 'includes/emails/class-gcp-email-parcel-received.php';
		require_once GCP_PLUGIN_DIR . 'includes/emails/class-gcp-email-shipment-requested.php';

		$emails['GCP_Email_Parcel_Received']    = new GCP_Email_Parcel_Received();
		$emails['GCP_Email_Shipment_Requested'] = new GCP_Email_Shipment_Requested();

		return $emails;
	}

	/**
	 * Whether the WooCommerce mailer can be used.
	 *
	 * @return bool
	 */
	private static function wc_mailer() {
		return function_exists( 'WC' ) && is_callable( array( WC(), 'mailer' ) );
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

		if ( self::wc_mailer() ) {
			WC()->mailer(); // Loads the e-mail classes so their hooks are live.

			/**
			 * Fires to send the native "parcel received" WooCommerce e-mail.
			 *
			 * @param int    $parcel_id Parcel ID.
			 * @param object $client    Client row.
			 */
			do_action( 'gcp_send_parcel_received_email', $parcel_id, $client );

			return;
		}

		self::parcel_created_fallback( $parcel_id, $client );
	}

	/**
	 * Notifies the shop manager that a shipment has been requested.
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

		if ( self::wc_mailer() ) {
			WC()->mailer();

			/**
			 * Fires to send the native "shipment requested" WooCommerce e-mail.
			 *
			 * @param int    $shipment_id Shipment ID.
			 * @param object $client      Client row.
			 */
			do_action( 'gcp_send_shipment_requested_email', $shipment_id, $client );

			return;
		}

		self::shipment_requested_fallback( $shipment_id, $client, $parcel_ids );
	}

	/**
	 * Plain wp_mail fallback used when WooCommerce is not active.
	 *
	 * @param int    $parcel_id Parcel ID.
	 * @param object $client    Client row.
	 * @return void
	 */
	private static function parcel_created_fallback( $parcel_id, $client ) {
		$user   = get_userdata( (int) $client->user_id );
		$parcel = GCP_Parcels::get( $parcel_id );

		if ( ! $user || ! $parcel ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: parcel reference. */
			__( '[%1$s] Your parcel %2$s has been received', 'gestionnaire-colis-pro' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$parcel->reference
		);

		$message = sprintf(
			/* translators: 1: parcel reference, 2: weight, 3: tracking number, 4: free storage days. */
			__( "Hello,\n\nWe have received your parcel %1\$s (weight: %2\$s kg, tracking number: %3\$s).\n\nIt is now available in your account, under “My parcels”. You benefit from %4\$d days of free storage.\n\nBest regards", 'gestionnaire-colis-pro' ),
			$parcel->reference,
			number_format_i18n( (float) $parcel->weight, 3 ),
			$parcel->tracking_number ? $parcel->tracking_number : '—',
			(int) GCP_Settings::get( 'free_storage_days', 15 )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Plain wp_mail fallback used when WooCommerce is not active.
	 *
	 * @param int    $shipment_id Shipment ID.
	 * @param object $client      Client row.
	 * @param array  $parcel_ids  Parcel IDs.
	 * @return void
	 */
	private static function shipment_requested_fallback( $shipment_id, $client, $parcel_ids ) {
		$shipment = GCP_Shipments::get( $shipment_id );
		if ( ! $shipment ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: shipment reference, 2: client reference. */
			__( 'New shipment request %1$s (client %2$s)', 'gestionnaire-colis-pro' ),
			$shipment->reference,
			$client->reference
		);

		$message = sprintf(
			/* translators: 1: shipment reference, 2: client reference, 3: parcels count, 4: carrier. */
			__( "A new shipment request %1\$s has just been created by client %2\$s.\n\nNumber of parcels: %3\$d\nPreferred carrier: %4\$s\n\nLog in to the administration to process it.", 'gestionnaire-colis-pro' ),
			$shipment->reference,
			$client->reference,
			count( $parcel_ids ),
			GCP_Carriers::name( $shipment->carrier )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $message );
	}
}
