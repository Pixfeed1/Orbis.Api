<?php
/**
 * WooCommerce My Account integration.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client-facing area: parcels, shipments, documents and shipment requests.
 *
 * The client only ever sees the information meant for them: internal notes,
 * dimensions and admin documents are never rendered here.
 */
class COLISLY_Account {

	/**
	 * Returns the My Account endpoint slugs.
	 *
	 * The defaults are translatable (French sites get the historical
	 * mes-colis/mes-expeditions/… URLs through the fr_FR translation) and each
	 * slug is filterable so a site can pin its own.
	 *
	 * @return array Map of endpoint key => slug.
	 */
	public static function endpoints() {
		return array(
			/**
			 * Filters a My Account endpoint slug of the plugin.
			 *
			 * @param string $slug Sanitized endpoint slug.
			 */
			'parcels'   => apply_filters( 'colisly_endpoint_parcels', sanitize_title( _x( 'my-parcels', 'My Account endpoint slug', 'colisly-parcel-forwarding' ) ) ),
			'shipments' => apply_filters( 'colisly_endpoint_shipments', sanitize_title( _x( 'my-shipments', 'My Account endpoint slug', 'colisly-parcel-forwarding' ) ) ),
			'documents' => apply_filters( 'colisly_endpoint_documents', sanitize_title( _x( 'my-documents', 'My Account endpoint slug', 'colisly-parcel-forwarding' ) ) ),
			'request'   => apply_filters( 'colisly_endpoint_request', sanitize_title( _x( 'shipment-request', 'My Account endpoint slug', 'colisly-parcel-forwarding' ) ) ),
		);
	}

	/**
	 * Returns one endpoint slug.
	 *
	 * @param string $key Endpoint key (parcels, shipments, documents, request).
	 * @return string
	 */
	public static function endpoint( $key ) {
		$endpoints = self::endpoints();

		return isset( $endpoints[ $key ] ) ? $endpoints[ $key ] : '';
	}

	/**
	 * Hooks everything.
	 *
	 * @return void
	 */
	public static function init() {
		// Endpoints (and their content hooks) are registered on init, after the
		// text domain has loaded, so translated slugs are taken into account.
		add_action( 'init', array( __CLASS__, 'register_endpoints' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ) );

		add_action( 'template_redirect', array( __CLASS__, 'handle_request_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Registers the My Account rewrite endpoints and their render hooks.
	 *
	 * @return void
	 */
	public static function register_endpoints() {
		$endpoints = self::endpoints();

		foreach ( $endpoints as $slug ) {
			add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES );
		}

		add_action( 'woocommerce_account_' . $endpoints['parcels'] . '_endpoint', array( __CLASS__, 'render_parcels' ) );
		add_action( 'woocommerce_account_' . $endpoints['shipments'] . '_endpoint', array( __CLASS__, 'render_shipments' ) );
		add_action( 'woocommerce_account_' . $endpoints['documents'] . '_endpoint', array( __CLASS__, 'render_documents' ) );
		add_action( 'woocommerce_account_' . $endpoints['request'] . '_endpoint', array( __CLASS__, 'render_request' ) );
	}

	/**
	 * Registers the endpoint query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		return array_merge( $vars, array_values( self::endpoints() ) );
	}

	/**
	 * Adds the plugin entries to the My Account menu.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu_items( $items ) {
		$logout = isset( $items['customer-logout'] ) ? array( 'customer-logout' => $items['customer-logout'] ) : array();
		unset( $items['customer-logout'] );

		$endpoints = self::endpoints();

		$items[ $endpoints['parcels'] ]   = __( 'My parcels', 'colisly-parcel-forwarding' );
		$items[ $endpoints['shipments'] ] = __( 'My shipments', 'colisly-parcel-forwarding' );
		$items[ $endpoints['documents'] ] = __( 'My documents', 'colisly-parcel-forwarding' );
		$items[ $endpoints['request'] ]   = __( 'Shipment request', 'colisly-parcel-forwarding' );

		return array_merge( $items, $logout );
	}

	/**
	 * Loads the front stylesheet on My Account pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'colisly-front', COLISLY_PLUGIN_URL . 'assets/css/front.css', array(), COLISLY_VERSION );
			wp_enqueue_script( 'colisly-front', COLISLY_PLUGIN_URL . 'assets/js/front.js', array(), COLISLY_VERSION, true );
			wp_localize_script(
				'colisly-front',
				'colislyFront',
				array(
					'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '€',
				)
			);
		}
	}

	/**
	 * Returns the client record of the current user, if any.
	 *
	 * @return object|null
	 */
	private static function current_client() {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		return COLISLY_Clients::get_by_user( get_current_user_id() );
	}

	/**
	 * Renders the client's parcels.
	 *
	 * Only client-safe fields are shown: reference, reception date, tracking
	 * number, weight, status and grouping permission.
	 *
	 * @return void
	 */
	public static function render_parcels() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'My parcels', 'colisly-parcel-forwarding' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}

		printf(
			'<p>%s <code>%s</code></p>',
			esc_html__( 'Your client reference:', 'colisly-parcel-forwarding' ),
			esc_html( $client->reference )
		);

		$parcels = COLISLY_Parcels::for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'No parcels yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}
		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Parcel number', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Reception date', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Tracking number', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Weight (kg)', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Status', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Grouping allowed', 'colisly-parcel-forwarding' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $parcels as $parcel ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Parcel number', 'colisly-parcel-forwarding' ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
						<td data-title="<?php esc_attr_e( 'Reception date', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Format::date( $parcel->received_at ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Tracking number', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
						<td data-title="<?php esc_attr_e( 'Weight (kg)', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Status', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Parcels::status_label( $parcel->status ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Grouping allowed', 'colisly-parcel-forwarding' ); ?>"><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly-parcel-forwarding' ) : esc_html__( 'No', 'colisly-parcel-forwarding' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the client's shipments.
	 *
	 * @return void
	 */
	public static function render_shipments() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'My shipments', 'colisly-parcel-forwarding' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}

