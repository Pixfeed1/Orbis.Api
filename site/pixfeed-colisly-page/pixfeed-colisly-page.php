<?php
/**
 * Plugin Name:       Pixfeed – Page Colisly
 * Description:       Affiche la page de présentation de Colisly, avec son propre gabarit, sur la page dont l’identifiant (slug) est « colisly ».
 * Version:           1.0.0
 * Author:            Pixfeed
 * Author URI:        https://pixfeed.net
 * License:           GPL-2.0-or-later
 * Text Domain:       pixfeed-colisly-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIXFEED_COLISLY_PAGE_VERSION', '1.0.0' );

/**
 * Identifiant de la page WordPress qui reçoit le gabarit.
 *
 * Modifiable avec le filtre pixfeed_colisly_page_slug.
 */
function pixfeed_colisly_page_slug() {
	return apply_filters( 'pixfeed_colisly_page_slug', 'colisly' );
}

/**
 * Remplace le gabarit du thème par le nôtre sur la page Colisly.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( is_page( pixfeed_colisly_page_slug() ) ) {
			return plugin_dir_path( __FILE__ ) . 'template.php';
		}
		return $template;
	},
	99
);

/**
 * Les images et les vidéos vivent dans le dossier de l’extension ; rien
 * n’est chargé du thème sur cette page, donc aucun conflit de styles.
 */
