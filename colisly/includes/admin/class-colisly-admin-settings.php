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
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		$settings = COLISLY_Settings::all();
		?>
		<div class="wrap colisly-wrap">
			<h1><?php esc_html_e( 'Settings — Colisly Parcel Forwarding', 'colisly' ); ?></h1>
			<?php COLISLY_Admin::maybe_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'colisly_save_settings' ); ?>
				<input type="hidden" name="action" value="colisly_save_settings" />

				<h2><?php esc_html_e( 'Storage', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-free-days"><?php esc_html_e( 'Free storage days', 'colisly' ); ?></label></th>
						<td>
							<input type="number" id="colisly-free-days" name="free_storage_days" min="0" value="<?php echo esc_attr( (string) $settings['free_storage_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Every parcel gets this free storage period from its reception (15 days by default).', 'colisly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-fee-day"><?php esc_html_e( 'Storage fee per day and per parcel', 'colisly' ); ?></label></th>
						<td><input type="number" id="colisly-fee-day" name="storage_fee_per_day" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['storage_fee_per_day'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Weight-based pricing', 'colisly' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A parcel price is given by the first tier whose maximum weight is greater than or equal to the parcel weight. Beyond the last tier: base price + price per kg.', 'colisly' ); ?></p>
				<table class="widefat fixed striped colisly-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
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
									<label class="screen-reader-text" for="colisly-tier-w-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></label>
									<input type="number" id="colisly-tier-w-<?php echo esc_attr( (string) $i ); ?>" name="tier_max_weight[]" step="0.001" min="0" value="<?php echo esc_attr( (string) $tier['max_weight'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-tier-p-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Price', 'colisly' ); ?></label>
									<input type="number" id="colisly-tier-p-<?php echo esc_attr( (string) $i ); ?>" name="tier_price[]" step="0.01" min="0" value="<?php echo esc_attr( (string) $tier['price'] ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a tier', 'colisly' ); ?></button></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-price-base"><?php esc_html_e( 'Base price (beyond tiers)', 'colisly' ); ?></label></th>
						<td><input type="number" id="colisly-price-base" name="price_base" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_base'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-price-kg"><?php esc_html_e( 'Price per kg (beyond tiers)', 'colisly' ); ?></label></th>
						<td><input type="number" id="colisly-price-kg" name="price_per_kg" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_per_kg'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Destination zones', 'colisly' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A carrier does not charge the same to reship next door and to the other side of the world. Group your destinations here, then price each carrier per zone below. Pick the countries by name from the list, or type their two-letter codes separated by commas; the names of the codes you entered are spelled out under the field. A country you do not list keeps the carrier default grid.', 'colisly' ); ?></p>
				<p class="description"><?php esc_html_e( 'Tick the customs column for the zones that need a declaration of the contents. The plugin cannot work this out on its own: reshipping from mainland France to Guadeloupe needs one, since the overseas departments sit outside the EU VAT territory, while reshipping to Belgium needs none. The client then declares what each parcel holds before it can leave.', 'colisly' ); ?></p>
				<table class="widefat fixed striped colisly-zones-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Zone name', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Countries', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Customs declaration', 'colisly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$zones   = COLISLY_Zones::all();
						$zones[] = array(
							'slug'      => '',
							'name'      => '',
							'countries' => array(),
						); // Extra empty row to add a zone.

						// Two letters are what a carrier grid is keyed on, but
						// nobody is expected to know that YT is Mayotte. The
						// picker turns the list into a choice of names, and the
						// field keeps holding the codes.
						$colisly_country_list = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();

						foreach ( $zones as $z => $zone ) :
							?>
							<tr>
								<td>
									<label class="screen-reader-text" for="colisly-zone-n-<?php echo esc_attr( (string) $z ); ?>"><?php esc_html_e( 'Zone name', 'colisly' ); ?></label>
									<input type="text" id="colisly-zone-n-<?php echo esc_attr( (string) $z ); ?>" name="zone_name[]" value="<?php echo esc_attr( $zone['name'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-zone-c-<?php echo esc_attr( (string) $z ); ?>"><?php esc_html_e( 'Countries', 'colisly' ); ?></label>
									<input type="text" id="colisly-zone-c-<?php echo esc_attr( (string) $z ); ?>" name="zone_countries[]" value="<?php echo esc_attr( implode( ', ', $zone['countries'] ) ); ?>" placeholder="FR, RE, YT" class="colisly-zone-countries" />
									<?php if ( $colisly_country_list ) : ?>
										<select class="colisly-country-picker" aria-label="<?php esc_attr_e( 'Add a country to this zone', 'colisly' ); ?>">
											<option value=""><?php esc_html_e( 'Add a country…', 'colisly' ); ?></option>
											<?php foreach ( $colisly_country_list as $colisly_code => $colisly_label ) : ?>
												<option value="<?php echo esc_attr( $colisly_code ); ?>"><?php echo esc_html( $colisly_label . ' (' . $colisly_code . ')' ); ?></option>
											<?php endforeach; ?>
										</select>
									<?php endif; ?>
									<span class="colisly-country-preview"></span>
								</td>
								<td>
									<input type="hidden" name="zone_customs[]" value="<?php echo empty( $zone['customs'] ) ? '0' : '1'; ?>" class="colisly-toggle-value" />
									<input type="checkbox" class="colisly-toggle" <?php checked( ! empty( $zone['customs'] ) ); ?> aria-label="<?php esc_attr_e( 'Customs declaration', 'colisly' ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a zone', 'colisly' ); ?></button></p>

				<h2><?php esc_html_e( 'Carriers', 'colisly' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A disabled carrier is no longer offered, neither at parcel reception nor to clients.', 'colisly' ); ?></p>
				<p class="description"><?php esc_html_e( 'Volumetric: express carriers price bulk rather than mass. Tick the box and the transport is billed on whichever is greater, the real weight or length x width x height divided by the divisor, parcel by parcel. A parcel whose dimensions were not entered is billed on its real weight.', 'colisly' ); ?></p>
				<p class="description"><?php esc_html_e( 'Most carriers should be priced with a weight bracket grid, filled in under “Carrier weight brackets” below. The two prices in this table are the fallback: they apply to a carrier with no grid, and beyond the last bracket of a carrier that has one.', 'colisly' ); ?></p>
				<table class="widefat fixed striped colisly-carriers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Base price (beyond brackets)', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Price per kg (beyond brackets)', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Volumetric', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Divisor', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'colisly' ); ?></th>
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
							'volumetric'   => 0,
						); // Extra empty row to add a carrier.
						foreach ( $carriers as $i => $carrier ) :
							?>
							<tr>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-n-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Name', 'colisly' ); ?></label>
									<input type="text" id="colisly-carrier-n-<?php echo esc_attr( (string) $i ); ?>" name="carrier_name[]" value="<?php echo esc_attr( $carrier['name'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-s-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Slug', 'colisly' ); ?></label>
									<input type="text" id="colisly-carrier-s-<?php echo esc_attr( (string) $i ); ?>" name="carrier_slug[]" value="<?php echo esc_attr( $carrier['slug'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-b-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Base price', 'colisly' ); ?></label>
									<input type="number" step="0.01" min="0" id="colisly-carrier-b-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_base[]" value="<?php echo esc_attr( isset( $carrier['price_base'] ) ? (string) $carrier['price_base'] : '' ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-k-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Price per kg', 'colisly' ); ?></label>
									<input type="number" step="0.01" min="0" id="colisly-carrier-k-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_per_kg[]" value="<?php echo esc_attr( isset( $carrier['price_per_kg'] ) ? (string) $carrier['price_per_kg'] : '' ); ?>" />
								</td>
								<td>
									<input type="hidden" name="carrier_volumetric[]" value="<?php echo empty( $carrier['volumetric'] ) ? '0' : '1'; ?>" class="colisly-toggle-value" />
									<input type="checkbox" class="colisly-toggle" <?php checked( ! empty( $carrier['volumetric'] ) ); ?> aria-label="<?php esc_attr_e( 'Volumetric', 'colisly' ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-carrier-d-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Divisor', 'colisly' ); ?></label>
									<input type="number" step="1" min="1" id="colisly-carrier-d-<?php echo esc_attr( (string) $i ); ?>" name="carrier_divisor[]" value="<?php echo esc_attr( isset( $carrier['volumetric_divisor'] ) && $carrier['volumetric_divisor'] ? (string) (int) $carrier['volumetric_divisor'] : '5000' ); ?>" />
								</td>
								<td>
									<input type="hidden" name="carrier_enabled[]" value="<?php echo empty( $carrier['enabled'] ) ? '0' : '1'; ?>" class="colisly-toggle-value" />
									<input type="checkbox" class="colisly-toggle" <?php checked( ! empty( $carrier['enabled'] ) ); ?> aria-label="<?php esc_attr_e( 'Enabled', 'colisly' ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a carrier', 'colisly' ); ?></button></p>

				<h2><?php esc_html_e( 'Carrier weight brackets', 'colisly' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Carriers usually publish a grid of weight brackets rather than a price per kilo. Fill one in here and it replaces the base price and the price per kg for that carrier: the first bracket whose maximum weight is greater than or equal to the shipment weight sets the price. Leave a carrier empty and it keeps billing base price + price per kg. Beyond the last bracket that formula applies again, but it can only ever charge more than the last bracket, never less.', 'colisly' ); ?>
				</p>
				<?php
				$saved_carriers = is_array( $settings['carriers'] ) ? $settings['carriers'] : array();
				if ( ! $saved_carriers ) :
					?>
					<p><?php esc_html_e( 'Add a carrier and save to configure its brackets. A zone you have just created appears here after saving too.', 'colisly' ); ?></p>
					<?php
				endif;
				foreach ( $saved_carriers as $carrier ) :
					if ( empty( $carrier['slug'] ) ) {
						continue;
					}
					$slug        = $carrier['slug'];
					$c_tiers     = isset( $carrier['tiers'] ) && is_array( $carrier['tiers'] ) ? $carrier['tiers'] : array();
					$c_tiers[]   = array(
						'max_weight' => '',
						'price'      => '',
					); // Extra empty row to add a bracket.
					?>
					<h3><?php echo esc_html( $carrier['name'] ); ?></h3>
					<table class="widefat fixed striped colisly-tiers-table colisly-carrier-tiers-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></th>
								<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $c_tiers as $j => $c_tier ) : ?>
								<tr>
									<td>
										<label class="screen-reader-text" for="colisly-ct-w-<?php echo esc_attr( $slug . '-' . $j ); ?>"><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></label>
										<input type="number" id="colisly-ct-w-<?php echo esc_attr( $slug . '-' . $j ); ?>" name="carrier_tier_max_weight[<?php echo esc_attr( $slug ); ?>][]" step="0.001" min="0" value="<?php echo esc_attr( (string) $c_tier['max_weight'] ); ?>" />
									</td>
									<td>
										<label class="screen-reader-text" for="colisly-ct-p-<?php echo esc_attr( $slug . '-' . $j ); ?>"><?php esc_html_e( 'Price', 'colisly' ); ?></label>
										<input type="number" id="colisly-ct-p-<?php echo esc_attr( $slug . '-' . $j ); ?>" name="carrier_tier_price[<?php echo esc_attr( $slug ); ?>][]" step="0.01" min="0" value="<?php echo esc_attr( (string) $c_tier['price'] ); ?>" />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a bracket', 'colisly' ); ?></button></p>
					<?php
					// One extra grid per zone, so a carrier can be priced
					// differently for each destination group.
					foreach ( COLISLY_Zones::all() as $zone ) :
						$z_tiers   = isset( $carrier['zone_tiers'][ $zone['slug'] ] ) && is_array( $carrier['zone_tiers'][ $zone['slug'] ] ) ? $carrier['zone_tiers'][ $zone['slug'] ] : array();
						$z_tiers[] = array(
							'max_weight' => '',
							'price'      => '',
						);
						?>
						<h4>
							<?php
							printf(
								/* translators: 1: carrier name, 2: zone name. */
								esc_html__( '%1$s — zone %2$s', 'colisly' ),
								esc_html( $carrier['name'] ),
								esc_html( $zone['name'] )
							);
							?>
						</h4>
						<table class="widefat fixed striped colisly-tiers-table colisly-carrier-tiers-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></th>
									<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $z_tiers as $zt => $z_tier ) : ?>
									<tr>
										<td>
											<label class="screen-reader-text" for="colisly-zt-w-<?php echo esc_attr( $slug . '-' . $zone['slug'] . '-' . $zt ); ?>"><?php esc_html_e( 'Maximum weight (kg)', 'colisly' ); ?></label>
											<input type="number" id="colisly-zt-w-<?php echo esc_attr( $slug . '-' . $zone['slug'] . '-' . $zt ); ?>" name="carrier_zone_max_weight[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $zone['slug'] ); ?>][]" step="0.001" min="0" value="<?php echo esc_attr( (string) $z_tier['max_weight'] ); ?>" />
										</td>
										<td>
											<label class="screen-reader-text" for="colisly-zt-p-<?php echo esc_attr( $slug . '-' . $zone['slug'] . '-' . $zt ); ?>"><?php esc_html_e( 'Price', 'colisly' ); ?></label>
											<input type="number" id="colisly-zt-p-<?php echo esc_attr( $slug . '-' . $zone['slug'] . '-' . $zt ); ?>" name="carrier_zone_price[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $zone['slug'] ); ?>][]" step="0.01" min="0" value="<?php echo esc_attr( (string) $z_tier['price'] ); ?>" />
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a bracket', 'colisly' ); ?></button></p>
						<?php
					endforeach;
					?>
					<?php
				endforeach;
				?>

				<h2><?php esc_html_e( 'Customs declaration', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="colisly-customs-categories"><?php esc_html_e( 'Content categories', 'colisly' ); ?></label></th>
						<td>
							<textarea id="colisly-customs-categories" name="customs_categories" rows="6" class="large-text" placeholder="<?php esc_attr_e( "Clothing&#10;Shoes&#10;Books", 'colisly' ); ?>"><?php echo esc_textarea( (string) $settings['customs_categories'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One category per line. Leave empty and clients describe the contents in their own words, which is what a customs form asks for. Fill it and the field becomes a menu of your categories, quicker to fill and consistent across clients.', 'colisly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="colisly-customs-max"><?php esc_html_e( 'Maximum lines per parcel', 'colisly' ); ?></label></th>
						<td>
							<input type="number" id="colisly-customs-max" name="customs_max_lines" min="0" step="1" value="<?php echo esc_attr( (string) (int) $settings['customs_max_lines'] ); ?>" />
							<p class="description"><?php esc_html_e( '0 for no limit. Set it to the number of lines your carrier forms hold. The client is given exactly that many lines to fill.', 'colisly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ask the client for', 'colisly' ); ?></th>
						<td>
							<?php
							$colisly_customs_columns = array(
								'quantity' => __( 'Quantity', 'colisly' ),
								'weight'   => __( 'Unit weight (kg)', 'colisly' ),
								'origin'   => __( 'Country of origin', 'colisly' ),
							);
							foreach ( $colisly_customs_columns as $colisly_col => $colisly_label ) :
								$colisly_on = ! empty( $settings[ 'customs_ask_' . $colisly_col ] );
								?>
								<p>
									<input type="hidden" name="<?php echo esc_attr( 'customs_ask_' . $colisly_col ); ?>" value="<?php echo $colisly_on ? '1' : '0'; ?>" class="colisly-toggle-value" />
									<label>
										<input type="checkbox" class="colisly-toggle" <?php checked( $colisly_on ); ?> />
										<?php echo esc_html( $colisly_label ); ?>
									</label>
								</p>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'The contents and the value are always asked, since a declaration without them declares nothing. The other three are what a real CN23 form needs, line by line. Untick them if you only want to know what a parcel holds before copying it onto your carrier\'s own form: three columns a client fills for nothing are three columns he fills badly.', 'colisly' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Insurance', 'colisly' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Cover levels offered to the client when requesting a shipment: how much the parcel is covered for, and what that costs. Leave the table empty and no insurance is offered at all.', 'colisly' ); ?></p>
				<table class="widefat fixed striped colisly-tiers-table colisly-insurance-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Covered up to', 'colisly' ); ?></th>
							<th><?php esc_html_e( 'Price', 'colisly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$insurance   = COLISLY_Insurance::options();
						$insurance[] = array(
							'cover' => '',
							'price' => '',
						); // Extra empty row to add a level.
						foreach ( $insurance as $k => $level ) :
							?>
							<tr>
								<td>
									<label class="screen-reader-text" for="colisly-ins-c-<?php echo esc_attr( (string) $k ); ?>"><?php esc_html_e( 'Covered up to', 'colisly' ); ?></label>
									<input type="number" id="colisly-ins-c-<?php echo esc_attr( (string) $k ); ?>" name="insurance_cover[]" step="0.01" min="0" value="<?php echo esc_attr( (string) $level['cover'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="colisly-ins-p-<?php echo esc_attr( (string) $k ); ?>"><?php esc_html_e( 'Price', 'colisly' ); ?></label>
									<input type="number" id="colisly-ins-p-<?php echo esc_attr( (string) $k ); ?>" name="insurance_price[]" step="0.01" min="0" value="<?php echo esc_attr( (string) $level['price'] ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button colisly-add-row"><?php esc_html_e( 'Add a cover level', 'colisly' ); ?></button></p>

				<h2><?php esc_html_e( 'Orders', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxes', 'colisly' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="orders_taxable" value="1" <?php checked( ! empty( $settings['orders_taxable'] ) ); ?> />
								<?php esc_html_e( 'Apply the shop taxes to shipment orders (parcels, storage and transport lines)', 'colisly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When unchecked, shipment orders are created tax-free.', 'colisly' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'colisly' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'E-mails', 'colisly' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_client_on_parcel" value="1" <?php checked( ! empty( $settings['notify_client_on_parcel'] ) ); ?> />
								<?php esc_html_e( 'Notify the client when a parcel is received', 'colisly' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="notify_admin_on_request" value="1" <?php checked( ! empty( $settings['notify_admin_on_request'] ) ); ?> />
								<?php esc_html_e( 'Notify the administrator when a shipment is requested', 'colisly' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="send_invoice_on_request" value="1" <?php checked( ! empty( $settings['send_invoice_on_request'] ) ); ?> />
								<?php esc_html_e( 'Send the WooCommerce invoice (with payment link) when a shipment is requested', 'colisly' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'colisly' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves the settings form.
	 *
	 * @return void
	 */
	/**
	 * Validates a grid of weight brackets posted as two parallel arrays.
	 *
	 * Shared by the parcel pricing tiers and by each carrier's own brackets,
	 * which follow exactly the same rules.
	 *
	 * @param array $weights Maximum weights, as posted.
	 * @param array $prices  Prices, as posted.
	 * @return array[] Brackets: max_weight, price.
	 */
	private static function sanitize_tiers( $weights, $prices ) {
		$tiers   = array();
		$weights = array_values( $weights );
		$prices  = array_values( $prices );

		foreach ( $weights as $i => $weight ) {
			$weight = is_scalar( $weight ) ? sanitize_text_field( (string) $weight ) : '';
			$price  = isset( $prices[ $i ] ) && is_scalar( $prices[ $i ] ) ? sanitize_text_field( (string) $prices[ $i ] ) : '';

			if ( '' === $weight || '' === $price ) {
				continue;
			}

			// A bracket capped at zero can never match a shipment, and silently
			// shifts everything to the next bracket. Drop it like an empty row.
			if ( (float) $weight <= 0 ) {
				continue;
			}

			$tiers[] = array(
				'max_weight' => max( 0, (float) $weight ),
				'price'      => max( 0, (float) $price ),
			);
		}

		return $tiers;
	}

	/**
	 * Validates the per-zone grids posted for one carrier.
	 *
	 * A zone that no longer exists is dropped rather than kept as an orphan
	 * grid nobody can see or edit again.
	 *
	 * @param array $weights Maximum weights, keyed by zone slug.
	 * @param array $prices  Prices, keyed by zone slug.
	 * @param array $zones   Zones being saved.
	 * @return array Grids keyed by zone slug, empty grids omitted.
	 */
	private static function sanitize_zone_tiers( $weights, $prices, $zones ) {
		$known = wp_list_pluck( $zones, 'slug' );
		$grids = array();

		foreach ( $weights as $zone_slug => $zone_weights ) {
			$zone_slug = sanitize_key( $zone_slug );

			if ( ! in_array( $zone_slug, $known, true ) ) {
				continue;
			}

			$grid = self::sanitize_tiers(
				(array) $zone_weights,
				isset( $prices[ $zone_slug ] ) ? (array) $prices[ $zone_slug ] : array()
			);

			if ( $grid ) {
				$grids[ $zone_slug ] = $grid;
			}
		}

		return $grids;
	}

	public static function handle_save() {
		if ( ! current_user_can( 'colisly_manage' ) ) {
			wp_die( esc_html__( 'Access denied.', 'colisly' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'colisly_save_settings' );

		$settings = COLISLY_Settings::all();

		$settings['free_storage_days']   = isset( $_POST['free_storage_days'] ) ? absint( $_POST['free_storage_days'] ) : 15;
		$settings['storage_fee_per_day'] = isset( $_POST['storage_fee_per_day'] ) ? max( 0, (float) $_POST['storage_fee_per_day'] ) : 0;
		$settings['price_base']          = isset( $_POST['price_base'] ) ? max( 0, (float) $_POST['price_base'] ) : 0;
		$settings['price_per_kg']        = isset( $_POST['price_per_kg'] ) ? max( 0, (float) $_POST['price_per_kg'] ) : 0;

		$settings['pricing_tiers'] = self::sanitize_tiers(
			isset( $_POST['tier_max_weight'] ) ? (array) wp_unslash( $_POST['tier_max_weight'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
			isset( $_POST['tier_price'] ) ? (array) wp_unslash( $_POST['tier_price'] ) : array() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
		);

		// Zones first: the carrier grids below are keyed by zone slug, and a
		// slug only exists once its zone has been saved.
		$zone_names     = isset( $_POST['zone_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['zone_name'] ) ) : array();
		$zone_countries = isset( $_POST['zone_countries'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['zone_countries'] ) ) : array();
		$zone_customs   = isset( $_POST['zone_customs'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['zone_customs'] ) ) : array();
		$zones          = array();

		foreach ( $zone_names as $i => $zone_name ) {
			if ( '' === trim( $zone_name ) ) {
				continue;
			}
			$zones[] = array(
				'slug'      => sanitize_key( sanitize_title( $zone_name ) ),
				'name'      => $zone_name,
				'countries' => COLISLY_Zones::parse_countries( isset( $zone_countries[ $i ] ) ? $zone_countries[ $i ] : '' ),
				'customs'   => empty( $zone_customs[ $i ] ) ? 0 : 1,
			);
		}
		$settings['zones'] = $zones;

		$carriers = array();
		$names    = isset( $_POST['carrier_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_name'] ) ) : array();
		$slugs    = isset( $_POST['carrier_slug'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['carrier_slug'] ) ) : array();
		$enabled  = isset( $_POST['carrier_enabled'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['carrier_enabled'] ) ) : array();
		$bases    = isset( $_POST['carrier_price_base'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_price_base'] ) ) : array();
		$rates    = isset( $_POST['carrier_price_per_kg'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['carrier_price_per_kg'] ) ) : array();
		$vols     = isset( $_POST['carrier_volumetric'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['carrier_volumetric'] ) ) : array();
		$divisors = isset( $_POST['carrier_divisor'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['carrier_divisor'] ) ) : array();

		// Brackets are posted per carrier slug, since a carrier can be renamed
		// or reordered in the same save without its grid following the wrong row.
		$zone_weights = isset( $_POST['carrier_zone_max_weight'] ) ? (array) wp_unslash( $_POST['carrier_zone_max_weight'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
		$zone_prices  = isset( $_POST['carrier_zone_price'] ) ? (array) wp_unslash( $_POST['carrier_zone_price'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.

		$tier_weights = isset( $_POST['carrier_tier_max_weight'] ) ? (array) wp_unslash( $_POST['carrier_tier_max_weight'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
		$tier_prices  = isset( $_POST['carrier_tier_price'] ) ? (array) wp_unslash( $_POST['carrier_tier_price'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.

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
				'slug'               => $slug,
				'name'               => $name,
				'enabled'            => empty( $enabled[ $i ] ) ? 0 : 1,
				'price_base'         => isset( $bases[ $i ] ) ? max( 0, COLISLY_Parcels::to_float( $bases[ $i ] ) ) : 0,
				'price_per_kg'       => isset( $rates[ $i ] ) ? max( 0, COLISLY_Parcels::to_float( $rates[ $i ] ) ) : 0,
				'volumetric'         => empty( $vols[ $i ] ) ? 0 : 1,
				'volumetric_divisor' => isset( $divisors[ $i ] ) && $divisors[ $i ] > 0 ? (int) $divisors[ $i ] : 5000,
				'tiers'              => self::sanitize_tiers(
					isset( $tier_weights[ $slug ] ) ? (array) $tier_weights[ $slug ] : array(),
					isset( $tier_prices[ $slug ] ) ? (array) $tier_prices[ $slug ] : array()
				),
				'zone_tiers'         => self::sanitize_zone_tiers(
					isset( $zone_weights[ $slug ] ) ? (array) $zone_weights[ $slug ] : array(),
					isset( $zone_prices[ $slug ] ) ? (array) $zone_prices[ $slug ] : array(),
					$zones
				),
			);
		}
		$settings['carriers'] = $carriers;

		// Same shape and same validation as the weight brackets: a level
		// covering nothing can never be chosen, so it is dropped like an
		// empty row.
		$insurance = array();
		foreach ( self::sanitize_tiers(
			isset( $_POST['insurance_cover'] ) ? (array) wp_unslash( $_POST['insurance_cover'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
			isset( $_POST['insurance_price'] ) ? (array) wp_unslash( $_POST['insurance_price'] ) : array() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per value below.
		) as $level ) {
			$insurance[] = array(
				'cover' => $level['max_weight'],
				'price' => $level['price'],
			);
		}
		$settings['insurance_options'] = $insurance;

		$settings['customs_categories'] = isset( $_POST['customs_categories'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customs_categories'] ) ) : '';
		$settings['customs_max_lines']  = isset( $_POST['customs_max_lines'] ) ? absint( $_POST['customs_max_lines'] ) : 0;

		foreach ( array( 'quantity', 'weight', 'origin' ) as $colisly_col ) {
			$key                = 'customs_ask_' . $colisly_col;
			$settings[ $key ] = isset( $_POST[ $key ] ) && '1' === (string) wp_unslash( $_POST[ $key ] ) ? 1 : 0;
		}

		$settings['orders_taxable']          = empty( $_POST['orders_taxable'] ) ? 0 : 1;
		$settings['notify_client_on_parcel'] = empty( $_POST['notify_client_on_parcel'] ) ? 0 : 1;
		$settings['notify_admin_on_request'] = empty( $_POST['notify_admin_on_request'] ) ? 0 : 1;
		$settings['send_invoice_on_request'] = empty( $_POST['send_invoice_on_request'] ) ? 0 : 1;

		COLISLY_Settings::update( $settings );

		COLISLY_Admin::redirect( 'colisly-settings', array(), __( 'Settings saved.', 'colisly' ) );
	}
}
