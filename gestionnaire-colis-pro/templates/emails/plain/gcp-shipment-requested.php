<?php
/**
 * Shipment requested e-mail (plain text).
 *
 * @package GestionnaireColisPro
 * @var object $shipment           Shipment row.
 * @var object $client             Client row.
 * @var string $email_heading      Heading.
 * @var string $additional_content Extra content from the e-mail settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

$gcp_parcels = GCP_Shipments::parcels( (int) $shipment->id );
$gcp_user    = get_userdata( (int) $client->user_id );

/* translators: 1: shipment reference, 2: client reference, 3: client name. */
echo esc_html( sprintf( __( 'La demande d’expédition %1$s vient d’être créée par le client %2$s (%3$s).', 'gestionnaire-colis-pro' ), $shipment->reference, $client->reference, $gcp_user ? $gcp_user->display_name : '' ) ) . "\n\n";

echo esc_html__( 'Transporteur :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( GCP_Carriers::name( $shipment->carrier ) ) . "\n";
echo esc_html__( 'Colis :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( implode( ', ', wp_list_pluck( $gcp_parcels, 'reference' ) ) ) . "\n";
echo esc_html__( 'Poids total :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ) . " kg\n";
echo esc_html__( 'Total :', 'gestionnaire-colis-pro' ) . ' ' . esc_html( GCP_Format::price( (float) $shipment->total_price ) ) . "\n\n";

echo esc_html__( 'Fiche client :', 'gestionnaire-colis-pro' ) . ' ' . esc_url_raw(
	add_query_arg(
		array(
			'page'   => 'gcp-clients',
			'client' => (int) $client->id,
		),
		admin_url( 'admin.php' )
	)
) . "\n";

if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
