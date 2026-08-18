<?php
/**
 * Parcels repository.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD, status lifecycle and queries for parcels.
 */
class COLISLY_Parcels {

	/**
	 * Statuses a parcel can have during its life cycle.
	 *
	 * @return array Map of status key => label.
	 */
	public static function statuses() {
		return array(
			'available'        => _x( 'Available', 'parcel status', 'colisly' ),
			'ordered'          => _x( 'Ordered', 'parcel status', 'colisly' ),
			'awaiting_payment' => _x( 'Awaiting payment', 'parcel status', 'colisly' ),
			'paid'             => _x( 'Paid', 'parcel status', 'colisly' ),
			'preparing'        => _x( 'Preparing', 'parcel status', 'colisly' ),
			'shipped'          => _x( 'Shipped', 'parcel status', 'colisly' ),
			'destroyed'        => _x( 'Destroyed', 'parcel status', 'colisly' ),
			'cancelled'        => _x( 'Cancelled', 'parcel status', 'colisly' ),
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
	 * Returns the parcels table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'colisly_parcels';
	}

	/**
	 * Normalizes a decimal value typed with a French comma (e.g. "2,5").
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function to_float( $value ) {
		return (float) str_replace( ',', '.', (string) $value );
	}

	/**
	 * Creates a parcel for a client.
	 *
	 * The price is computed automatically from the weight and stored with the
	 * parcel; the reference (COL000001) and reception date are generated.
	 *
	 * @param array $data {
	 *     Parcel data.
	 *
	 *     @type int    $client_id        Client ID (required).
	 *     @type string $tracking_number  Carrier tracking number.
	 *     @type float  $weight           Real weight in kg (required, > 0).
	 *     @type float  $length           Length in cm (admin only).
	 *     @type float  $width            Width in cm (admin only).
	 *     @type float  $height           Height in cm (admin only).
	 *     @type string $photo_path       Private-directory path of the reception photo.
	 *     @type string $internal_note    Internal admin-only comment.
	 *     @type int    $allow_grouping   Whether grouping is allowed (1/0).
	 *     @type array  $allowed_carriers Carrier slugs allowed for this parcel (empty = all).
	 * }
	 * @return int|WP_Error Parcel ID on success.
	 */
	public static function create( $data ) {
		global $wpdb;

		$client = COLISLY_Clients::get( isset( $data['client_id'] ) ? (int) $data['client_id'] : 0 );
		if ( ! $client ) {
			return new WP_Error( 'colisly_invalid_client', __( 'Client not found.', 'colisly' ) );
		}

		$weight = isset( $data['weight'] ) ? self::to_float( $data['weight'] ) : 0;
		if ( $weight <= 0 ) {
			return new WP_Error( 'colisly_invalid_weight', __( 'The parcel weight must be greater than zero.', 'colisly' ) );
		}

		$carriers = array();
		if ( ! empty( $data['allowed_carriers'] ) && is_array( $data['allowed_carriers'] ) ) {
			$known = wp_list_pluck( COLISLY_Carriers::all(), 'slug' );
			foreach ( $data['allowed_carriers'] as $slug ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $slug, $known, true ) ) {
					$carriers[] = $slug;
				}
			}
		}

