<?php
/**
 * Admin parcels screens: list and creation form.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the parcels list and the parcel creation form.
 */
class GCP_Admin_Parcels {

	/**
	 * Renders the parcels list with search and status filter.
	 *
	 * @return void
	 */
	public static function render_list() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$result = GCP_Parcels::paged_list(
			array(
				'search'   => $search,
				'status'   => $status,
				'per_page' => 20,
				'paged'    => $paged,
			)
		);
		?>
		<div class="wrap gcp-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Colis', 'gestionnaire-colis-pro' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gcp-new-parcel' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Nouveau colis', 'gestionnaire-colis-pro' ); ?></a>
			<hr class="wp-header-end" />
			<?php GCP_Admin::maybe_notice(); ?>

			<form method="get" class="gcp-search-form">
				<input type="hidden" name="page" value="gcp-parcels" />
				<p class="search-box">
					<label class="screen-reader-text" for="gcp-parcel-search"><?php esc_html_e( 'Rechercher un colis', 'gestionnaire-colis-pro' ); ?></label>
					<input type="search" id="gcp-parcel-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'N° colis, suivi, client…', 'gestionnaire-colis-pro' ); ?>" />
					<label class="screen-reader-text" for="gcp-parcel-status"><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></label>
					<select name="status" id="gcp-parcel-status">
						<option value=""><?php esc_html_e( 'Tous les statuts', 'gestionnaire-colis-pro' ); ?></option>
						<?php foreach ( GCP_Parcels::statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'gestionnaire-colis-pro' ); ?></button>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Numéro', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Client', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Reçu le', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Suivi', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Tarif', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Regroupement', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'gestionnaire-colis-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'Aucun colis trouvé.', 'gestionnaire-colis-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $parcel ) : ?>
							<?php
							$client_url = add_query_arg(
								array(
									'page'   => 'gcp-clients',
									'client' => (int) $parcel->client_id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
								<td>
									<a href="<?php echo esc_url( $client_url ); ?>">
										<?php echo esc_html( $parcel->client_reference . ' — ' . $parcel->display_name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( GCP_Format::date( $parcel->received_at ) ); ?></td>
								<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
								<td><?php echo esc_html( GCP_Format::price( (float) $parcel->price ) ); ?></td>
								<td><?php echo $parcel->allow_grouping ? esc_html__( 'Oui', 'gestionnaire-colis-pro' ) : esc_html__( 'Non', 'gestionnaire-colis-pro' ); ?></td>
								<td><?php GCP_Admin_Clients::parcel_status_form( $parcel ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$pages = (int) ceil( $result['total'] / 20 );
			if ( $pages > 1 ) :
				?>
				<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">
					<?php
					$base = add_query_arg(
						array_filter(
							array(
								'page'   => 'gcp-parcels',
								's'      => $search,
								'status' => $status,
							)
						),
						admin_url( 'admin.php' )
					);
					for ( $i = 1; $i <= $pages; $i++ ) {
						if ( $i === $paged ) {
							printf( '<span class="paging-input current">%s</span> ', esc_html( number_format_i18n( $i ) ) );
						} else {
							printf( '<a class="button" href="%1$s">%2$s</a> ', esc_url( add_query_arg( 'paged', $i, $base ) ), esc_html( number_format_i18n( $i ) ) );
						}
					}
					?>
				</span></div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the parcel creation form.
	 *
	 * @return void
	 */
	public static function render_new() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill.
		$preselected = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;
		$client      = $preselected ? GCP_Clients::get( $preselected ) : null;
		$client_user = $client ? get_userdata( (int) $client->user_id ) : null;
		?>
		<div class="wrap gcp-wrap">
			<h1><?php esc_html_e( 'Nouveau colis', 'gestionnaire-colis-pro' ); ?></h1>
			<?php GCP_Admin::maybe_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="gcp-new-parcel-form">
				<?php wp_nonce_field( 'gcp_create_parcel' ); ?>
				<input type="hidden" name="action" value="gcp_create_parcel" />

				<h2><?php esc_html_e( '1. Client', 'gestionnaire-colis-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gcp-client-search-input"><?php esc_html_e( 'Rechercher le client', 'gestionnaire-colis-pro' ); ?></label></th>
						<td>
							<input
								type="text"
								id="gcp-client-search-input"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Référence (CL000001), nom ou e-mail…', 'gestionnaire-colis-pro' ); ?>"
								autocomplete="off"
								value="<?php echo esc_attr( $client ? $client->reference . ' — ' . ( $client_user ? $client_user->display_name : '' ) : '' ); ?>"
							/>
							<input type="hidden" name="client_id" id="gcp-client-id" value="<?php echo esc_attr( $client ? (string) $client->id : '' ); ?>" required />
							<div id="gcp-client-results" class="gcp-client-results" role="listbox"></div>
							<p class="description"><?php esc_html_e( 'Recherche par référence interne, nom ou adresse e-mail.', 'gestionnaire-colis-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="gcp-client-stock">
					<?php if ( $client ) : ?>
						<h3><?php esc_html_e( 'Colis de ce client encore en stock', 'gestionnaire-colis-pro' ); ?></h3>
						<?php $stock = GCP_Parcels::in_stock_for_client( (int) $client->id ); ?>
						<table class="wp-list-table widefat fixed striped gcp-stock-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Numéro du colis', 'gestionnaire-colis-pro' ); ?></th>
									<th><?php esc_html_e( 'Poids (kg)', 'gestionnaire-colis-pro' ); ?></th>
									<th><?php esc_html_e( 'Regroupement autorisé', 'gestionnaire-colis-pro' ); ?></th>
									<th><?php esc_html_e( 'Commentaire interne', 'gestionnaire-colis-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $stock ) ) : ?>
									<tr><td colspan="4"><?php esc_html_e( 'Aucun colis en stock pour ce client.', 'gestionnaire-colis-pro' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $stock as $parcel ) : ?>
										<tr>
											<td><?php echo esc_html( $parcel->reference ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
											<td><?php echo $parcel->allow_grouping ? esc_html__( 'Oui', 'gestionnaire-colis-pro' ) : esc_html__( 'Non', 'gestionnaire-colis-pro' ); ?></td>
											<td><?php echo esc_html( $parcel->internal_note ? $parcel->internal_note : '—' ); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<h2><?php esc_html_e( '2. Informations du colis', 'gestionnaire-colis-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gcp-tracking"><?php esc_html_e( 'Numéro de suivi du transporteur', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><input type="text" id="gcp-tracking" name="tracking_number" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gcp-weight"><?php esc_html_e( 'Poids réel (kg)', 'gestionnaire-colis-pro' ); ?> <span class="description">(<?php esc_html_e( 'obligatoire', 'gestionnaire-colis-pro' ); ?>)</span></label></th>
						<td><input type="number" id="gcp-weight" name="weight" step="0.001" min="0.001" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dimensions (cm, visibles uniquement par les administrateurs)', 'gestionnaire-colis-pro' ); ?></th>
						<td class="gcp-dimensions">
							<label for="gcp-length"><?php esc_html_e( 'Longueur', 'gestionnaire-colis-pro' ); ?></label>
							<input type="number" id="gcp-length" name="length" step="0.01" min="0" />
							<label for="gcp-width"><?php esc_html_e( 'Largeur', 'gestionnaire-colis-pro' ); ?></label>
							<input type="number" id="gcp-width" name="width" step="0.01" min="0" />
							<label for="gcp-height"><?php esc_html_e( 'Hauteur', 'gestionnaire-colis-pro' ); ?></label>
							<input type="number" id="gcp-height" name="height" step="0.01" min="0" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gcp-photo"><?php esc_html_e( 'Photo du colis à la réception (facultatif)', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><input type="file" id="gcp-photo" name="gcp_photo" accept="image/*" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gcp-note"><?php esc_html_e( 'Commentaire interne (jamais visible par le client)', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><textarea id="gcp-note" name="internal_note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Emballage endommagé, colis fragile, anomalie…', 'gestionnaire-colis-pro' ); ?>"></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( '3. Règles d’expédition', 'gestionnaire-colis-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Regroupement', 'gestionnaire-colis-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_grouping" value="1" checked />
								<?php esc_html_e( 'Autoriser le regroupement de ce colis avec d’autres colis', 'gestionnaire-colis-pro' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Si décoché, ce colis devra obligatoirement être expédié seul. Cette décision n’est jamais modifiable par le client.', 'gestionnaire-colis-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Transporteurs autorisés', 'gestionnaire-colis-pro' ); ?></th>
						<td>
							<?php foreach ( GCP_Carriers::all( true ) as $carrier ) : ?>
								<label class="gcp-carrier-choice">
									<input type="checkbox" name="allowed_carriers[]" value="<?php echo esc_attr( $carrier['slug'] ); ?>" checked />
									<?php echo esc_html( $carrier['name'] ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Décochez les transporteurs incompatibles (hors gabarit, restrictions…). Seuls les transporteurs cochés seront proposés au client.', 'gestionnaire-colis-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="description">
					<?php esc_html_e( 'Le tarif du colis est calculé automatiquement à partir de son poids dès l’enregistrement, puis utilisé lors de la demande d’expédition.', 'gestionnaire-colis-pro' ); ?>
				</p>

				<?php submit_button( __( 'Enregistrer le colis', 'gestionnaire-colis-pro' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles the parcel creation form.
	 *
	 * @return void
	 */
	public static function handle_create() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		check_admin_referer( 'gcp_create_parcel' );

		$photo_id = 0;

		if ( ! empty( $_FILES['gcp_photo']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$upload = media_handle_upload( 'gcp_photo', 0 );

			if ( is_wp_error( $upload ) ) {
				GCP_Admin::redirect( 'gcp-new-parcel', array(), $upload->get_error_message(), 'error' );
			}

			$photo_id = (int) $upload;
		}

		$carriers = array();
		if ( isset( $_POST['allowed_carriers'] ) && is_array( $_POST['allowed_carriers'] ) ) {
			$carriers = array_map( 'sanitize_key', wp_unslash( $_POST['allowed_carriers'] ) );
		}

		$result = GCP_Parcels::create(
			array(
				'client_id'        => isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0,
				'tracking_number'  => isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '',
				'weight'           => isset( $_POST['weight'] ) ? GCP_Parcels::to_float( sanitize_text_field( wp_unslash( $_POST['weight'] ) ) ) : 0,
				'length'           => isset( $_POST['length'] ) ? sanitize_text_field( wp_unslash( $_POST['length'] ) ) : '',
				'width'            => isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '',
				'height'           => isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '',
				'photo_id'         => $photo_id,
				'internal_note'    => isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '',
				'allow_grouping'   => ! empty( $_POST['allow_grouping'] ),
				'allowed_carriers' => $carriers,
			)
		);

		if ( is_wp_error( $result ) ) {
			GCP_Admin::redirect( 'gcp-new-parcel', array(), $result->get_error_message(), 'error' );
		}

		$parcel = GCP_Parcels::get( (int) $result );

		GCP_Admin::redirect(
			'gcp-clients',
			array( 'client' => (int) $parcel->client_id ),
			sprintf(
				/* translators: %s: parcel reference. */
				__( 'Colis %s enregistré. Le tarif a été calculé automatiquement.', 'gestionnaire-colis-pro' ),
				$parcel->reference
			)
		);
	}

	/**
	 * Handles a parcel status change.
	 *
	 * @return void
	 */
	public static function handle_set_status() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		$parcel_id = isset( $_POST['parcel_id'] ) ? absint( $_POST['parcel_id'] ) : 0;

		check_admin_referer( 'gcp_set_parcel_status_' . $parcel_id );

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$parcel = GCP_Parcels::get( $parcel_id );
		$result = GCP_Parcels::set_status( $parcel_id, $status );

		$client_id = $parcel ? (int) $parcel->client_id : 0;

		if ( is_wp_error( $result ) ) {
			GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		GCP_Admin::redirect( 'gcp-clients', array( 'client' => $client_id ), __( 'Statut du colis mis à jour.', 'gestionnaire-colis-pro' ) );
	}
}
