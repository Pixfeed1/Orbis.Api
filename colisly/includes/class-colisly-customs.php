<?php
/**
 * Customs declaration of a parcel's contents.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores what a parcel contains and what it is worth, for the destinations
 * that require a declaration.
 *
 * Which destinations those are is not something the plugin can decide on its
 * own. Reshipping from mainland France to Guadeloupe needs a declaration even
 * though both are France, because the overseas departments sit outside the EU
 * VAT territory, while reshipping to Belgium needs none. Guessing that from a
 * country code would be wrong somewhere, and wrong in customs is expensive, so
 * the forwarder marks the zones that require one.
 */
class COLISLY_Customs {

	/**
	 * Returns the customs items table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'colisly_customs_items';
	}

	/**
	 * Whether a destination requires a customs declaration.
	 *
	 * @param string $country ISO country code.
	 * @return bool
	 */
	public static function required_for( $country ) {
		$zone = COLISLY_Zones::for_country( $country );

		return (bool) ( $zone && ! empty( $zone['customs'] ) );
	}

	/**
	 * Returns the declared items of a parcel, in the order they were entered.
	 *
	 * @param int $parcel_id Parcel ID.
	 * @return object[]
	 */
	public static function items( $parcel_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}colisly_customs_items WHERE parcel_id = %d ORDER BY position ASC, id ASC",
				(int) $parcel_id
			)
		);
	}

	/**
	 * Whether a parcel carries a usable declaration.
	 *
	 * One line with a description is enough to call it declared; the rest is
	 * the operator's problem, not a reason to block the client.
	 *
	 * @param int $parcel_id Parcel ID.
	 * @return bool
	 */
	public static function declared( $parcel_id ) {
		return ! empty( self::items( $parcel_id ) );
	}

	/**
	 * Returns the totals a customs form asks for.
	 *
	 * @param int $parcel_id Parcel ID.
	 * @return array { quantity: int, weight: float, value: float }
	 */
	public static function totals( $parcel_id ) {
		$quantity = 0;
		$weight   = 0.0;
		$value    = 0.0;

		foreach ( self::items( $parcel_id ) as $item ) {
			$quantity += (int) $item->quantity;
			$weight   += (int) $item->quantity * (float) $item->unit_weight;
			$value    += (int) $item->quantity * (float) $item->unit_value;
		}

		return array(
			'quantity' => $quantity,
			'weight'   => round( $weight, 3 ),
			'value'    => round( $value, 2 ),
		);
	}

	/**
	 * Replaces the declaration of a parcel with the given lines.
	 *
	 * The whole declaration is rewritten rather than patched line by line,
	 * because the form posts it whole and a half-applied declaration would be
	 * worse than none.
	 *
	 * @param int   $parcel_id Parcel ID.
	 * @param array $lines     Lines: description, quantity, unit_weight,
	 *                         unit_value, origin_country, hs_code.
	 * @return int|WP_Error Number of lines saved.
	 */
	public static function save( $parcel_id, $lines ) {
		global $wpdb;

		$parcel = COLISLY_Parcels::get( $parcel_id );
		if ( ! $parcel ) {
			return new WP_Error( 'colisly_parcel_not_found', __( 'Parcel not found.', 'colisly' ) );
		}

		$now   = current_time( 'mysql', true );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$description = isset( $line['description'] ) ? sanitize_text_field( $line['description'] ) : '';

			// A line without a description declares nothing, and the form
			// always posts one blank row for adding the next item.
			if ( '' === trim( $description ) ) {
				continue;
			}

			$clean[] = array(
				'description'    => $description,
				'quantity'       => max( 1, isset( $line['quantity'] ) ? (int) $line['quantity'] : 1 ),
				'unit_weight'    => max( 0, isset( $line['unit_weight'] ) ? COLISLY_Parcels::to_float( $line['unit_weight'] ) : 0 ),
				'unit_value'     => max( 0, isset( $line['unit_value'] ) ? COLISLY_Parcels::to_float( $line['unit_value'] ) : 0 ),
				'origin_country' => isset( $line['origin_country'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $line['origin_country'] ), 0, 2 ) ) : '',
				'hs_code'        => isset( $line['hs_code'] ) ? sanitize_text_field( $line['hs_code'] ) : '',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::table(), array( 'parcel_id' => (int) $parcel->id ), array( '%d' ) );

		foreach ( $clean as $position => $line ) {
			$line['parcel_id']  = (int) $parcel->id;
			$line['position']   = $position;
			$line['created_at'] = $now;
			$line['updated_at'] = $now;

			$wpdb->insert( self::table(), $line );
		}

		COLISLY_History::log(
			(int) $parcel->client_id,
			'customs_declared',
			sprintf(
				/* translators: 1: parcel reference, 2: number of declared lines. */
				_n(
					'Customs declaration of parcel %1$s updated: %2$d line.',
					'Customs declaration of parcel %1$s updated: %2$d lines.',
					count( $clean ),
					'colisly'
				),
				$parcel->reference,
				count( $clean )
			),
			(int) $parcel->id
		);

		/**
		 * Fires after a parcel's customs declaration has been saved.
		 *
		 * @param int   $parcel_id Parcel ID.
		 * @param array $lines     Lines actually stored.
		 */
		do_action( 'colisly_customs_saved', (int) $parcel->id, $clean );

		return count( $clean );
	}

	/**
	 * Outputs the printable customs declaration of a parcel.
	 *
	 * A standalone page rather than an admin screen: it is meant to be printed
	 * and folded into the pouch, so the WordPress chrome would only get in the
	 * way and waste a sheet.
	 *
	 * @param object $parcel Parcel row.
	 * @return void
	 */
	public static function render_form( $parcel ) {
		$client = COLISLY_Clients::get( (int) $parcel->client_id );
		$user   = $client ? get_userdata( (int) $client->user_id ) : null;
		$items  = self::items( (int) $parcel->id );
		$totals = self::totals( (int) $parcel->id );

		$shipping = array();
		if ( $client && class_exists( 'WC_Customer' ) ) {
			$customer = new WC_Customer( (int) $client->user_id );
			$shipping = array_filter( $customer->get_shipping() );
			if ( ! $shipping ) {
				$shipping = array_filter( $customer->get_billing() );
			}
		}

		$sender = array_filter(
			array(
				get_bloginfo( 'name' ),
				get_option( 'woocommerce_store_address' ),
				get_option( 'woocommerce_store_address_2' ),
				trim( get_option( 'woocommerce_store_postcode' ) . ' ' . get_option( 'woocommerce_store_city' ) ),
			)
		);

		$recipient = array_filter(
			array(
				trim( ( isset( $shipping['first_name'] ) ? $shipping['first_name'] : '' ) . ' ' . ( isset( $shipping['last_name'] ) ? $shipping['last_name'] : '' ) ),
				isset( $shipping['address_1'] ) ? $shipping['address_1'] : '',
				isset( $shipping['address_2'] ) ? $shipping['address_2'] : '',
				trim( ( isset( $shipping['postcode'] ) ? $shipping['postcode'] : '' ) . ' ' . ( isset( $shipping['city'] ) ? $shipping['city'] : '' ) ),
				isset( $shipping['country'] ) ? $shipping['country'] : '',
			)
		);

		if ( ! $recipient && $user ) {
			$recipient = array( $user->display_name );
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<title><?php echo esc_html( sprintf( /* translators: %s: parcel reference. */ __( 'Customs declaration %s', 'colisly' ), $parcel->reference ) ); ?></title>
			<style>
				body { color: #000; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; margin: 24px; }
				h1 { font-size: 18px; margin: 0 0 4px; }
				.colisly-sub { color: #444; margin: 0 0 18px; }
				.colisly-parties { display: flex; gap: 32px; margin-bottom: 18px; }
				.colisly-party { border: 1px solid #000; flex: 1; padding: 8px 10px; }
				.colisly-party h2 { font-size: 11px; letter-spacing: .06em; margin: 0 0 6px; text-transform: uppercase; }
				table { border-collapse: collapse; width: 100%; }
				th, td { border: 1px solid #000; padding: 5px 7px; text-align: left; }
				th { background: #eee; }
				td.num, th.num { text-align: right; }
				tfoot td { font-weight: bold; }
				.colisly-warn { border: 2px solid #000; font-weight: bold; margin-top: 14px; padding: 8px 10px; }
				.colisly-sign { margin-top: 26px; }
				.colisly-sign div { border-top: 1px solid #000; margin-top: 34px; padding-top: 4px; width: 240px; }
				@media print { body { margin: 0; } .colisly-noprint { display: none; } }
			</style>
		</head>
		<body>
			<p class="colisly-noprint"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'colisly' ); ?></button></p>

			<h1><?php esc_html_e( 'Customs declaration', 'colisly' ); ?></h1>
			<p class="colisly-sub">
				<?php
				printf(
					/* translators: 1: parcel reference, 2: tracking number. */
					esc_html__( 'Parcel %1$s — tracking %2$s', 'colisly' ),
					esc_html( $parcel->reference ),
					esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' )
				);
				?>
			</p>

			<div class="colisly-parties">
				<div class="colisly-party">
					<h2><?php esc_html_e( 'Sender', 'colisly' ); ?></h2>
					<?php echo wp_kses_post( implode( '<br />', array_map( 'esc_html', $sender ) ) ); ?>
				</div>
				<div class="colisly-party">
					<h2><?php esc_html_e( 'Recipient', 'colisly' ); ?></h2>
					<?php echo wp_kses_post( implode( '<br />', array_map( 'esc_html', $recipient ) ) ); ?>
				</div>
			</div>

			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Detailed description of contents', 'colisly' ); ?></th>
						<th class="num"><?php esc_html_e( 'Quantity', 'colisly' ); ?></th>
						<th class="num"><?php esc_html_e( 'Net weight (kg)', 'colisly' ); ?></th>
						<th class="num"><?php esc_html_e( 'Value', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'HS tariff number', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Country of origin', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item->description ); ?></td>
							<td class="num"><?php echo esc_html( (string) (int) $item->quantity ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $item->quantity * (float) $item->unit_weight, 3 ) ); ?></td>
							<td class="num"><?php echo esc_html( COLISLY_Format::price( (int) $item->quantity * (float) $item->unit_value ) ); ?></td>
							<td><?php echo esc_html( $item->hs_code ? $item->hs_code : '—' ); ?></td>
							<td><?php echo esc_html( $item->origin_country ? $item->origin_country : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $items ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nothing declared for this parcel.', 'colisly' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<td><?php esc_html_e( 'Total', 'colisly' ); ?></td>
						<td class="num"><?php echo esc_html( (string) $totals['quantity'] ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $totals['weight'], 3 ) ); ?></td>
						<td class="num"><?php echo esc_html( COLISLY_Format::price( $totals['value'] ) ); ?></td>
						<td colspan="2"></td>
					</tr>
					<tr>
						<td colspan="2"><?php esc_html_e( 'Gross weight of the parcel (kg)', 'colisly' ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
						<td colspan="3"></td>
					</tr>
				</tfoot>
			</table>

			<?php if ( $totals['weight'] > (float) $parcel->weight + 0.001 ) : ?>
				<p class="colisly-warn">
					<?php
					// Declared contents cannot weigh more than the parcel they
					// travel in; customs will stop on that before anything else.
					printf(
						/* translators: 1: declared net weight, 2: parcel gross weight. */
						esc_html__( 'Warning: the declared contents weigh %1$s kg, more than the parcel itself (%2$s kg). Check the declaration before shipping.', 'colisly' ),
						esc_html( number_format_i18n( $totals['weight'], 3 ) ),
						esc_html( number_format_i18n( (float) $parcel->weight, 3 ) )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="colisly-sign">
				<p><?php esc_html_e( 'I certify that the particulars given in this declaration are correct and that this item does not contain any dangerous article or article prohibited by legislation or by postal or customs regulations.', 'colisly' ); ?></p>
				<div><?php esc_html_e( 'Date and signature', 'colisly' ); ?></div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Removes the declaration of a parcel.
	 *
	 * @param int $parcel_id Parcel ID.
	 * @return void
	 */
	public static function delete_for_parcel( $parcel_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::table(), array( 'parcel_id' => (int) $parcel_id ), array( '%d' ) );
	}
}
