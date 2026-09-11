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
	/**
	 * Returns the label format from the settings.
	 *
	 * Every label printer has its own size, and a 62 × 30 mm label, the
	 * common small one, has no room for anything but what finds the parcel.
	 * So the size is the forwarder's, and everything beyond the reference,
	 * the client, the date and the comment is opt-in.
	 *
	 * @return array { width: int, height: int, weight: bool, tracking: bool }
	 */
	public static function format() {
		return array(
			'width'    => min( 300, max( 20, (int) COLISLY_Settings::get( 'label_width', 62 ) ) ),
			'height'   => min( 300, max( 10, (int) COLISLY_Settings::get( 'label_height', 30 ) ) ),
			'weight'   => (bool) COLISLY_Settings::get( 'label_show_weight', 0 ),
			'tracking' => (bool) COLISLY_Settings::get( 'label_show_tracking', 0 ),
		);
	}

	/**
	 * Builds the label HTML of a parcel.
	 *
	 * A standalone page, like the customs form: it goes to a label printer
	 * or a sheet, and the WordPress chrome would only get in the way. Type
	 * sizes follow the label height, so the reference stays the biggest
	 * thing on a 30 mm label as on a 62 mm one.
	 *
	 * @param object $parcel Parcel row.
	 * @return string
	 */
	public static function html( $parcel ) {
		$client = COLISLY_Clients::get( (int) $parcel->client_id );
		$name   = $client ? COLISLY_Clients::name( $client ) : '';
		$format = self::format();
		$dims   = ( (float) $parcel->length > 0 && (float) $parcel->width > 0 && (float) $parcel->height > 0 )
			? sprintf( '%s × %s × %s cm', number_format_i18n( (float) $parcel->length, 1 ), number_format_i18n( (float) $parcel->width, 1 ), number_format_i18n( (float) $parcel->height, 1 ) )
			: '';

		// Millimetres, derived from the height: the reference takes about a
		// quarter of it, the rest shares what is left.
		$h        = $format['height'];
		$ref_size = round( max( 5, min( 16, $h * 0.26 ) ), 1 );
		$txt_size = round( max( 2.6, min( 5, $h * 0.11 ) ), 1 );
		$pad      = round( max( 1, min( 4, $h * 0.06 ) ), 1 );

		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<title><?php echo esc_html( sprintf( /* translators: %s: parcel reference. */ __( 'Label %s', 'colisly' ), $parcel->reference ) ); ?></title>
			<style>
				@page { size: <?php echo esc_html( $format['width'] . 'mm ' . $format['height'] . 'mm' ); ?>; margin: 0; }
				html, body { margin: 0; padding: 0; }
				body { color: #000; font-family: DejaVu Sans, Arial, sans-serif; }
				.colisly-label { box-sizing: border-box; height: <?php echo esc_html( $format['height'] ); ?>mm; overflow: hidden; padding: <?php echo esc_html( $pad ); ?>mm; width: <?php echo esc_html( $format['width'] ); ?>mm; }
				.colisly-label p { margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
				.colisly-label-ref { font-family: DejaVu Sans Mono, Consolas, monospace; font-size: <?php echo esc_html( $ref_size ); ?>mm; font-weight: bold; letter-spacing: .02em; line-height: 1.05; }
				.colisly-label-client { font-size: <?php echo esc_html( $txt_size ); ?>mm; font-weight: bold; line-height: 1.25; }
				.colisly-label-meta { font-size: <?php echo esc_html( $txt_size ); ?>mm; line-height: 1.25; }
				.colisly-label-note { font-size: <?php echo esc_html( $txt_size ); ?>mm; font-weight: bold; line-height: 1.25; }
				.colisly-noprint { margin: 8px; }
				@media screen { body { padding: 8px; } .colisly-label { outline: 1px dashed #999; } }
				@media print { .colisly-noprint { display: none; } }
			</style>
		</head>
		<body>
			<p class="colisly-noprint"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'colisly' ); ?></button></p>
			<div class="colisly-label">
				<p class="colisly-label-ref"><?php echo esc_html( $parcel->reference ); ?></p>
				<p class="colisly-label-client"><?php echo esc_html( $name ); ?><?php echo $client ? esc_html( ' · ' . $client->reference ) : ''; ?></p>
				<p class="colisly-label-meta">
					<?php
					echo esc_html( sprintf( /* translators: %s: reception date. */ __( 'Received %s', 'colisly' ), COLISLY_Format::date( $parcel->received_at ) ) );
					if ( $format['weight'] ) {
						echo esc_html( ' · ' . number_format_i18n( (float) $parcel->weight, 3 ) . ' kg' . ( $dims ? ' · ' . $dims : '' ) );
					}
					?>
				</p>
				<?php if ( $format['tracking'] && $parcel->tracking_number ) : ?>
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
