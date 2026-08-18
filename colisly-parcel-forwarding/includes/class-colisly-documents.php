<?php
/**
 * Client documents.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents attached to a client record, stored in the private directory and
 * served only through the authenticated download endpoint.
 */
class COLISLY_Documents {

	/**
	 * Registers a document stored in the private directory.
	 *
	 * @param int    $client_id  Client ID.
	 * @param array  $file       File info from COLISLY_Files::upload() (path, name, type).
	 * @param string $title      Optional title; defaults to the original file name.
	 * @param string $visibility 'client' (visible to the client) or 'admin'.
	 * @return int|WP_Error Document ID.
	 */
	public static function add( $client_id, $file, $title = '', $visibility = 'client' ) {
		global $wpdb;

		$client = COLISLY_Clients::get( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'colisly_invalid_client', __( 'Client not found.', 'colisly-parcel-forwarding' ) );
		}

		if ( empty( $file['path'] ) || ! COLISLY_Files::resolve( $file['path'] ) ) {
			return new WP_Error( 'colisly_invalid_file', __( 'File not found.', 'colisly-parcel-forwarding' ) );
		}

		$name     = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'colisly_documents',
			array(
				'client_id'   => (int) $client_id,
				'file_path'   => sanitize_file_name( $file['path'] ),
				'file_name'   => $name,
				'mime_type'   => isset( $file['type'] ) ? sanitize_text_field( $file['type'] ) : '',
				'title'       => sanitize_text_field( $title ? $title : $name ),
				'visibility'  => 'admin' === $visibility ? 'admin' : 'client',
				'uploaded_by' => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'colisly_db_error', __( 'The document could not be saved.', 'colisly-parcel-forwarding' ) );
		}

		$document_id = (int) $wpdb->insert_id;

		COLISLY_History::log(
			(int) $client_id,
			'document_added',
			sprintf( /* translators: %s: document title. */ __( 'Document “%s” added.', 'colisly-parcel-forwarding' ), $title ? $title : $name )
		);

		return $document_id;
	}

	/**
	 * Returns a document row by ID.
	 *
	 * @param int $document_id Document ID.
	 * @return object|null
	 */
	public static function get( $document_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}colisly_documents WHERE id = %d", (int) $document_id )
		);
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
					"SELECT * FROM {$wpdb->prefix}colisly_documents WHERE client_id = %d AND visibility = 'client' ORDER BY id DESC",
					(int) $client_id
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}colisly_documents WHERE client_id = %d ORDER BY id DESC",
				(int) $client_id
			)
		);
	}
}
