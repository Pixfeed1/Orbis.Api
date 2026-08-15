<?php
/**
 * Operation history log.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records and reads the history of operations per client.
 */
class GCP_History {

	/**
	 * Logs an operation.
	 *
	 * @param int    $client_id   Client ID.
	 * @param string $event       Event key (e.g. parcel_created).
	 * @param string $message     Human readable message.
	 * @param int    $parcel_id   Optional related parcel ID.
	 * @param int    $shipment_id Optional related shipment ID.
	 * @return void
	 */
	public static function log( $client_id, $event, $message, $parcel_id = 0, $shipment_id = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'gcp_history',
			array(
				'client_id'   => (int) $client_id,
				'parcel_id'   => $parcel_id ? (int) $parcel_id : null,
				'shipment_id' => $shipment_id ? (int) $shipment_id : null,
				'event'       => sanitize_key( $event ),
				'message'     => sanitize_textarea_field( $message ),
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		/**
		 * Fires after an operation has been logged.
		 *
		 * @param int    $client_id Client ID.
		 * @param string $event     Event key.
		 * @param string $message   Message.
		 */
		do_action( 'gcp_history_logged', $client_id, $event, $message );
	}

	/**
	 * Returns the history of a client, newest first.
	 *
	 * @param int $client_id Client ID.
	 * @param int $limit     Maximum number of entries.
	 * @return object[]
	 */
	public static function for_client( $client_id, $limit = 100 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcp_history WHERE client_id = %d ORDER BY id DESC LIMIT %d",
				(int) $client_id,
				(int) $limit
			)
		);
	}
}
