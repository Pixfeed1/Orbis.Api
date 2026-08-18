<?php
/**
 * Admin settings screen.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the plugin settings.
 */
class COLISLY_Admin_Settings {

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly-parcel-forwarding' ) );
		}

		$settings = COLISLY_Settings::all();
		?>
		<div class="wrap colisly-wrap">
			<h1><?php esc_html_e( 'Settings — Colisly Parcel Forwarding', 'colisly-parcel-forwarding' ); ?></h1>
			<?php COLISLY_Admin::maybe_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'colisly_save_settings' ); ?>
				<input type="hidden" name="action" value="colisly_save_settings" />

				<h2><?php esc_html_e( 'Storage', 'colisly-parcel-forwarding' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-free-days"><?php esc_html_e( 'Free storage days', 'colisly-parcel-forwarding' ); ?></label></th>
						<td>
							<input type="number" id="colisly-free-days" name="free_storage_days" min="0" value="<?php echo esc_attr( (string) $settings['free_storage_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Every parcel gets this free storage period from its reception (15 days by default).', 'colisly-parcel-forwarding' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-fee-day"><?php esc_html_e( 'Storage fee per day and per parcel', 'colisly-parcel-forwarding' ); ?></label></th>
						<td><input type="number" id="colisly-fee-day" name="storage_fee_per_day" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['storage_fee_per_day'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Weight-based pricing', 'colisly-parcel-forwarding' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A parcel price is given by the first tier whose maximum weight is greater than or equal to the parcel weight. Beyond the last tier: base price + price per kg.', 'colisly-parcel-forwarding' ); ?></p>
				<table class="widefat fixed striped colisly-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Maximum weight (kg)', 'colisly-parcel-forwarding' ); ?></th>
							<th><?php esc_html_e( 'Price', 'colisly-parcel-forwarding' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$tiers   = is_array( $settings['pricing_tiers'] ) ? $settings['pricing_tiers'] : array();
						$tiers[] = array(
							'max_weight' => '',
							'price'      => '',
						); // Extra empty row to add a tier.
						foreach ( $tiers as $i => $tier ) :
							?>
							<tr>
								<td>
									<label class="screen-reader-text" for="colisly-tier-w-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Maximum weight (kg)', 'colisly-parcel-forwarding' ); ?></label>
									<input type="number" id="colisly-tier-w-<?php echo esc_attr( (string) $i ); ?>" name="tier_max_weight[]" step="0.001" min="0" value="<?php echo esc_attr( (string) $tier['max_weight'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-tier-p-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Price', 'colisly-parcel-forwarding' ); ?></label>
									<input type="number" id="colisly-tier-p-<?php echo esc_attr( (string) $i ); ?>" name="tier_price[]" step="0.01" min="0" value="<?php echo esc_attr( (string) $tier['price'] ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-price-base"><?php esc_html_e( 'Base price (beyond tiers)', 'colisly-parcel-forwarding' ); ?></label></th>
						<td><input type="number" id="colisly-price-base" name="price_base" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_base'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-price-kg"><?php esc_html_e( 'Price per kg (beyond tiers)', 'colisly-parcel-forwarding' ); ?></label></th>
						<td><input type="number" id="colisly-price-kg" name="price_per_kg" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_per_kg'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Carriers', 'colisly-parcel-forwarding' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A disabled carrier is no longer offered, neither at parcel reception nor to clients.', 'colisly-parcel-forwarding' ); ?></p>
				<table class="widefat fixed striped colisly-carriers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'colisly-parcel-forwarding' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'colisly-parcel-forwarding' ); ?></th>
							<th><?php esc_html_e( 'Base price', 'colisly-parcel-forwarding' ); ?></th>
							<th><?php esc_html_e( 'Price per kg', 'colisly-parcel-forwarding' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'colisly-parcel-forwarding' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$carriers   = is_array( $settings['carriers'] ) ? $settings['carriers'] : array();
						$carriers[] = array(
							'slug'         => '',
							'name'         => '',
							'enabled'      => 1,
							'price_base'   => '',
							'price_per_kg' => '',
						); // Extra empty row to add a carrier.
						foreach ( $carriers as $i => $carrier ) :
							?>
							<tr>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-n-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Name', 'colisly-parcel-forwarding' ); ?></label>
									<input type="text" id="colisly-carrier-n-<?php echo esc_attr( (string) $i ); ?>" name="carrier_name[]" value="<?php echo esc_attr( $carrier['name'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-s-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Slug', 'colisly-parcel-forwarding' ); ?></label>
									<input type="text" id="colisly-carrier-s-<?php echo esc_attr( (string) $i ); ?>" name="carrier_slug[]" value="<?php echo esc_attr( $carrier['slug'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-b-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Base price', 'colisly-parcel-forwarding' ); ?></label>
									<input type="number" step="0.01" min="0" id="colisly-carrier-b-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_base[]" value="<?php echo esc_attr( isset( $carrier['price_base'] ) ? (string) $carrier['price_base'] : '' ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-k-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Price per kg', 'colisly-parcel-forwarding' ); ?></label>
									<input type="number" step="0.01" min="0" id="colisly-carrier-k-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_per_kg[]" value="<?php echo esc_attr( isset( $carrier['price_per_kg'] ) ? (string) $carrier['price_per_kg'] : '' ); ?>" />
								</td>
								<td>
									<input type="hidden" name="carrier_enabled[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo empty( $carrier['enabled'] ) ? '0' : '1'; ?>" class="colisly-carrier-enabled-value" />
									<input type="checkbox" class="colisly-carrier-enabled" <?php checked( ! empty( $carrier['enabled'] ) ); ?> aria-label="<?php esc_attr_e( 'Enabled', 'colisly-parcel-forwarding' ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Orders', 'colisly-parcel-forwarding' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxes', 'colisly-parcel-forwarding' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="orders_taxable" value="1" <?php checked( ! empty( $settings['orders_taxable'] ) ); ?> />
								<?php esc_html_e( 'Apply the shop taxes to shipment orders (parcels, storage and transport lines)', 'colisly-parcel-forwarding' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When unchecked, shipment orders are created tax-free.', 'colisly-parcel-forwarding' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'colisly-parcel-forwarding' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'E-mails', 'colisly-parcel-forwarding' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_client_on_parcel" value="1" <?php checked( ! empty( $settings['notify_client_on_parcel'] ) ); ?> />
								<?php esc_html_e( 'Notify the client when a parcel is received', 'colisly-parcel-forwarding' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="notify_admin_on_request" value="1" <?php checked( ! empty( $settings['notify_admin_on_request'] ) ); ?> />
								<?php esc_html_e( 'Notify the administrator when a shipment is requested', 'colisly-parcel-forwarding' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="send_invoice_on_request" value="1" <?php checked( ! empty( $settings['send_invoice_on_request'] ) ); ?> />
								<?php esc_html_e( 'Send the WooCommerce invoice (with payment link) when a shipment is requested', 'colisly-parcel-forwarding' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'colisly-parcel-forwarding' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves the settings form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly-parcel-forwarding' ) );
		}

		check_admin_referer( 'colisly_save_settings' );

		$settings = COLISLY_Settings::all();

		$settings['free_storage_days']   = isset( $_POST['free_storage_days'] ) ? absint( $_POST['free_storage_days'] ) : 15;
		$settings['storage_fee_per_day'] = isset( $_POST['storage_fee_per_day'] ) ? max( 0, (float) $_POST['storage_fee_per_day'] ) : 0;
		$settings['price_base']          = isset( $_POST['price_base'] ) ? max( 0, (float) $_POST['price_base'] ) : 0;
		$settings['price_per_kg']        = isset( $_POST['price_per_kg'] ) ? max( 0, (float) $_POST['price_per_kg'] ) : 0;

		$tiers   = array();
		$weights = isset( $_POST['tier_max_weight'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['tier_max_weight'] ) ) : array();
		$prices  = isset( $_POST['tier_price'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['tier_price'] ) ) : array();

		foreach ( $weights as $i => $weight ) {
			if ( '' === $weight || ! isset( $prices[ $i ] ) || '' === $prices[ $i ] ) {
				continue;
			}
			$tiers[] = array(
				'max_weight' => max( 0, (float) $weight ),
				'price'      => max( 0, (float) $prices[ $i ] ),
			);
		}
		$settings['pricing_tiers'] = $tiers;

		$carriers = array();
		$names    = isset( $_POST['carrier_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_name'] ) ) : array();
		$slugs    = isset( $_POST['carrier_slug'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['carrier_slug'] ) ) : array();
		$enabled  = isset( $_POST['carrier_enabled'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['carrier_enabled'] ) ) : array();
		$bases    = isset( $_POST['carrier_price_base'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_price_base'] ) ) : array();
		$rates    = isset( $_POST['carrier_price_per_kg'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_price_per_kg'] ) ) : array();

		foreach ( $names as $i => $name ) {
			$slug = isset( $slugs[ $i ] ) ? $slugs[ $i ] : '';
			if ( '' === $name && '' === $slug ) {
				continue;
			}
			if ( '' === $slug ) {
				$slug = sanitize_key( sanitize_title( $name ) );
			}
			if ( '' === $name ) {
				$name = ucfirst( $slug );
			}
			$carriers[] = array(
				'slug'         => $slug,
				'name'         => $name,
				'enabled'      => empty( $enabled[ $i ] ) ? 0 : 1,
				'price_base'   => isset( $bases[ $i ] ) ? max( 0, COLISLY_Parcels::to_float( $bases[ $i ] ) ) : 0,
				'price_per_kg' => isset( $rates[ $i ] ) ? max( 0, COLISLY_Parcels::to_float( $rates[ $i ] ) ) : 0,
			);
		}
		$settings['carriers'] = $carriers;

		$settings['orders_taxable']          = empty( $_POST['orders_taxable'] ) ? 0 : 1;
		$settings['notify_client_on_parcel'] = empty( $_POST['notify_client_on_parcel'] ) ? 0 : 1;
		$settings['notify_admin_on_request'] = empty( $_POST['notify_admin_on_request'] ) ? 0 : 1;
		$settings['send_invoice_on_request'] = empty( $_POST['send_invoice_on_request'] ) ? 0 : 1;

		COLISLY_Settings::update( $settings );

		COLISLY_Admin::redirect( 'colisly-settings', array(), __( 'Settings saved.', 'colisly-parcel-forwarding' ) );
	}
}
