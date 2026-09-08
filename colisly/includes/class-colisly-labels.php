<?php
/**
 * Printable parcel labels.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A label to stick on the parcel the moment it is booked in.
 *
 * The reference only exists once the parcel is saved, so the operator could
 * not label a carton with it while typing, and reached for a separate label
 * program and a per-client counter instead. The label is printed right after
 * saving, from the confirmation, and again from any parcel row: the
 * reference in large type, the client, the date, the weight, and the
 * internal comment for whatever the shelf system needs.
 */
class COLISLY_Labels {

	/**
	 * Builds the protected URL of a parcel label.
	 *
	 * @param object $parcel Parcel row.
	 * @return string
	 */
	public static function url( $parcel ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'colisly_parcel_label',
					'parcel' => (int) $parcel->id,
				),
				admin_url( 'admin-post.php' )
			),
			'colisly_parcel_label_' . (int) $parcel->id
		);
	}

	/**
	 * Builds the label HTML of a parcel.
	 *
	 * A standalone page, like the customs form: it goes to a label printer
	 * or a sheet, and the WordPress chrome would only get in the way.
	 *
	 * @param object $parcel Parcel row.
	 * @return string
	 */
	public static function html( $parcel ) {
		$client = COLISLY_Clients::get( (int) $parcel->client_id );
		$name   = $client ? COLISLY_Clients::name( $client ) : '';
		$dims   = ( (float) $parcel->length > 0 && (float) $parcel->width > 0 && (float) $parcel->height > 0 )
			? sprintf( '%s × %s × %s cm', number_format_i18n( (float) $parcel->length, 1 ), number_format_i18n( (float) $parcel->width, 1 ), number_format_i18n( (float) $parcel->height, 1 ) )
			: '';

		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<title><?php echo esc_html( sprintf( /* translators: %s: parcel reference. */ __( 'Label %s', 'colisly' ), $parcel->reference ) ); ?></title>
			<style>
				@page { size: 100mm 62mm; margin: 4mm; }
				body { color: #000; font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 12px; }
				.colisly-label { border: 2px solid #000; box-sizing: border-box; max-width: 100mm; padding: 6mm 5mm; }
				.colisly-label-ref { font-family: DejaVu Sans Mono, Consolas, monospace; font-size: 30pt; font-weight: bold; letter-spacing: .04em; line-height: 1; margin: 0 0 4mm; }
				.colisly-label-client { font-size: 14pt; font-weight: bold; margin: 0 0 1mm; }
				.colisly-label-meta { font-size: 10pt; margin: 0; }
				.colisly-label-note { border-top: 1px solid #000; font-size: 12pt; font-weight: bold; margin-top: 3mm; padding-top: 2mm; }
				.colisly-noprint { margin-bottom: 10px; }
				@media print { body { padding: 0; } .colisly-noprint { display: none; } .colisly-label { border-width: 1px; max-width: none; } }
			</style>
		</head>
		<body>
			<p class="colisly-noprint"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'colisly' ); ?></button></p>
			<div class="colisly-label">
				<p class="colisly-label-ref"><?php echo esc_html( $parcel->reference ); ?></p>
				<p class="colisly-label-client"><?php echo esc_html( $name ); ?><?php echo $client ? esc_html( ' · ' . $client->reference ) : ''; ?></p>
				<p class="colisly-label-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: reception date, 2: weight in kg. */
							__( 'Received %1$s — %2$s kg', 'colisly' ),
							COLISLY_Format::date( $parcel->received_at ),
							number_format_i18n( (float) $parcel->weight, 3 )
						)
					);
					if ( $dims ) {
						echo esc_html( ' — ' . $dims );
					}
					?>
				</p>
				<?php if ( $parcel->tracking_number ) : ?>
					<p class="colisly-label-meta"><?php echo esc_html( sprintf( /* translators: %s: tracking number. */ __( 'Tracking %s', 'colisly' ), $parcel->tracking_number ) ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $parcel->internal_note ) ) : ?>
					<p class="colisly-label-note"><?php echo esc_html( $parcel->internal_note ); ?></p>
				<?php endif; ?>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}
}
