<?php
/**
 * Installation routines: database schema, capabilities, default options.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation, deactivation and database schema.
 */
class GCP_Install {

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::add_capabilities();
		self::add_default_options();
		update_option( 'gcp_db_version', GCP_VERSION );
		// My Account endpoints are registered on init; flush on next load.
		update_option( 'gcp_flush_rewrite_rules', 'yes' );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Runs schema upgrades when the plugin is updated.
	 *
	 * @return void
	 */
	public static function maybe_update() {
		if ( get_option( 'gcp_db_version' ) !== GCP_VERSION ) {
			self::create_tables();
			self::add_capabilities();
			update_option( 'gcp_db_version', GCP_VERSION );
		}

		if ( 'yes' === get_option( 'gcp_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'gcp_flush_rewrite_rules' );
		}
	}

	/**
	 * Creates or updates the custom database tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();

		$tables = "
CREATE TABLE {$wpdb->prefix}gcp_clients (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NOT NULL,
	reference VARCHAR(20) NOT NULL DEFAULT '',
	phone VARCHAR(50) NOT NULL DEFAULT '',
	admin_notes TEXT NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY user_id (user_id),
	UNIQUE KEY reference (reference)
) $collate;
CREATE TABLE {$wpdb->prefix}gcp_parcels (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	reference VARCHAR(20) NOT NULL DEFAULT '',
	client_id BIGINT UNSIGNED NOT NULL,
	tracking_number VARCHAR(100) NOT NULL DEFAULT '',
	weight DECIMAL(10,3) NOT NULL DEFAULT 0,
	length DECIMAL(10,2) NULL,
	width DECIMAL(10,2) NULL,
	height DECIMAL(10,2) NULL,
	photo_id BIGINT UNSIGNED NULL,
	photo_path VARCHAR(255) NOT NULL DEFAULT '',
	internal_note TEXT NULL,
	allow_grouping TINYINT(1) NOT NULL DEFAULT 1,
	allowed_carriers TEXT NULL,
	price DECIMAL(12,2) NOT NULL DEFAULT 0,
	status VARCHAR(30) NOT NULL DEFAULT 'available',
	shipment_id BIGINT UNSIGNED NULL,
	received_at DATETIME NOT NULL,
	shipped_at DATETIME NULL,
	created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY reference (reference),
	KEY client_id (client_id),
	KEY status (status),
	KEY shipment_id (shipment_id)
) $collate;
CREATE TABLE {$wpdb->prefix}gcp_shipments (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	reference VARCHAR(20) NOT NULL DEFAULT '',
	client_id BIGINT UNSIGNED NOT NULL,
	carrier VARCHAR(100) NOT NULL DEFAULT '',
	status VARCHAR(30) NOT NULL DEFAULT 'requested',
	total_weight DECIMAL(10,3) NOT NULL DEFAULT 0,
	total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
	storage_fees DECIMAL(12,2) NOT NULL DEFAULT 0,
	requested_at DATETIME NOT NULL,
	shipped_at DATETIME NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY reference (reference),
	KEY client_id (client_id),
	KEY status (status)
) $collate;
CREATE TABLE {$wpdb->prefix}gcp_documents (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	client_id BIGINT UNSIGNED NOT NULL,
	attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	file_path VARCHAR(255) NOT NULL DEFAULT '',
	file_name VARCHAR(255) NOT NULL DEFAULT '',
	mime_type VARCHAR(100) NOT NULL DEFAULT '',
	title VARCHAR(255) NOT NULL DEFAULT '',
	visibility VARCHAR(20) NOT NULL DEFAULT 'client',
	uploaded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY client_id (client_id)
) $collate;
CREATE TABLE {$wpdb->prefix}gcp_history (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	client_id BIGINT UNSIGNED NOT NULL,
	parcel_id BIGINT UNSIGNED NULL,
	shipment_id BIGINT UNSIGNED NULL,
	event VARCHAR(50) NOT NULL DEFAULT '',
	message TEXT NULL,
	user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY client_id (client_id),
	KEY parcel_id (parcel_id),
	KEY event (event)
) $collate;
";

		dbDelta( $tables );
	}

	/**
	 * Grants plugin capabilities to site managers.
	 *
	 * @return void
	 */
	public static function add_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'gcp_manage' );
			}
		}
	}

	/**
	 * Removes plugin capabilities.
	 *
	 * @return void
	 */
	public static function remove_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->remove_cap( 'gcp_manage' );
			}
		}
	}

	/**
	 * Seeds default settings on first activation.
	 *
	 * @return void
	 */
	public static function add_default_options() {
		if ( false !== get_option( 'gcp_settings', false ) ) {
			return;
		}

		add_option( 'gcp_settings', GCP_Settings::defaults() );
	}
}
