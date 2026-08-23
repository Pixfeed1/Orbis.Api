<?php
/**
 * Privacy: personal data exporter and eraser.
 *
 * Plugs into the native WordPress privacy tools (Tools → Export/Erase
 * Personal Data) so the client data stored by the plugin is covered by the
 * standard GDPR workflows.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin with the WordPress privacy tools.
 */
class COLISLY_Privacy {

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
		$exporters['colisly'] = array(
			'exporter_friendly_name' => __( 'Colisly Parcel Forwarding', 'colisly' ),
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
		$erasers['colisly'] = array(
			'eraser_friendly_name' => __( 'Colisly Parcel Forwarding', 'colisly' ),
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

		return $user ? COLISLY_Clients::get_by_user( (int) $user->ID ) : null;
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
			'group_id'    => 'colisly_client',
			'group_label' => __( 'Client record (Colisly Parcel Forwarding)', 'colisly' ),
			'item_id'     => 'colisly-client-' . (int) $client->id,
			'data'        => array(
				array(
					'name'  => __( 'Client reference', 'colisly' ),
					'value' => $client->reference,
				),
				array(
					'name'  => __( 'Phone', 'colisly' ),
					'value' => $client->phone,
				),
				array(
					'name'  => __( 'Created on', 'colisly' ),
					'value' => $client->created_at,
				),
				// The eraser blanks this field, so the right of access has to
				// cover it too: it is data held about this person.
				array(
					'name'  => __( 'Internal notes', 'colisly' ),
					'value' => (string) $client->admin_notes,
				),
			),
		);

		foreach ( COLISLY_Parcels::for_client( (int) $client->id ) as $parcel ) {
			$items[] = array(
				'group_id'    => 'colisly_parcels',
				'group_label' => __( 'Parcels (Colisly Parcel Forwarding)', 'colisly' ),
				'item_id'     => 'colisly-parcel-' . (int) $parcel->id,
				'data'        => array(
					array(
						'name'  => __( 'Parcel number', 'colisly' ),
						'value' => $parcel->reference,
					),
					array(
						'name'  => __( 'Tracking number', 'colisly' ),
						'value' => $parcel->tracking_number,
					),
					array(
						'name'  => __( 'Internal comment', 'colisly' ),
						'value' => (string) $parcel->internal_note,
					),
					array(
						'name'  => __( 'Weight (kg)', 'colisly' ),
						'value' => $parcel->weight,
					),
					array(
						'name'  => __( 'Status', 'colisly' ),
						'value' => COLISLY_Parcels::status_label( $parcel->status ),
					),
					array(
						'name'  => __( 'Received on', 'colisly' ),
						'value' => $parcel->received_at,
					),
				),
			);
		}

		foreach ( COLISLY_Shipments::for_client( (int) $client->id ) as $shipment ) {
			$items[] = array(
				'group_id'    => 'colisly_shipments',
				'group_label' => __( 'Shipments (Colisly Parcel Forwarding)', 'colisly' ),
				'item_id'     => 'colisly-shipment-' . (int) $shipment->id,
				'data'        => array(
					array(
						'name'  => __( 'Reference', 'colisly' ),
						'value' => $shipment->reference,
					),
					array(
						'name'  => __( 'Carrier', 'colisly' ),
						'value' => COLISLY_Carriers::name( $shipment->carrier ),
					),
					array(
						'name'  => __( 'Status', 'colisly' ),
						'value' => COLISLY_Shipments::status_label( $shipment->status ),
					),
					array(
						'name'  => __( 'Requested on', 'colisly' ),
						'value' => $shipment->requested_at,
					),
					array(
						'name'  => __( 'Total', 'colisly' ),
						'value' => $shipment->total_price,
					),
				),
			);
		}

		// Every document, not only the ones the client can see in their
		// account: the eraser deletes them all, so the export must list them
		// all. Their visibility is reported rather than used as a filter.
		foreach ( COLISLY_Documents::for_client( (int) $client->id ) as $document ) {
			$items[] = array(
				'group_id'    => 'colisly_documents',
				'group_label' => __( 'Documents (Colisly Parcel Forwarding)', 'colisly' ),
				'item_id'     => 'colisly-document-' . (int) $document->id,
				'data'        => array(
					array(
						'name'  => __( 'Title', 'colisly' ),
						'value' => $document->title,
					),
					array(
						'name'  => __( 'Added on', 'colisly' ),
						'value' => $document->created_at,
					),
					array(
						'name'  => __( 'Shared with the client', 'colisly' ),
						'value' => 'client' === $document->visibility ? __( 'Yes', 'colisly' ) : __( 'No', 'colisly' ),
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
		foreach ( COLISLY_Documents::for_client( $client_id ) as $document ) {
			if ( ! empty( $document->file_path ) ) {
				COLISLY_Files::delete( $document->file_path );
			}
			$removed = true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'colisly_documents', array( 'client_id' => $client_id ), array( '%d' ) );

		// Blank direct identifiers on the client record.
		if ( '' !== (string) $client->phone || null !== $client->admin_notes ) {
			$removed = true;
		}
		COLISLY_Clients::update(
			$client_id,
			array(
				'phone'       => '',
				'admin_notes' => '',
			)
		);

		// Blank tracking numbers, internal notes and reception photos.
		foreach ( COLISLY_Parcels::for_client( $client_id ) as $parcel ) {
			if ( ! empty( $parcel->photo_path ) ) {
				COLISLY_Files::delete( $parcel->photo_path );
			}
			if ( '' !== (string) $parcel->tracking_number || ! empty( $parcel->internal_note ) || ! empty( $parcel->photo_path ) ) {
				$removed = true;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'colisly_parcels',
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
				"UPDATE {$wpdb->prefix}colisly_history SET message = '' WHERE client_id = %d",
				$client_id
			)
		);

		COLISLY_History::log( $client_id, 'privacy_erasure', __( 'Personal data erased following a privacy request.', 'colisly' ) );

		return array(
			'items_removed'  => $removed,
			'items_retained' => true,
			'messages'       => array(
				__( 'Parcel and shipment records are retained as business/accounting records; documents, phone number, notes, tracking numbers and photos have been removed.', 'colisly' ),
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

		$client = COLISLY_Clients::get_by_user( (int) $user_id );

		if ( ! $client ) {
			return;
		}

		$client_id = (int) $client->id;

		foreach ( COLISLY_Documents::for_client( $client_id ) as $document ) {
			if ( ! empty( $document->file_path ) ) {
				COLISLY_Files::delete( $document->file_path );
			}
		}

		foreach ( COLISLY_Parcels::for_client( $client_id ) as $parcel ) {
			if ( ! empty( $parcel->photo_path ) ) {
				COLISLY_Files::delete( $parcel->photo_path );
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'colisly_documents', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'colisly_history', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'colisly_parcels', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'colisly_shipments', array( 'client_id' => $client_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'colisly_clients', array( 'id' => $client_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
