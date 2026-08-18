<?php
/**
 * Clients repository.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD and search for client records linked to WordPress users.
 */
class COLISLY_Clients {

	/**
	 * Returns the clients table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'colisly_clients';
	}

	/**
	 * Creates a client record for a WordPress user.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $phone   Optional phone number.
	 * @return int|WP_Error Client ID on success.
	 */
	public static function create( $user_id, $phone = '' ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'colisly_invalid_user', __( 'User not found.', 'colisly' ) );
		}

		$existing = self::get_by_user( $user_id );
		if ( $existing ) {
			return (int) $existing->id;
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'user_id'    => (int) $user_id,
				'reference'  => '',
				'phone'      => sanitize_text_field( $phone ? $phone : get_user_meta( $user_id, 'billing_phone', true ) ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'colisly_db_error', __( 'The client record could not be created.', 'colisly' ) );
		}

		$client_id = (int) $wpdb->insert_id;
		$reference = self::format_reference( $client_id );

		$wpdb->update(
			self::table(),
			array( 'reference' => $reference ),
			array( 'id' => $client_id ),
			array( '%s' ),
			array( '%d' )
		);

		COLISLY_History::log( $client_id, 'client_created', sprintf( /* translators: %s: client reference. */ __( 'Client record %s created.', 'colisly' ), $reference ) );

		/**
		 * Fires after a client record has been created.
		 *
		 * @param int $client_id Client ID.
		 * @param int $user_id   WordPress user ID.
		 */
		do_action( 'colisly_client_created', $client_id, $user_id );

		return $client_id;
	}

	/**
	 * Formats a client reference from its numeric ID (e.g. CL000001).
	 *
	 * @param int $id Client ID.
	 * @return string
	 */
	public static function format_reference( $id ) {
		/**
		 * Filters the generated client reference.
		 *
		 * @param string $reference Reference.
		 * @param int    $id        Client ID.
		 */
		return apply_filters( 'colisly_client_reference', 'CL' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ), $id );
	}

	/**
	 * Returns a client row by ID.
	 *
	 * @param int $client_id Client ID.
	 * @return object|null
	 */
	public static function get( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}colisly_clients WHERE id = %d", (int) $client_id )
		);
	}

	/**
	 * Returns a client row by WordPress user ID.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}colisly_clients WHERE user_id = %d", (int) $user_id )
		);
	}

	/**
	 * Returns a client row by its reference (e.g. CL000001).
	 *
	 * @param string $reference Client reference.
	 * @return object|null
	 */
	public static function get_by_reference( $reference ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}colisly_clients WHERE reference = %s", sanitize_text_field( $reference ) )
		);
	}

	/**
	 * Returns the search SQL fragments (joins, where, parameters).
	 *
	 * @param string $term Search term (may be empty).
	 * @return array { joins: string, where: string, params: array }
	 */
	private static function search_sql( $term ) {
		global $wpdb;

		$joins = "FROM {$wpdb->prefix}colisly_clients c
			INNER JOIN {$wpdb->users} u ON u.ID = c.user_id";
		$where  = '';
		$params = array();

		if ( '' !== $term ) {
			$like   = '%' . $wpdb->esc_like( $term ) . '%';
			$joins .= "
			LEFT JOIN {$wpdb->usermeta} fn ON fn.user_id = u.ID AND fn.meta_key = 'first_name'
			LEFT JOIN {$wpdb->usermeta} ln ON ln.user_id = u.ID AND ln.meta_key = 'last_name'";
			$where  = ' WHERE (c.reference LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR fn.meta_value LIKE %s OR ln.meta_value LIKE %s OR c.phone LIKE %s)';
			$params = array( $like, $like, $like, $like, $like, $like );
		}

		return array(
			'joins'  => $joins,
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Searches clients by reference, name, e-mail or phone number.
	 *
	 * @param string $term  Search term.
	 * @param int    $limit Maximum results.
	 * @return object[] Rows with client fields plus user_email and display_name.
	 */
	public static function search( $term, $limit = 20 ) {
		return self::paged_list( trim( (string) $term ), (int) $limit, 1 );
	}

	/**
	 * Counts the clients matching an optional search term (SQL COUNT, so it
	 * scales to large client bases).
	 *
	 * @param string $term Search term.
	 * @return int
	 */
	public static function count( $term = '' ) {
		global $wpdb;

		$sql = self::search_sql( trim( (string) $term ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments contain only literals and placeholders; values go through $wpdb->prepare().
		$query = "SELECT COUNT(DISTINCT c.id) {$sql['joins']}{$sql['where']}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $sql['params'] ? $wpdb->prepare( $query, $sql['params'] ) : $query );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns a paged list of clients with user data (SQL LIMIT/OFFSET).
	 *
	 * @param string $term     Optional search term.
	 * @param int    $per_page Items per page.
	 * @param int    $paged    Page number (1-based).
	 * @return object[]
	 */
	public static function paged_list( $term = '', $per_page = 20, $paged = 1 ) {
		global $wpdb;

		$sql    = self::search_sql( trim( (string) $term ) );
		$params = $sql['params'];

		$params[] = (int) $per_page;
		$params[] = (int) ( ( max( 1, (int) $paged ) - 1 ) * $per_page );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments contain only literals and placeholders; values go through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, u.user_email, u.display_name {$sql['joins']}{$sql['where']} GROUP BY c.id ORDER BY c.id DESC LIMIT %d OFFSET %d",
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Updates the internal admin notes and phone of a client.
	 *
	 * @param int   $client_id Client ID.
	 * @param array $data      Fields: phone, admin_notes.
	 * @return bool
	 */
	public static function update( $client_id, $data ) {
		global $wpdb;

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		if ( isset( $data['phone'] ) ) {
			$fields['phone'] = sanitize_text_field( $data['phone'] );
			$formats[]       = '%s';
		}

		if ( isset( $data['admin_notes'] ) ) {
			$fields['admin_notes'] = sanitize_textarea_field( $data['admin_notes'] );
			$formats[]             = '%s';
		}

		return false !== $wpdb->update( self::table(), $fields, array( 'id' => (int) $client_id ), $formats, array( '%d' ) );
	}

	/**
	 * Computes the dashboard indicators for a client.
	 *
	 * @param int $client_id Client ID.
	 * @return array {
	 *     Indicators.
	 *
	 *     @type int    $parcels_in_stock  Number of parcels currently in stock.
	 *     @type float  $weight_in_stock   Total stored weight (kg).
	 *     @type int    $shipments_count   Number of shipments done.
	 *     @type float  $storage_fees_due  Storage fees currently due.
	 *     @type string $last_reception    Date of the last parcel reception.
	 *     @type string $last_shipment     Date of the last shipment.
	 * }
	 */
	public static function indicators( $client_id ) {
		global $wpdb;

		$client_id = (int) $client_id;
		$in_stock  = COLISLY_Parcels::in_stock_for_client( $client_id );

		$weight = 0.0;
		foreach ( $in_stock as $parcel ) {
			$weight += (float) $parcel->weight;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$shipments_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}colisly_shipments WHERE client_id = %d AND status = 'shipped'",
				$client_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_reception = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(received_at) FROM {$wpdb->prefix}colisly_parcels WHERE client_id = %d",
				$client_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_shipment = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(shipped_at) FROM {$wpdb->prefix}colisly_shipments WHERE client_id = %d AND status = 'shipped'",
				$client_id
			)
		);

		return array(
			'parcels_in_stock' => count( $in_stock ),
			'weight_in_stock'  => round( $weight, 3 ),
			'shipments_count'  => $shipments_count,
			'storage_fees_due' => COLISLY_Storage::fees_for_parcels( $in_stock ),
			'last_reception'   => $last_reception ? $last_reception : '',
			'last_shipment'    => $last_shipment ? $last_shipment : '',
		);
	}
}
