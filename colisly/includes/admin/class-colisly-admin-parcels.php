<?php
/**
 * Admin parcels screens: list and creation form.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the parcels list and the parcel creation form.
 */
class COLISLY_Admin_Parcels {

	/**
	 * Renders the parcels list with search and status filter.
	 *
	 * @return void
	 */
	public static function render_list() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$result = COLISLY_Parcels::paged_list(
			array(
				'search'   => $search,
				'status'   => $status,
				'per_page' => 20,
				'paged'    => $paged,
			)
		);
		?>
		<div class="wrap colisly-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Parcels', 'colisly' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=colisly-new-parcel' ) ); ?>" class="page-title-action"><?php esc_html_e( 'New parcel', 'colisly' ); ?></a>
			<hr class="wp-header-end" />
			<?php COLISLY_Admin::maybe_notice(); ?>

			<form method="get" class="colisly-search-form">
				<input type="hidden" name="page" value="colisly-parcels" />
				<p class="search-box">
					<label class="screen-reader-text" for="colisly-parcel-search"><?php esc_html_e( 'Search parcels', 'colisly' ); ?></label>
					<input type="search" id="colisly-parcel-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Parcel number, tracking, client…', 'colisly' ); ?>" />
					<label class="screen-reader-text" for="colisly-parcel-status"><?php esc_html_e( 'Status', 'colisly' ); ?></label>
					<select name="status" id="colisly-parcel-status">
						<option value=""><?php esc_html_e( 'All statuses', 'colisly' ); ?></option>
						<?php foreach ( COLISLY_Parcels::statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'colisly' ); ?></button>
				</p>
			</form>

