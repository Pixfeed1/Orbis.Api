<?php
/**
 * Shipments repository.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipment orders created from client requests or by the administration.
 */
class GCP_Shipments {

	/**
	 * Shipment statuses.
	 *
	 * @return array Map of status key => label.
	 */
	public static function statuses() {
		return array(
			'requested'        => __( 'Demandée', 'gestionnaire-colis-pro' ),
			'awaiting_payment' => __( 'En attente de paiement', 'gestionnaire-colis-pro' ),
			'paid'             => __( 'Payée', 'gestionnaire-colis-pro' ),
			'preparing'        => __( 'En préparation', 'gestionnaire-colis-pro' ),
			'shipped'          => __( 'Expédiée', 'gestionnaire-colis-pro' ),
			'cancelled'        => __( 'Annulée', 'gestionnaire-colis-pro' ),
		);
	}

	/**
	 * Returns the label of a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$statuses = self::statuses();

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	/**
	 * Creates a shipment request from a set of in-stock parcels.
	 *
	 * Enforces the grouping rules: a parcel whose grouping is forbidden must be
	 * shipped alone, and the chosen carrier must be allowed for every parcel.
	 *
	 * @param int    $client_id  Client ID.
	 * @param int[]  $parcel_ids Parcel IDs to ship.
	 * @param string $carrier    Carrier slug.
	 * @return int|WP_Error Shipment ID on success.
	 */
	public static function request( $client_id, $parcel_ids, $carrier ) {
		global $wpdb;

		$client = GCP_Clients::get( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'gcp_invalid_client', __( 'Client introuvable.', 'gestionnaire-colis-pro' ) );
		}

		$parcel_ids = array_unique( array_filter( array_map( 'intval', (array) $parcel_ids ) ) );
		if ( empty( $parcel_ids ) ) {
			return new WP_Error( 'gcp_no_parcels', __( 'Sélectionnez au moins un colis.', 'gestionnaire-colis-pro' ) );
		}

		$carrier = sanitize_key( $carrier );
		$enabled = wp_list_pluck( GCP_Carriers::all( true ), 'slug' );
		if ( ! in_array( $carrier, $enabled, true ) ) {
			return new WP_Error( 'gcp_invalid_carrier', __( 'Transporteur invalide.', 'gestionnaire-colis-pro' ) );
		}

		$parcels      = array();
		$total_weight = 0.0;
		$total_price  = 0.0;

