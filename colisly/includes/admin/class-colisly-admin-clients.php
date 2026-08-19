<?php
/**
 * Admin clients screens: list and client record ("fiche client").
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the clients list and the client record page.
 */
class COLISLY_Admin_Clients {

	/**
	 * Routes between the list view and the single client view.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ) );
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
		$clients  = COLISLY_Clients::paged_list( $term, $per_page, $paged );
		$total    = COLISLY_Clients::count( $term );
		?>
		<div class="wrap colisly-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'colisly' ); ?></h1>
			<hr class="wp-header-end" />
			<?php COLISLY_Admin::maybe_notice(); ?>

			<form method="get" class="colisly-search-form">
				<input type="hidden" name="page" value="colisly-clients" />
				<p class="search-box">
					<label class="screen-reader-text" for="colisly-client-search"><?php esc_html_e( 'Search clients', 'colisly' ); ?></label>
					<input type="search" id="colisly-client-search" name="s" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Reference, name, e-mail, phone…', 'colisly' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'colisly' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Add a client', 'colisly' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="colisly-inline-form">
				<?php wp_nonce_field( 'colisly_create_client' ); ?>
				<input type="hidden" name="action" value="colisly_create_client" />
				<label for="colisly-new-client-user"><?php esc_html_e( 'WordPress user', 'colisly' ); ?></label>
				<?php
				wp_dropdown_users(
					array(
						'name'             => 'user_id',
						'id'               => 'colisly-new-client-user',
						'show'             => 'display_name_with_login',
						'number'           => 200,
						'show_option_none' => __( '— Select —', 'colisly' ),
					)
				);
				?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the client record', 'colisly' ); ?></button>
			</form>

			<div class="colisly-table-wrap">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reference', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Name', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'E-mail', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Parcels in stock', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Created on', 'colisly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $clients ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No clients found.', 'colisly' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $clients as $client ) : ?>
								<?php
								$url = add_query_arg(
									array(
										'page'   => 'colisly-clients',
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
									<td><?php echo esc_html( number_format_i18n( count( COLISLY_Parcels::in_stock_for_client( (int) $client->id ) ) ) ); ?></td>
									<td><?php echo esc_html( COLISLY_Format::date( $client->created_at ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

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
		$client = COLISLY_Clients::get( $client_id );

		if ( ! $client ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Client not found.', 'colisly' ) . '</p></div>';
			return;
		}

		$user       = get_userdata( (int) $client->user_id );
		$indicators = COLISLY_Clients::indicators( $client_id );
		$in_stock   = COLISLY_Parcels::in_stock_for_client( $client_id );
		$shipped    = COLISLY_Parcels::shipped_for_client( $client_id );
		$shipments  = COLISLY_Shipments::for_client( $client_id );
		$documents  = COLISLY_Documents::for_client( $client_id );
		$history    = COLISLY_History::for_client( $client_id );

		$new_parcel_url = add_query_arg(
			array(
				'page'   => 'colisly-new-parcel',
				'client' => (int) $client->id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap colisly-wrap">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: 1: client reference, 2: client name. */
					esc_html__( 'Client record %1$s — %2$s', 'colisly' ),
					esc_html( $client->reference ),
					esc_html( $user ? $user->display_name : '' )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( $new_parcel_url ); ?>" class="page-title-action"><?php esc_html_e( 'New parcel', 'colisly' ); ?></a>
			<hr class="wp-header-end" />
			<?php COLISLY_Admin::maybe_notice(); ?>