			<div class="colisly-table-wrap">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Number', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Client', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Received on', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Tracking', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Grouping', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Status', 'colisly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="8"><?php esc_html_e( 'No parcels found.', 'colisly' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $result['items'] as $parcel ) : ?>
								<?php
								$client_url = add_query_arg(
									array(
										'page'   => 'colisly-clients',
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
									<td><?php echo esc_html( COLISLY_Format::date( $parcel->received_at ) ); ?></td>
									<td><?php echo esc_html( $parcel->tracking_number ? $parcel->tracking_number : '—' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
									<td><?php echo esc_html( COLISLY_Format::price( (float) $parcel->price ) ); ?></td>
									<td><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly' ) : esc_html__( 'No', 'colisly' ); ?></td>
									<td><?php COLISLY_Admin_Clients::parcel_status_form( $parcel ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php
			$pages = (int) ceil( $result['total'] / 20 );
			if ( $pages > 1 ) :
				?>
				<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">
					<?php
					$base = add_query_arg(
						array_filter(
							array(
								'page'   => 'colisly-parcels',
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
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill.
		$preselected = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;
		$client      = $preselected ? COLISLY_Clients::get( $preselected ) : null;
		$client_user = $client ? get_userdata( (int) $client->user_id ) : null;
		?>
		<div class="wrap colisly-wrap">
			<h1><?php esc_html_e( 'New parcel', 'colisly' ); ?></h1>
			<?php COLISLY_Admin::maybe_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="colisly-new-parcel-form">
				<?php wp_nonce_field( 'colisly_create_parcel' ); ?>
				<input type="hidden" name="action" value="colisly_create_parcel" />

				<h2><?php esc_html_e( '1. Client', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-client-search-input"><?php esc_html_e( 'Search for the client', 'colisly' ); ?></label></th>
						<td>
							<input
								type="text"
								id="colisly-client-search-input"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Reference (CL000001), name or e-mail…', 'colisly' ); ?>"
								autocomplete="off"
								value="<?php echo esc_attr( $client ? $client->reference . ' — ' . ( $client_user ? $client_user->display_name : '' ) : '' ); ?>"
							/>
							<input type="hidden" name="client_id" id="colisly-client-id" value="<?php echo esc_attr( $client ? (string) $client->id : '' ); ?>" required />
							<div id="colisly-client-results" class="colisly-client-results" role="listbox"></div>
							<p class="description"><?php esc_html_e( 'Search by internal reference, name or e-mail address.', 'colisly' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="colisly-client-stock">
					<?php if ( $client ) : ?>
						<h3><?php esc_html_e( 'Parcels of this client still in stock', 'colisly' ); ?></h3>
						<?php $stock = COLISLY_Parcels::in_stock_for_client( (int) $client->id ); ?>
						<div class="colisly-table-wrap">
							<table class="wp-list-table widefat fixed striped colisly-stock-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Parcel number', 'colisly' ); ?></th>
										<th><?php esc_html_e( 'Weight (kg)', 'colisly' ); ?></th>
										<th><?php esc_html_e( 'Grouping allowed', 'colisly' ); ?></th>
										<th><?php esc_html_e( 'Internal comment', 'colisly' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $stock ) ) : ?>
										<tr><td colspan="4"><?php esc_html_e( 'No parcels in stock for this client.', 'colisly' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $stock as $parcel ) : ?>
											<tr>
												<td><?php echo esc_html( $parcel->reference ); ?></td>
												<td><?php echo esc_html( number_format_i18n( (float) $parcel->weight, 3 ) ); ?></td>
												<td><?php echo $parcel->allow_grouping ? esc_html__( 'Yes', 'colisly' ) : esc_html__( 'No', 'colisly' ); ?></td>
												<td><?php echo esc_html( $parcel->internal_note ? $parcel->internal_note : '—' ); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<h2><?php esc_html_e( '2. Parcel information', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-tracking"><?php esc_html_e( 'Carrier tracking number', 'colisly' ); ?></label></th>
						<td><input type="text" id="colisly-tracking" name="tracking_number" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-weight"><?php esc_html_e( 'Real weight (kg)', 'colisly' ); ?> <span class="description">(<?php esc_html_e( 'required', 'colisly' ); ?>)</span></label></th>
						<td><input type="number" id="colisly-weight" name="weight" step="0.001" min="0.001" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dimensions (cm, visible to administrators only)', 'colisly' ); ?></th>
						<td class="colisly-dimensions">
							<span class="colisly-dimension">
								<label for="colisly-length"><?php esc_html_e( 'Length', 'colisly' ); ?></label>
								<input type="number" id="colisly-length" name="length" step="0.01" min="0" />
							</span>
							<span class="colisly-dimension">
								<label for="colisly-width"><?php esc_html_e( 'Width', 'colisly' ); ?></label>
								<input type="number" id="colisly-width" name="width" step="0.01" min="0" />
							</span>
							<span class="colisly-dimension">
								<label for="colisly-height"><?php esc_html_e( 'Height', 'colisly' ); ?></label>
								<input type="number" id="colisly-height" name="height" step="0.01" min="0" />
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-photo"><?php esc_html_e( 'Photo of the parcel at reception (optional)', 'colisly' ); ?></label></th>
						<td><input type="file" id="colisly-photo" name="colisly_photo" accept="image/*" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-note"><?php esc_html_e( 'Internal comment (never visible to the client)', 'colisly' ); ?></label></th>
						<td><textarea id="colisly-note" name="internal_note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Damaged packaging, fragile parcel, anomaly…', 'colisly' ); ?>"></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( '3. Shipping rules', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Grouping', 'colisly' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_grouping" value="1" checked />
								<?php esc_html_e( 'Allow this parcel to be grouped with other parcels', 'colisly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'If unchecked, this parcel will have to be shipped alone. This decision can never be changed by the client.', 'colisly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed carriers', 'colisly' ); ?></th>
						<td>
							<?php foreach ( COLISLY_Carriers::all( true ) as $carrier ) : ?>
								<label class="colisly-carrier-choice">
									<input type="checkbox" name="allowed_carriers[]" value="<?php echo esc_attr( $carrier['slug'] ); ?>" checked />
									<?php echo esc_html( $carrier['name'] ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Uncheck incompatible carriers (oversize, restrictions…). Only checked carriers will be offered to the client. Leaving none checked places no restriction: every carrier stays available.', 'colisly' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="description">
					<?php esc_html_e( 'The parcel price is computed automatically from its weight when saved, then used for the shipment request.', 'colisly' ); ?>
				</p>

				<?php submit_button( __( 'Save the parcel', 'colisly' ) ); ?>
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
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'colisly_create_parcel' );

		$photo_path = '';

		if ( ! empty( $_FILES['colisly_photo']['name'] ) ) {
			$photo = COLISLY_Files::upload( 'colisly_photo', COLISLY_Files::photo_mimes() );

			if ( is_wp_error( $photo ) ) {
				COLISLY_Admin::redirect( 'colisly-new-parcel', array(), $photo->get_error_message(), 'error' );
			}

			$photo_path = $photo['path'];
		}

		$carriers = array();
		if ( isset( $_POST['allowed_carriers'] ) && is_array( $_POST['allowed_carriers'] ) ) {
			$carriers = array_map( 'sanitize_key', wp_unslash( $_POST['allowed_carriers'] ) );
		}

		$result = COLISLY_Parcels::create(
			array(
				'client_id'        => isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0,
				'tracking_number'  => isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '',
				'weight'           => isset( $_POST['weight'] ) ? COLISLY_Parcels::to_float( sanitize_text_field( wp_unslash( $_POST['weight'] ) ) ) : 0,
				'length'           => isset( $_POST['length'] ) ? sanitize_text_field( wp_unslash( $_POST['length'] ) ) : '',
				'width'            => isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '',
				'height'           => isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '',
				'photo_path'       => $photo_path,
				'internal_note'    => isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '',
				'allow_grouping'   => ! empty( $_POST['allow_grouping'] ),
				'allowed_carriers' => $carriers,
			)
		);

		if ( is_wp_error( $result ) ) {
			COLISLY_Admin::redirect( 'colisly-new-parcel', array(), $result->get_error_message(), 'error' );
		}

		$parcel = COLISLY_Parcels::get( (int) $result );

		COLISLY_Admin::redirect(
			'colisly-clients',
			array( 'client' => (int) $parcel->client_id ),
			sprintf(
				/* translators: %s: parcel reference. */
				__( 'Parcel %s saved. Its price has been computed automatically.', 'colisly' ),
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
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		$parcel_id = isset( $_POST['parcel_id'] ) ? absint( $_POST['parcel_id'] ) : 0;

		check_admin_referer( 'colisly_set_parcel_status_' . $parcel_id );

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$parcel = COLISLY_Parcels::get( $parcel_id );
		$result = COLISLY_Parcels::set_status( $parcel_id, $status );

		$client_id = $parcel ? (int) $parcel->client_id : 0;

		if ( is_wp_error( $result ) ) {
			COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), $result->get_error_message(), 'error' );
		}

		COLISLY_Admin::redirect( 'colisly-clients', array( 'client' => $client_id ), __( 'Parcel status updated.', 'colisly' ) );
	}
}
