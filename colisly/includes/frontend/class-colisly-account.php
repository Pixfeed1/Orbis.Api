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
			'parcels'   => apply_filters( 'colisly_endpoint_parcels', sanitize_title( _x( 'my-parcels', 'My Account endpoint slug', 'colisly' ) ) ),
			'shipments' => apply_filters( 'colisly_endpoint_shipments', sanitize_title( _x( 'my-shipments', 'My Account endpoint slug', 'colisly' ) ) ),
			'documents' => apply_filters( 'colisly_endpoint_documents', sanitize_title( _x( 'my-documents', 'My Account endpoint slug', 'colisly' ) ) ),
			'request'   => apply_filters( 'colisly_endpoint_request', sanitize_title( _x( 'shipment-request', 'My Account endpoint slug', 'colisly' ) ) ),
			'customs'   => apply_filters( 'colisly_endpoint_customs', sanitize_title( _x( 'customs-declaration', 'My Account endpoint slug', 'colisly' ) ) ),
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
		add_action( 'template_redirect', array( __CLASS__, 'handle_customs_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_cancel_submit' ) );
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
		add_action( 'woocommerce_account_' . $endpoints['customs'] . '_endpoint', array( __CLASS__, 'render_customs' ) );
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

		$items[ $endpoints['parcels'] ]   = __( 'My parcels', 'colisly' );
		$items[ $endpoints['shipments'] ] = __( 'My shipments', 'colisly' );
		$items[ $endpoints['documents'] ] = __( 'My documents', 'colisly' );
		$items[ $endpoints['request'] ]   = __( 'Shipment request', 'colisly' );

		// The tab only appears when it is of any use, that is when at least one
		// destination the shop serves asks for a declaration.
		foreach ( COLISLY_Zones::all() as $colisly_zone ) {
			if ( ! empty( $colisly_zone['customs'] ) ) {
				$items[ $endpoints['customs'] ] = __( 'Customs declaration', 'colisly' );
				break;
			}
		}

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

		echo '<h2>' . esc_html__( 'My parcels', 'colisly' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
			return;
		}

		printf(
			'<p>%s <code>%s</code></p>',
			esc_html__( 'Your client reference:', 'colisly' ),
			esc_html( $client->reference )
		);

		$parcels = COLISLY_Parcels::for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'No parcels yet.', 'colisly' ) . '</p>';
			return;
		}
		?>
		<div class="colisly-table-wrap">
			<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parcel number', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Reception date', 'colisly' ); ?></th>
						<th class="colisly-col-tracking"><?php esc_html_e( 'Tracking number', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Status', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Grouping allowed', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $parcels as $parcel ) : ?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Parcel number', 'colisly' ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
							<td data-title="<?php esc_attr_e( 'Reception date', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Format::date( $parcel->received_at ) ); ?></td>
							<td class="colisly-col-tracking" data-title="<?php esc_attr_e( 'Tracking number', 'colisly' ); ?>"><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
							<td data-title="<?php esc_attr_e( 'Weight (kg)', 'colisly' ); ?>"><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Status', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Parcels::status_label( $parcel->status ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Grouping allowed', 'colisly' ); ?>"><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly' ) : esc_html__( 'No', 'colisly' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the client's shipments.
	 *
	 * @return void
	 */
	public static function render_shipments() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'My shipments', 'colisly' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
			return;
		}

		if ( ! empty( $_GET['colisly_cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( __( 'Your shipment request has been withdrawn and the parcels are back in your stock.', 'colisly' ), 'success' );
		}

		if ( ! empty( $_GET['colisly_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( sanitize_text_field( wp_unslash( $_GET['colisly_error'] ) ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$shipments = COLISLY_Shipments::for_client( (int) $client->id );

		if ( empty( $shipments ) ) {
			echo '<p>' . esc_html__( 'No shipments yet.', 'colisly' ) . '</p>';
			return;
		}
		?>
		<div class="colisly-table-wrap">
			<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reference', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Requested on', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Carrier', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Parcels', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Insured for', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Total', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Status', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $shipments as $shipment ) : ?>
						<?php
						$refs  = wp_list_pluck( COLISLY_Shipments::parcels( (int) $shipment->id ), 'reference' );
						$order = ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $shipment->order_id ) : null;
						?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Reference', 'colisly' ); ?>"><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
							<td data-title="<?php esc_attr_e( 'Requested on', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Format::date( $shipment->requested_at ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Carrier', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Carriers::name( $shipment->carrier ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Parcels', 'colisly' ); ?>"><?php echo esc_html( implode( ', ', $refs ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Weight (kg)', 'colisly' ); ?>"><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Insured for', 'colisly' ); ?>"><?php echo (float) $shipment->insured_value > 0 ? esc_html( COLISLY_Format::price( (float) $shipment->insured_value ) ) : '—'; ?></td>
							<td data-title="<?php esc_attr_e( 'Total', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Format::price( (float) $shipment->total_price ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Status', 'colisly' ); ?>"><?php echo esc_html( COLISLY_Shipments::status_label( $shipment->status ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Actions', 'colisly' ); ?>">
								<?php if ( $order && $order->needs_payment() ) : ?>
									<a class="woocommerce-button button pay" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay', 'colisly' ); ?></a>
								<?php elseif ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
										<?php
										printf(
											/* translators: %s: order number. */
											esc_html__( 'Order #%s', 'colisly' ),
											esc_html( $order->get_order_number() )
										);
										?>
									</a>
								<?php endif; ?>
								<?php if ( COLISLY_Shipments::client_can_cancel( $shipment ) ) : ?>
									<?php
									// A request the client changed his mind about used
									// to be a dead end: the only thing on offer was to
									// pay for it. Withdrawing it puts the parcels back
									// in stock and cancels the unpaid order with them.
									?>
									<form method="post" class="colisly-inline-form">
										<?php wp_nonce_field( 'colisly_cancel_shipment_' . (int) $shipment->id ); ?>
										<input type="hidden" name="colisly_action" value="cancel_shipment" />
										<input type="hidden" name="colisly_shipment" value="<?php echo esc_attr( (string) $shipment->id ); ?>" />
										<button
											type="submit"
											class="woocommerce-button button colisly-cancel"
											data-colisly-confirm="<?php esc_attr_e( 'Withdraw this shipment request? The parcels go back into your stock.', 'colisly' ); ?>"
										><?php esc_html_e( 'Cancel', 'colisly' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( ! $order && ! COLISLY_Shipments::client_can_cancel( $shipment ) ) : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the client's documents (client-visible only).
	 *
	 * @return void
	 */
	public static function render_documents() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'My documents', 'colisly' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
			return;
		}

		$documents = COLISLY_Documents::for_client( (int) $client->id, true );

		if ( empty( $documents ) ) {
			echo '<p>' . esc_html__( 'No documents yet.', 'colisly' ) . '</p>';
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

		echo '<h2>' . esc_html__( 'Shipment request', 'colisly' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
			return;
		}

		if ( ! empty( $_GET['colisly_requested'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( __( 'Your shipment request has been recorded. We will get back to you shortly.', 'colisly' ), 'success' );
		}

		if ( ! empty( $_GET['colisly_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( sanitize_text_field( wp_unslash( $_GET['colisly_error'] ) ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$parcels = COLISLY_Parcels::in_stock_for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'No parcels available for a shipment.', 'colisly' ) . '</p>';
			return;
		}

		// Nothing can be reshipped, let alone priced, before the account says
		// where to. The form used to ask for a destination country only, so a
		// request could reach the forwarder with no street to deliver to.
		$address     = COLISLY_Shipments::client_address( $client );
		$missing     = COLISLY_Shipments::address_missing_fields( $address );
		$address_url = function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' )
			? wc_get_endpoint_url( 'edit-address', 'shipping', wc_get_page_permalink( 'myaccount' ) )
			: '';

		if ( ! empty( $missing ) ) {
			wc_print_notice(
				sprintf(
					/* translators: %s: comma separated list of address fields. */
					esc_html__( 'Your delivery address is incomplete, so no shipment can be requested yet. Still missing: %s.', 'colisly' ),
					esc_html( implode( ', ', $missing ) )
				),
				'error'
			);

			if ( $address_url ) {
				printf(
					'<p><a class="woocommerce-button button" href="%s">%s</a></p>',
					esc_url( $address_url ),
					esc_html__( 'Complete my delivery address', 'colisly' )
				);
			}

			return;
		}
		?>
		<form method="post" class="colisly-request-form">
			<?php wp_nonce_field( 'colisly_request_shipment' ); ?>
			<input type="hidden" name="colisly_action" value="request_shipment" />

			<p><?php esc_html_e( 'Select the parcels to ship:', 'colisly' ); ?></p>
			<div class="colisly-table-wrap">
				<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
					<thead>
						<tr>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Include in the shipment', 'colisly' ); ?></span></th>
							<th><?php esc_html_e( 'Parcel number', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Grouping allowed', 'colisly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $parcels as $parcel ) : ?>
							<tr>
								<td data-title="<?php esc_attr_e( 'Include in the shipment', 'colisly' ); ?>">
									<input
										type="checkbox"
										name="colisly_parcels[]"
										value="<?php echo esc_attr( (string) $parcel->id ); ?>"
										id="colisly-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"
										data-grouping="<?php echo $parcel->allow_grouping ? '1' : '0'; ?>"
										data-carriers="<?php echo esc_attr( implode( ',', COLISLY_Parcels::allowed_carrier_slugs( $parcel ) ) ); ?>"
										data-weight="<?php echo esc_attr( (string) (float) $parcel->weight ); ?>"
										data-volume="<?php echo esc_attr( (string) ( (float) $parcel->length * (float) $parcel->width * (float) $parcel->height ) ); ?>"
										data-price="<?php echo esc_attr( (string) (float) $parcel->price ); ?>"
										data-storage="<?php echo esc_attr( (string) COLISLY_Storage::fees_for_parcel( $parcel ) ); ?>"
									/>
								</td>
								<td data-title="<?php esc_attr_e( 'Parcel number', 'colisly' ); ?>"><label for="colisly-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></label></td>
								<td data-title="<?php esc_attr_e( 'Weight (kg)', 'colisly' ); ?>"><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
								<td data-title="<?php esc_attr_e( 'Grouping allowed', 'colisly' ); ?>"><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly' ) : esc_html__( 'No — this parcel must be shipped alone', 'colisly' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php
			// The destination is the address, not a separate choice. A country
			// picked apart from it could be priced for Madagascar and printed
			// on a label bound for France, and only the label would be true.
			$formatted = function_exists( 'WC' ) && WC()->countries
				? WC()->countries->get_formatted_address( $address )
				: implode( ', ', array_filter( $address ) );
			?>
			<h3><?php esc_html_e( 'Delivered to', 'colisly' ); ?></h3>
			<address class="colisly-address"><?php echo wp_kses_post( $formatted ); ?></address>
			<input type="hidden" name="colisly_country" id="colisly-country" value="<?php echo esc_attr( $address['country'] ); ?>" />
			<p class="colisly-note">
				<?php esc_html_e( 'Your parcels are reshipped to this address, and the transport is priced on it.', 'colisly' ); ?>
				<?php if ( $address_url ) : ?>
					<a href="<?php echo esc_url( $address_url ); ?>"><?php esc_html_e( 'Change my delivery address', 'colisly' ); ?></a>
				<?php endif; ?>
			</p>
			<p>
				<label for="colisly-carrier"><?php esc_html_e( 'Preferred carrier:', 'colisly' ); ?></label>
				<select name="colisly_carrier" id="colisly-carrier" required>
					<option value=""><?php esc_html_e( '— Select —', 'colisly' ); ?></option>
					<?php foreach ( COLISLY_Carriers::all( true ) as $carrier ) : ?>
						<?php
						$base = isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0;
						$rate = isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0;

						// The estimate has to apply the same rule as the server.
						// Passing only base and rate made a carrier priced by
						// bracket show one figure here and charge another at
						// checkout, so the grid travels with the option.
						$grid = static function ( $rows ) {
							$out = array();
							foreach ( (array) $rows as $row ) {
								$out[] = array(
									'w' => (float) $row['max_weight'],
									'p' => (float) $row['price'],
								);
							}
							usort(
								$out,
								static function ( $a, $b ) {
									return $a['w'] <=> $b['w'];
								}
							);
							return $out;
						};

						$tiers = ! empty( $carrier['tiers'] ) && is_array( $carrier['tiers'] ) ? $grid( $carrier['tiers'] ) : array();

						// The estimate has to know every destination the client
						// could pick, so each zone grid is keyed by the countries
						// it covers rather than resolved server side.
						$by_country = array();
						foreach ( COLISLY_Zones::all() as $zone ) {
							if ( empty( $carrier['zone_tiers'][ $zone['slug'] ] ) ) {
								continue;
							}
							$zone_grid = $grid( $carrier['zone_tiers'][ $zone['slug'] ] );
							foreach ( $zone['countries'] as $code ) {
								$by_country[ $code ] = $zone_grid;
							}
						}
						?>
						<option
							value="<?php echo esc_attr( $carrier['slug'] ); ?>"
							data-base="<?php echo esc_attr( (string) $base ); ?>"
							data-rate="<?php echo esc_attr( (string) $rate ); ?>"
							data-tiers="<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>"
							data-zone-tiers="<?php echo esc_attr( wp_json_encode( $by_country ) ); ?>"
							data-volumetric="<?php echo empty( $carrier['volumetric'] ) ? '0' : '1'; ?>"
							data-divisor="<?php echo esc_attr( ! empty( $carrier['volumetric_divisor'] ) ? (string) (int) $carrier['volumetric_divisor'] : '5000' ); ?>"
						>
							<?php
							if ( $tiers ) {
								// Announcing "base + per kg" for a carrier billed
								// by bracket would state a price it never charges.
								echo esc_html( $carrier['name'] );
							} else {
								printf(
									/* translators: 1: carrier name, 2: base price, 3: price per kg. */
									esc_html__( '%1$s — %2$s + %3$s/kg', 'colisly' ),
									esc_html( $carrier['name'] ),
									esc_html( COLISLY_Format::price( $base ) ),
									esc_html( COLISLY_Format::price( $rate ) )
								);
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( COLISLY_Insurance::offered() ) : ?>
				<p>
					<label for="colisly-insurance"><?php esc_html_e( 'Insurance:', 'colisly' ); ?></label>
					<select name="colisly_insurance" id="colisly-insurance">
						<option value="0" data-price="0"><?php esc_html_e( 'No insurance', 'colisly' ); ?></option>
						<?php foreach ( COLISLY_Insurance::options() as $level ) : ?>
							<option value="<?php echo esc_attr( (string) $level['cover'] ); ?>" data-price="<?php echo esc_attr( (string) $level['price'] ); ?>">
								<?php
								printf(
									/* translators: 1: covered amount, 2: price of the cover. */
									esc_html__( 'Covered up to %1$s — %2$s', 'colisly' ),
									esc_html( COLISLY_Format::price( $level['cover'] ) ),
									esc_html( COLISLY_Format::price( $level['price'] ) )
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p id="colisly-estimate" class="colisly-estimate" hidden>
				<strong><?php esc_html_e( 'Estimated total:', 'colisly' ); ?></strong>
				<span id="colisly-estimate-amount"></span>
				<span class="colisly-note"><?php esc_html_e( '(parcels + storage fees + transport — confirmed on the payment page)', 'colisly' ); ?></span>
			</p>
			<?php
			// Declaring at the moment of the request is where it belongs: this
			// is when the client knows the parcel is leaving, and what for.
			// Only the destination it is actually leaving for decides, so a
			// client reshipping inside the EU is not asked to fill a form no
			// customs office will ever read.
			$needs_customs = COLISLY_Customs::required_for( $address['country'] );
			?>
			<?php if ( $needs_customs ) : ?>
				<h3><?php esc_html_e( 'Customs declaration', 'colisly' ); ?></h3>
				<p class="colisly-note"><?php esc_html_e( 'Required for some destinations. Declare the contents of the parcels you are sending; a parcel left undeclared will be refused if its destination asks for one.', 'colisly' ); ?></p>
				<?php foreach ( $parcels as $parcel ) : ?>
					<h4>
						<?php
						printf(
							/* translators: 1: parcel reference, 2: weight in kg. */
							esc_html__( 'Parcel %1$s (%2$s kg)', 'colisly' ),
							esc_html( $parcel->reference ),
							esc_html( number_format_i18n( (float) $parcel->weight, 3 ) )
						);
						?>
					</h4>
					<?php self::customs_lines_table( $parcel, 'colisly_customs[' . (int) $parcel->id . ']' ); ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<p class="colisly-note"><?php esc_html_e( 'Only carriers compatible with every selected parcel can be accepted.', 'colisly' ); ?></p>

			<button type="submit" class="woocommerce-button button"><?php esc_html_e( 'Send the request', 'colisly' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Withdraws a shipment request at the client's own initiative.
	 *
	 * @return void
	 */
	public static function handle_cancel_submit() {
		if ( empty( $_POST['colisly_action'] ) || 'cancel_shipment' !== $_POST['colisly_action'] ) {
			return;
		}

		$shipment_id = isset( $_POST['colisly_shipment'] ) ? absint( wp_unslash( $_POST['colisly_shipment'] ) ) : 0;

		check_admin_referer( 'colisly_cancel_shipment_' . $shipment_id );

		$client = self::current_client();

		if ( ! $client ) {
			return;
		}

		$url      = wc_get_account_endpoint_url( self::endpoint( 'shipments' ) );
		$shipment = COLISLY_Shipments::get( $shipment_id );

		// Somebody else's shipment is not reported as uncancellable, only as
		// absent: whether it exists is none of this client's business.
		if ( ! $shipment || (int) $shipment->client_id !== (int) $client->id ) {
			wp_safe_redirect( add_query_arg( 'colisly_error', rawurlencode( __( 'Shipment not found.', 'colisly' ) ), $url ) );
			exit;
		}

		if ( ! COLISLY_Shipments::client_can_cancel( $shipment ) ) {
			wp_safe_redirect(
				add_query_arg(
					'colisly_error',
					rawurlencode( __( 'This shipment can no longer be cancelled. Please get in touch with us.', 'colisly' ) ),
					$url
				)
			);
			exit;
		}

		$result = COLISLY_Shipments::set_status( $shipment_id, 'cancelled' );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'colisly_error', rawurlencode( $result->get_error_message() ), $url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'colisly_cancelled', '1', $url ) );
		exit;
	}

	/**
	 * Processes the shipment request form.
	 *
	 * @return void
	 */
	/**
	 * Renders the declaration lines of one parcel as an editable table.
	 *
	 * Shared by the dedicated tab and by the shipment request, so a client
	 * meets the same fields wherever he declares.
	 *
	 * @param object $parcel Parcel row.
	 * @param string $prefix Field name prefix, so several parcels can be
	 *                       posted from the same form.
	 * @return void
	 */
	private static function customs_lines_table( $parcel, $prefix ) {
		$categories = COLISLY_Customs::categories();
		$max        = COLISLY_Customs::max_lines();
		$items      = COLISLY_Customs::items( (int) $parcel->id );

		$ask_quantity = COLISLY_Customs::asks( 'quantity' );
		$ask_weight   = COLISLY_Customs::asks( 'weight' );
		$ask_origin   = COLISLY_Customs::asks( 'origin' );

		$blank = static function () {
			return (object) array(
				'description'    => '',
				'quantity'       => 1,
				'unit_weight'    => '',
				'unit_value'     => '',
				'origin_country' => '',
			);
		};

		/*
		 * The form offered exactly one blank line, so a cap of three lines was
		 * a promise it never kept: the client could only ever declare one item
		 * per submission, and on the request form there is no second
		 * submission. A cap now means the client is given that many lines.
		 */
		$rows = $items;

		if ( $max > 0 ) {
			while ( count( $rows ) < $max ) {
				$rows[] = $blank();
			}
		} else {
			// Uncapped, a handful of lines to start with and a button for the
			// rest, since there is no number that is right for every parcel.
			for ( $colisly_b = 0; $colisly_b < 3; $colisly_b++ ) {
				$rows[] = $blank();
			}
		}

		?>
		<div class="colisly-table-wrap">
			<table class="woocommerce-orders-table shop_table shop_table_responsive colisly-front-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Contents', 'colisly' ); ?></th>
						<?php if ( $ask_quantity ) : ?>
							<th><?php esc_html_e( 'Quantity', 'colisly' ); ?></th>
						<?php endif; ?>
						<?php if ( $ask_weight ) : ?>
							<th><?php esc_html_e( 'Unit weight (kg)', 'colisly' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Value', 'colisly' ); ?></th>
						<?php if ( $ask_origin ) : ?>
							<th><?php esc_html_e( 'Country of origin', 'colisly' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $k => $item ) : ?>
						<?php $name = $prefix . '[' . $k . ']'; ?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Contents', 'colisly' ); ?>">
								<?php if ( $categories ) : ?>
									<select name="<?php echo esc_attr( $name ); ?>[description]" aria-label="<?php esc_attr_e( 'Contents', 'colisly' ); ?>">
										<option value=""><?php esc_html_e( '— Select —', 'colisly' ); ?></option>
										<?php foreach ( $categories as $category ) : ?>
											<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $category, $item->description ); ?>><?php echo esc_html( $category ); ?></option>
										<?php endforeach; ?>
										<?php if ( '' !== $item->description && ! in_array( $item->description, $categories, true ) ) : ?>
											<option value="<?php echo esc_attr( $item->description ); ?>" selected><?php echo esc_html( $item->description ); ?></option>
										<?php endif; ?>
									</select>
								<?php else : ?>
									<input type="text" name="<?php echo esc_attr( $name ); ?>[description]" value="<?php echo esc_attr( $item->description ); ?>" placeholder="<?php esc_attr_e( 'Cotton t-shirts', 'colisly' ); ?>" aria-label="<?php esc_attr_e( 'Contents', 'colisly' ); ?>" />
								<?php endif; ?>
							</td>
							<?php if ( $ask_quantity ) : ?>
								<td data-title="<?php esc_attr_e( 'Quantity', 'colisly' ); ?>">
									<input type="number" min="1" step="1" name="<?php echo esc_attr( $name ); ?>[quantity]" value="<?php echo esc_attr( (string) $item->quantity ); ?>" aria-label="<?php esc_attr_e( 'Quantity', 'colisly' ); ?>" />
								</td>
							<?php endif; ?>
							<?php if ( $ask_weight ) : ?>
								<td data-title="<?php esc_attr_e( 'Unit weight (kg)', 'colisly' ); ?>">
									<input type="text" name="<?php echo esc_attr( $name ); ?>[unit_weight]" value="<?php echo esc_attr( (string) $item->unit_weight ); ?>" aria-label="<?php esc_attr_e( 'Unit weight (kg)', 'colisly' ); ?>" />
								</td>
							<?php endif; ?>
							<td data-title="<?php esc_attr_e( 'Value', 'colisly' ); ?>">
								<input type="text" name="<?php echo esc_attr( $name ); ?>[unit_value]" value="<?php echo esc_attr( (string) $item->unit_value ); ?>" aria-label="<?php esc_attr_e( 'Value', 'colisly' ); ?>" />
							</td>
							<?php if ( $ask_origin ) : ?>
								<td data-title="<?php esc_attr_e( 'Country of origin', 'colisly' ); ?>">
									<input type="text" maxlength="2" size="2" name="<?php echo esc_attr( $name ); ?>[origin_country]" value="<?php echo esc_attr( $item->origin_country ); ?>" placeholder="FR" aria-label="<?php esc_attr_e( 'Country of origin', 'colisly' ); ?>" />
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( 0 === $max ) : ?>
			<p>
				<button type="button" class="button colisly-add-customs-line"><?php esc_html_e( 'Add a line', 'colisly' ); ?></button>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the customs declaration screen.
	 *
	 * @return void
	 */
	public static function render_customs() {
		$client = self::current_client();

		echo '<h2>' . esc_html__( 'Customs declaration', 'colisly' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
			return;
		}

		$parcels = COLISLY_Parcels::in_stock_for_client( (int) $client->id );

		if ( ! $parcels ) {
			echo '<p>' . esc_html__( 'You have no parcel in stock to declare.', 'colisly' ) . '</p>';
			return;
		}

		// The columns the forwarder actually asks for are his choice, so the
		// wording cannot promise a quantity and a weight that may not be there.
		echo '<p>' . esc_html__( 'Some destinations require the contents of a parcel to be declared before it can be shipped. Describe what each parcel holds, item by item, filling every column shown.', 'colisly' ) . '</p>';

		foreach ( $parcels as $parcel ) :
			?>
			<form method="post" class="colisly-customs-form">
				<?php wp_nonce_field( 'colisly_save_customs_' . $parcel->id ); ?>
				<input type="hidden" name="colisly_action" value="save_customs" />
				<input type="hidden" name="parcel_id" value="<?php echo esc_attr( (string) $parcel->id ); ?>" />

				<h3>
					<?php
					printf(
						/* translators: 1: parcel reference, 2: weight in kg. */
						esc_html__( 'Parcel %1$s (%2$s kg)', 'colisly' ),
						esc_html( $parcel->reference ),
						esc_html( number_format_i18n( (float) $parcel->weight, 3 ) )
					);
					?>
				</h3>

				<?php self::customs_lines_table( $parcel, 'customs' ); ?>

				<p><button type="submit" class="woocommerce-button button"><?php esc_html_e( 'Save the declaration', 'colisly' ); ?></button></p>
			</form>
			<?php
		endforeach;
	}

	/**
	 * Saves a customs declaration posted from the account area.
	 *
	 * @return void
	 */
	public static function handle_customs_submit() {
		if ( empty( $_POST['colisly_action'] ) || 'save_customs' !== $_POST['colisly_action'] ) {
			return;
		}

		$parcel_id = isset( $_POST['parcel_id'] ) ? absint( $_POST['parcel_id'] ) : 0;

		check_admin_referer( 'colisly_save_customs_' . $parcel_id );

		$client = self::current_client();
		$parcel = COLISLY_Parcels::get( $parcel_id );

		// A declaration may only be written by the client the parcel belongs to.
		if ( ! $client || ! $parcel || (int) $parcel->client_id !== (int) $client->id ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		$lines = array();
		if ( isset( $_POST['customs'] ) && is_array( $_POST['customs'] ) ) {
			foreach ( wp_unslash( $_POST['customs'] ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in COLISLY_Customs::save().
				if ( is_array( $line ) ) {
					$lines[] = $line;
				}
			}
		}

		$saved = COLISLY_Customs::save( $parcel_id, $lines );

		$url = wc_get_account_endpoint_url( self::endpoint( 'customs' ) );

		wp_safe_redirect(
			add_query_arg(
				'colisly_customs',
				is_wp_error( $saved ) ? 'error' : 'saved',
				$url
			)
		);
		exit;
	}

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

		$insurance = isset( $_POST['colisly_insurance'] ) ? sanitize_text_field( wp_unslash( $_POST['colisly_insurance'] ) ) : 0;

		// The destination is read back from the account rather than from the
		// posted field, so a hand-crafted request cannot get itself priced for
		// a cheap zone and delivered to an expensive one.
		$address = COLISLY_Shipments::client_address( $client );
		$missing = COLISLY_Shipments::address_missing_fields( $address );
		$country = $address['country'];

		$url = wc_get_account_endpoint_url( self::endpoint( 'request' ) );

		if ( ! empty( $missing ) ) {
			wp_safe_redirect(
				add_query_arg(
					'colisly_error',
					rawurlencode(
						sprintf(
							/* translators: %s: comma separated list of address fields. */
							__( 'Your delivery address is incomplete, so no shipment can be requested yet. Still missing: %s.', 'colisly' ),
							implode( ', ', $missing )
						)
					),
					$url
				)
			);
			exit;
		}

		// Declarations are saved before the request is built, so the request
		// sees them and a parcel declared in the same submission is not
		// refused as undeclared.
		if ( isset( $_POST['colisly_customs'] ) && is_array( $_POST['colisly_customs'] ) ) {
			foreach ( wp_unslash( $_POST['colisly_customs'] ) as $colisly_pid => $colisly_lines ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in COLISLY_Customs::save().
				$colisly_pid    = absint( $colisly_pid );
				$colisly_parcel = COLISLY_Parcels::get( $colisly_pid );

				// Only the parcels of the client posting the form.
				if ( ! $colisly_parcel || (int) $colisly_parcel->client_id !== (int) $client->id ) {
					continue;
				}

				COLISLY_Customs::save( $colisly_pid, is_array( $colisly_lines ) ? $colisly_lines : array() );
			}
		}

		$result = COLISLY_Shipments::request( (int) $client->id, $parcel_ids, $carrier, $insurance, $country );

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
