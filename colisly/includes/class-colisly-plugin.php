<?php
/**
 * Main plugin class.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin components.
 */
final class COLISLY_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var COLISLY_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return COLISLY_Plugin
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
		add_action( 'init', array( 'COLISLY_Install', 'maybe_update' ), 5 );
		// Order meta migration runs in the admin only: WooCommerce order types
		// are registered by then, and front-end requests stay untouched.
		add_action( 'admin_init', array( 'COLISLY_Install', 'migrate_legacy_order_meta' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( COLISLY_PLUGIN_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'store_readiness_notice' ) );

		COLISLY_Emails::init();
		COLISLY_Ajax::init();
		COLISLY_Downloads::init();
		COLISLY_Orders::init();
		COLISLY_Privacy::init();

		if ( is_admin() ) {
			require_once COLISLY_PLUGIN_DIR . 'includes/admin/class-colisly-admin.php';
			COLISLY_Admin::init();
		}

		require_once COLISLY_PLUGIN_DIR . 'includes/frontend/class-colisly-account.php';
		COLISLY_Account::init();
	}

	/**
	 * Adds a Settings shortcut on the plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=colisly-settings' ) ),
				esc_html__( 'Settings', 'colisly' )
			)
		);

		return $links;
	}

	/**
	 * Warns when the shop cannot take a payment.
	 *
	 * Shipment requests end on the WooCommerce payment page. A store left in
	 * coming soon mode, or with no payment method enabled, sends every customer
	 * into a dead end with nothing to explain it, so say it here.
	 *
	 * @return void
	 */
	public function store_readiness_notice() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'colisly' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return;
		}

		$problems = array();

		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) ) {
			$problems[] = __( 'the store is in coming soon mode, so customers cannot reach the payment page', 'colisly' );
		}

		if ( ! WC()->payment_gateways->get_available_payment_gateways() ) {
			$problems[] = __( 'no payment method is enabled, so shipment orders cannot be paid', 'colisly' );
		}

		if ( ! $problems ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: list of blocking WooCommerce settings. */
					__( 'Colisly: shipment requests will not go through because %s.', 'colisly' ),
					implode( __( ', and ', 'colisly' ), $problems )
				)
			)
		);
	}

	/**
	 * Declares compatibility with WooCommerce HPOS.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', COLISLY_PLUGIN_FILE, true );
		}
	}
}
