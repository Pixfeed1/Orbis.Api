<?php
/**
 * Plugin Name:       Gestionnaire Colis Pro
 * Plugin URI:        https://github.com/pixfeed1/orbis.api
 * Description:       Client and parcel management for a parcel receiving, storage, grouping and forwarding business, natively integrated with WooCommerce.
 * Version:           1.4.0
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

define( 'GCP_VERSION', '1.4.0' );
define( 'GCP_PLUGIN_FILE', __FILE__ );
define( 'GCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GCP_PLUGIN_DIR . 'includes/class-gcp-install.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-settings.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-format.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-files.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-downloads.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-carriers.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-pricing.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-storage.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-history.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-clients.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-parcels.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-shipments.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-orders.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-documents.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-emails.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-ajax.php';
require_once GCP_PLUGIN_DIR . 'includes/class-gcp-plugin.php';

register_activation_hook( __FILE__, array( 'GCP_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GCP_Install', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return GCP_Plugin
 */
function gcp() {
	return GCP_Plugin::instance();
}

gcp();
