<?php
/**
 * Parcel received e-mail (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/gcp-parcel-received.php.
 *
 * @package GestionnaireColisPro
 * @var object   $parcel             Parcel row.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Extra content from the e-mail settings.
 * @var WC_Email $email              E-mail object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Bonjour,', 'gestionnaire-colis-pro' ); ?></p>

<p>
	<?php
	printf(
		/* translators: %s: parcel reference. */
		esc_html__( 'Nous avons bien réceptionné votre colis %s à l’entrepôt. Il est désormais visible dans votre espace client, rubrique « Mes colis ».', 'gestionnaire-colis-pro' ),
		'<strong>' . esc_html( $parcel->reference ) . '</strong>'
	);
	?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse;">
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Numéro du colis', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( $parcel->reference ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Numéro de suivi', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Poids', 'gestionnaire-colis-pro' ); ?></th>
		<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?> kg</td>
	</tr>
	<tr>
		<th scope="row" style="text-align: left;"><?php esc_html_e( 'Stockage gratuit', 'gestionnaire-colis-pro' ); ?></th>
		<td>
			<?php
			printf(
				/* translators: %d: number of free storage days. */
				esc_html( _n( '%d jour', '%d jours', (int) GCP_Settings::get( 'free_storage_days', 15 ), 'gestionnaire-colis-pro' ) ),
				(int) GCP_Settings::get( 'free_storage_days', 15 )
			);
			?>
		</td>
	</tr>
</table>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
