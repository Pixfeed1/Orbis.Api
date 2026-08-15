<?php
/**
 * WooCommerce My Account integration.
 *
 * @package GestionnaireColisPro
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
class GCP_Account {

	/**
	 * Endpoint slugs.
	 */
	const EP_PARCELS   = 'mes-colis';
	const EP_SHIPMENTS = 'mes-expeditions';
	const EP_DOCUMENTS = 'mes-documents';
	const EP_REQUEST   = 'demande-expedition';

	/**
	 * Hooks everything.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_endpoints' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ) );

		add_action( 'woocommerce_account_' . self::EP_PARCELS . '_endpoint', array( __CLASS__, 'render_parcels' ) );
		add_action( 'woocommerce_account_' . self::EP_SHIPMENTS . '_endpoint', array( __CLASS__, 'render_shipments' ) );
		add_action( 'woocommerce_account_' . self::EP_DOCUMENTS . '_endpoint', array( __CLASS__, 'render_documents' ) );
		add_action( 'woocommerce_account_' . self::EP_REQUEST . '_endpoint', array( __CLASS__, 'render_request' ) );

		add_action( 'template_redirect', array( __CLASS__, 'handle_request_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Registers the My Account rewrite endpoints.
	 *
	 * @return void
	 */
	public static function register_endpoints() {
		add_rewrite_endpoint( self::EP_PARCELS, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_SHIPMENTS, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_DOCUMENTS, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_REQUEST, EP_ROOT | EP_PAGES );
	}

	/**
	 * Registers the endpoint query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::EP_PARCELS;
		$vars[] = self::EP_SHIPMENTS;
		$vars[] = self::EP_DOCUMENTS;
		$vars[] = self::EP_REQUEST;

		return $vars;
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

		$items[ self::EP_PARCELS ]   = __( 'Mes colis', 'gestionnaire-colis-pro' );
		$items[ self::EP_SHIPMENTS ] = __( 'Mes expéditions', 'gestionnaire-colis-pro' );
		$items[ self::EP_DOCUMENTS ] = __( 'Mes documents', 'gestionnaire-colis-pro' );
		$items[ self::EP_REQUEST ]   = __( 'Demande d’expédition', 'gestionnaire-colis-pro' );

		return array_merge( $items, $logout );
	}

	/**
	 * Loads the front stylesheet on My Account pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'gcp-front', GCP_PLUGIN_URL . 'assets/css/front.css', array(), GCP_VERSION );
			wp_enqueue_script( 'gcp-front', GCP_PLUGIN_URL . 'assets/js/front.js', array(), GCP_VERSION, true );
			wp_localize_script(
				'gcp-front',
				'gcpFront',
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

		return GCP_Clients::get_by_user( get_current_user_id() );
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

		echo '<h2>' . esc_html__( 'Mes colis', 'gestionnaire-colis-pro' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'Aucune fiche client n’est associée à votre compte pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}

		printf(
			'<p>%s <code>%s</code></p>',
			esc_html__( 'Votre référence client :', 'gestionnaire-colis-pro' ),
			esc_html( $client->reference )
		);

		$parcels = GCP_Parcels::for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'Aucun colis pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}
		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive gcp-front-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Numéro du colis', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Date de réception', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Numéro de suivi', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Regroupement autorisé', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $parcels as $parcel ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Numéro du colis', 'gestionnaire-colis-pro' ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
						<td data-title="<?php esc_attr_e( 'Date de réception', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Format::date( $parcel->received_at ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Numéro de suivi', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
						<td data-title="<?php esc_attr_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Statut', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Parcels::status_label( $parcel->status ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Regroupement autorisé', 'gestionnaire-colis-pro' ); ?>"><?php echo $parcel->allow_grouping ? esc_html__( 'Oui', 'gestionnaire-colis-pro' ) : esc_html__( 'Non', 'gestionnaire-colis-pro' ); ?></td>
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

		echo '<h2>' . esc_html__( 'Mes expéditions', 'gestionnaire-colis-pro' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'Aucune fiche client n’est associée à votre compte pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}

		$shipments = GCP_Shipments::for_client( (int) $client->id );

		if ( empty( $shipments ) ) {
			echo '<p>' . esc_html__( 'Aucune expédition pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}
		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive gcp-front-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Référence', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Demandée le', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Transporteur', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Colis', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Total', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Paiement', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $shipments as $shipment ) : ?>
					<?php
					$refs  = wp_list_pluck( GCP_Shipments::parcels( (int) $shipment->id ), 'reference' );
					$order = ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $shipment->order_id ) : null;
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Référence', 'gestionnaire-colis-pro' ); ?>"><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
						<td data-title="<?php esc_attr_e( 'Demandée le', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Format::date( $shipment->requested_at ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Transporteur', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Carriers::name( $shipment->carrier ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Colis', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( implode( ', ', $refs ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Total', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Format::price( (float) $shipment->total_price ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Statut', 'gestionnaire-colis-pro' ); ?>"><?php echo esc_html( GCP_Shipments::status_label( $shipment->status ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Paiement', 'gestionnaire-colis-pro' ); ?>">
							<?php if ( $order && $order->needs_payment() ) : ?>
								<a class="woocommerce-button button pay" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Payer', 'gestionnaire-colis-pro' ); ?></a>
							<?php elseif ( $order ) : ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
									<?php
									printf(
										/* translators: %s: order number. */
										esc_html__( 'Commande n°%s', 'gestionnaire-colis-pro' ),
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

		echo '<h2>' . esc_html__( 'Mes documents', 'gestionnaire-colis-pro' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'Aucune fiche client n’est associée à votre compte pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}

		$documents = GCP_Documents::for_client( (int) $client->id, true );

		if ( empty( $documents ) ) {
			echo '<p>' . esc_html__( 'Aucun document pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}

		echo '<ul class="gcp-documents-list">';
		foreach ( $documents as $document ) {
			if ( empty( $document->file_path ) ) {
				continue;
			}
			printf(
				'<li><a href="%1$s">%2$s</a> <span class="gcp-doc-date">(%3$s)</span></li>',
				esc_url( GCP_Downloads::document_url( $document ) ),
				esc_html( $document->title ),
				esc_html( GCP_Format::date( $document->created_at ) )
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

		echo '<h2>' . esc_html__( 'Demande d’expédition', 'gestionnaire-colis-pro' ) . '</h2>';

		if ( ! $client ) {
			echo '<p>' . esc_html__( 'Aucune fiche client n’est associée à votre compte pour le moment.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}

		if ( ! empty( $_GET['gcp_requested'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( __( 'Votre demande d’expédition a bien été enregistrée. Nous revenons vers vous rapidement.', 'gestionnaire-colis-pro' ), 'success' );
		}

		if ( ! empty( $_GET['gcp_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			wc_print_notice( sanitize_text_field( wp_unslash( $_GET['gcp_error'] ) ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$parcels = GCP_Parcels::in_stock_for_client( (int) $client->id );

		if ( empty( $parcels ) ) {
			echo '<p>' . esc_html__( 'Aucun colis disponible pour une expédition.', 'gestionnaire-colis-pro' ) . '</p>';
			return;
		}
		?>
		<form method="post" class="gcp-request-form">
			<?php wp_nonce_field( 'gcp_request_shipment' ); ?>
			<input type="hidden" name="gcp_action" value="request_shipment" />

			<p><?php esc_html_e( 'Sélectionnez les colis à expédier :', 'gestionnaire-colis-pro' ); ?></p>
			<table class="woocommerce-orders-table shop_table shop_table_responsive gcp-front-table">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Numéro du colis', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Regroupement autorisé', 'gestionnaire-colis-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $parcels as $parcel ) : ?>
						<tr>
							<td>
								<input
									type="checkbox"
									name="gcp_parcels[]"
									value="<?php echo esc_attr( (string) $parcel->id ); ?>"
									id="gcp-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"
									data-grouping="<?php echo $parcel->allow_grouping ? '1' : '0'; ?>"
									data-carriers="<?php echo esc_attr( implode( ',', GCP_Parcels::allowed_carrier_slugs( $parcel ) ) ); ?>"
									data-weight="<?php echo esc_attr( (string) (float) $parcel->weight ); ?>"
									data-price="<?php echo esc_attr( (string) (float) $parcel->price ); ?>"
									data-storage="<?php echo esc_attr( (string) GCP_Storage::fees_for_parcel( $parcel ) ); ?>"
								/>
							</td>
							<td><label for="gcp-parcel-<?php echo esc_attr( (string) $parcel->id ); ?>"><strong><?php echo esc_html( $parcel->reference ); ?></strong></label></td>
							<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
							<td><?php echo $parcel->allow_grouping ? esc_html__( 'Oui', 'gestionnaire-colis-pro' ) : esc_html__( 'Non — ce colis doit être expédié seul', 'gestionnaire-colis-pro' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<label for="gcp-carrier"><?php esc_html_e( 'Transporteur souhaité :', 'gestionnaire-colis-pro' ); ?></label>
				<select name="gcp_carrier" id="gcp-carrier" required>
					<option value=""><?php esc_html_e( '— Sélectionner —', 'gestionnaire-colis-pro' ); ?></option>
					<?php foreach ( GCP_Carriers::all( true ) as $carrier ) : ?>
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
								esc_html__( '%1$s — %2$s + %3$s/kg', 'gestionnaire-colis-pro' ),
								esc_html( $carrier['name'] ),
								esc_html( GCP_Format::price( $base ) ),
								esc_html( GCP_Format::price( $rate ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p id="gcp-estimate" class="gcp-estimate" hidden>
				<strong><?php esc_html_e( 'Total estimé :', 'gestionnaire-colis-pro' ); ?></strong>
				<span id="gcp-estimate-amount"></span>
				<span class="gcp-note"><?php esc_html_e( '(colis + frais de stockage + transport — confirmé sur la page de paiement)', 'gestionnaire-colis-pro' ); ?></span>
			</p>
			<p class="gcp-note"><?php esc_html_e( 'Seuls les transporteurs compatibles avec l’ensemble des colis sélectionnés pourront être retenus.', 'gestionnaire-colis-pro' ); ?></p>

			<button type="submit" class="woocommerce-button button"><?php esc_html_e( 'Envoyer la demande', 'gestionnaire-colis-pro' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Processes the shipment request form.
	 *
	 * @return void
	 */
	public static function handle_request_submit() {
		if ( empty( $_POST['gcp_action'] ) || 'request_shipment' !== $_POST['gcp_action'] ) {
			return;
		}

		check_admin_referer( 'gcp_request_shipment' );

		$client = self::current_client();

		if ( ! $client ) {
			return;
		}

		$parcel_ids = isset( $_POST['gcp_parcels'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['gcp_parcels'] ) ) : array();
		$carrier    = isset( $_POST['gcp_carrier'] ) ? sanitize_key( wp_unslash( $_POST['gcp_carrier'] ) ) : '';

		$result = GCP_Shipments::request( (int) $client->id, $parcel_ids, $carrier );

		$url = wc_get_account_endpoint_url( self::EP_REQUEST );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'gcp_error', rawurlencode( $result->get_error_message() ), $url ) );
			exit;
		}

		// Send the customer straight to the native WooCommerce payment page.
		$shipment = GCP_Shipments::get( (int) $result );
		if ( $shipment && ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $shipment->order_id );
			if ( $order && $order->needs_payment() ) {
				wp_safe_redirect( $order->get_checkout_payment_url() );
				exit;
			}
		}

		wp_safe_redirect( add_query_arg( 'gcp_requested', '1', $url ) );
		exit;
	}
}
