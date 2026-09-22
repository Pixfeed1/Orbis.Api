<?php
/**
 * Native WooCommerce order integration for shipments.
 *
 * Each shipment request becomes a real WooCommerce order (one fee line per
 * parcel, a storage fee line and the carrier as shipping line). The customer
 * pays through the standard WooCommerce checkout, and the shipment status is
 * kept in sync with the order status in both directions.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates WooCommerce orders for shipments and syncs their statuses.
 */
class COLISLY_Orders {

	/**
	 * Re-entrancy guard for the two-way status sync.
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Hooks the WooCommerce side of the sync.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'order_cancelled' ) );

		// Label plugins take the parcel weight from the products, and a
		// shipment order has none. Colissimo Officiel filters its label
		// weight; the real weight of the shipment is handed to it there.
		add_filter( 'lpc_payload_letter_parcel_weight', array( __CLASS__, 'colissimo_label_weight' ), 10, 3 );
	}

	/**
	 * Weight of the shipment, in kg, for a label plugin that asks.
	 *
	 * @param WC_Order|int $order Order or order ID.
	 * @return float 0 when the order is not a shipment order.
	 */
	public static function shipment_weight( $order ) {
		$order = is_object( $order ) ? $order : wc_get_order( (int) $order );
		if ( ! $order ) {
			return 0.0;
		}

		$shipment_id = self::shipment_id_from_order( $order );
		$shipment    = $shipment_id ? COLISLY_Shipments::get( $shipment_id ) : null;

		return $shipment ? (float) $shipment->total_weight : 0.0;
	}

	/**
	 * Gives Colissimo Officiel the real weight of a shipment order.
	 *
	 * With no product line, that plugin only knows its packaging weight,
	 * and the operator had to type the parcels' weight on every label. When
	 * the weight it computed is nothing but that packaging, the shipment
	 * weight is added to it. A weight typed by hand on the label form is
	 * larger than that and is left alone; so is a return label.
	 *
	 * @param string|float $weight       Weight in kg as computed by the label plugin.
	 * @param string       $order_number Order number.
	 * @param bool         $is_return    Whether a return label is being generated.
	 * @return string|float
	 */
	public static function colissimo_label_weight( $weight, $order_number, $is_return = false ) {
		if ( $is_return || ! self::available() ) {
			return $weight;
		}

		$shipment_weight = self::shipment_weight( (int) $order_number );
		if ( $shipment_weight <= 0 ) {
			return $weight;
		}

		$packaging = (float) get_option( 'lpc_packaging_weight', '0' );
		$packaging = $packaging > 0 && function_exists( 'wc_get_weight' ) ? (float) wc_get_weight( $packaging, 'kg' ) : 0.0;

		if ( (float) $weight > $packaging + 0.011 ) {
			return $weight;
		}

		return number_format( $shipment_weight + $packaging, 2, '.', '' );
	}

	/**
	 * Whether WooCommerce order functions are available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'wc_create_order' );
	}

	/**
	 * Returns the shipment ID linked to an order, if any.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	public static function shipment_id_from_order( $order ) {
		$shipment_id = (int) $order->get_meta( '_colisly_shipment_id' );

		// Orders created before the prefix rename, not migrated yet.
		if ( ! $shipment_id ) {
			$shipment_id = (int) $order->get_meta( '_gcp_shipment_id' );
		}

		return $shipment_id;
	}

	/**
	 * Whether the tariffs typed in the settings are read as including tax.
	 *
	 * True when shipment orders carry the shop taxes and WooCommerce is set
	 * to "prices entered with tax". WooCommerce only honours that setting
	 * for products: a fee line is always taxed on top of its amount, so a
	 * forwarder who typed 15.00 with tax in would have billed 18.00.
	 *
	 * @return bool
	 */
	public static function tariffs_include_tax() {
		return (bool) COLISLY_Settings::get( 'orders_taxable', 0 )
			&& function_exists( 'wc_tax_enabled' ) && wc_tax_enabled()
			&& function_exists( 'wc_prices_include_tax' ) && wc_prices_include_tax();
	}

