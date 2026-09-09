<?php
/**
 * Parcel received e-mail (plain text).
 *
 * @package ColislyParcelForwarding
 * @var object $parcel             Parcel row.
 * @var string $email_heading      Heading.
 * @var string $additional_content Extra content from the e-mail settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo esc_html__( 'Hello,', 'colisly' ) . "\n\n";

/* translators: %s: parcel reference. */
echo esc_html( sprintf( __( 'We have received your parcel %s at the warehouse. It is now visible in your account, under “My parcels”.', 'colisly' ), $parcel->reference ) ) . "\n\n";

echo esc_html__( 'Parcel number:', 'colisly' ) . ' ' . esc_html( $parcel->reference ) . "\n";
echo esc_html__( 'Tracking number:', 'colisly' ) . ' ' . esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ) . "\n";
echo esc_html__( 'Weight:', 'colisly' ) . ' ' . esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ) . " kg\n";
if ( (float) $parcel->advanced_fees > 0 ) {
	echo esc_html( COLISLY_Parcels::advanced_fees_text( $parcel ) ) . ' — ' . esc_html__( 'Paid on your behalf to take delivery of the parcel, added at cost to your next shipment order.', 'colisly' ) . "\n";
}
/* translators: %d: number of free storage days. */
echo esc_html( sprintf( __( 'Free storage: %d days', 'colisly' ), (int) COLISLY_Settings::get( 'free_storage_days', 15 ) ) ) . "\n";

if ( $additional_content ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
