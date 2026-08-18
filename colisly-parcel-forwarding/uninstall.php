<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin data when the plugin is deleted from the site,
 * only if the site owner opted in via the colisly_remove_data_on_uninstall option.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'colisly_remove_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'colisly_history', 'colisly_documents', 'colisly_parcels', 'colisly_shipments', 'colisly_clients' ) as $colisly_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$colisly_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove the private files directory.
$colisly_uploads = wp_upload_dir( null, false );
$colisly_private = $colisly_uploads['basedir'] . '/colisly-private';
if ( is_dir( $colisly_private ) ) {
	foreach ( (array) scandir( $colisly_private ) as $colisly_entry ) {
		if ( '.' !== $colisly_entry && '..' !== $colisly_entry ) {
			wp_delete_file( $colisly_private . '/' . $colisly_entry );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing the emptied private directory on uninstall.
	rmdir( $colisly_private );
}

delete_option( 'colisly_settings' );
delete_option( 'colisly_db_version' );
delete_option( 'colisly_flush_rewrite_rules' );
delete_option( 'colisly_remove_data_on_uninstall' );