	/**
	 * The amount a taxable fee line is written as, from a tariff.
	 *
	 * With tariffs including tax, the shop's own base tax is taken out, the
	 * way WooCommerce does for a product price, and the order then adds the
	 * tax of the client's location: a French client pays the tariff as typed,
	 * a client the shop does not tax pays it net. Unrounded on purpose, as
	 * WooCommerce keeps inclusive prices, so the total lands on the cent.
	 *
	 * @param float $amount Tariff as typed in the settings.
	 * @return float
	 */
	public static function net_amount( $amount ) {
		$amount = (float) $amount;

		if ( $amount <= 0 || ! self::tariffs_include_tax() ) {
			return $amount;
		}

		$rates = WC_Tax::get_base_tax_rates( '' );
		if ( empty( $rates ) ) {
			return $amount;
		}

		return $amount - array_sum( WC_Tax::calc_inclusive_tax( $amount, $rates ) );
	}

	/**
	 * Gives the discount line the tax it takes off.
	 *
	 * WooCommerce apportions the tax of a negative fee over the products of
	 * the order, and a shipment order has none: the discount came out with
	 * no tax at all, so the tax line was that of the fees before discount.
	 * The discount is a share of the taxable fee lines it reduces, so it
	 * carries the same share of their taxes, rate by rate.
	 *
	 * @param WC_Order $order Order whose totals were just calculated.
	 * @return void
	 */
	private static function tax_discount_line( $order ) {
		if ( ! function_exists( 'wc_tax_enabled' ) || ! wc_tax_enabled() ) {
			return;
		}

		$discount = null;
		$base     = 0.0;
		$taxes    = array();

		foreach ( $order->get_fees() as $fee ) {
			if ( 'taxable' !== $fee->get_tax_status() ) {
				continue;
			}
			if ( (float) $fee->get_total() < 0 ) {
				$discount = $fee;
				continue;
			}
			$base += (float) $fee->get_total();
			$fee_taxes = $fee->get_taxes();
			foreach ( isset( $fee_taxes['total'] ) ? (array) $fee_taxes['total'] : array() as $rate_id => $amount ) {
				$taxes[ $rate_id ] = ( isset( $taxes[ $rate_id ] ) ? $taxes[ $rate_id ] : 0.0 ) + (float) $amount;
			}
		}

		if ( ! $discount || $base <= 0 || empty( $taxes ) ) {
			return;
		}

		$share = (float) $discount->get_total() / $base;
		foreach ( $taxes as $rate_id => $amount ) {
			$taxes[ $rate_id ] = wc_round_tax_total( $amount * $share );
		}

		$discount->set_taxes( array( 'total' => $taxes ) );
		$order->update_taxes();
		$order->calculate_totals( false );
	}

