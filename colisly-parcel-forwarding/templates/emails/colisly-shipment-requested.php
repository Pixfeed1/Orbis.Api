<?php
/**
 * Shipment requested e-mail (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/colisly-shipment-requested.php.
 *
 * @package ColislyParcelForwarding
 * @var object   $shipment           Shipment row.
 * @var object   $client             Client row.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Extra content from the e-mail settings.
 * @var WC_Email $email              E-mail object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

$colisly_parcels = COLISLY_Shipments::parcels( (int) $shipment->id );
$colisly_user    = get_userdata( (int) $client->user_id );
?>

<p>
	<?php
	printf(
		/* translators: 1: shipment reference, 2: client reference, 3: client name. */
		esc_html__( 'Shipment request %1$s has just been created by client %2$s (%3$s).', 'colisly-parcel-forwarding' ),
		'<strong>' . esc_html( $shipment->reference ) . '</strong>',
		'<strong>' . esc_html( $client->reference ) . '</strong>',
		esc_html( $colisly_user ? $colisly_user->display_name : '' )
	);
	?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse;">
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Carrier', 'colisly-parcel-forwarding' ); ?></th>
		<td><?php echo esc_html( COLISLY_Carriers::name( $shipment->carrier ) ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Parcels', 'colisly-parcel-forwarding' ); ?></th>
		<td><?php echo esc_html( implode( ', ', wp_list_pluck( $colisly_parcels, 'reference' ) ) ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Total weight', 'colisly-parcel-forwarding' ); ?></th>
		<td><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?> kg</td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Total (parcels + storage + transport)', 'colisly-parcel-forwarding' ); ?></th>
		<td><?php echo esc_html( COLISLY_Format::price( (float) $shipment->total_price ) ); ?></td>
	</tr>
</table>

<?php
$colisly_client_url = add_query_arg(
	array(
		'page'   => 'colisly-clients',
		'client' => (int) $client->id,
	),
	admin_url( 'admin.php' )
);
?>
<p>
	<a href="<?php echo esc_url( $colisly_client_url ); ?>"><?php esc_html_e( 'Open the client record', 'colisly-parcel-forwarding' ); ?></a>
</p>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
