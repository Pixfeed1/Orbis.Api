<?php
/**
 * Main plugin class.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin components.
 */
final class PXFWD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PXFWD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return PXFWD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wires hooks.
	 */
	private function __construct() {
		// Translations are loaded automatically by WordPress (4.6+) from
		// translate.wordpress.org, so no load_plugin_textdomain() call here.
		add_action( 'init', array( 'PXFWD_Install', 'maybe_update' ), 5 );
		// Order meta migration runs in the admin only: WooCommerce order types
		// are registered by then, and front-end requests stay untouched.
		add_action( 'admin_init', array( 'PXFWD_Install', 'migrate_legacy_order_meta' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

		PXFWD_Emails::init();
		PXFWD_Ajax::init();
		PXFWD_Downloads::init();
		PXFWD_Orders::init();
		PXFWD_Privacy::init();

		if ( is_admin() ) {
			require_once PXFWD_PLUGIN_DIR . 'includes/admin/class-pxfwd-admin.php';
			PXFWD_Admin::init();
		}

		require_once PXFWD_PLUGIN_DIR . 'includes/frontend/class-pxfwd-account.php';
		PXFWD_Account::init();
	}

	/**
	 * Declares compatibility with WooCommerce HPOS.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PXFWD_PLUGIN_FILE, true );
		}
	}
}