		foreach ( $parcel_ids as $parcel_id ) {
			$parcel = GCP_Parcels::get( $parcel_id );

			if ( ! $parcel || (int) $parcel->client_id !== (int) $client->id ) {
				return new WP_Error( 'gcp_invalid_parcel', __( 'Colis introuvable pour ce client.', 'gestionnaire-colis-pro' ) );
			}

			if ( 'available' !== $parcel->status ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'gcp_parcel_unavailable', sprintf( __( 'Le colis %s n’est plus disponible.', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			if ( ! $parcel->allow_grouping && count( $parcel_ids ) > 1 ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'gcp_grouping_forbidden', sprintf( __( 'Le colis %s doit être expédié seul (regroupement interdit).', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			$allowed = GCP_Parcels::allowed_carrier_slugs( $parcel );
			if ( ! empty( $allowed ) && ! in_array( $carrier, $allowed, true ) ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'gcp_carrier_forbidden', sprintf( __( 'Le transporteur choisi n’est pas autorisé pour le colis %s.', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			$parcels[]     = $parcel;
			$total_weight += (float) $parcel->weight;
			$total_price  += (float) $parcel->price;
		}

		$storage_fees = GCP_Storage::fees_for_parcels( $parcels );
		$now          = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcp_shipments',
			array(
				'reference'    => '',
				'client_id'    => (int) $client->id,
				'carrier'      => $carrier,
				'status'       => 'requested',
				'total_weight' => round( $total_weight, 3 ),
				'total_price'  => round( $total_price + $storage_fees, 2 ),
				'storage_fees' => $storage_fees,
				'requested_at' => $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcp_db_error', __( 'Impossible de créer la demande d’expédition.', 'gestionnaire-colis-pro' ) );
		}

		$shipment_id = (int) $wpdb->insert_id;
		$reference   = self::format_reference( $shipment_id );

		$wpdb->update(
			$wpdb->prefix . 'gcp_shipments',
			array( 'reference' => $reference ),
			array( 'id' => $shipment_id ),
			array( '%s' ),
			array( '%d' )
		);

		GCP_Parcels::attach_to_shipment( $parcel_ids, $shipment_id );

		GCP_History::log(
			(int) $client->id,
			'shipment_requested',
			sprintf(
				/* translators: 1: shipment reference, 2: number of parcels, 3: carrier name. */
				__( 'Demande d’expédition %1$s créée (%2$d colis, transporteur %3$s).', 'gestionnaire-colis-pro' ),
				$reference,
				count( $parcel_ids ),
				GCP_Carriers::name( $carrier )
			),
			0,
			$shipment_id
		);

		/**
		 * Fires after a shipment request has been created.
		 *
		 * @param int    $shipment_id Shipment ID.
		 * @param object $client      Client row.
		 * @param array  $parcel_ids  Parcel IDs.
		 */
		do_action( 'gcp_shipment_requested', $shipment_id, $client, $parcel_ids );

		return $shipment_id;
	}

	/**
	 * Formats a shipment reference from its numeric ID (e.g. EXP000001).
	 *
	 * @param int $id Shipment ID.
	 * @return string
	 */
	public static function format_reference( $id ) {
		/**
		 * Filters the generated shipment reference.
		 *
		 * @param string $reference Reference.
		 * @param int    $id        Shipment ID.
		 */
		return apply_filters( 'gcp_shipment_reference', 'EXP' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ), $id );
	}

	/**
	 * Returns a shipment row by ID.
	 *
	 * @param int $shipment_id Shipment ID.
	 * @return object|null
	 */
	public static function get( $shipment_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gcp_shipments WHERE id = %d", (int) $shipment_id )
		);
	}

	/**
	 * Returns the shipments of a client, newest first.
	 *
	 * @param int $client_id Client ID.
	 * @return object[]
	 */
	public static function for_client( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcp_shipments WHERE client_id = %d ORDER BY id DESC",
				(int) $client_id
			)
		);
	}

	/**
	 * Returns the parcels attached to a shipment.
	 *
	 * @param int $shipment_id Shipment ID.
	 * @return object[]
	 */
	public static function parcels( $shipment_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcp_parcels WHERE shipment_id = %d ORDER BY id ASC",
				(int) $shipment_id
			)
		);
	}

	/**
	 * Updates the status of a shipment and cascades to its parcels.
	 *
	 * @param int    $shipment_id Shipment ID.
	 * @param string $status      New status key.
	 * @return bool|WP_Error
	 */
	public static function set_status( $shipment_id, $status ) {
		global $wpdb;

		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return new WP_Error( 'gcp_invalid_status', __( 'Statut d’expédition invalide.', 'gestionnaire-colis-pro' ) );
		}

		$shipment = self::get( $shipment_id );
		if ( ! $shipment ) {
			return new WP_Error( 'gcp_invalid_shipment', __( 'Expédition introuvable.', 'gestionnaire-colis-pro' ) );
		}

		if ( $shipment->status === $status ) {
			return true;
		}

		$fields  = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s', '%s' );

		if ( 'shipped' === $status ) {
			$fields['shipped_at'] = current_time( 'mysql', true );
			$formats[]            = '%s';
		}

		$wpdb->update( $wpdb->prefix . 'gcp_shipments', $fields, array( 'id' => (int) $shipment_id ), $formats, array( '%d' ) );

		// Cascade to parcels: shipment statuses map 1:1 onto parcel statuses,
		// except a cancelled shipment which puts parcels back in stock.
		$parcel_status = 'cancelled' === $status ? 'available' : $status;
		foreach ( self::parcels( $shipment_id ) as $parcel ) {
			GCP_Parcels::set_status( (int) $parcel->id, $parcel_status );

			if ( 'cancelled' === $status ) {
				$wpdb->update(
					$wpdb->prefix . 'gcp_parcels',
					array( 'shipment_id' => null ),
					array( 'id' => (int) $parcel->id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		GCP_History::log(
			(int) $shipment->client_id,
			'shipment_status_changed',
			sprintf(
				/* translators: 1: shipment reference, 2: old status, 3: new status. */
				__( 'Expédition %1$s : statut « %2$s » → « %3$s ».', 'gestionnaire-colis-pro' ),
				$shipment->reference,
				self::status_label( $shipment->status ),
				self::status_label( $status )
			),
			0,
			(int) $shipment_id
		);

		/**
		 * Fires after a shipment status changed.
		 *
		 * @param int    $shipment_id Shipment ID.
		 * @param string $status      New status.
		 * @param string $old_status  Previous status.
		 */
		do_action( 'gcp_shipment_status_changed', (int) $shipment_id, $status, $shipment->status );

		return true;
	}
}
