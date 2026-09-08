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
				<?php echo esc_html( ' — ' . COLISLY_Shipments::status_label( $shipment->status ) . ' — ' . COLISLY_Carriers::name( $shipment->carrier ) ); ?>
				<?php if ( $shipment->destination_country ) : ?>
					<?php echo esc_html( ' — ' . $shipment->destination_country ); ?>
				<?php endif; ?>
				<?php if ( $client ) : ?>
					· <a href="<?php echo esc_url( $client_url ); ?>"><?php echo esc_html( sprintf( /* translators: 1: client reference, 2: client name. */ __( 'Client record %1$s — %2$s', 'colisly' ), $client->reference, COLISLY_Clients::name( $client ) ) ); ?></a>
				<?php endif; ?>
			</p>

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
						<?php echo esc_html( ' — ' . number_format_i18n( (float) $parcel->weight, 3 ) . ' kg' . ( $dims ? ' — ' . $dims : '' ) ); ?>
						<?php if ( $parcel->tracking_number ) : ?>
							<?php echo esc_html( ' — ' . $parcel->tracking_number ); ?>
						<?php endif; ?>
						<?php if ( $items ) : ?>
							· <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=colisly_customs_form&parcel=' . (int) $parcel->id ), 'colisly_customs_form_' . (int) $parcel->id ) ); ?>" target="_blank"><?php esc_html_e( 'Customs form', 'colisly' ); ?></a>
						<?php endif; ?>
					</p>

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
											__( '%1$s x%2$d — %3$s — origin %4$s', 'colisly' ),
											$item->description,
											(int) $item->quantity,
											COLISLY_Format::price( $line_value ),
											$item->origin_country ? $item->origin_country : '—'
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
