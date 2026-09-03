<?php
/**
 * AJAX endpoints.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX: client live search used by the parcel creation form.
 */
class COLISLY_Ajax {

	/**
	 * Hooks AJAX actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_colisly_search_clients', array( __CLASS__, 'search_clients' ) );
	}

	/**
	 * Searches clients by reference, name, e-mail or phone.
	 *
	 * @return void
	 */
	public static function search_clients() {
		check_ajax_referer( 'colisly_admin', 'nonce' );

		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'colisly' ) ), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$results = array();

		foreach ( COLISLY_Clients::search( $term ) as $client ) {
			$stock     = COLISLY_Parcels::in_stock_for_client( (int) $client->id );
			$results[] = array(
				'id'        => (int) $client->id,
				'user_id'   => (int) $client->user_id,
				'is_new'    => false,
				'reference' => $client->reference,
				'name'      => COLISLY_Clients::name( $client ),
				'email'     => $client->user_email,
				'phone'     => $client->phone,
				'in_stock'  => count( $stock ),
				'parcels'   => array_map(
					static function ( $parcel ) {
						return array(
							'reference'      => $parcel->reference,
							'weight'         => (float) $parcel->weight,
							'allow_grouping' => (bool) $parcel->allow_grouping,
							'internal_note'  => (string) $parcel->internal_note,
						);
					},
					$stock
				),
			);
		}

		// Customers the plugin has no record for yet come after: picking one
		// creates his record along with his first parcel.
		foreach ( COLISLY_Clients::search_users_without_record( $term ) as $user ) {
			$results[] = array(
				'id'        => 0,
				'user_id'   => (int) $user->user_id,
				'is_new'    => true,
				'reference' => '',
				'name'      => COLISLY_Clients::name( $user ),
				'email'     => $user->user_email,
				'phone'     => (string) get_user_meta( (int) $user->user_id, 'billing_phone', true ),
				'in_stock'  => 0,
				'parcels'   => array(),
			);
		}

		wp_send_json_success( $results );
	}
}
