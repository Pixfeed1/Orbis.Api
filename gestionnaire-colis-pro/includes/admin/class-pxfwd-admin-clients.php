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
class PXFWD_Admin_Clients {

	/**
	 * Routes between the list view and the single client view.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'pxfwd_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'gestionnaire-colis-pro' ) );
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
		$clients  = PXFWD_Clients::paged_list( $term, $per_page, $paged );
		$total    = PXFWD_Clients::count( $term );
		?>
		<div class="wrap pxfwd-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'gestionnaire-colis-pro' ); ?></h1>
			<hr class="wp-header-end" />
			<?php PXFWD_Admin::maybe_notice(); ?>

			<form method="get" class="pxfwd-search-form">
				<input type="hidden" name="page" value="pxfwd-clients" />
				<p class="search-box">
					<label class="screen-reader-text" for="pxfwd-client-search"><?php esc_html_e( 'Search clients', 'gestionnaire-colis-pro' ); ?></label>
					<input type="search" id="pxfwd-client-search" name="s" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Reference, name, e-mail, phone…', 'gestionnaire-colis-pro' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'gestionnaire-colis-pro' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Add a client', 'gestionnaire-colis-pro' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pxfwd-inline-form">
				<?php wp_nonce_field( 'pxfwd_create_client' ); ?>
				<input type="hidden" name="action" value="pxfwd_create_client" />
				<label for="pxfwd-new-client-user"><?php esc_html_e( 'WordPress user', 'gestionnaire-colis-pro' ); ?></label>
				<?php
				wp_dropdown_users(
					array(
						'name'             => 'user_id',
						'id'               => 'pxfwd-new-client-user',
						'show'             => 'display_name_with_login',
						'number'           => 200,
						'show_option_none' => __( '— Select —', 'gestionnaire-colis-pro' ),
					)
				);
				?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the client record', 'gestionnaire-colis-pro' ); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reference', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Name', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Parcels in stock', 'gestionnaire-colis-pro' ); ?></th>
						<th><?php esc_html_e( 'Created on', 'gestionnaire-colis-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $clients ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No clients found.', 'gestionnaire-colis-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $clients as $client ) : ?>
							<?php
							$url = add_query_arg(
								array(
									'page'   => 'pxfwd-clients',
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
								<td><?php echo esc_html( number_format_i18n( count( PXFWD_Parcels::in_stock_for_client( (int) $client->id ) ) ) ); ?></td>
								<td><?php echo esc_html( PXFWD_Format::date( $client->created_at ) ); ?></td>
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
		$client = PXFWD_Clients::get( $client_id );

		if ( ! $client ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Client not found.', 'gestionnaire-colis-pro' ) . '</p></div>';
			return;
		}

		$user       = get_userdata( (int) $client->user_id );
		$indicators = PXFWD_Clients::indicators( $client_id );
		$in_stock   = PXFWD_Parcels::in_stock_for_client( $client_id );
		$shipped    = PXFWD_Parcels::shipped_for_client( $client_id );
		$shipments  = PXFWD_Shipments::for_client( $client_id );
		$documents  = PXFWD_Documents::for_client( $client_id );
		$history    = PXFWD_History::for_client( $client_id );

		$new_parcel_url = add_query_arg(
			array(
				'page'   => 'pxfwd-new-parcel',
				'client' => (int) $client->id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap pxfwd-wrap">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: 1: client reference, 2: client name. */
					esc_html__( 'Client record %1$s — %2$s', 'gestionnaire-colis-pro' ),
					esc_html( $client->reference ),
					esc_html( $user ? $user->display_name : '' )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $new_parcel_url ); ?>" class="page-title-action"><?php esc_html_e( 'New parcel', 'gestionnaire-colis-pro' ); ?></a>
			<hr class="wp-header-end" />
			<?php PXFWD_Admin::maybe_notice(); ?>

			<div class="pxfwd-indicators">
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['parcels_in_stock'] ) ); ?></span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Parcels in stock', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['weight_in_stock'], 3 ) ); ?> kg</span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Total stored weight', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['shipments_count'] ) ); ?></span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Shipments done', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( PXFWD_Format::price( $indicators['storage_fees_due'] ) ); ?></span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Storage fees due', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( $indicators['last_reception'] ? PXFWD_Format::date( $indicators['last_reception'] ) : '—' ); ?></span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Last reception', 'gestionnaire-colis-pro' ); ?></span>
				</div>
				<div class="pxfwd-indicator">
					<span class="pxfwd-indicator-value"><?php echo esc_html( $indicators['last_shipment'] ? PXFWD_Format::date( $indicators['last_shipment'] ) : '—' ); ?></span>
					<span class="pxfwd-indicator-label"><?php esc_html_e( 'Last shipment', 'gestionnaire-colis-pro' ); ?></span>
				</div>
			</div>

			<h2 class="nav-tab-wrapper pxfwd-tabs">
				<a href="#pxfwd-tab-infos" class="nav-tab nav-tab-active"><?php esc_html_e( 'Information', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#pxfwd-tab-stock" class="nav-tab"><?php esc_html_e( 'Parcels in stock', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#pxfwd-tab-shipped" class="nav-tab"><?php esc_html_e( 'Shipped parcels', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#pxfwd-tab-shipments" class="nav-tab"><?php esc_html_e( 'Shipments', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#pxfwd-tab-documents" class="nav-tab"><?php esc_html_e( 'Documents', 'gestionnaire-colis-pro' ); ?></a>
				<a href="#pxfwd-tab-history" class="nav-tab"><?php esc_html_e( 'History', 'gestionnaire-colis-pro' ); ?></a>
			</h2>

			<div id="pxfwd-tab-infos" class="pxfwd-tab-panel pxfwd-tab-active">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pxfwd_update_client_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="pxfwd_update_client" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Client reference', 'gestionnaire-colis-pro' ); ?></th>
							<td><code><?php echo esc_html( $client->reference ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'User', 'gestionnaire-colis-pro' ); ?></th>
							<td>
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a>
									(<?php echo esc_html( $user->user_email ); ?>)
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pxfwd-phone"><?php esc_html_e( 'Phone', 'gestionnaire-colis-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="pxfwd-phone" name="phone" value="<?php echo esc_attr( $client->phone ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="pxfwd-admin-notes"><?php esc_html_e( 'Internal notes (never visible to the client)', 'gestionnaire-colis-pro' ); ?></label></th>
							<td><textarea id="pxfwd-admin-notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $client->admin_notes ); ?></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Save', 'gestionnaire-colis-pro' ) ); ?>
				</form>
			</div>

			<div id="pxfwd-tab-stock" class="pxfwd-tab-panel">
				<?php self::parcels_table( $in_stock, true ); ?>
			</div>

			<div id="pxfwd-tab-shipped" class="pxfwd-tab-panel">
				<?php self::parcels_table( $shipped, false ); ?>
			</div>

			<div id="pxfwd-tab-shipments" class="pxfwd-tab-panel">
				<?php self::shipments_table( $shipments ); ?>
			</div>

			<div id="pxfwd-tab-documents" class="pxfwd-tab-panel">
				<?php self::documents_table( $documents ); ?>
				<h3><?php esc_html_e( 'Add a document', 'gestionnaire-colis-pro' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="pxfwd-inline-form">
					<?php wp_nonce_field( 'pxfwd_add_document_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="pxfwd_add_document" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<input type="file" name="pxfwd_document" required />
					<label>
						<input type="checkbox" name="visibility_client" value="1" checked />
						<?php esc_html_e( 'Visible to the client', 'gestionnaire-colis-pro' ); ?>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Add', 'gestionnaire-colis-pro' ); ?></button>
				</form>
			</div>

			<div id="pxfwd-tab-history" class="pxfwd-tab-panel">
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
					<th><?php esc_html_e( 'Number', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Received on', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Tracking', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Weight (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Price', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Grouping', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Storage fees', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Internal comment', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Photo', 'gestionnaire-colis-pro' ); ?></th>
					<?php if ( $with_action ) : ?>
						<th><?php esc_html_e( 'Status', 'gestionnaire-colis-pro' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $parcels ) ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No parcels.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $parcels as $parcel ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
							<td><?php echo esc_html( PXFWD_Format::date( $parcel->received_at ) ); ?></td>
							<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
							<td><?php echo esc_html( PXFWD_Format::price( (float) $parcel->price ) ); ?></td>
							<td><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'gestionnaire-colis-pro' ) : esc_html__( 'No', 'gestionnaire-colis-pro' ); ?></td>
							<td><?php echo esc_html( PXFWD_Format::price( PXFWD_Storage::fees_for_parcel( $parcel ) ) ); ?></td>
							<td><?php echo esc_html( $parcel->internal_note ? $parcel->internal_note : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $parcel->photo_path ) ) : ?>
									<a href="<?php echo esc_url( PXFWD_Downloads::photo_url( $parcel ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'gestionnaire-colis-pro' ); ?></a>
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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pxfwd-status-form">
			<?php wp_nonce_field( 'pxfwd_set_parcel_status_' . (int) $parcel->id ); ?>
			<input type="hidden" name="action" value="pxfwd_set_parcel_status" />
			<input type="hidden" name="parcel_id" value="<?php echo esc_attr( (string) $parcel->id ); ?>" />
			<label class="screen-reader-text" for="pxfwd-status-<?php echo esc_attr( (string) $parcel->id ); ?>"><?php esc_html_e( 'Status', 'gestionnaire-colis-pro' ); ?></label>
			<select name="status" id="pxfwd-status-<?php echo esc_attr( (string) $parcel->id ); ?>">
				<?php foreach ( PXFWD_Parcels::statuses() as $key => $label ) : ?>
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
					<th><?php esc_html_e( 'Reference', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Requested on', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Weight (kg)', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Storage fees', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Total', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Order', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $shipments ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No shipments.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $shipments as $shipment ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
							<td><?php echo esc_html( PXFWD_Format::date( $shipment->requested_at ) ); ?></td>
							<td>
								<?php echo esc_html( PXFWD_Carriers::name( $shipment->carrier ) ); ?>
								<?php if ( isset( $shipment->carrier_price ) && (float) $shipment->carrier_price > 0 ) : ?>
									(<?php echo esc_html( PXFWD_Format::price( (float) $shipment->carrier_price ) ); ?>)
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
							<td><?php echo esc_html( PXFWD_Format::price( (float) $shipment->storage_fees ) ); ?></td>
							<td><?php echo esc_html( PXFWD_Format::price( (float) $shipment->total_price ) ); ?></td>
							<td>
								<?php $order = ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $shipment->order_id ) : null; ?>
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
										<?php
										printf(
											/* translators: %s: order number. */
											esc_html__( '#%s', 'gestionnaire-colis-pro' ),
											esc_html( $order->get_order_number() )
										);
										?>
									</a>
									(<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>)
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pxfwd-status-form">
									<?php wp_nonce_field( 'pxfwd_set_shipment_status_' . (int) $shipment->id ); ?>
									<input type="hidden" name="action" value="pxfwd_set_shipment_status" />
									<input type="hidden" name="shipment_id" value="<?php echo esc_attr( (string) $shipment->id ); ?>" />
									<label class="screen-reader-text" for="pxfwd-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>"><?php esc_html_e( 'Status', 'gestionnaire-colis-pro' ); ?></label>
									<select name="status" id="pxfwd-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>">
										<?php foreach ( PXFWD_Shipments::statuses() as $key => $label ) : ?>
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
					<th><?php esc_html_e( 'Title', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Added on', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Visibility', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'File', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $documents ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No documents.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $documents as $document ) : ?>
						<tr>
							<td><?php echo esc_html( $document->title ); ?></td>
							<td><?php echo esc_html( PXFWD_Format::date( $document->created_at ) ); ?></td>
							<td><?php echo 'admin' === $document->visibility ? esc_html__( 'Internal', 'gestionnaire-colis-pro' ) : esc_html__( 'Client', 'gestionnaire-colis-pro' ); ?></td>
							<td>
								<?php if ( ! empty( $document->file_path ) ) : ?>
									<a href="<?php echo esc_url( PXFWD_Downloads::document_url( $document ) ); ?>"><?php esc_html_e( 'Download', 'gestionnaire-colis-pro' ); ?></a>
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
					<th><?php esc_html_e( 'Event', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'Details', 'gestionnaire-colis-pro' ); ?></th>
					<th><?php esc_html_e( 'By', 'gestionnaire-colis-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No operations recorded.', 'gestionnaire-colis-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $entries as $entry ) : ?>
						<?php $author = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null; ?>
						<tr>
							<td><?php echo esc_html( PXFWD_Format::date( $entry->created_at, true ) ); ?></td>
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
		if ( ! current_user_can( 'pxfwd_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'gestionnaire-colis-pro' ) );
		}

		check_admin_referer( 'pxfwd_create_client' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$result  = PXFWD_Clients::create( $user_id );

		if ( is_wp_error( $result ) ) {
			PXFWD_Admin::redirect( 'pxfwd-clients', array(), $result->get_error_message(), 'error' );
		}

		PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => (int) $result ), __( 'Client record created.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles the "update client" form.
	 *
	 * @return void
	 */
	public static function handle_update() {
		if ( ! current_user_can( 'pxfwd_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'gestionnaire-colis-pro' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'pxfwd_update_client_' . $client_id );

		PXFWD_Clients::update(
			$client_id,
			array(
				'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'admin_notes' => isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '',
			)
		);

		PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), __( 'Client record updated.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles the "add document" form.
	 *
	 * @return void
	 */
	public static function handle_add_document() {
		if ( ! current_user_can( 'pxfwd_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'gestionnaire-colis-pro' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'pxfwd_add_document_' . $client_id );

		$file = PXFWD_Files::upload( 'pxfwd_document', PXFWD_Files::document_mimes() );

		if ( is_wp_error( $file ) ) {
			PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), $file->get_error_message(), 'error' );
		}

		$visibility = empty( $_POST['visibility_client'] ) ? 'admin' : 'client';
		$result     = PXFWD_Documents::add( $client_id, $file, '', $visibility );

		if ( is_wp_error( $result ) ) {
			PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), __( 'Document added.', 'gestionnaire-colis-pro' ) );
	}

	/**
	 * Handles a shipment status change.
	 *
	 * @return void
	 */
	public static function handle_set_shipment_status() {
		if ( ! current_user_can( 'pxfwd_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'gestionnaire-colis-pro' ) );
		}

		$shipment_id = isset( $_POST['shipment_id'] ) ? absint( $_POST['shipment_id'] ) : 0;

		check_admin_referer( 'pxfwd_set_shipment_status_' . $shipment_id );

		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$shipment = PXFWD_Shipments::get( $shipment_id );
		$result   = PXFWD_Shipments::set_status( $shipment_id, $status );

		$client_id = $shipment ? (int) $shipment->client_id : 0;

		if ( is_wp_error( $result ) ) {
			PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		PXFWD_Admin::redirect( 'pxfwd-clients', array( 'client' => $client_id ), __( 'Shipment status updated.', 'gestionnaire-colis-pro' ) );
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
					'page' => 'pxfwd-clients',
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
