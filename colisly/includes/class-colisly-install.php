<?php
/**
 * Installation routines: database schema, capabilities, default options.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation, deactivation and database schema.
 */
class COLISLY_Install {

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::migrate_legacy_prefix();
		self::create_tables();
		self::add_capabilities();
		self::add_default_options();
		update_option( 'colisly_db_version', COLISLY_VERSION );
		// My Account endpoints are registered on init; flush on next load.
		update_option( 'colisly_flush_rewrite_rules', 'yes' );
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
		if ( get_option( 'colisly_db_version' ) !== COLISLY_VERSION ) {
			self::migrate_legacy_prefix();
			self::create_tables();
			self::add_capabilities();
			update_option( 'colisly_db_version', COLISLY_VERSION );
		}

		if ( 'yes' === get_option( 'colisly_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'colisly_flush_rewrite_rules' );
		}
	}

	/**
	 * Migrates data stored under the plugin's former "gcp" prefix.
	 *
	 * The prefix was renamed to "colisly" to meet the four-character minimum
	 * required for plugin prefixes. Sites installed before that rename keep
	 * their clients, parcels, shipments, documents, history and private files.
	 *
	 * @return void
	 */
	public static function migrate_legacy_prefix() {
		global $wpdb;

		// Tables.
		foreach ( array( 'clients', 'parcels', 'shipments', 'documents', 'history' ) as $entity ) {
			$legacy  = $wpdb->prefix . 'gcp_' . $entity;
			$current = $wpdb->prefix . 'colisly_' . $entity;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_legacy = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) ) === $legacy;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_current = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $current ) ) === $current;

			if ( $has_legacy && ! $has_current ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "RENAME TABLE `{$legacy}` TO `{$current}`" );
			}
		}

		// Options.
		foreach ( array( 'settings', 'db_version', 'flush_rewrite_rules', 'remove_data_on_uninstall' ) as $option ) {
			$legacy_value = get_option( 'gcp_' . $option, null );

			if ( null !== $legacy_value && false === get_option( 'colisly_' . $option, false ) ) {
				update_option( 'colisly_' . $option, $legacy_value );
			}

			delete_option( 'gcp_' . $option );
		}

		// Capability.
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && ! empty( $role->capabilities['gcp_manage'] ) ) {
				$role->add_cap( 'colisly_manage' );
				$role->remove_cap( 'gcp_manage' );
			}
		}

		// Private files directory.
		$uploads = wp_upload_dir( null, false );
		$legacy  = $uploads['basedir'] . '/gcp-private';
		$current = $uploads['basedir'] . '/colisly-private';

		if ( is_dir( $legacy ) && ! is_dir( $current ) ) {
			global $wp_filesystem;

			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( WP_Filesystem() && $wp_filesystem ) {
				$wp_filesystem->move( $legacy, $current );
			}
		}
	}

	/**
	 * Migrates the shipment meta stored on WooCommerce orders.
	 *
	 * Runs separately from migrate_legacy_prefix() because it needs the
	 * WooCommerce order types to be registered, which only happens after
	 * init; it is therefore hooked on admin_init. Runs once, then records a
	 * flag. Orders not migrated yet are still resolved through the legacy
	 * meta key by COLISLY_Orders.
	 *
	 * @return void
	 */
	public static function migrate_legacy_order_meta() {
		if ( 'done' === get_option( 'colisly_order_meta_migrated' ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'meta_key'     => '_gcp_shipment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'return'       => 'objects',
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			$order->update_meta_data( '_colisly_shipment_id', $order->get_meta( '_gcp_shipment_id' ) );
			$order->update_meta_data( '_colisly_shipment_reference', $order->get_meta( '_gcp_shipment_reference' ) );
			$order->delete_meta_data( '_gcp_shipment_id' );
			$order->delete_meta_data( '_gcp_shipment_reference' );
			$order->save();
		}

		update_option( 'colisly_order_meta_migrated', 'done' );
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
CREATE TABLE {$wpdb->prefix}colisly_clients (
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
CREATE TABLE {$wpdb->prefix}colisly_parcels (
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
CREATE TABLE {$wpdb->prefix}colisly_shipments (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	reference VARCHAR(20) NOT NULL DEFAULT '',
	client_id BIGINT UNSIGNED NOT NULL,
	order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	carrier VARCHAR(100) NOT NULL DEFAULT '',
	status VARCHAR(30) NOT NULL DEFAULT 'requested',
	total_weight DECIMAL(10,3) NOT NULL DEFAULT 0,
	total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
	storage_fees DECIMAL(12,2) NOT NULL DEFAULT 0,
	carrier_price DECIMAL(12,2) NOT NULL DEFAULT 0,
	requested_at DATETIME NOT NULL,
	shipped_at DATETIME NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY reference (reference),
	KEY client_id (client_id),
	KEY status (status)
) $collate;
CREATE TABLE {$wpdb->prefix}colisly_documents (
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
CREATE TABLE {$wpdb->prefix}colisly_history (
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
				$role->add_cap( 'colisly_manage' );
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
				$role->remove_cap( 'colisly_manage' );
			}
		}
	}

	/**
	 * Seeds default settings on first activation.
	 *
	 * @return void
	 */
	public static function add_default_options() {
		if ( false !== get_option( 'colisly_settings', false ) ) {
			return;
		}

		add_option( 'colisly_settings', COLISLY_Settings::defaults() );
	}
}
