<?php
/**
 * Client documents.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents attached to a client record (media library attachments).
 */
class GCP_Documents {

	/**
	 * Registers a document for a client.
	 *
	 * @param int    $client_id     Client ID.
	 * @param int    $attachment_id Media attachment ID.
	 * @param string $title         Document title.
	 * @param string $visibility    'client' (visible to the client) or 'admin'.
	 * @return int|WP_Error Document ID.
	 */
	public static function add( $client_id, $attachment_id, $title = '', $visibility = 'client' ) {
		global $wpdb;

		$client = GCP_Clients::get( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'gcp_invalid_client', __( 'Client introuvable.', 'gestionnaire-colis-pro' ) );
		}

		if ( ! get_post( $attachment_id ) ) {
			return new WP_Error( 'gcp_invalid_attachment', __( 'Fichier introuvable.', 'gestionnaire-colis-pro' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcp_documents',
			array(
				'client_id'     => (int) $client_id,
				'attachment_id' => (int) $attachment_id,
				'title'         => sanitize_text_field( $title ? $title : get_the_title( $attachment_id ) ),
				'visibility'    => 'admin' === $visibility ? 'admin' : 'client',
				'uploaded_by'   => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcp_db_error', __( 'Impossible d’enregistrer le document.', 'gestionnaire-colis-pro' ) );
		}

		$document_id = (int) $wpdb->insert_id;

		GCP_History::log(
			(int) $client_id,
			'document_added',
			sprintf( /* translators: %s: document title. */ __( 'Document « %s » ajouté.', 'gestionnaire-colis-pro' ), get_the_title( $attachment_id ) )
		);

		return $document_id;
	}

	/**
	 * Returns the documents of a client.
	 *
	 * @param int  $client_id   Client ID.
	 * @param bool $client_view When true, only documents visible to the client.
	 * @return object[]
	 */
	public static function for_client( $client_id, $client_view = false ) {
		global $wpdb;

		if ( $client_view ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcp_documents WHERE client_id = %d AND visibility = 'client' ORDER BY id DESC",
					(int) $client_id
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcp_documents WHERE client_id = %d ORDER BY id DESC",
				(int) $client_id
			)
		);
	}
}
