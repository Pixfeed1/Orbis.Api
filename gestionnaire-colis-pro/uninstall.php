<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin data when the plugin is deleted from the site,
 * only if the site owner opted in via the pxfwd_remove_data_on_uninstall option.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'pxfwd_remove_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'pxfwd_history', 'pxfwd_documents', 'pxfwd_parcels', 'pxfwd_shipments', 'pxfwd_clients' ) as $pxfwd_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$pxfwd_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove the private files directory.
$pxfwd_uploads = wp_upload_dir( null, false );
$pxfwd_private = $pxfwd_uploads['basedir'] . '/pxfwd-private';
if ( is_dir( $pxfwd_private ) ) {
	foreach ( (array) scandir( $pxfwd_private ) as $pxfwd_entry ) {
		if ( '.' !== $pxfwd_entry && '..' !== $pxfwd_entry ) {
			wp_delete_file( $pxfwd_private . '/' . $pxfwd_entry );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing the emptied private directory on uninstall.
	rmdir( $pxfwd_private );
}

delete_option( 'pxfwd_settings' );
delete_option( 'pxfwd_db_version' );
delete_option( 'pxfwd_flush_rewrite_rules' );
delete_option( 'pxfwd_remove_data_on_uninstall' );