			<div class="colisly-indicators">
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['parcels_in_stock'] ) ); ?></span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Parcels in stock', 'colisly' ); ?></span>
				</div>
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['weight_in_stock'], 3 ) ); ?> kg</span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Total stored weight', 'colisly' ); ?></span>
				</div>
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( number_format_i18n( $indicators['shipments_count'] ) ); ?></span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Shipments done', 'colisly' ); ?></span>
				</div>
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( COLISLY_Format::price( $indicators['storage_fees_due'] ) ); ?></span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Storage fees due', 'colisly' ); ?></span>
				</div>
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( $indicators['last_reception'] ? COLISLY_Format::date( $indicators['last_reception'] ) : '—' ); ?></span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Last reception', 'colisly' ); ?></span>
				</div>
				<div class="colisly-indicator">
					<span class="colisly-indicator-value"><?php echo esc_html( $indicators['last_shipment'] ? COLISLY_Format::date( $indicators['last_shipment'] ) : '—' ); ?></span>
					<span class="colisly-indicator-label"><?php esc_html_e( 'Last shipment', 'colisly' ); ?></span>
				</div>
			</div>

			<h2 class="nav-tab-wrapper colisly-tabs">
				<a href="#colisly-tab-infos" class="nav-tab nav-tab-active"><?php esc_html_e( 'Information', 'colisly' ); ?></a>
				<a href="#colisly-tab-stock" class="nav-tab"><?php esc_html_e( 'Parcels in stock', 'colisly' ); ?></a>
				<a href="#colisly-tab-shipped" class="nav-tab"><?php esc_html_e( 'Shipped parcels', 'colisly' ); ?></a>
				<a href="#colisly-tab-shipments" class="nav-tab"><?php esc_html_e( 'Shipments', 'colisly' ); ?></a>
				<a href="#colisly-tab-documents" class="nav-tab"><?php esc_html_e( 'Documents', 'colisly' ); ?></a>
				<a href="#colisly-tab-history" class="nav-tab"><?php esc_html_e( 'History', 'colisly' ); ?></a>
			</h2>

			<div id="colisly-tab-infos" class="colisly-tab-panel colisly-tab-active">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'colisly_update_client_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="colisly_update_client" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Client reference', 'colisly' ); ?></th>
							<td><code><?php echo esc_html( $client->reference ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'User', 'colisly' ); ?></th>
							<td>
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a>
									(<?php echo esc_html( $user->user_email ); ?>)
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="colisly-phone"><?php esc_html_e( 'Phone', 'colisly' ); ?></label></th>
							<td><input type="text" class="regular-text" id="colisly-phone" name="phone" value="<?php echo esc_attr( $client->phone ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="colisly-admin-notes"><?php esc_html_e( 'Internal notes (never visible to the client)', 'colisly' ); ?></label></th>
							<td><textarea id="colisly-admin-notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $client->admin_notes ); ?></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Save', 'colisly' ) ); ?>
				</form>
			</div>

			<div id="colisly-tab-stock" class="colisly-tab-panel">
				<?php self::parcels_table( $in_stock, true ); ?>
			</div>

			<div id="colisly-tab-shipped" class="colisly-tab-panel">
				<?php self::parcels_table( $shipped, false ); ?>
			</div>

			<div id="colisly-tab-shipments" class="colisly-tab-panel">
				<?php self::shipments_table( $shipments ); ?>
			</div>

			<div id="colisly-tab-documents" class="colisly-tab-panel">
				<?php self::documents_table( $documents ); ?>
				<h3><?php esc_html_e( 'Add a document', 'colisly' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="colisly-inline-form">
					<?php wp_nonce_field( 'colisly_add_document_' . (int) $client->id ); ?>
					<input type="hidden" name="action" value="colisly_add_document" />
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->id ); ?>" />
					<input type="file" name="colisly_document" required />
					<label>
						<input type="checkbox" name="visibility_client" value="1" checked />
						<?php esc_html_e( 'Visible to the client', 'colisly' ); ?>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Add', 'colisly' ); ?></button>
				</form>
			</div>

			<div id="colisly-tab-history" class="colisly-tab-panel">
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
		<div class="colisly-table-wrap">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Number', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Received on', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Tracking', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Grouping', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Storage fees', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Internal comment', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Photo', 'colisly' ); ?></th>
						<?php if ( $with_action ) : ?>
							<th><?php esc_html_e( 'Status', 'colisly' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $parcels ) ) : ?>
						<tr><td colspan="10"><?php esc_html_e( 'No parcels.', 'colisly' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $parcels as $parcel ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $parcel->reference ); ?></strong></td>
								<td><?php echo esc_html( COLISLY_Format::date( $parcel->received_at ) ); ?></td>
								<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
								<td><?php echo esc_html( COLISLY_Format::price( (float) $parcel->price ) ); ?></td>
								<td><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly' ) : esc_html__( 'No', 'colisly' ); ?></td>
								<td><?php echo esc_html( COLISLY_Format::price( COLISLY_Storage::fees_for_parcel( $parcel ) ) ); ?></td>
								<td><?php echo esc_html( $parcel->internal_note ? $parcel->internal_note : '—' ); ?></td>
								<td>
									<?php if ( ! empty( $parcel->photo_path ) ) : ?>
										<a href="<?php echo esc_url( COLISLY_Downloads::photo_url( $parcel ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'colisly' ); ?></a>
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
		</div>
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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="colisly-status-form">
			<?php wp_nonce_field( 'colisly_set_parcel_status_' . (int) $parcel->id ); ?>
			<input type="hidden" name="action" value="colisly_set_parcel_status" />
			<input type="hidden" name="parcel_id" value="<?php echo esc_attr( (string) $parcel->id ); ?>" />
			<label class="screen-reader-text" for="colisly-status-<?php echo esc_attr( (string) $parcel->id ); ?>"><?php esc_html_e( 'Status', 'colisly' ); ?></label>
			<select name="status" id="colisly-status-<?php echo esc_attr( (string) $parcel->id ); ?>">
				<?php foreach ( COLISLY_Parcels::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $parcel->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'colisly' ); ?></button>
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
		<div class="colisly-table-wrap">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reference', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Requested on', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Carrier', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Storage fees', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Total', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Order', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Status', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $shipments ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No shipments.', 'colisly' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $shipments as $shipment ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $shipment->reference ); ?></strong></td>
								<td><?php echo esc_html( COLISLY_Format::date( $shipment->requested_at ) ); ?></td>
								<td>
									<?php echo esc_html( COLISLY_Carriers::name( $shipment->carrier ) ); ?>
									<?php if ( isset( $shipment->carrier_price ) && (float) $shipment->carrier_price > 0 ) : ?>
										(<?php echo esc_html( COLISLY_Format::price( (float) $shipment->carrier_price ) ); ?>)
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( (float) $shipment->total_weight, 3 ) ); ?></td>
								<td><?php echo esc_html( COLISLY_Format::price( (float) $shipment->storage_fees ) ); ?></td>
								<td><?php echo esc_html( COLISLY_Format::price( (float) $shipment->total_price ) ); ?></td>
								<td>
									<?php $order = ! empty( $shipment->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $shipment->order_id ) : null; ?>
									<?php if ( $order ) : ?>
										<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
											<?php
											printf(
												/* translators: %s: order number. */
												esc_html__( '#%s', 'colisly' ),
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
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="colisly-status-form">
										<?php wp_nonce_field( 'colisly_set_shipment_status_' . (int) $shipment->id ); ?>
										<input type="hidden" name="action" value="colisly_set_shipment_status" />
										<input type="hidden" name="shipment_id" value="<?php echo esc_attr( (string) $shipment->id ); ?>" />
										<label class="screen-reader-text" for="colisly-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>"><?php esc_html_e( 'Status', 'colisly' ); ?></label>
										<select name="status" id="colisly-ship-status-<?php echo esc_attr( (string) $shipment->id ); ?>">
											<?php foreach ( COLISLY_Shipments::statuses() as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $shipment->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'colisly' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
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
		<div class="colisly-table-wrap">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Added on', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Visibility', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'File', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $documents ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No documents.', 'colisly' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $documents as $document ) : ?>
							<tr>
								<td><?php echo esc_html( $document->title ); ?></td>
								<td><?php echo esc_html( COLISLY_Format::date( $document->created_at ) ); ?></td>
								<td><?php echo 'admin' === $document->visibility ? esc_html__( 'Internal', 'colisly' ) : esc_html__( 'Client', 'colisly' ); ?></td>
								<td>
									<?php if ( ! empty( $document->file_path ) ) : ?>
										<a href="<?php echo esc_url( COLISLY_Downloads::document_url( $document ) ); ?>"><?php esc_html_e( 'Download', 'colisly' ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
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
		<div class="colisly-table-wrap">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Event', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'Details', 'colisly' ); ?></th>
						<th><?php esc_html_e( 'By', 'colisly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No operations recorded.', 'colisly' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<?php $author = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null; ?>
							<tr>
								<td><?php echo esc_html( COLISLY_Format::date( $entry->created_at, true ) ); ?></td>
								<td><code><?php echo esc_html( $entry->event ); ?></code></td>
								<td><?php echo esc_html( (string) $entry->message ); ?></td>
								<td><?php echo esc_html( $author ? $author->display_name : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Handles the "create client" form.
	 *
	 * @return void
	 */
	public static function handle_create() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ) );
		}

		check_admin_referer( 'colisly_create_client' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$result  = COLISLY_Clients::create( $user_id );

		if ( is_wp_error( $result ) ) {
			COLISLY_Admin::redirect( 'colisly-clients', array(), $result->get_error_message(), 'error' );
		}

		COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => (int) $result ), __( 'Client record created.', 'colisly' ) );
	}

	/**
	 * Handles the "update client" form.
	 *
	 * @return void
	 */
	public static function handle_update() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'colisly_update_client_' . $client_id );

		COLISLY_Clients::update(
			$client_id,
			array(
				'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'admin_notes' => isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '',
			)
		);

		COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), __( 'Client record updated.', 'colisly' ) );
	}

	/**
	 * Handles the "add document" form.
	 *
	 * @return void
	 */
	public static function handle_add_document() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		check_admin_referer( 'colisly_add_document_' . $client_id );

		$file = COLISLY_Files::upload( 'colisly_document', COLISLY_Files::document_mimes() );

		if ( is_wp_error( $file ) ) {
			COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), $file->get_error_message(), 'error' );
		}

		$visibility = empty( $_POST['visibility_client'] ) ? 'admin' : 'client';
		$result     = COLISLY_Documents::add( $client_id, $file, '', $visibility );

		if ( is_wp_error( $result ) ) {
			COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), __( 'Document added.', 'colisly' ) );
	}

	/**
	 * Handles a shipment status change.
	 *
	 * @return void
	 */
	public static function handle_set_shipment_status() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ) );
		}

		$shipment_id = isset( $_POST['shipment_id'] ) ? absint( $_POST['shipment_id'] ) : 0;

		check_admin_referer( 'colisly_set_shipment_status_' . $shipment_id );

		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$shipment = COLISLY_Shipments::get( $shipment_id );
		$result   = COLISLY_Shipments::set_status( $shipment_id, $status );

		$client_id = $shipment ? (int) $shipment->client_id : 0;

		if ( is_wp_error( $result ) ) {
			COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), __( 'Shipment status updated.', 'colisly' ) );
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
					'page' => 'colisly-clients',
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
