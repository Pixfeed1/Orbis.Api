<?php
/**
 * Admin clients screens: list and client record ("fiche client").
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the clients list and the client record page.
 */
class GCP_Admin_Clients {

	/**
	 * Routes between the list view and the single client view.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$client_id = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;

		if ( $client_id ) {
			self::render_single( $client_id );
		} else {
			self::render_list();
		}
	}

	/**
	 * Renders the searchable clients list.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$term  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$per_page = 20;
		$clients  = GCP_Clients::paged_list( $term, $per_page, $paged );
		$total    = GCP_Clients::count( $term );
		?>
		<div class="wrap gcp-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'gestionnaire-colis-pro' ); ?></h1>
			<hr class="wp-header-end" />
			<?php GCP_Admin::maybe_notice(); ?>

			<form method="get" class="gcp-search-form">
				<input type="hidden" name="page" value="gcp-clients" />
				<p class="search-box">
					<label class="screen-reader-text" for="gcp-client-search"><?php esc_html_e( 'Rechercher un client', 'gestionnaire-colis-pro' ); ?></label>
					<input type="search" id="gcp-client-search" name="s" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Référence, nom, e-mail, téléphone…', 'gestionnaire-colis-pro' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Rechercher', 'gestionnaire-colis-pro' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Ajouter un client', 'gestionnaire-colis-pro' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcp-inline-form">
				<?php wp_nonce_field( 'gcp_create_client' ); ?>
				<input type="hidden" name="action" value="gcp_create_client" />
				<label for="gcp-new-client-user"><?php esc_html_e( 'Utilisateur WordPress', 'gestionnaire-colis-pro' ); ?></label>
				<?php
				wp_dropdown_users(
					array(
						'name'             => 'user_id',
						'id'               => 'gcp-new-client-user',
						'show'             => 'display_name_with_login',
						'number'           => 200,
						'show_option_none' => __( '— Sélectionner —', 'gestionnaire-colis-pro' ),
					)
				);
				?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Créer la fiche client', 'gestionnaire-colis-pro' ); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Référence', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Nom', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Téléphone', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Colis en stock', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Créé le', 'gestionnaire-colis-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $clients ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Aucun client trouvé.', 'gestionnaire-colis-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $clients as $client ) : ?>
							<?php
							$url = add_query_arg(
								array(
									'page'   => 'gcp-clients',
									'client' => (int) $client->id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td><a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $client->reference ); ?></strong></a></td>
								<td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $client->display_name ); ?></a></td>
								<td><?php echo esc_html( $client->user_email ); ?></td>
								<td><?php echo esc_html( $client->phone ); ?></td>
								<td><?php echo esc_html( number_format_i18n( count( GCP_Parcels::in_stock_for_client( (int) $client->id ) ) ) ); ?></td>
								<td><?php echo esc_html( GCP_Format::date( $client->created_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php self::pagination( $total, $per_page, $paged, compact( 'term' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the single client record with indicators and tabs.
	 *
	 * @param int $client_id Client ID.
	 * @return void
	 */
	private static function render_single( $client_id ) {
		$client = GCP_Clients::get( $client_id );

		if ( ! $client ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Client introuvable.', 'gestionnaire-colis-pro' ) . '</p></div>';
			return;
		}

		$user       = get_userdata( (int) $client->user_id );
		$indicators = GCP_Clients::indicators( $client_id );
		$in_stock   = GCP_Parcels::in_stock_for_client( $client_id );
		$shipped    = GCP_Parcels::shipped_for_client( $client_id );
		$shipments  = GCP_Shipments::for_client( $client_id );
		$documents  = GCP_Documents::for_client( $client_id );
		$history    = GCP_History::for_client( $client_id );

		$new_parcel_url = add_query_arg(
			array(
				'page'   => 'gcp-new-parcel',
				'client' => (int) $client->id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap gcp-wrap">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: 1: client reference, 2: client name. */
					esc_html__( 'Fiche client %1$s — %2$s', 'gestionnaire-colis-pro' ),
					esc_html( $client->reference ),
					esc_html( $user ? $user->display_name : '' )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $new_parcel_url ); ?>" class="page-title-action"><?php esc_html_e( 'Nouveau colis', 'gestionnaire-colis-pro' ); ?></a>
			<hr class="wp-header-end" />
			<?php GCP_Admin::maybe_notice(); ?>

			<div class="gcp-indicators">
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['parcels_in_stock'] ) ); ?></span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Colis en stock', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['weight_in_stock'], 3 ) ); ?> kg</span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Poids total stocké', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['shipments_count'] ) ); ?></span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Expéditions réalisées', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( GCP_Format::price( $indicators['storage_fees_due'] ) ); ?></span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Frais de stockage dus', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( $indicators['last_reception'] ? GCP_Format::date( $indicators['last_reception'] ) : '—' ); ?></span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Dernière réception', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="gcp-indicator">
					<span class="gcp-indicator-value"><?php echo esc_html( $indicators['last_shipment'] ? GCP_Format::date( $indicators['last_shipment'] ) : '—' ); ?></span>
					<span class="gcp-indicator-label"><?php esc_html_e( 'Dernière expédition', 'gestionnaire-colis-pro' ); ?></span>
				</div>
			</div>

			<h2 class="nav-tab-wrapper gcp-tabs">
				<a href="#gcp-tab-infos" class="nav-tab nav-tab-active"><?php esc_html_e( 'Informations', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#gcp-tab-stock" class="nav-tab"><?php esc_html_e( 'Colis en stock', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#gcp-tab-shipped" class="nav-tab"><?php esc_html_e( 'Colis expédiés', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#gcp-tab-shipments" class="nav-tab"><?php esc_html_e( 'Expéditions', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#gcp-tab-documents" class="nav-tab"><?php esc_html_e( 'Documents', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#gcp-tab-history" class="nav-tab"><?php esc_html_e( 'Historique', 'gestionnaire-colis-pro' ); ?></a>
			</h2>

			<div id="gcp-tab-infos" class="gcp-tab-panel gcp-tab-active">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'gcp_update_client_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="gcp_update_client" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Référence client', 'gestionnaire-colis-pro' ); ?></th>
							<td><code><?php echo esc_html( $client->reference ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Utilisateur', 'gestionnaire-colis-pro' ); ?></th>
							<td>
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a>
									(<?php echo esc_html( $user->user_email ); ?>)
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gcp-phone"><?php esc_html_e( 'Téléphone', 'gestionnaire-colis-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gcp-phone" name="phone" value="<?php echo esc_attr( $client->phone ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gcp-admin-notes"><?php esc_html_e( 'Notes internes (jamais visibles par le client)', 'gestionnaire-colis-pro' ); ?></label></th>
							<td><textarea id="gcp-admin-notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $client->admin_notes ); ?></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Enregistrer', 'gestionnaire-colis-pro' ) ); ?>
				</form>
			</div>

			<div id="gcp-tab-stock" class="gcp-tab-panel">
				<?php self::parcels_table( $in_stock, true ); ?>
			</div>

			<div id="gcp-tab-shipped" class="gcp-tab-panel">
				<?php self::parcels_table( $shipped, false ); ?>
			</div>

			<div id="gcp-tab-shipments" class="gcp-tab-panel">
				<?php self::shipments_table( $shipments ); ?>
			</div>

			<div id="gcp-tab-documents" class="gcp-tab-panel">
				<?php self::documents_table( $documents ); ?>
				<h3><?php esc_html_e( 'Ajouter un document', 'gestionnaire-colis-pro' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="gcp-inline-form">
					<?php wp_nonce_field( 'gcp_add_document_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="gcp_add_document" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<input type="file" name="gcp_document" required />
					<label>
						<input type="checkbox" name="visibility_client" value="1" checked />
						<?php esc_html_e( 'Visible par le client', 'gestionnaire-colis-pro' ); ?>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Ajouter', 'gestionnaire-colis-pro' ); ?></button>
				</form>
			</div>

			<div id="gcp-tab-history" class="gcp-tab-panel">
				<?php self::history_table( $history ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a parcels table.
	 *
	 * @param object[] $parcels     Parcel rows.
	 * @param bool     $with_action Whether to display the status action column.
	 * @return void
	 */
	private static function parcels_table( $parcels, $with_action ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Numéro', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Reçu le', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Suivi', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Tarif', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Regroupement', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Frais de stockage', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Commentaire interne', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Photo', 'gestionnaire-colis-pro' ); ?></th>
					<?php if ( $with_action ) : ?>
						<th><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $parcels ) ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'Aucun colis.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $parcels as $parcel ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
							<td><?php echo esc_html( GCP_Format::date( $parcel->received_at ) ); ?></td>
							<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
							<td><?php echo esc_html( GCP_Format::price( (float) $parcel->price ) ); ?></td>
							<td><?php echo $parcel->allow_grouping ? esc_html__( 'Oui', 'gestionnaire-colis-pro' ) : esc_html__( 'Non', 'gestionnaire-colis-pro' ); ?></td>
							<td><?php echo esc_html( GCP_Format::price( GCP_Storage::fees_for_parcel( $parcel ) ) ); ?></td>
							<td><?php echo esc_html( $parcel->internal_note ? $parcel->internal_note : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $parcel->photo_path ) ) : ?>
									<a href="<?php echo esc_url( GCP_Downloads::photo_url( $parcel ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Voir', 'gestionnaire-colis-pro' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<?php if ( $with_action ) : ?>
								<td><?php self::parcel_status_form( $parcel ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the inline status change form for a parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return void
	 */
	public static function parcel_status_form( $parcel ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcp-status-form">
			<?php wp_nonce_field( 'gcp_set_parcel_status_' . (int) $parcel->id ); ?>
			<input type="hidden" name="action" value="gcp_set_parcel_status" />
			<input type="hidden" name="parcel_id" value="<?php echo esc_attr( (string) $parcel->id ); ?>" />
			<label class="screen-reader-text" for="gcp-status-<?php echo esc_attr( (string) $parcel->id ); ?>"><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></label>
			<select name="status" id="gcp-status-<?php echo esc_attr( (string) $parcel->id ); ?>">
				<?php foreach ( GCP_Parcels::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $parcel->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'gestionnaire-colis-pro' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Renders the shipments table.
	 *
	 * @param object[] $shipments Shipment rows.
	 * @return void
	 */
	private static function shipments_table( $shipments ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Référence', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Demandée le', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Transporteur', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Frais de stockage', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Total', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $shipments ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Aucune expédition.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $shipments as $shipment ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
							<td><?php echo esc_html( GCP_Format::date( $shipment->requested_at ) ); ?></td>
							<td><?php echo esc_html( GCP_Carriers::name( $shipment->carrier ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
							<td><?php echo esc_html( GCP_Format::price( (float) $shipment->storage_fees ) ); ?></td>
							<td><?php echo esc_html( GCP_Format::price( (float) $shipment->total_price ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcp-status-form">
									<?php wp_nonce_field( 'gcp_set_shipment_status_' . (int) $shipment->id ); ?>
									<input type="hidden" name="action" value="gcp_set_shipment_status" />
									<input type="hidden" name="shipment_id" value="<?php echo esc_attr( (string) $shipment->id ); ?>" />
									<label class="screen-reader-text" for="gcp-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>"><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></label>
									<select name="status" id="gcp-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>">
										<?php foreach ( GCP_Shipments::statuses() as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $shipment->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'gestionnaire-colis-pro' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the documents table.
	 *
	 * @param object[] $documents Document rows.
	 * @return void
	 */
	private static function documents_table( $documents ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titre', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Ajouté le', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Visibilité', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Fichier', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $documents ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Aucun document.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $documents as $document ) : ?>
						<tr>
							<td><?php echo esc_html( $document->title ); ?></td>
							<td><?php echo esc_html( GCP_Format::date( $document->created_at ) ); ?></td>
							<td><?php echo 'admin' === $document->visibility ? esc_html__( 'Interne', 'gestionnaire-colis-pro' ) : esc_html__( 'Client', 'gestionnaire-colis-pro' ); ?></td>
							<td>
								<?php if ( ! empty( $document->file_path ) ) : ?>
									<a href="<?php echo esc_url( GCP_Downloads::document_url( $document ) ); ?>"><?php esc_html_e( 'Télécharger', 'gestionnaire-colis-pro' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the history table.
	 *
	 * @param object[] $entries History rows.
	 * @return void
	 */
	private static function history_table( $entries ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Événement', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Détail', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Par', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Aucune opération enregistrée.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $entries as $entry ) : ?>
						<?php $author = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null; ?>
						<tr>
							<td><?php echo esc_html( GCP_Format::date( $entry->created_at, true ) ); ?></td>
							<td><code><?php echo esc_html( $entry->event ); ?></code></td>
							<td><?php echo esc_html( (string) $entry->message ); ?></td>
							<td><?php echo esc_html( $author ? $author->display_name : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handles the "create client" form.
	 *
	 * @return void
	 */
	public static function handle_create() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		check_admin_referer( 'gcp_create_client' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$result  = GCP_Clients::create( $user_id );

		if ( is_wp_error( $result ) ) {
			GCP_Admin::redirect( 'gcp-clients', array(), $result->get_error_message(), 'error' );
		}

		GCP_Admin::redirect( 'gcp-clients', array( 'client' => (int) $result ), __( 'Fiche client créée.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles the "update client" form.
	 *
	 * @return void
	 */
	public static function handle_update() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'gcp_update_client_' . $client_id );

		GCP_Clients::update(
			$client_id,
			array(
				'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'admin_notes' => isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '',
			)
		);

		GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), __( 'Fiche client mise à jour.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles the "add document" form.
	 *
	 * @return void
	 */
	public static function handle_add_document() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'gcp_add_document_' . $client_id );

		$file = GCP_Files::upload( 'gcp_document', GCP_Files::document_mimes() );

		if ( is_wp_error( $file ) ) {
			GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), $file->get_error_message(), 'error' );
		}

		$visibility = empty( $_POST['visibility_client'] ) ? 'admin' : 'client';
		$result     = GCP_Documents::add( $client_id, $file, '', $visibility );

		if ( is_wp_error( $result ) ) {
			GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), __( 'Document ajouté.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles a shipment status change.
	 *
	 * @return void
	 */
	public static function handle_set_shipment_status() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		$shipment_id = isset( $_POST['shipment_id'] ) ? absint( $_POST['shipment_id'] ) : 0;

		check_admin_referer( 'gcp_set_shipment_status_' . $shipment_id );

		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$shipment = GCP_Shipments::get( $shipment_id );
		$result   = GCP_Shipments::set_status( $shipment_id, $status );

		$client_id = $shipment ? (int) $shipment->client_id : 0;

		if ( is_wp_error( $result ) ) {
			GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), __( 'Statut de l’expédition mis à jour.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Renders simple pagination links.
	 *
	 * @param int   $total    Total items.
	 * @param int   $per_page Items per page.
	 * @param int   $paged    Current page.
	 * @param array $extra    Extra args (term).
	 * @return void
	 */
	private static function pagination( $total, $per_page, $paged, $extra = array() ) {
		$pages = (int) ceil( $total / $per_page );

		if ( $pages < 2 ) {
			return;
		}

		$base = add_query_arg(
			array_filter(
				array(
					'page' => 'gcp-clients',
					's'    => isset( $extra['term'] ) ? $extra['term'] : '',
				)
			),
			admin_url( 'admin.php' )
		);

		echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			if ( $i === $paged ) {
				printf( '<span class="paging-input current">%s</span> ', esc_html( number_format_i18n( $i ) ) );
			} else {
				printf(
					'<a class="button" href="%1$s">%2$s</a> ',
					esc_url( add_query_arg( 'paged', $i, $base ) ),
					esc_html( number_format_i18n( $i ) )
				);
			}
		}
		echo '</span></div></div>';
	}
}
