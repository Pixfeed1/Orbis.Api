<?php
/**
 * Authenticated download endpoints for private files.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves client documents and reception photos with authorization checks.
 */
class GCP_Downloads {

	/**
	 * Hooks the download actions.
	 *
	 * The admin_post_* hooks run for any logged-in user, which covers both the
	 * administration and the customer account area.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_gcp_download_document', array( __CLASS__, 'document' ) );
		add_action( 'admin_post_nopriv_gcp_download_document', array( __CLASS__, 'require_login' ) );
		add_action( 'admin_post_gcp_parcel_photo', array( __CLASS__, 'photo' ) );
		add_action( 'admin_post_nopriv_gcp_parcel_photo', array( __CLASS__, 'require_login' ) );
	}

	/**
	 * Sends anonymous visitors to the login screen.
	 *
	 * @return void
	 */
	public static function require_login() {
		auth_redirect();
	}

	/**
	 * Builds the protected download URL of a document.
	 *
	 * @param object $document Document row.
	 * @return string
	 */
	public static function document_url( $document ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'gcp_download_document',
					'document' => (int) $document->id,
				),
				admin_url( 'admin-post.php' )
			),
			'gcp_download_document_' . (int) $document->id
		);
	}

	/**
	 * Builds the protected URL of a parcel reception photo (admin only).
	 *
	 * @param object $parcel Parcel row.
	 * @return string
	 */
	public static function photo_url( $parcel ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'gcp_parcel_photo',
					'parcel' => (int) $parcel->id,
				),
				admin_url( 'admin-post.php' )
			),
			'gcp_parcel_photo_' . (int) $parcel->id
		);
	}

	/**
	 * Whether a user may download a document.
	 *
	 * Managers can download everything; a customer can only download their own
	 * documents when these are marked visible to the client.
	 *
	 * @param object $document Document row.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function user_can_download_document( $document, $user_id ) {
		if ( user_can( $user_id, 'gcp_manage' ) ) {
			return true;
		}

		if ( 'client' !== $document->visibility ) {
			return false;
		}

		$client = GCP_Clients::get( (int) $document->client_id );

		return $client && (int) $client->user_id === (int) $user_id;
	}

	/**
	 * Streams a client document after authorization.
	 *
	 * @return void
	 */
	public static function document() {
		$document_id = isset( $_GET['document'] ) ? absint( $_GET['document'] ) : 0;

		check_admin_referer( 'gcp_download_document_' . $document_id );

		$document = GCP_Documents::get( $document_id );

		if ( ! $document || empty( $document->file_path ) ) {
			wp_die( esc_html__( 'Document introuvable.', 'gestionnaire-colis-pro' ), '', array( 'response' => 404 ) );
		}

		if ( ! self::user_can_download_document( $document, get_current_user_id() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ), '', array( 'response' => 403 ) );
		}

		GCP_Files::send( $document->file_path, $document->file_name ? $document->file_name : $document->title );
	}

	/**
	 * Streams a parcel reception photo (managers only).
	 *
	 * @return void
	 */
	public static function photo() {
		$parcel_id = isset( $_GET['parcel'] ) ? absint( $_GET['parcel'] ) : 0;

		check_admin_referer( 'gcp_parcel_photo_' . $parcel_id );

		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ), '', array( 'response' => 403 ) );
		}

		$parcel = GCP_Parcels::get( $parcel_id );

		if ( ! $parcel || empty( $parcel->photo_path ) ) {
			wp_die( esc_html__( 'Photo introuvable.', 'gestionnaire-colis-pro' ), '', array( 'response' => 404 ) );
		}

		GCP_Files::send( $parcel->photo_path, $parcel->reference . '-photo', true );
	}
}
