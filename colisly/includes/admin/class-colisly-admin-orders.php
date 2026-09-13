<?php
/**
 * Colisly panel on the WooCommerce order screen.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows, on the order a shipment created, what the operator needs to prepare
 * it: the parcels, what they declare, the invoices the client attached and
 * the customs form to print.
 *
 * The forwarder works from WooCommerce > Orders, since that is where payment
 * shows up. Until now everything about the shipment lived on the client
 * record, so preparing an order meant leaving the order to go and find it.
 */
class COLISLY_Admin_Orders {

	/**
	 * Hooks the meta box on both order storages.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ), 10, 2 );
	}

	/**
	 * Registers the meta box on the order screen, whatever storage is in use.
	 *
	 * @param string $screen Screen ID.
	 * @param mixed  $object Post or order being edited.
	 * @return void
	 */
	public static function register( $screen, $object = null ) {
		// Legacy post storage uses the post type; High-Performance Order
		// Storage uses its own screen id. Either way the box only exists on
		// an order a shipment created, so an ordinary shop order is untouched.
		$order_screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$order_screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		if ( ! in_array( $screen, $order_screens, true ) ) {
			return;
		}

		$order = self::order_from( $object );
		if ( ! $order || ! COLISLY_Orders::shipment_id_from_order( $order ) ) {
			return;
		}

		// The copy button needs the plugin script, which only loads on the
		// plugin's own screens otherwise.
		COLISLY_Admin::enqueue_assets( 'colisly-order' );

		add_meta_box(
			'colisly-shipment',
			__( 'Colisly shipment', 'colisly' ),
			array( __CLASS__, 'render' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Resolves the order from what the screen hands over.
	 *
	 * @param mixed $object WC_Order, WP_Post or null.
	 * @return WC_Order|null
	 */
	private static function order_from( $object ) {
		if ( $object instanceof WC_Order ) {
			return $object;
		}

		if ( $object instanceof WP_Post && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $object->ID );
			return $order instanceof WC_Order ? $order : null;
		}

		return null;
	}

	/**
	 * Meta box callback.
	 *
	 * @param mixed $object Post or order.
	 * @return void
	 */
	public static function render( $object ) {
		$order = self::order_from( $object );

		if ( $order ) {
			echo self::panel( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
		}
	}

	/**
	 * What a postage website asks for, one value per line, in its order.
	 *
	 * Forwarders on a per-weight contract print their labels on the
	 * carrier's own website and retype the order. This is the same data,
	 * ready to paste: name, address, postcode, city, country, phone,
	 * e-mail, then the weight of the shipment.
	 *
	 * @param WC_Order $order Order.
	 * @return string[]
	 */
	public static function carrier_lines( $order ) {
		$country   = (string) $order->get_shipping_country();
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
		$lines     = array(
			trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
			$order->get_shipping_company(),
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
			$order->get_shipping_postcode(),
			$order->get_shipping_city(),
			$order->get_shipping_state(),
			isset( $countries[ $country ] ) ? $countries[ $country ] : $country,
			$order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone(),
			$order->get_billing_email(),
		);

		$weight = COLISLY_Orders::shipment_weight( $order );
		if ( $weight > 0 ) {
			$lines[] = number_format( $weight, 3, '.', '' ) . ' kg';
		}

		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $lines ) ), 'strlen' ) );
	}

	/**
	 * Builds the panel HTML for an order, empty when no shipment is behind it.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function panel( $order ) {
		$shipment_id = COLISLY_Orders::shipment_id_from_order( $order );
		$shipment    = $shipment_id ? COLISLY_Shipments::get( $shipment_id ) : null;

		if ( ! $shipment ) {
			return '';
		}

		$client     = COLISLY_Clients::get( (int) $shipment->client_id );
		$client_url = add_query_arg(
			array(
				'page'   => 'colisly-clients',
				'client' => (int) $shipment->client_id,
			),
			admin_url( 'admin.php' )
		);

		ob_start();
		?>
		<div class="colisly-order-panel">
			<p>
				<strong><?php echo esc_html( $shipment->reference ); ?></strong>
				<?php echo esc_html( ' · ' . COLISLY_Shipments::status_label( $shipment->status ) . ' · ' . COLISLY_Carriers::name( $shipment->carrier ) ); ?>
				<?php if ( $shipment->destination_country ) : ?>
					<?php echo esc_html( ' · ' . $shipment->destination_country ); ?>
				<?php endif; ?>
				<?php if ( $client ) : ?>
					· <a href="<?php echo esc_url( $client_url ); ?>"><?php echo esc_html( sprintf( /* translators: 1: client reference, 2: client name. */ __( 'Client record %1$s, %2$s', 'colisly' ), $client->reference, COLISLY_Clients::name( $client ) ) ); ?></a>
				<?php endif; ?>
			</p>

			<?php $colisly_carrier_lines = self::carrier_lines( $order ); ?>
			<div class="colisly-order-copy">
				<p class="colisly-order-copy-title"><?php esc_html_e( 'For the carrier’s website', 'colisly' ); ?></p>
				<pre class="colisly-order-copy-lines"><?php echo esc_html( implode( "\n", $colisly_carrier_lines ) ); ?></pre>
				<p>
					<button type="button" class="button colisly-copy" data-colisly-copy="<?php echo esc_attr( implode( "\n", $colisly_carrier_lines ) ); ?>"><?php esc_html_e( 'Copy', 'colisly' ); ?></button>
					<span class="description"><?php esc_html_e( 'One value per line, in the order postage sites ask for them: name, address, postcode, city, country, phone, e-mail, weight.', 'colisly' ); ?></span>
				</p>
			</div>

			<?php foreach ( COLISLY_Shipments::parcels( (int) $shipment->id ) as $parcel ) : ?>
				<?php
				$items    = COLISLY_Customs::items( (int) $parcel->id );
				$invoices = COLISLY_Customs::invoices( (int) $parcel->id );
				$dims     = ( (float) $parcel->length > 0 && (float) $parcel->width > 0 && (float) $parcel->height > 0 )
					? sprintf( '%s × %s × %s cm', number_format_i18n( (float) $parcel->length, 1 ), number_format_i18n( (float) $parcel->width, 1 ), number_format_i18n( (float) $parcel->height, 1 ) )
					: '';
				?>
				<div class="colisly-order-parcel">
					<p>
						<strong><?php echo esc_html( $parcel->reference ); ?></strong>
						<?php echo esc_html( ' · ' . number_format_i18n( (float) $parcel->weight, 3 ) . ' kg' . ( $dims ? ' · ' . $dims : '' ) ); ?>
						<?php if ( $parcel->tracking_number ) : ?>
							<?php echo esc_html( ' · ' . $parcel->tracking_number ); ?>
						<?php endif; ?>
						<?php if ( $items ) : ?>
							· <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=colisly_customs_form&parcel=' . (int) $parcel->id ), 'colisly_customs_form_' . (int) $parcel->id ) ); ?>" target="_blank"><?php esc_html_e( 'Customs form', 'colisly' ); ?></a>
						<?php endif; ?>
					</p>

					<?php if ( (float) $parcel->advanced_fees > 0 ) : ?>
						<p class="colisly-order-advanced"><?php echo esc_html( COLISLY_Parcels::advanced_fees_text( $parcel ) ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== trim( (string) $parcel->internal_note ) ) : ?>
						<?php
						// What the operator wrote at reception, shelf, bin or
						// state of the carton: exactly what is needed to find
						// the parcel once the order is paid. Never shown to the
						// client; this panel is the operator's.
						?>
						<p class="colisly-order-note"><?php echo esc_html( sprintf( /* translators: %s: internal comment. */ __( 'Internal comment: %s', 'colisly' ), $parcel->internal_note ) ); ?></p>
					<?php endif; ?>

					<?php if ( $items ) : ?>
						<ul class="colisly-order-declaration">
							<?php
							$total = 0.0;
							foreach ( $items as $item ) :
								$line_value = (int) $item->quantity * (float) $item->unit_value;
								$total     += $line_value;
								?>
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: contents, 2: quantity, 3: total value of the line, 4: country of origin. */
											__( '%1$s x%2$d, %3$s, origin %4$s', 'colisly' ),
											$item->description,
											(int) $item->quantity,
											COLISLY_Format::price( $line_value ),
											$item->origin_country ? $item->origin_country : '–'
										)
									);
									?>
								</li>
							<?php endforeach; ?>
							<li><strong><?php echo esc_html( sprintf( /* translators: %s: total declared value. */ __( 'Total declared: %s', 'colisly' ), COLISLY_Format::price( $total ) ) ); ?></strong></li>
						</ul>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No customs declaration for this parcel.', 'colisly' ); ?></p>
					<?php endif; ?>

					<?php if ( $invoices ) : ?>
						<ul class="colisly-order-invoices">
							<?php foreach ( $invoices as $invoice ) : ?>
								<li><a href="<?php echo esc_url( COLISLY_Downloads::document_url( $invoice ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: file name. */ __( 'Invoice: %s', 'colisly' ), $invoice->file_name ? $invoice->file_name : $invoice->title ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No purchase invoice attached.', 'colisly' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
