<?php
/**
 * Parcel received e-mail (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/colisly-parcel-received.php.
 *
 * @package ColislyParcelForwarding
 * @var object   $parcel             Parcel row.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Extra content from the e-mail settings.
 * @var WC_Email $email              E-mail object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Hello,', 'colisly' ); ?></p>

<p>
	<?php
	printf(
		/* translators: %s: parcel reference. */
		esc_html__( 'We have received your parcel %s at the warehouse. It is now visible in your account, under “My parcels”.', 'colisly' ),
		'<strong>' . esc_html( $parcel->reference ) . '</strong>'
	);
	?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse;">
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Parcel number', 'colisly' ); ?></th>
		<td><?php echo esc_html( $parcel->reference ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Tracking number', 'colisly' ); ?></th>
		<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Weight', 'colisly' ); ?></th>
		<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?> kg</td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Free storage', 'colisly' ); ?></th>
		<td>
			<?php
			printf(
				/* translators: %d: number of free storage days. */
				esc_html( _n( '%d day', '%d days', (int) COLISLY_Settings::get( 'free_storage_days', 15 ), 'colisly' ) ),
				(int) COLISLY_Settings::get( 'free_storage_days', 15 )
			);
			?>
		</td>
	</tr>
</table>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
