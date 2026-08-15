<?php
/**
 * Privacy: personal data exporter and eraser.
 *
 * Plugs into the native WordPress privacy tools (Tools → Export/Erase
 * Personal Data) so the client data stored by the plugin is covered by the
 * standard GDPR workflows.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin with the WordPress privacy tools.
 */
class GCP_Privacy {

	/**
	 * Hooks the exporter, the eraser and the account-deletion cleanup.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_user_deleted' ) );
	}

	/**
	 * Registers the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['gestionnaire-colis-pro'] = array(
			'exporter_friendly_name' => __( 'Gestionnaire Colis Pro', 'gestionnaire-colis-pro' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['gestionnaire-colis-pro'] = array(
			'eraser_friendly_name' => __( 'Gestionnaire Colis Pro', 'gestionnaire-colis-pro' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Returns the client row attached to an e-mail address, if any.
	 *
	 * @param string $email E-mail address.
	 * @return object|null
	 */
	private static function client_by_email( $email ) {
		$user = get_user_by( 'email', $email );

		return $user ? GCP_Clients::get_by_user( (int) $user->ID ) : null;
	}

	/**
	 * Exports the personal data held by the plugin for an e-mail address.
	 *
	 * @param string $email Requester e-mail.
	 * @return array { data: array, done: bool }
	 */
	public static function export( $email ) {
		$client = self::client_by_email( $email );

		if ( ! $client ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$items = array();

		$items[] = array(
			'group_id'    => 'gcp_client',
			'group_label' => __( 'Client record (Gestionnaire Colis Pro)', 'gestionnaire-colis-pro' ),
			'item_id'     => 'gcp-client-' . (int) $client->id,
			'data'        => array(
				array(
					'name'  => __( 'Client reference', 'gestionnaire-colis-pro' ),
					'value' => $client->reference,
				),
				array(
					'name'  => __( 'Phone', 'gestionnaire-colis-pro' ),
					'value' => $client->phone,
				),
				array(
					'name'  => __( 'Created on', 'gestionnaire-colis-pro' ),
					'value' => $client->created_at,
				),
			),
		);

		foreach ( GCP_Parcels::for_client( (int) $client->id ) as $parcel ) {
			$items[] = array(
				'group_id'    => 'gcp_parcels',
				'group_label' => __( 'Parcels (Gestionnaire Colis Pro)', 'gestionnaire-colis-pro' ),
				'item_id'     => 'gcp-parcel-' . (int) $parcel->id,
				'data'        => array(
					array(
						'name'  => __( 'Parcel number', 'gestionnaire-colis-pro' ),
						'value' => $parcel->reference,
					),
					array(
						'name'  => __( 'Tracking number', 'gestionnaire-colis-pro' ),
						'value' => $parcel->tracking_number,
					),
					array(
						'name'  => __( 'Weight (kg)', 'gestionnaire-colis-pro' ),
						'value' => $parcel->weight,
					),
					array(
						'name'  => __( 'Status', 'gestionnaire-colis-pro' ),
						'value' => GCP_Parcels::status_label( $parcel->status ),
					),
					array(
						'name'  => __( 'Received on', 'gestionnaire-colis-pro' ),
						'value' => $parcel->received_at,
					),
				),
			);
		}

		foreach ( GCP_Shipments::for_client( (int) $client->id ) as $shipment ) {
			$items[] = array(
				'group_id'    => 'gcp_shipments',
				'group_label' => __( 'Shipments (Gestionnaire Colis Pro)', 'gestionnaire-colis-pro' ),
				'item_id'     => 'gcp-shipment-' . (int) $shipment->id,
				'data'        => array(
					array(
						'name'  => __( 'Reference', 'gestionnaire-colis-pro' ),
						'value' => $shipment->reference,
					),
					array(
						'name'  => __( 'Carrier', 'gestionnaire-colis-pro' ),
						'value' => GCP_Carriers::name( $shipment->carrier ),
					),
					array(
						'name'  => __( 'Status', 'gestionnaire-colis-pro' ),
						'value' => GCP_Shipments::status_label( $shipment->status ),
					),
					array(
						'name'  => __( 'Requested on', 'gestionnaire-colis-pro' ),
						'value' => $shipment->requested_at,
					),
					array(
						'name'  => __( 'Total', 'gestionnaire-colis-pro' ),
						'value' => $shipment->total_price,
					),
				),
			);
		}

		foreach ( GCP_Documents::for_client( (int) $client->id, true ) as $document ) {
			$items[] = array(
				'group_id'    => 'gcp_documents',
				'group_label' => __( 'Documents (Gestionnaire Colis Pro)', 'gestionnaire-colis-pro' ),
				'item_id'     => 'gcp-document-' . (int) $document->id,
				'data'        => array(
					array(
						'name'  => __( 'Title', 'gestionnaire-colis-pro' ),
						'value' => $document->title,
					),
					array(
						'name'  => __( 'Added on', 'gestionnaire-colis-pro' ),
						'value' => $document->created_at,
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erases the personal data held by the plugin for an e-mail address.
	 *
	 * Documents (rows and private files) are deleted, direct identifiers
	 * (phone, notes, tracking numbers, photos) are blanked. Parcel and
	 * shipment records themselves are retained as business/accounting
	 * records, which is reported to the requester.
	 *
	 * @param string $email Requester e-mail.
	 * @return array { items_removed, items_retained, messages, done }
	 */
	public static function erase( $email ) {
		global $wpdb;

		$client = self::client_by_email( $email );

		if ( ! $client ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed   = false;
		$client_id = (int) $client->id;

		// Delete documents: private files first, then the rows.
		foreach ( GCP_Documents::for_client( $client_id ) as $document ) {
			if ( ! empty( $document->file_path ) ) {
				GCP_Files::delete( $document->file_path );
			}
			$removed = true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'gcp_documents', array( 'client_id' => $client_id ), array( '%d' ) );

		// Blank direct identifiers on the client record.
		if ( '' !== (string) $client->phone || null !== $client->admin_notes ) {
			$removed = true;
		}
		GCP_Clients::update(
			$client_id,
			array(
				'phone'       => '',
				'admin_notes' => '',
			)
		);

		// Blank tracking numbers, internal notes and reception photos.
		foreach ( GCP_Parcels::for_client( $client_id ) as $parcel ) {
			if ( ! empty( $parcel->photo_path ) ) {
				GCP_Files::delete( $parcel->photo_path );
			}
			if ( '' !== (string) $parcel->tracking_number || ! empty( $parcel->internal_note ) || ! empty( $parcel->photo_path ) ) {
				$removed = true;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'gcp_parcels',
				array(
					'tracking_number' => '',
					'internal_note'   => '',
					'photo_path'      => '',
					'updated_at'      => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $parcel->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		// Blank free-text history messages, keep the event trail.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}gcp_history SET message = '' WHERE client_id = %d",
				$client_id
			)
		);

		GCP_History::log( $client_id, 'privacy_erasure', __( 'Personal data erased following a privacy request.', 'gestionnaire-colis-pro' ) );

		return array(
			'items_removed'  => $removed,
			'items_retained' => true,
			'messages'       => array(
				__( 'Parcel and shipment records are retained as business/accounting records; documents, phone number, notes, tracking numbers and photos have been removed.', 'gestionnaire-colis-pro' ),
			),
			'done'           => true,
		);
	}

	/**
	 * Removes all plugin data of a client when their user account is deleted.
	 *
	 * @param int $user_id Deleted user ID.
	 * @return void
	 */
	public static function on_user_deleted( $user_id ) {
		global $wpdb;

		$client = GCP_Clients::get_by_user( (int) $user_id );

		if ( ! $client ) {
			return;
		}

		$client_id = (int) $client->id;

		foreach ( GCP_Documents::for_client( $client_id ) as $document ) {
			if ( ! empty( $document->file_path ) ) {
				GCP_Files::delete( $document->file_path );
			}
		}

		foreach ( GCP_Parcels::for_client( $client_id ) as $parcel ) {
			if ( ! empty( $parcel->photo_path ) ) {
				GCP_Files::delete( $parcel->photo_path );
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'gcp_documents', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'gcp_history', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'gcp_parcels', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'gcp_shipments', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'gcp_clients', array( 'id' => $client_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
