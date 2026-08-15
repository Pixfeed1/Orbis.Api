<?php
/**
 * Native WooCommerce order integration for shipments.
 *
 * Each shipment request becomes a real WooCommerce order (one fee line per
 * parcel, a storage fee line and the carrier as shipping line). The customer
 * pays through the standard WooCommerce checkout, and the shipment status is
 * kept in sync with the order status in both directions.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates WooCommerce orders for shipments and syncs their statuses.
 */
class GCP_Orders {

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
	private static function shipment_id_from_order( $order ) {
		return (int) $order->get_meta( '_gcp_shipment_id' );
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
			return new WP_Error( 'gcp_wc_missing', __( 'WooCommerce n’est pas disponible.', 'gestionnaire-colis-pro' ) );
		}

		$order = wc_create_order(
			array(
				'customer_id' => (int) $client->user_id,
				'created_via' => 'gestionnaire-colis-pro',
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
		$shipping = array_filter( $customer->get_shipping() );
		if ( ! empty( $shipping ) ) {
			$order->set_address( $shipping, 'shipping' );
		}

		// One fee line per parcel, priced at reception time.
		foreach ( GCP_Shipments::parcels( (int) $shipment->id ) as $parcel ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name(
				sprintf(
					/* translators: 1: parcel reference, 2: weight in kg. */
					__( 'Colis %1$s (%2$s kg)', 'gestionnaire-colis-pro' ),
					$parcel->reference,
					wc_format_localized_decimal( (float) $parcel->weight )
				)
			);
			$fee->set_tax_status( 'none' );
			$fee->set_total( (string) $parcel->price );
			$order->add_item( $fee );
		}

		// Storage fees, when due.
		if ( (float) $shipment->storage_fees > 0 ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( __( 'Frais de stockage', 'gestionnaire-colis-pro' ) );
			$fee->set_tax_status( 'none' );
			$fee->set_total( (string) $shipment->storage_fees );
			$order->add_item( $fee );
		}

		// The chosen carrier appears as the native shipping line; its price
		// will come from the future carriers/pricing module.
		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( GCP_Carriers::name( $shipment->carrier ) );
		$shipping_item->set_method_id( 'gcp_carrier' );
		$shipping_item->set_total( '0' );
		$order->add_item( $shipping_item );

		$order->update_meta_data( '_gcp_shipment_id', (int) $shipment->id );
		$order->update_meta_data( '_gcp_shipment_reference', $shipment->reference );
		$order->add_order_note(
			sprintf(
				/* translators: %s: shipment reference. */
				__( 'Commande créée pour la demande d’expédition %s.', 'gestionnaire-colis-pro' ),
				$shipment->reference
			)
		);
		$order->calculate_totals( false );
		$order->update_status( 'pending' );
		$order->save();

		$order_id = (int) $order->get_id();

		$wpdb->update(
			$wpdb->prefix . 'gcp_shipments',
			array(
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $shipment->id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		GCP_History::log(
			(int) $shipment->client_id,
			'order_created',
			sprintf(
				/* translators: 1: order number, 2: shipment reference. */
				__( 'Commande WooCommerce n°%1$s créée pour l’expédition %2$s.', 'gestionnaire-colis-pro' ),
				$order->get_order_number(),
				$shipment->reference
			),
			0,
			(int) $shipment->id
		);

		// Native WooCommerce "customer invoice" e-mail, with the payment link.
		if ( GCP_Settings::get( 'send_invoice_on_request', 1 ) && function_exists( 'WC' ) && WC()->mailer() ) {
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
		do_action( 'gcp_shipment_order_created', $order_id, $shipment );

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

		$shipment = GCP_Shipments::get( $shipment_id );
		if ( ! $shipment || in_array( $shipment->status, array( 'paid', 'preparing', 'shipped' ), true ) ) {
			return;
		}

		self::$syncing = true;
		GCP_Shipments::set_status( $shipment_id, 'paid' );
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

		$shipment = GCP_Shipments::get( $shipment_id );
		if ( ! $shipment || 'shipped' === $shipment->status || 'cancelled' === $shipment->status ) {
			return;
		}

		self::$syncing = true;
		GCP_Shipments::set_status( $shipment_id, 'cancelled' );
		self::$syncing = false;
	}

	/**
	 * Reflects a shipment status change onto its WooCommerce order.
	 *
	 * Called by GCP_Shipments::set_status(); shipping the parcels completes
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
			$order->update_status( 'completed', sprintf( /* translators: %s: shipment reference. */ __( 'Expédition %s expédiée.', 'gestionnaire-colis-pro' ), $shipment->reference ) );
		} elseif ( 'cancelled' === $status && $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
			$order->update_status( 'cancelled', sprintf( /* translators: %s: shipment reference. */ __( 'Expédition %s annulée.', 'gestionnaire-colis-pro' ), $shipment->reference ) );
		}

		self::$syncing = false;
	}
}