		$now      = current_time( 'mysql', true );
		$price    = COLISLY_Pricing::price_for_weight( $weight );
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'reference'        => '',
				'client_id'        => (int) $client->id,
				'tracking_number'  => isset( $data['tracking_number'] ) ? sanitize_text_field( $data['tracking_number'] ) : '',
				'weight'           => $weight,
				'length'           => isset( $data['length'] ) && '' !== $data['length'] ? self::to_float( $data['length'] ) : null,
				'width'            => isset( $data['width'] ) && '' !== $data['width'] ? self::to_float( $data['width'] ) : null,
				'height'           => isset( $data['height'] ) && '' !== $data['height'] ? self::to_float( $data['height'] ) : null,
				'photo_path'       => ! empty( $data['photo_path'] ) ? sanitize_file_name( $data['photo_path'] ) : '',
				'internal_note'    => isset( $data['internal_note'] ) ? sanitize_textarea_field( $data['internal_note'] ) : '',
				'allow_grouping'   => empty( $data['allow_grouping'] ) ? 0 : 1,
				'allowed_carriers' => wp_json_encode( $carriers ),
				'price'            => $price,
				'status'           => 'available',
				'received_at'      => $now,
				'created_by'       => get_current_user_id(),
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'colisly_db_error', __( 'The parcel could not be saved.', 'colisly' ) );
		}

		$parcel_id = (int) $wpdb->insert_id;
		$reference = self::format_reference( $parcel_id );

		$wpdb->update(
			self::table(),
			array( 'reference' => $reference ),
			array( 'id' => $parcel_id ),
			array( '%s' ),
			array( '%d' )
		);

		COLISLY_History::log(
			(int) $client->id,
			'parcel_created',
			sprintf(
				/* translators: 1: parcel reference, 2: weight in kg, 3: price. */
				__( 'Parcel %1$s received (%2$s kg, price %3$s).', 'colisly' ),
				$reference,
				number_format_i18n( $weight, 3 ),
				number_format_i18n( $price, 2 )
			),
			$parcel_id
		);

		/**
		 * Fires after a parcel has been created.
		 *
		 * @param int    $parcel_id Parcel ID.
		 * @param object $client    Client row.
		 */
		do_action( 'colisly_parcel_created', $parcel_id, $client );

		return $parcel_id;
	}

	/**
	 * Formats a parcel reference from its numeric ID (e.g. COL000001).
	 *
	 * @param int $id Parcel ID.
	 * @return string
	 */
	public static function format_reference( $id ) {
		/**
		 * Filters the generated parcel reference.
		 *
		 * @param string $reference Reference.
		 * @param int    $id        Parcel ID.
		 */
		return apply_filters( 'colisly_parcel_reference', 'COL' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ), $id );
	}

	/**
	 * Returns a parcel row by ID.
	 *
	 * @param int $parcel_id Parcel ID.
	 * @return object|null
	 */
	public static function get( $parcel_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}colisly_parcels WHERE id = %d", (int) $parcel_id )
		);
	}

	/**
	 * Returns the decoded list of allowed carrier slugs for a parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return string[] Empty array means every carrier is allowed.
	 */
	public static function allowed_carrier_slugs( $parcel ) {
		if ( empty( $parcel->allowed_carriers ) ) {
			return array();
		}

		$decoded = json_decode( (string) $parcel->allowed_carriers, true );

		return is_array( $decoded ) ? array_map( 'sanitize_key', $decoded ) : array();
	}

	/**
	 * Returns the parcels of a client that are still in the warehouse
	 * (not yet part of a shipment order).
	 *
	 * @param int $client_id Client ID.
	 * @return object[]
	 */
	public static function in_stock_for_client( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}colisly_parcels WHERE client_id = %d AND status = 'available' ORDER BY received_at DESC",
				(int) $client_id
			)
		);
	}

	/**
	 * Returns the shipped parcels of a client.
	 *
	 * @param int $client_id Client ID.
	 * @return object[]
	 */
	public static function shipped_for_client( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}colisly_parcels WHERE client_id = %d AND status = 'shipped' ORDER BY shipped_at DESC",
				(int) $client_id
			)
		);
	}

	/**
	 * Returns all parcels of a client, newest first.
	 *
	 * @param int $client_id Client ID.
	 * @return object[]
	 */
	public static function for_client( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}colisly_parcels WHERE client_id = %d ORDER BY received_at DESC",
				(int) $client_id
			)
		);
	}

	/**
	 * Returns a paged list of parcels for the admin list table.
	 *
	 * @param array $args Query args: search, status, per_page, paged.
	 * @return array { items: object[], total: int }
	 */
	public static function paged_list( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'per_page' => 20,
				'paged'    => 1,
			)
		);

		$where  = ' WHERE 1=1';
		$params = array();

		if ( '' !== $args['search'] ) {
			$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (p.reference LIKE %s OR p.tracking_number LIKE %s OR c.reference LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}

		if ( '' !== $args['status'] && array_key_exists( $args['status'], self::statuses() ) ) {
			$where   .= ' AND p.status = %s';
			$params[] = $args['status'];
		}

		$join = "FROM {$wpdb->prefix}colisly_parcels p
			INNER JOIN {$wpdb->prefix}colisly_clients c ON c.id = p.client_id
			INNER JOIN {$wpdb->users} u ON u.ID = c.user_id";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- $join/$where are built from safe literals and placeholders; user values go through $wpdb->prepare(), which is skipped only when there is no placeholder at all.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$params ? $wpdb->prepare( "SELECT COUNT(*) $join $where", $params ) : "SELECT COUNT(*) $join $where"
		);

		$params_page   = $params;
		$params_page[] = (int) $args['per_page'];
		$params_page[] = (int) ( ( $args['paged'] - 1 ) * $args['per_page'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, c.reference AS client_reference, u.display_name, u.user_email $join $where ORDER BY p.id DESC LIMIT %d OFFSET %d",
				$params_page
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Updates the status of a parcel and logs the transition.
	 *
	 * @param int    $parcel_id Parcel ID.
	 * @param string $status    New status key.
	 * @return bool|WP_Error
	 */
	public static function set_status( $parcel_id, $status ) {
		global $wpdb;

		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return new WP_Error( 'colisly_invalid_status', __( 'Invalid parcel status.', 'colisly' ) );
		}

		$parcel = self::get( $parcel_id );
		if ( ! $parcel ) {
			return new WP_Error( 'colisly_invalid_parcel', __( 'Parcel not found.', 'colisly' ) );
		}

		if ( $parcel->status === $status ) {
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

		$updated = $wpdb->update( self::table(), $fields, array( 'id' => (int) $parcel_id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return new WP_Error( 'colisly_db_error', __( 'The status could not be updated.', 'colisly' ) );
		}

		COLISLY_History::log(
			(int) $parcel->client_id,
			'parcel_status_changed',
			sprintf(
				/* translators: 1: parcel reference, 2: old status, 3: new status. */
				__( 'Parcel %1$s: status “%2$s” → “%3$s”.', 'colisly' ),
				$parcel->reference,
				self::status_label( $parcel->status ),
				self::status_label( $status )
			),
			(int) $parcel_id
		);

		/**
		 * Fires after a parcel status changed.
		 *
		 * @param int    $parcel_id  Parcel ID.
		 * @param string $status     New status.
		 * @param string $old_status Previous status.
		 */
		do_action( 'colisly_parcel_status_changed', (int) $parcel_id, $status, $parcel->status );

		return true;
	}

	/**
	 * Attaches parcels to a shipment and marks them ordered.
	 *
	 * @param int[] $parcel_ids  Parcel IDs.
	 * @param int   $shipment_id Shipment ID.
	 * @return void
	 */
	public static function attach_to_shipment( $parcel_ids, $shipment_id ) {
		global $wpdb;

		foreach ( array_map( 'intval', $parcel_ids ) as $parcel_id ) {
			$wpdb->update(
				self::table(),
				array(
					'shipment_id' => (int) $shipment_id,
					'updated_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => $parcel_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			self::set_status( $parcel_id, 'ordered' );
		}
	}
}
