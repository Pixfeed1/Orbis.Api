<?php
/**
 * Plugin Name:       Colisly Parcel Forwarding
 * Plugin URI:        https://wordpress.org/plugins/colisly/
 * Description:       Client and parcel management for a parcel receiving, storage, grouping and forwarding business, natively integrated with WooCommerce.
 * Version:           1.6.10
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Pixfeed
 * Author URI:        https://pixfeed.net/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       colisly
 * Domain Path:       /languages
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'COLISLY_VERSION', '1.6.10' );
define( 'COLISLY_PLUGIN_FILE', __FILE__ );
define( 'COLISLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COLISLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-install.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-settings.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-format.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-files.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-downloads.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-carriers.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-pricing.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-storage.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-history.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-clients.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-parcels.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-shipments.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-orders.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-documents.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-emails.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-ajax.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-privacy.php';
require_once COLISLY_PLUGIN_DIR . 'includes/class-colisly-plugin.php';

register_activation_hook( __FILE__, array( 'COLISLY_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'COLISLY_Install', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return COLISLY_Plugin
 */
function colisly() {
	return COLISLY_Plugin::instance();
}

colisly();
