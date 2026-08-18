<?php
/**
 * Admin bootstrap: menus, assets, form handlers.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once COLISLY_PLUGIN_DIR . 'includes/admin/class-colisly-admin-clients.php';
require_once COLISLY_PLUGIN_DIR . 'includes/admin/class-colisly-admin-parcels.php';
require_once COLISLY_PLUGIN_DIR . 'includes/admin/class-colisly-admin-settings.php';

/**
 * Registers the admin menu and routes form submissions.
 */
class COLISLY_Admin {

	/**
	 * Hooks admin actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_colisly_create_client', array( 'COLISLY_Admin_Clients', 'handle_create' ) );
		add_action( 'admin_post_colisly_update_client', array( 'COLISLY_Admin_Clients', 'handle_update' ) );
		add_action( 'admin_post_colisly_add_document', array( 'COLISLY_Admin_Clients', 'handle_add_document' ) );
		add_action( 'admin_post_colisly_create_parcel', array( 'COLISLY_Admin_Parcels', 'handle_create' ) );
		add_action( 'admin_post_colisly_set_parcel_status', array( 'COLISLY_Admin_Parcels', 'handle_set_status' ) );
		add_action( 'admin_post_colisly_set_shipment_status', array( 'COLISLY_Admin_Clients', 'handle_set_shipment_status' ) );
		add_action( 'admin_post_colisly_save_settings', array( 'COLISLY_Admin_Settings', 'handle_save' ) );
	}

	/**
	 * Registers the plugin menu and submenus.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Colisly Parcel Forwarding', 'colisly-parcel-forwarding' ),
			__( 'Colisly', 'colisly-parcel-forwarding' ),
			'colisly_manage',
			'colisly-clients',
			array( 'COLISLY_Admin_Clients', 'render' ),
			'dashicons-archive',
			56
		);

		add_submenu_page(
			'colisly-clients',
			__( 'Clients', 'colisly-parcel-forwarding' ),
			__( 'Clients', 'colisly-parcel-forwarding' ),
			'colisly_manage',
			'colisly-clients',
			array( 'COLISLY_Admin_Clients', 'render' )
		);

		add_submenu_page(
			'colisly-clients',
			__( 'Parcels', 'colisly-parcel-forwarding' ),
			__( 'Parcels', 'colisly-parcel-forwarding' ),
			'colisly_manage',
			'colisly-parcels',
			array( 'COLISLY_Admin_Parcels', 'render_list' )
		);

		add_submenu_page(
			'colisly-clients',
			__( 'New parcel', 'colisly-parcel-forwarding' ),
			__( 'New parcel', 'colisly-parcel-forwarding' ),
			'colisly_manage',
			'colisly-new-parcel',
			array( 'COLISLY_Admin_Parcels', 'render_new' )
		);

		add_submenu_page(
			'colisly-clients',
			__( 'Settings', 'colisly-parcel-forwarding' ),
			__( 'Settings', 'colisly-parcel-forwarding' ),
			'colisly_manage',
			'colisly-settings',
			array( 'COLISLY_Admin_Settings', 'render' )
		);
	}

	/**
	 * Loads admin CSS/JS on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'colisly-' ) ) {
			return;
		}

		wp_enqueue_style(
			'colisly-admin',
			COLISLY_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			COLISLY_VERSION
		);

		wp_enqueue_script(
			'colisly-admin',
			COLISLY_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			COLISLY_VERSION,
			true
		);

		wp_localize_script(
			'colisly-admin',
			'colislyAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'colisly_admin' ),
				'i18n'    => array(
					'noResults'   => __( 'No clients found.', 'colisly-parcel-forwarding' ),
					'inStock'     => __( 'parcel(s) in stock', 'colisly-parcel-forwarding' ),
					'yes'         => __( 'Yes', 'colisly-parcel-forwarding' ),
					'no'          => __( 'No', 'colisly-parcel-forwarding' ),
					'refCol'      => __( 'Parcel number', 'colisly-parcel-forwarding' ),
					'weightCol'   => __( 'Weight (kg)', 'colisly-parcel-forwarding' ),
					'groupingCol' => __( 'Grouping allowed', 'colisly-parcel-forwarding' ),
					'noteCol'     => __( 'Internal comment', 'colisly-parcel-forwarding' ),
					'noParcels'   => __( 'No parcels in stock for this client.', 'colisly-parcel-forwarding' ),
				),
			)
		);
	}

	/**
	 * Redirects back to a plugin admin page with a notice.
	 *
	 * @param string $page   Page slug (e.g. colisly-clients).
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
					'colisly_notice' => $notice,
					'colisly_type'   => $type,
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
		if ( empty( $_GET['colisly_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['colisly_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['colisly_type'] ) && 'error' === $_GET['colisly_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $notice )
		);
	}
}
