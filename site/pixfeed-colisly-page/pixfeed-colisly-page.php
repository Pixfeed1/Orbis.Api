<?php
/**
 * Plugin Name:       Pixfeed – Page Colisly
 * Description:       Affiche la page de présentation de Colisly, avec son propre gabarit plein écran, sur la page dont l’identifiant (slug) est « colisly ». Yoast et Site Kit continuent d’y fonctionner.
 * Version:           1.1.0
 * Author:            Pixfeed
 * Author URI:        https://pixfeed.net
 * License:           GPL-2.0-or-later
 * Text Domain:       pixfeed-colisly-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIXFEED_COLISLY_PAGE_VERSION', '1.1.0' );

/**
 * Identifiant de la page WordPress qui reçoit le gabarit.
 */
function pixfeed_colisly_page_slug() {
	return apply_filters( 'pixfeed_colisly_page_slug', 'colisly' );
}

/**
 * Sommes-nous sur la page Colisly ?
 */
function pixfeed_colisly_is_page() {
	return is_page( pixfeed_colisly_page_slug() );
}

/**
 * Titre et description de la page, en un seul endroit.
 */
function pixfeed_colisly_seo() {
	return array(
		'title'       => 'Colisly, extension WooCommerce de réexpédition de colis | Pixfeed',
		'description' => 'Colisly transforme une boutique WooCommerce en plateforme de réexpédition de colis : réception, stockage, groupage, réexpédition et espace client. Extension WordPress gratuite, GPL.',
		'image'       => plugin_dir_url( __FILE__ ) . 'assets/img/hero-poster-l.jpg',
	);
}

/**
 * Remplace le gabarit du thème par le nôtre sur la page Colisly.
 */
add_filter(
	'template_include',
	function ( $template ) {
		return pixfeed_colisly_is_page() ? plugin_dir_path( __FILE__ ) . 'template.php' : $template;
	},
	99
);

/**
 * Sur cette page, seuls nos styles et les scripts de mesure d’audience
 * (Site Kit, Google Analytics) sont chargés. Les feuilles de style du thème
 * et des blocs restyleraient la page ; elles sont retirées.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! pixfeed_colisly_is_page() ) {
			return;
		}

		$u = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'pixfeed-colisly-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=JetBrains+Mono:wght@500;600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'pixfeed-colisly-page', $u . 'assets/css/page.css', array(), PIXFEED_COLISLY_PAGE_VERSION );

		pixfeed_colisly_strip_assets();
	},
	999
);

/**
 * Retire tout ce qui n’est ni à nous ni à la mesure d’audience. Appelé une
 * seconde fois juste avant l’impression, pour les extensions qui ajoutent
 * leurs fichiers tard.
 */
function pixfeed_colisly_strip_assets() {
	if ( ! pixfeed_colisly_is_page() ) {
		return;
	}
	$keep = function ( $handle ) {
		return 0 === strpos( $handle, 'pixfeed-colisly' ) || false !== strpos( $handle, 'googlesitekit' ) || false !== strpos( $handle, 'google_gtagjs' );
	};
	foreach ( (array) wp_styles()->queue as $handle ) {
		if ( ! $keep( $handle ) ) {
			wp_dequeue_style( $handle );
		}
	}
	foreach ( (array) wp_scripts()->queue as $handle ) {
		if ( ! $keep( $handle ) ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_print_styles', 'pixfeed_colisly_strip_assets', 999 );
add_action( 'wp_print_scripts', 'pixfeed_colisly_strip_assets', 999 );
add_action( 'wp_print_footer_scripts', 'pixfeed_colisly_strip_assets', 1 );

/**
 * Le superflu de l’en-tête : émojis, styles globaux du thème, générateur.
 */
add_action(
	'wp',
	function () {
		if ( ! pixfeed_colisly_is_page() ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
	}
);

/**
 * Titre de l’onglet quand Yoast n’est pas là pour le fournir.
 */
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		return pixfeed_colisly_is_page() ? pixfeed_colisly_seo()['title'] : $title;
	},
	20
);

/**
 * Avec Yoast : les champs SEO de la page sont préremplis une fois, puis
 * modifiables dans Yoast comme pour toute autre page.
 * Sans Yoast : description, canonique et balises de partage sont écrites ici.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! pixfeed_colisly_is_page() ) {
			return;
		}
		$seo = pixfeed_colisly_seo();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$id = get_queried_object_id();
			if ( $id && '' === (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ) ) {
				update_post_meta( $id, '_yoast_wpseo_title', $seo['title'] );
				update_post_meta( $id, '_yoast_wpseo_metadesc', $seo['description'] );
				update_post_meta( $id, '_yoast_wpseo_opengraph-image', $seo['image'] );
				update_post_meta( $id, '_yoast_wpseo_opengraph-title', 'Colisly, la réexpédition de colis dans WooCommerce' );
			}
			return;
		}
		add_action(
			'wp_head',
			function () use ( $seo ) {
				$url = get_permalink();
				echo '<meta name="description" content="' . esc_attr( $seo['description'] ) . '">' . "\n";
				echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
				echo '<meta property="og:type" content="website">' . "\n";
				echo '<meta property="og:locale" content="fr_FR">' . "\n";
				echo '<meta property="og:title" content="Colisly, la réexpédition de colis dans WooCommerce">' . "\n";
				echo '<meta property="og:description" content="' . esc_attr( $seo['description'] ) . '">' . "\n";
				echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
				echo '<meta property="og:image" content="' . esc_url( $seo['image'] ) . '">' . "\n";
				echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			},
			2
		);
	}
);
