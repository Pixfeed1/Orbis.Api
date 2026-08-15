<?php
/**
 * Admin bootstrap: menus, assets, form handlers.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GCP_PLUGIN_DIR . 'includes/admin/class-gcp-admin-clients.php';
require_once GCP_PLUGIN_DIR . 'includes/admin/class-gcp-admin-parcels.php';
require_once GCP_PLUGIN_DIR . 'includes/admin/class-gcp-admin-settings.php';

/**
 * Registers the admin menu and routes form submissions.
 */
class GCP_Admin {

	/**
	 * Hooks admin actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_gcp_create_client', array( 'GCP_Admin_Clients', 'handle_create' ) );
		add_action( 'admin_post_gcp_update_client', array( 'GCP_Admin_Clients', 'handle_update' ) );
		add_action( 'admin_post_gcp_add_document', array( 'GCP_Admin_Clients', 'handle_add_document' ) );
		add_action( 'admin_post_gcp_create_parcel', array( 'GCP_Admin_Parcels', 'handle_create' ) );
		add_action( 'admin_post_gcp_set_parcel_status', array( 'GCP_Admin_Parcels', 'handle_set_status' ) );
		add_action( 'admin_post_gcp_set_shipment_status', array( 'GCP_Admin_Clients', 'handle_set_shipment_status' ) );
		add_action( 'admin_post_gcp_save_settings', array( 'GCP_Admin_Settings', 'handle_save' ) );
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
			'gcp_manage',
			'gcp-clients',
			array( 'GCP_Admin_Clients', 'render' ),
			'dashicons-archive',
			56
		);

		add_submenu_page(
			'gcp-clients',
			__( 'Clients', 'gestionnaire-colis-pro' ),
			__( 'Clients', 'gestionnaire-colis-pro' ),
			'gcp_manage',
			'gcp-clients',
			array( 'GCP_Admin_Clients', 'render' )
		);

		add_submenu_page(
			'gcp-clients',
			__( 'Colis', 'gestionnaire-colis-pro' ),
			__( 'Colis', 'gestionnaire-colis-pro' ),
			'gcp_manage',
			'gcp-parcels',
			array( 'GCP_Admin_Parcels', 'render_list' )
		);

		add_submenu_page(
			'gcp-clients',
			__( 'Nouveau colis', 'gestionnaire-colis-pro' ),
			__( 'Nouveau colis', 'gestionnaire-colis-pro' ),
			'gcp_manage',
			'gcp-new-parcel',
			array( 'GCP_Admin_Parcels', 'render_new' )
		);

		add_submenu_page(
			'gcp-clients',
			__( 'Réglages', 'gestionnaire-colis-pro' ),
			__( 'Réglages', 'gestionnaire-colis-pro' ),
			'gcp_manage',
			'gcp-settings',
			array( 'GCP_Admin_Settings', 'render' )
		);
	}

	/**
	 * Loads admin CSS/JS on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'gcp-' ) ) {
			return;
		}

		wp_enqueue_style(
			'gcp-admin',
			GCP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GCP_VERSION
		);

		wp_enqueue_script(
			'gcp-admin',
			GCP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			GCP_VERSION,
			true
		);

		wp_localize_script(
			'gcp-admin',
			'gcpAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gcp_admin' ),
				'i18n'    => array(
					'noResults'   => __( 'Aucun client trouvé.', 'gestionnaire-colis-pro' ),
					'inStock'     => __( 'colis en stock', 'gestionnaire-colis-pro' ),
					'yes'         => __( 'Oui', 'gestionnaire-colis-pro' ),
					'no'          => __( 'Non', 'gestionnaire-colis-pro' ),
					'refCol'      => __( 'Numéro du colis', 'gestionnaire-colis-pro' ),
					'weightCol'   => __( 'Poids (kg)', 'gestionnaire-colis-pro' ),
					'groupingCol' => __( 'Regroupement autorisé', 'gestionnaire-colis-pro' ),
					'noteCol'     => __( 'Commentaire interne', 'gestionnaire-colis-pro' ),
					'noParcels'   => __( 'Aucun colis en stock pour ce client.', 'gestionnaire-colis-pro' ),
				),
			)
		);
	}

	/**
	 * Redirects back to a plugin admin page with a notice.
	 *
	 * @param string $page   Page slug (e.g. gcp-clients).
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
					'gcp_notice' => $notice,
					'gcp_type'   => $type,
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
		if ( empty( $_GET['gcp_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['gcp_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['gcp_type'] ) && 'error' === $_GET['gcp_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $notice )
		);
	}
}
