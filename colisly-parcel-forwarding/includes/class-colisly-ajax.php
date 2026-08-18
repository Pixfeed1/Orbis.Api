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
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'colisly-parcel-forwarding' ) ), 403 );
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
				'reference' => $client->reference,
				'name'      => $client->display_name,
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

		wp_send_json_success( $results );
	}
}
