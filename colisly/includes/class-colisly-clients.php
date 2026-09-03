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
			$match  = self::match_sql( $term );
			$where  = ' WHERE ' . $match['where'];
			$params = $match['params'];
		}

		return array(
			'joins'  => $joins,
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Searches the WordPress users the plugin holds no client record for.
	 *
	 * A customer registered on the shop is a client the moment a parcel
	 * arrives for him, and the operator learns about him at the counter, not
	 * on a screen where a record is created in advance. Until now the parcel
	 * form only knew people with a record, so every new customer had to be
	 * created by hand elsewhere before his first parcel could be booked in.
	 * These users are offered alongside the clients; the record is created
	 * when a parcel is actually recorded for them.
	 *
	 * @param string $term  Search term.
	 * @param int    $limit Maximum results.
	 * @return object[] User rows: user_id, user_email, display_name, user_login.
	 */
	public static function search_users_without_record( $term, $limit = 20 ) {
		global $wpdb;

		$term = trim( (string) $term );
		if ( '' === $term ) {
			return array();
		}

		$match  = self::match_sql( $term );
		$params = $match['params'];

		$params[] = (int) $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the condition is built from literals and placeholders; values go through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID AS user_id, u.user_email, u.display_name, u.user_login
				FROM {$wpdb->users} u
				LEFT JOIN {$wpdb->prefix}colisly_clients c ON c.user_id = u.ID
				WHERE c.id IS NULL AND {$match['where']}
				ORDER BY u.ID DESC LIMIT %d",
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the SQL condition matching a term against everything a client
	 * is known by, for queries that join the clients table as `c` and the
	 * users table as `u`.
	 *
	 * The search used to read the WordPress first and last name only. A
	 * customer created by WooCommerce, at checkout or from its Customers
	 * screen, carries a billing name and usually no WordPress name at all,
	 * so the operator typed the name he saw on every order and found nobody.
	 * The name of the person, the company and the phone are matched wherever
	 * WooCommerce may have stored them, and the login as well, since that is
	 * what the Users screen shows.
	 *
	 * The meta lookup is a single EXISTS rather than one LEFT JOIN per key:
	 * it cannot multiply rows, so the callers need no GROUP BY, and it adds
	 * a key without adding a join.
	 *
	 * @param string $term Search term, already trimmed and non-empty.
	 * @return array { where: string, params: array } A parenthesised condition
	 *               and the values its placeholders expect, in order.
	 */
	public static function match_sql( $term ) {
		global $wpdb;

		/*
		 * "Fabrice Rav" is how an operator types a name, and no single field
		 * holds it: the first name sits in one meta row and the last name in
		 * another. Each word is therefore matched on its own, anywhere, and
		 * every word has to match somewhere. One word is the plain search.
		 */
		$words = preg_split( '/\s+/', trim( (string) $term ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_slice( (array) $words, 0, 6 );

		if ( count( $words ) > 1 ) {
			$conditions = array();
			$params     = array();

			foreach ( $words as $word ) {
				$part         = self::match_sql( $word );
				$conditions[] = $part['where'];
				$params       = array_merge( $params, $part['params'] );
			}

			return array(
				'where'  => '(' . implode( ' AND ', $conditions ) . ')',
				'params' => $params,
			);
		}

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		/**
		 * Filters the user meta keys a client search matches against.
		 *
		 * @param string[] $keys Meta keys.
		 */
		$keys = apply_filters(
			'colisly_client_search_meta_keys',
			array(
				'first_name',
				'last_name',
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_phone',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
			)
		);
		$keys = array_values( array_filter( array_map( 'strval', (array) $keys ) ) );

		$where  = '(c.reference LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s OR c.phone LIKE %s';
		$params = array( $like, $like, $like, $like, $like );

		if ( $keys ) {
			$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
			$where       .= " OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} m WHERE m.user_id = u.ID AND m.meta_key IN ({$placeholders}) AND m.meta_value LIKE %s)";
			$params       = array_merge( $params, $keys, array( $like ) );
		}

		$where .= ')';

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Returns the best name known for a client.
	 *
	 * WordPress gives a user its login as display name until somebody types a
	 * real one, and WooCommerce rarely does: a customer created at checkout is
	 * "fabrice-1" to WordPress and "Fabrice Ravalomanana" on every one of his
	 * orders. The display name is used when it is a real name, the billing
	 * name when the display name is only the login, and the login when
	 * nothing better exists.
	 *
	 * @param object $client Client row, ideally carrying display_name and
	 *                       user_login from the list queries; user_id is
	 *                       enough otherwise.
	 * @return string
	 */
	public static function name( $client ) {
		$user_id = isset( $client->user_id ) ? (int) $client->user_id : 0;
		$display = isset( $client->display_name ) ? trim( (string) $client->display_name ) : '';
		$login   = isset( $client->user_login ) ? (string) $client->user_login : '';

		if ( '' === $display || '' === $login ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$display = trim( (string) $user->display_name );
				$login   = (string) $user->user_login;
			}
		}

		if ( '' !== $display && $display !== $login ) {
			return $display;
		}

		$billing = trim( (string) get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . (string) get_user_meta( $user_id, 'billing_last_name', true ) );
		if ( '' !== $billing ) {
			return $billing;
		}

		return '' !== $display ? $display : $login;
	}

	/**
	 * Searches clients by reference, name, e-mail or phone number.
	 *
	 * @param string $term  Search term.
	 * @param int    $limit Maximum results.
	 * @return object[] Rows with client fields plus user_email, display_name
	 *                  and user_login.
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
				"SELECT c.*, u.user_email, u.display_name, u.user_login {$sql['joins']}{$sql['where']} ORDER BY c.id DESC LIMIT %d OFFSET %d",
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
