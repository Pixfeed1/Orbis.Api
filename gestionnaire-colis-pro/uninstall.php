<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin data when the plugin is deleted from the site,
 * only if the site owner opted in via the gcp_remove_data_on_uninstall option.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'gcp_remove_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'gcp_history', 'gcp_documents', 'gcp_parcels', 'gcp_shipments', 'gcp_clients' ) as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove the private files directory.
$gcp_uploads = wp_upload_dir( null, false );
$gcp_private = $gcp_uploads['basedir'] . '/gcp-private';
if ( is_dir( $gcp_private ) ) {
	foreach ( (array) scandir( $gcp_private ) as $gcp_entry ) {
		if ( '.' !== $gcp_entry && '..' !== $gcp_entry ) {
			wp_delete_file( $gcp_private . '/' . $gcp_entry );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing the emptied private directory on uninstall.
	rmdir( $gcp_private );
}

delete_option( 'gcp_settings' );
delete_option( 'gcp_db_version' );
delete_option( 'gcp_flush_rewrite_rules' );
delete_option( 'gcp_remove_data_on_uninstall' );
