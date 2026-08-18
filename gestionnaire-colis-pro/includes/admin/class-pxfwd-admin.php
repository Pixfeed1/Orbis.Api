<?php
/**
 * Admin bootstrap: menus, assets, form handlers.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once PXFWD_PLUGIN_DIR . 'includes/admin/class-pxfwd-admin-clients.php';
require_once PXFWD_PLUGIN_DIR . 'includes/admin/class-pxfwd-admin-parcels.php';
require_once PXFWD_PLUGIN_DIR . 'includes/admin/class-pxfwd-admin-settings.php';

/**
 * Registers the admin menu and routes form submissions.
 */
class PXFWD_Admin {

	/**
	 * Hooks admin actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_pxfwd_create_client', array( 'PXFWD_Admin_Clients', 'handle_create' ) );
		add_action( 'admin_post_pxfwd_update_client', array( 'PXFWD_Admin_Clients', 'handle_update' ) );
		add_action( 'admin_post_pxfwd_add_document', array( 'PXFWD_Admin_Clients', 'handle_add_document' ) );
		add_action( 'admin_post_pxfwd_create_parcel', array( 'PXFWD_Admin_Parcels', 'handle_create' ) );
		add_action( 'admin_post_pxfwd_set_parcel_status', array( 'PXFWD_Admin_Parcels', 'handle_set_status' ) );
		add_action( 'admin_post_pxfwd_set_shipment_status', array( 'PXFWD_Admin_Clients', 'handle_set_shipment_status' ) );
		add_action( 'admin_post_pxfwd_save_settings', array( 'PXFWD_Admin_Settings', 'handle_save' ) );
	}

	/**
	 * Registers the plugin menu and submenus.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Gestionnaire Colis Pro', 'gestionnaire-colis-pro' ),
			__( 'Colis Pro', 'gestionnaire-colis-pro' ),
			'pxfwd_manage',
			'pxfwd-clients',
			array( 'PXFWD_Admin_Clients', 'render' ),
			'dashicons-archive',
			56
		);

		add_submenu_page(
			'pxfwd-clients',
			__( 'Clients', 'gestionnaire-colis-pro' ),
			__( 'Clients', 'gestionnaire-colis-pro' ),
			'pxfwd_manage',
			'pxfwd-clients',
			array( 'PXFWD_Admin_Clients', 'render' )
		);

		add_submenu_page(
			'pxfwd-clients',
			__( 'Parcels', 'gestionnaire-colis-pro' ),
			__( 'Parcels', 'gestionnaire-colis-pro' ),
			'pxfwd_manage',
			'pxfwd-parcels',
			array( 'PXFWD_Admin_Parcels', 'render_list' )
		);

		add_submenu_page(
			'pxfwd-clients',
			__( 'New parcel', 'gestionnaire-colis-pro' ),
			__( 'New parcel', 'gestionnaire-colis-pro' ),
			'pxfwd_manage',
			'pxfwd-new-parcel',
			array( 'PXFWD_Admin_Parcels', 'render_new' )
		);

		add_submenu_page(
			'pxfwd-clients',
			__( 'Settings', 'gestionnaire-colis-pro' ),
			__( 'Settings', 'gestionnaire-colis-pro' ),
			'pxfwd_manage',
			'pxfwd-settings',
			array( 'PXFWD_Admin_Settings', 'render' )
		);
	}

	/**
	 * Loads admin CSS/JS on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'pxfwd-' ) ) {
			return;
		}

		wp_enqueue_style(
			'pxfwd-admin',
			PXFWD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			PXFWD_VERSION
		);

		wp_enqueue_script(
			'pxfwd-admin',
			PXFWD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			PXFWD_VERSION,
			true
		);

		wp_localize_script(
			'pxfwd-admin',
			'pxfwdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pxfwd_admin' ),
				'i18n'    => array(
					'noResults'   => __( 'No clients found.', 'gestionnaire-colis-pro' ),
					'inStock'     => __( 'parcel(s) in stock', 'gestionnaire-colis-pro' ),
					'yes'         => __( 'Yes', 'gestionnaire-colis-pro' ),
					'no'          => __( 'No', 'gestionnaire-colis-pro' ),
					'refCol'      => __( 'Parcel number', 'gestionnaire-colis-pro' ),
					'weightCol'   => __( 'Weight (kg)', 'gestionnaire-colis-pro' ),
					'groupingCol' => __( 'Grouping allowed', 'gestionnaire-colis-pro' ),
					'noteCol'     => __( 'Internal comment', 'gestionnaire-colis-pro' ),
					'noParcels'   => __( 'No parcels in stock for this client.', 'gestionnaire-colis-pro' ),
				),
			)
		);
	}

	/**
	 * Redirects back to a plugin admin page with a notice.
	 *
	 * @param string $page   Page slug (e.g. pxfwd-clients).
	 * @param array  $args   Extra query args.
	 * @param string $notice Notice key.
	 * @param string $type   Notice type: success|error.
	 * @return void
	 */
	public static function redirect( $page, $args = array(), $notice = '', $type = 'success' ) {
		$url = add_query_arg(
			array_merge(
				array( 'page' => $page ),
				$args,
				$notice ? array(
					'pxfwd_notice' => $notice,
					'pxfwd_type'   => $type,
				) : array()
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Prints the notice passed back in the URL after a form submission.
	 *
	 * @return void
	 */
	public static function maybe_notice() {
		if ( empty( $_GET['pxfwd_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['pxfwd_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['pxfwd_type'] ) && 'error' === $_GET['pxfwd_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $notice )
		);
	}
}
