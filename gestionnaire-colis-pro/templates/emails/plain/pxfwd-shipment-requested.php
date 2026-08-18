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

$pxfwd_parcels = PXFWD_Shipments::parcels( (int) $shipment->id );
$pxfwd_user    = get_userdata( (int) $client->user_id );

/* translators: 1: shipment reference, 2: client reference, 3: client name. */
echo esc_html( sprintf( __( 'Shipment request %1$s has just been created by client %2$s (%3$s).', 'gestionnaire-colis-pro' ), $shipment->reference, $client->reference, $pxfwd_user ? $pxfwd_user->display_name : '' ) ) . "\n\n";

echo esc_html__( 'Carrier:', 'gestionnaire-colis-pro' ) . ' ' . esc_html( PXFWD_Carriers::name( $shipment->carrier ) ) . "\n";
echo esc_html__( 'Parcels:', 'gestionnaire-colis-pro' ) . ' ' . esc_html( implode( ', ', wp_list_pluck( $pxfwd_parcels, 'reference' ) ) ) . "\n";
echo esc_html__( 'Total weight:', 'gestionnaire-colis-pro' ) . ' ' . esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ) . " kg\n";
echo esc_html__( 'Total:', 'gestionnaire-colis-pro' ) . ' ' . esc_html( PXFWD_Format::price( (float) $shipment->total_price ) ) . "\n\n";

$pxfwd_client_url = add_query_arg(
	array(
		'page'   => 'pxfwd-clients',
		'client' => (int) $client->id,
	),
	admin_url( 'admin.php' )
);

echo esc_html__( 'Client record:', 'gestionnaire-colis-pro' ) . ' ' . esc_url( $pxfwd_client_url ) . "\n";

if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
