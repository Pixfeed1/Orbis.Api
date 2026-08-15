<?php
/**
 * Shipment requested e-mail (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/gcp-shipment-requested.php.
 *
 * @package GestionnaireColisPro
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

$gcp_parcels = GCP_Shipments::parcels( (int) $shipment->id );
$gcp_user    = get_userdata( (int) $client->user_id );
?>

<p>
	<?php
	printf(
		/* translators: 1: shipment reference, 2: client reference, 3: client name. */
		esc_html__( 'Shipment request %1$s has just been created by client %2$s (%3$s).', 'gestionnaire-colis-pro' ),
		'<strong>' . esc_html( $shipment->reference ) . '</strong>',
		'<strong>' . esc_html( $client->reference ) . '</strong>',
		esc_html( $gcp_user ? $gcp_user->display_name : '' )
	);
	?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse;">
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Carrier', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( GCP_Carriers::name( $shipment->carrier ) ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Parcels', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( implode( ', ', wp_list_pluck( $gcp_parcels, 'reference' ) ) ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Total weight', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?> kg</td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Total (parcels + storage + transport)', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( GCP_Format::price( (float) $shipment->total_price ) ); ?></td>
	</tr>
</table>

<?php
$gcp_client_url = add_query_arg(
	array(
		'page'   => 'gcp-clients',
		'client' => (int) $client->id,
	),
	admin_url( 'admin.php' )
);
?>
<p>
	<a href="<?php echo esc_url( $gcp_client_url ); ?>"><?php esc_html_e( 'Open the client record', 'gestionnaire-colis-pro' ); ?></a>
</p>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