		$shipments = COLISLY_Shipments::for_client( (int) $client->id );

		if ( empty( $shipments ) ) {
			echo '<p>' . esc_html__( 'No shipments yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}
		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reference', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Requested on', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Parcels', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Weight (kg)', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Total', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Status', 'colisly-parcel-forwarding' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'colisly-parcel-forwarding' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $shipments as $shipment ) : ?>
					<?php
					$refs  = wp_list_pluck( COLISLY_Shipments::parcels( (int) $shipment->id ), 'reference' );
					$order = ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $shipment->order_id ) : null;
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Reference', 'colisly-parcel-forwarding' ); ?>"><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
						<td data-title="<?php esc_attr_e( 'Requested on', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Format::date( $shipment->requested_at ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Carrier', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Carriers::name( $shipment->carrier ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Parcels', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( implode( ', ', $refs ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Weight (kg)', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Total', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Format::price( (float) $shipment->total_price ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Status', 'colisly-parcel-forwarding' ); ?>"><?php echo esc_html( COLISLY_Shipments::status_label( $shipment->status ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Payment', 'colisly-parcel-forwarding' ); ?>">
							<?php if ( $order && $order->needs_payment() ) : ?>
								<a class="woocommerce-button button pay" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay', 'colisly-parcel-forwarding' ); ?></a>
							<?php elseif ( $order ) : ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
									<?php
									printf(
										/* translators: %s: order number. */
										esc_html__( 'Order #%s', 'colisly-parcel-forwarding' ),
										esc_html( $order->get_order_number() )
									);
									?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the client's documents (client-visible only).
	 *
	 * @return void
	 */
	public static function render_documents() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'My documents', 'colisly-parcel-forwarding' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}

		$documents = COLISLY_Documents::for_client( (int) $client->id, true );

		if ( empty( $documents ) ) {
			echo '<p>' . esc_html__( 'No documents yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}

		echo '<ul class="colisly-documents-list">';
		foreach ( $documents as $document ) {
			if ( empty( $document->file_path ) ) {
				continue;
			}
			printf(
				'<li><a href="%1$s">%2$s</a> <span class="colisly-doc-date">(%3$s)</span></li>',
				esc_url( COLISLY_Downloads::document_url( $document ) ),
				esc_html( $document->title ),
				esc_html( COLISLY_Format::date( $document->created_at ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Renders the shipment request form.
	 *
	 * @return void
	 */
	public static function render_request() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'Shipment request', 'colisly-parcel-forwarding' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}

		if ( ! empty( $_GET['colisly_requested'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( __( 'Your shipment request has been recorded. We will get back to you shortly.', 'colisly-parcel-forwarding' ), 'success' );
		}

		if ( ! empty( $_GET['colisly_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( sanitize_text_field( wp_unslash( $_GET['colisly_error'] ) ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$parcels = COLISLY_Parcels::in_stock_for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'No parcels available for a shipment.', 'colisly-parcel-forwarding' ) . '</p>';
			return;
		}
		?>
		<form method="post" class="colisly-request-form">
			<?php wp_nonce_field( 'colisly_request_shipment' ); ?>
			<input type="hidden" name="colisly_action" value="request_shipment" />

			<p><?php esc_html_e( 'Select the parcels to ship:', 'colisly-parcel-forwarding' ); ?></p>
			<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Parcel number', 'colisly-parcel-forwarding' ); ?></th>
						<th><?php esc_html_e( 'Weight (kg)', 'colisly-parcel-forwarding' ); ?></th>
						<th><?php esc_html_e( 'Grouping allowed', 'colisly-parcel-forwarding' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $parcels as $parcel ) : ?>
						<tr>
							<td>
								<input
									type="checkbox"
									name="colisly_parcels[]"
									value="<?php echo esc_attr( (string) $parcel->id ); ?>"
									id="colisly-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"
									data-grouping="<?php echo $parcel->allow_grouping ? '1' : '0'; ?>"
									data-carriers="<?php echo esc_attr( implode( ',', COLISLY_Parcels::allowed_carrier_slugs( $parcel ) ) ); ?>"
									data-weight="<?php echo esc_attr( (string) (float) $parcel->weight ); ?>"
									data-price="<?php echo esc_attr( (string) (float) $parcel->price ); ?>"
									data-storage="<?php echo esc_attr( (string) COLISLY_Storage::fees_for_parcel( $parcel ) ); ?>"
								/>
							</td>
							<td><label for="colisly-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></label></td>
							<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
							<td><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly-parcel-forwarding' ) : esc_html__( 'No — this parcel must be shipped alone', 'colisly-parcel-forwarding' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<label for="colisly-carrier"><?php esc_html_e( 'Preferred carrier:', 'colisly-parcel-forwarding' ); ?></label>
				<select name="colisly_carrier" id="colisly-carrier" required>
					<option value=""><?php esc_html_e( '— Select —', 'colisly-parcel-forwarding' ); ?></option>
					<?php foreach ( COLISLY_Carriers::all( true ) as $carrier ) : ?>
						<?php
						$base = isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0;
						$rate = isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0;
						?>
						<option
							value="<?php echo esc_attr( $carrier['slug'] ); ?>"
							data-base="<?php echo esc_attr( (string) $base ); ?>"
							data-rate="<?php echo esc_attr( (string) $rate ); ?>"
						>
							<?php
							printf(
								/* translators: 1: carrier name, 2: base price, 3: price per kg. */
								esc_html__( '%1$s — %2$s + %3$s/kg', 'colisly-parcel-forwarding' ),
								esc_html( $carrier['name'] ),
								esc_html( COLISLY_Format::price( $base ) ),
								esc_html( COLISLY_Format::price( $rate ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p id="colisly-estimate" class="colisly-estimate" hidden>
				<strong><?php esc_html_e( 'Estimated total:', 'colisly-parcel-forwarding' ); ?></strong>
				<span id="colisly-estimate-amount"></span>
				<span class="colisly-note"><?php esc_html_e( '(parcels + storage fees + transport — confirmed on the payment page)', 'colisly-parcel-forwarding' ); ?></span>
			</p>
			<p class="colisly-note"><?php esc_html_e( 'Only carriers compatible with every selected parcel can be accepted.', 'colisly-parcel-forwarding' ); ?></p>

			<button type="submit" class="woocommerce-button button"><?php esc_html_e( 'Send the request', 'colisly-parcel-forwarding' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Processes the shipment request form.
	 *
	 * @return void
	 */
	public static function handle_request_submit() {
		if ( empty( $_POST['colisly_action'] ) || 'request_shipment' !== $_POST['colisly_action'] ) {
			return;
		}

		check_admin_referer( 'colisly_request_shipment' );

		$client = self::current_client();

		if ( ! $client ) {
			return;
		}

		$parcel_ids = isset( $_POST['colisly_parcels'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['colisly_parcels'] ) ) : array();
		$carrier    = isset( $_POST['colisly_carrier'] ) ? sanitize_key( wp_unslash( $_POST['colisly_carrier'] ) ) : '';

		$result = COLISLY_Shipments::request( (int) $client->id, $parcel_ids, $carrier );

		$url = wc_get_account_endpoint_url( self::endpoint( 'request' ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'colisly_error', rawurlencode( $result->get_error_message() ), $url ) );
			exit;
		}

		// Send the customer straight to the native WooCommerce payment page.
		$shipment = COLISLY_Shipments::get( (int) $result );
		if ( $shipment && ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $shipment->order_id );
			if ( $order && $order->needs_payment() ) {
				wp_safe_redirect( $order->get_checkout_payment_url() );
				exit;
			}
		}

		wp_safe_redirect( add_query_arg( 'colisly_requested', '1', $url ) );
		exit;
	}
}
