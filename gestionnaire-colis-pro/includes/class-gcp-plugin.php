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
final class GCP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var GCP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return GCP_Plugin
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
		add_action( 'init', array( 'GCP_Install', 'maybe_update' ), 5 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

		GCP_Emails::init();
		GCP_Ajax::init();
		GCP_Downloads::init();

		if ( is_admin() ) {
			require_once GCP_PLUGIN_DIR . 'includes/admin/class-gcp-admin.php';
			GCP_Admin::init();
		}

		require_once GCP_PLUGIN_DIR . 'includes/frontend/class-gcp-account.php';
		GCP_Account::init();
	}

	/**
	 * Declares compatibility with WooCommerce HPOS.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GCP_PLUGIN_FILE, true );
		}
	}
}
