<?php
/**
 * Parcel received e-mail (plain text).
 *
 * @package GestionnaireColisPro
 * @var object $parcel             Parcel row.
 * @var string $email_heading      Heading.
 * @var string $additional_content Extra content from the e-mail settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo esc_html__( 'Bonjour,', 'gestionnaire-colis-pro' ) . "\n\n";

/* translators: %s: parcel reference. */
echo esc_html( sprintf( __( 'Nous avons bien réceptionné votre colis %s à l’entrepôt. Il est désormais visible dans votre espace client, rubrique « Mes colis ».', 'gestionnaire-colis-pro' ), $parcel->reference ) ) . "\n\n";

echo esc_html__( 'Numéro du colis :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( $parcel->reference ) . "\n";
echo esc_html__( 'Numéro de suivi :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ) . "\n";
echo esc_html__( 'Poids :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ) . " kg\n";
/* translators: %d: number of free storage days. */
echo esc_html( sprintf( __( 'Stockage gratuit : %d jours', 'gestionnaire-colis-pro' ), (int) GCP_Settings::get( 'free_storage_days', 15 ) ) ) . "\n";

if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