	/**
	 * Creates the WooCommerce order for a freshly requested shipment.
	 *
	 * @param object $shipment Shipment row.
	 * @param object $client   Client row.
	 * @return int|WP_Error Order ID.
	 */
	public static function create_for_shipment( $shipment, $client ) {
		global $wpdb;

		if ( ! self::available() ) {
			return new WP_Error( 'colisly_wc_missing', __( 'WooCommerce is not available.', 'colisly' ) );
		}

		$order = wc_create_order(
			array(
				'customer_id' => (int) $client->user_id,
				'created_via' => 'colisly',
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Billing address from the customer profile.
		$customer = new WC_Customer( (int) $client->user_id );
		$billing  = array_filter( $customer->get_billing() );
		if ( ! empty( $billing ) ) {
			$order->set_address( $billing, 'billing' );
		}

		// The delivery address is where the parcels are actually reshipped, so
		// it is resolved the same way the request form displays it. Reading
		// the shipping fields raw would have left the order with no address at
		// all for the many accounts that only ever filled the billing one, and
		// a forwarding order without a destination is worthless.
		$shipping = array_filter( COLISLY_Shipments::client_address( $client ) );
		if ( ! empty( $shipping ) ) {
			$order->set_address( $shipping, 'shipping' );
		}

		// Fees follow the shop tax setting chosen in the plugin settings.
		$tax_status = COLISLY_Settings::get( 'orders_taxable', 0 ) ? 'taxable' : 'none';

		// What a fee line is written as depends on how the shop enters its
		// prices, see net_amount(): a tariff typed with tax in has the tax
		// taken out here so WooCommerce can put the client's own back.
		$net = static function ( $amount ) {
			return self::net_amount( (float) $amount );
		};

		// One fee line per parcel, priced at reception time.
		foreach ( COLISLY_Shipments::parcels( (int) $shipment->id ) as $parcel ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name(
				sprintf(
					/* translators: 1: parcel reference, 2: weight in kg. */
					__( 'Parcel %1$s (%2$s kg)', 'colisly' ),
					$parcel->reference,
					wc_format_localized_decimal( (float) $parcel->weight )
				)
			);
			$fee->set_tax_status( $tax_status );
			$fee->set_total( (string) $net( $parcel->price ) );
			$order->add_item( $fee );

			// Duties or taxes the forwarder paid to take delivery of this
			// parcel, billed back at cost. Money passed through rather than
			// a service sold, so it carries no tax of its own unless the
			// shop decides otherwise.
			if ( (float) $parcel->advanced_fees > 0 ) {
				$fee = new WC_Order_Item_Fee();
				$fee->set_name(
					sprintf(
						/* translators: 1: what the fees were for, 2: parcel reference. */
						__( '%1$s advanced on parcel %2$s', 'colisly' ),
						COLISLY_Parcels::advanced_fees_label( $parcel ),
						$parcel->reference
					)
				);
				/**
				 * Filters whether fees advanced on a parcel are taxed on the order.
				 *
				 * @param bool   $taxable Default false: a disbursement, not a service.
				 * @param object $parcel  Parcel row.
				 */
				$fee->set_tax_status( apply_filters( 'colisly_advanced_fees_taxable', false, $parcel ) ? 'taxable' : 'none' );
				$fee->set_total( (string) $parcel->advanced_fees );
				$order->add_item( $fee );
			}
		}

		// Storage fees, when due.
		if ( (float) $shipment->storage_fees > 0 ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( __( 'Storage fees', 'colisly' ) );
			$fee->set_tax_status( $tax_status );
			$fee->set_total( (string) $net( $shipment->storage_fees ) );
			$order->add_item( $fee );
		}

		// Insurance, when the client took a cover level.
		if ( (float) $shipment->insurance_price > 0 ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name(
				sprintf(
					/* translators: %s: insured value. */
					__( 'Insurance (cover %s)', 'colisly' ),
					COLISLY_Format::price( (float) $shipment->insured_value )
				)
			);
			$fee->set_tax_status( $tax_status );
			$fee->set_total( (string) $net( $shipment->insurance_price ) );
			$order->add_item( $fee );
		}

		// The discount granted at request time, as a negative line named after
		// its reason, so the client reads on the order what he was told on the
		// form. Taxed like the handling fees it reduces.
		if ( (float) $shipment->discount > 0 ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( '' !== (string) $shipment->discount_label ? (string) $shipment->discount_label : __( 'Discount', 'colisly' ) );
			$fee->set_tax_status( $tax_status );
			$fee->set_total( '-' . (string) $net( $shipment->discount ) );
			$order->add_item( $fee );
		}

		// The chosen carrier appears as the native shipping line, priced from
		// the carrier tariff (base + per-kg) configured in the settings.
		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( COLISLY_Carriers::name( $shipment->carrier ) );
		$shipping_item->set_method_id( 'colisly_carrier' );
		$shipping_item->set_total( (string) $shipment->carrier_price );
		$order->add_item( $shipping_item );

		$order->update_meta_data( '_colisly_shipment_id', (int) $shipment->id );
		$order->update_meta_data( '_colisly_shipment_reference', $shipment->reference );
		// The weight of what actually ships, in kg, for whatever tool reads
		// order meta: a shipment order has no product to carry it.
		$order->update_meta_data( '_colisly_total_weight', number_format( (float) $shipment->total_weight, 3, '.', '' ) );
		$order->add_order_note(
			sprintf(
				/* translators: %s: shipment reference. */
				__( 'Order created for shipment request %s.', 'colisly' ),
				$shipment->reference
			)
		);
		$order->calculate_totals( (bool) COLISLY_Settings::get( 'orders_taxable', 0 ) );
		self::tax_discount_line( $order );
		$order->update_status( 'pending' );
		$order->save();

		$order_id = (int) $order->get_id();

		$wpdb->update(
			$wpdb->prefix . 'colisly_shipments',
			array(
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $shipment->id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		COLISLY_History::log(
			(int) $shipment->client_id,
			'order_created',
			sprintf(
				/* translators: 1: order number, 2: shipment reference. */
				__( 'WooCommerce order #%1$s created for shipment %2$s.', 'colisly' ),
				$order->get_order_number(),
				$shipment->reference
			),
			0,
			(int) $shipment->id
		);

		// Native WooCommerce "customer invoice" e-mail, with the payment link.
		if ( COLISLY_Settings::get( 'send_invoice_on_request', 1 ) && function_exists( 'WC' ) && WC()->mailer() ) {
			$emails = WC()->mailer()->get_emails();
			if ( isset( $emails['WC_Email_Customer_Invoice'] ) ) {
				$emails['WC_Email_Customer_Invoice']->trigger( $order_id );
			}
		}

		/**
		 * Fires after the WooCommerce order of a shipment has been created.
		 *
		 * @param int    $order_id Order ID.
		 * @param object $shipment Shipment row.
		 */
		do_action( 'colisly_shipment_order_created', $order_id, $shipment );

		return $order_id;
	}

	/**
	 * Marks the shipment paid when its order is paid.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function order_paid( $order_id ) {
		if ( self::$syncing ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$shipment_id = self::shipment_id_from_order( $order );
		if ( ! $shipment_id ) {
			return;
		}

		$shipment = COLISLY_Shipments::get( $shipment_id );
		if ( ! $shipment || in_array( $shipment->status, array( 'paid', 'preparing', 'shipped' ), true ) ) {
			return;
		}

		self::$syncing = true;
		COLISLY_Shipments::set_status( $shipment_id, 'paid' );
		self::$syncing = false;
	}

	/**
	 * Cancels the shipment when its order is cancelled (unless already shipped).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function order_cancelled( $order_id ) {
		if ( self::$syncing ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$shipment_id = self::shipment_id_from_order( $order );
		if ( ! $shipment_id ) {
			return;
		}

		$shipment = COLISLY_Shipments::get( $shipment_id );
		if ( ! $shipment || 'shipped' === $shipment->status || 'cancelled' === $shipment->status ) {
			return;
		}

		self::$syncing = true;
		COLISLY_Shipments::set_status( $shipment_id, 'cancelled' );
		self::$syncing = false;
	}

	/**
	 * Reflects a shipment status change onto its WooCommerce order.
	 *
	 * Called by COLISLY_Shipments::set_status(); shipping the parcels completes
	 * the order, cancelling the shipment cancels an unpaid order.
	 *
	 * @param object $shipment Shipment row (before update).
	 * @param string $status   New shipment status.
	 * @return void
	 */
	public static function sync_from_shipment( $shipment, $status ) {
		if ( self::$syncing || ! self::available() || empty( $shipment->order_id ) ) {
			return;
		}

		$order = wc_get_order( (int) $shipment->order_id );
		if ( ! $order ) {
			return;
		}

		self::$syncing = true;

		if ( 'shipped' === $status && ! $order->has_status( array( 'completed', 'cancelled', 'refunded' ) ) ) {
			$order->update_status( 'completed', sprintf( /* translators: %s: shipment reference. */ __( 'Shipment %s shipped.', 'colisly' ), $shipment->reference ) );
		} elseif ( 'cancelled' === $status && $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
			$order->update_status( 'cancelled', sprintf( /* translators: %s: shipment reference. */ __( 'Shipment %s cancelled.', 'colisly' ), $shipment->reference ) );
		}

		self::$syncing = false;
	}
}
