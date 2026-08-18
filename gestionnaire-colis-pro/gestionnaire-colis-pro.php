<?php
/**
 * Plugin Name:       Gestionnaire Colis Pro
 * Plugin URI:        https://github.com/pixfeed1/orbis.api
 * Description:       Client and parcel management for a parcel receiving, storage, grouping and forwarding business, natively integrated with WooCommerce.
 * Version:           1.6.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Pixfeed
 * Author URI:        https://github.com/pixfeed1
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gestionnaire-colis-pro
 * Domain Path:       /languages
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PXFWD_VERSION', '1.6.0' );
define( 'PXFWD_PLUGIN_FILE', __FILE__ );
define( 'PXFWD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PXFWD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-install.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-settings.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-format.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-files.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-downloads.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-carriers.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-pricing.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-storage.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-history.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-clients.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-parcels.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-shipments.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-orders.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-documents.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-emails.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-ajax.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-privacy.php';
require_once PXFWD_PLUGIN_DIR . 'includes/class-pxfwd-plugin.php';

register_activation_hook( __FILE__, array( 'PXFWD_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PXFWD_Install', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return PXFWD_Plugin
 */
function pxfwd() {
	return PXFWD_Plugin::instance();
}

pxfwd();
