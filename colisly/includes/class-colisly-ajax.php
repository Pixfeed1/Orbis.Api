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
		add_action( 'wp_ajax_colisly_check_code', array( __CLASS__, 'check_code' ) );
	}

	/**
	 * Tells a client whether the promotion code he typed unlocks the promotion.
	 *
	 * Checked here rather than in the page so the code is never in the
	 * page's source. The answer is the promotion as a discount candidate,
	 * for the live estimate; the request itself checks the code again when
	 * the form is posted.
	 *
	 * @return void
	 */
	public static function check_code() {
		check_ajax_referer( 'colisly_front', 'nonce' );

		$client = is_user_logged_in() ? COLISLY_Clients::get_by_user( get_current_user_id() ) : null;
		if ( ! $client ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'colisly' ) ), 403 );
		}

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( '' === trim( $code ) || ! COLISLY_Discounts::promo_running() || ! COLISLY_Discounts::code_matches( $code ) ) {
			wp_send_json_error( array( 'message' => __( 'This code is not valid.', 'colisly' ) ) );
		}

		$promo = null;
		foreach ( COLISLY_Discounts::candidates( $client, $code ) as $candidate ) {
			if ( 'promo' === $candidate['kind'] ) {
				$promo = $candidate;
			}
		}

		if ( ! $promo ) {
			wp_send_json_error( array( 'message' => __( 'This code is not valid.', 'colisly' ) ) );
		}

		wp_send_json_success(
			array(
				'discount' => $promo,
				/* translators: %s: name and rate of the promotion, e.g. "Promotion 10%". */
				'message'  => sprintf( __( 'Code accepted: %s.', 'colisly' ), $promo['label'] ),
			)
		);
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
