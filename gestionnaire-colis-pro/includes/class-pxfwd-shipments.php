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
class PXFWD_Shipments {

	/**
	 * Shipment statuses.
	 *
	 * @return array Map of status key => label.
	 */
	public static function statuses() {
		return array(
			'requested'        => _x( 'Requested', 'shipment status', 'gestionnaire-colis-pro' ),
			'awaiting_payment' => _x( 'Awaiting payment', 'shipment status', 'gestionnaire-colis-pro' ),
			'paid'             => _x( 'Paid', 'shipment status', 'gestionnaire-colis-pro' ),
			'preparing'        => _x( 'Preparing', 'shipment status', 'gestionnaire-colis-pro' ),
			'shipped'          => _x( 'Shipped', 'shipment status', 'gestionnaire-colis-pro' ),
			'cancelled'        => _x( 'Cancelled', 'shipment status', 'gestionnaire-colis-pro' ),
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

		$client = PXFWD_Clients::get( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'pxfwd_invalid_client', __( 'Client not found.', 'gestionnaire-colis-pro' ) );
		}

		$parcel_ids = array_unique( array_filter( array_map( 'intval', (array) $parcel_ids ) ) );
		if ( empty( $parcel_ids ) ) {
			return new WP_Error( 'pxfwd_no_parcels', __( 'Select at least one parcel.', 'gestionnaire-colis-pro' ) );
		}

		$carrier = sanitize_key( $carrier );
		$enabled = wp_list_pluck( PXFWD_Carriers::all( true ), 'slug' );
		if ( ! in_array( $carrier, $enabled, true ) ) {
			return new WP_Error( 'pxfwd_invalid_carrier', __( 'Invalid carrier.', 'gestionnaire-colis-pro' ) );
		}

		$parcels      = array();
		$total_weight = 0.0;
		$total_price  = 0.0;

		foreach ( $parcel_ids as $parcel_id ) {
			$parcel = PXFWD_Parcels::get( $parcel_id );

			if ( ! $parcel || (int) $parcel->client_id !== (int) $client->id ) {
				return new WP_Error( 'pxfwd_invalid_parcel', __( 'Parcel not found for this client.', 'gestionnaire-colis-pro' ) );
			}

			if ( 'available' !== $parcel->status ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'pxfwd_parcel_unavailable', sprintf( __( 'Parcel %s is no longer available.', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			if ( ! $parcel->allow_grouping && count( $parcel_ids ) > 1 ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'pxfwd_grouping_forbidden', sprintf( __( 'Parcel %s must be shipped alone (grouping forbidden).', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			$allowed = PXFWD_Parcels::allowed_carrier_slugs( $parcel );
			if ( ! empty( $allowed ) && ! in_array( $carrier, $allowed, true ) ) {
				/* translators: %s: parcel reference. */
				return new WP_Error( 'pxfwd_carrier_forbidden', sprintf( __( 'The chosen carrier is not allowed for parcel %s.', 'gestionnaire-colis-pro' ), $parcel->reference ) );
			}

			$parcels[]     = $parcel;
			$total_weight += (float) $parcel->weight;
			$total_price  += (float) $parcel->price;
		}

		$storage_fees  = PXFWD_Storage::fees_for_parcels( $parcels );
		$carrier_price = PXFWD_Carriers::price_for( $carrier, $total_weight );
		$now           = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'pxfwd_shipments',
			array(
				'reference'     => '',
				'client_id'     => (int) $client->id,
				'carrier'       => $carrier,
				'status'        => 'requested',
				'total_weight'  => round( $total_weight, 3 ),
				'total_price'   => round( $total_price + $storage_fees + $carrier_price, 2 ),
				'storage_fees'  => $storage_fees,
				'carrier_price' => $carrier_price,
				'requested_at'  => $now,
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'pxfwd_db_error', __( 'The shipment request could not be created.', 'gestionnaire-colis-pro' ) );
		}

		$shipment_id = (int) $wpdb->insert_id;
		$reference   = self::format_reference( $shipment_id );

		$wpdb->update(
			$wpdb->prefix . 'pxfwd_shipments',
			array( 'reference' => $reference ),
			array( 'id' => $shipment_id ),
			array( '%s' ),
			array( '%d' )
		);

		PXFWD_Parcels::attach_to_shipment( $parcel_ids, $shipment_id );

		// Native WooCommerce payment: the request becomes a real order and the
		// shipment waits for its payment. Without WooCommerce, it stays
		// "requested" and is handled manually.
		if ( PXFWD_Orders::available() ) {
			$order_result = PXFWD_Orders::create_for_shipment( self::get( $shipment_id ), $client );
			if ( ! is_wp_error( $order_result ) ) {
				self::set_status( $shipment_id, 'awaiting_payment' );
			}
		}

		PXFWD_History::log(
			(int) $client->id,
			'shipment_requested',
			sprintf(
				/* translators: 1: shipment reference, 2: number of parcels, 3: carrier name. */
				__( 'Shipment request %1$s created (%2$d parcels, carrier %3$s).', 'gestionnaire-colis-pro' ),
				$reference,
				count( $parcel_ids ),
				PXFWD_Carriers::name( $carrier )
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
		do_action( 'pxfwd_shipment_requested', $shipment_id, $client, $parcel_ids );

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
		return apply_filters( 'pxfwd_shipment_reference', 'EXP' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ), $id );
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
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pxfwd_shipments WHERE id = %d", (int) $shipment_id )
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
				"SELECT * FROM {$wpdb->prefix}pxfwd_shipments WHERE client_id = %d ORDER BY id DESC",
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
				"SELECT * FROM {$wpdb->prefix}pxfwd_parcels WHERE shipment_id = %d ORDER BY id ASC",
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
			return new WP_Error( 'pxfwd_invalid_status', __( 'Invalid shipment status.', 'gestionnaire-colis-pro' ) );
		}

		$shipment = self::get( $shipment_id );
		if ( ! $shipment ) {
			return new WP_Error( 'pxfwd_invalid_shipment', __( 'Shipment not found.', 'gestionnaire-colis-pro' ) );
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

		$wpdb->update( $wpdb->prefix . 'pxfwd_shipments', $fields, array( 'id' => (int) $shipment_id ), $formats, array( '%d' ) );

		// Cascade to parcels: shipment statuses map 1:1 onto parcel statuses,
		// except a cancelled shipment which puts parcels back in stock.
		$parcel_status = 'cancelled' === $status ? 'available' : $status;
		foreach ( self::parcels( $shipment_id ) as $parcel ) {
			PXFWD_Parcels::set_status( (int) $parcel->id, $parcel_status );

			if ( 'cancelled' === $status ) {
				$wpdb->update(
					$wpdb->prefix . 'pxfwd_parcels',
					array( 'shipment_id' => null ),
					array( 'id' => (int) $parcel->id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		PXFWD_Orders::sync_from_shipment( $shipment, $status );

		PXFWD_History::log(
			(int) $shipment->client_id,
			'shipment_status_changed',
			sprintf(
				/* translators: 1: shipment reference, 2: old status, 3: new status. */
				__( 'Shipment %1$s: status “%2$s” → “%3$s”.', 'gestionnaire-colis-pro' ),
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
		do_action( 'pxfwd_shipment_status_changed', (int) $shipment_id, $status, $shipment->status );

		return true;
	}
}
