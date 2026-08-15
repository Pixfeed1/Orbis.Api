<?php
/**
 * Admin settings screen.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the plugin settings.
 */
class GCP_Admin_Settings {

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		$settings = GCP_Settings::all();
		?>
		<div class="wrap gcp-wrap">
			<h1><?php esc_html_e( 'Réglages — Gestionnaire Colis Pro', 'gestionnaire-colis-pro' ); ?></h1>
			<?php GCP_Admin::maybe_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'gcp_save_settings' ); ?>
				<input type="hidden" name="action" value="gcp_save_settings" />

				<h2><?php esc_html_e( 'Stockage', 'gestionnaire-colis-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gcp-free-days"><?php esc_html_e( 'Jours de stockage gratuits', 'gestionnaire-colis-pro' ); ?></label></th>
						<td>
							<input type="number" id="gcp-free-days" name="free_storage_days" min="0" value="<?php echo esc_attr( (string) $settings['free_storage_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Chaque colis bénéficie de cette période de stockage gratuit à compter de sa réception (15 jours par défaut).', 'gestionnaire-colis-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gcp-fee-day"><?php esc_html_e( 'Frais de stockage par jour et par colis', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><input type="number" id="gcp-fee-day" name="storage_fee_per_day" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['storage_fee_per_day'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tarification au poids', 'gestionnaire-colis-pro' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Le tarif d’un colis est déterminé par le premier palier dont le poids maximal est supérieur ou égal au poids du colis. Au-delà du dernier palier : prix de base + prix par kg.', 'gestionnaire-colis-pro' ); ?></p>
				<table class="widefat fixed striped gcp-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Poids maximal (kg)', 'gestionnaire-colis-pro' ); ?></th>
							<th><?php esc_html_e( 'Prix', 'gestionnaire-colis-pro' ); ?></th>
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
									<label class="screen-reader-text" for="gcp-tier-w-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Poids maximal (kg)', 'gestionnaire-colis-pro' ); ?></label>
									<input type="number" id="gcp-tier-w-<?php echo esc_attr( (string) $i ); ?>" name="tier_max_weight[]" step="0.001" min="0" value="<?php echo esc_attr( (string) $tier['max_weight'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="gcp-tier-p-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Prix', 'gestionnaire-colis-pro' ); ?></label>
									<input type="number" id="gcp-tier-p-<?php echo esc_attr( (string) $i ); ?>" name="tier_price[]" step="0.01" min="0" value="<?php echo esc_attr( (string) $tier['price'] ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gcp-price-base"><?php esc_html_e( 'Prix de base (hors paliers)', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><input type="number" id="gcp-price-base" name="price_base" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_base'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gcp-price-kg"><?php esc_html_e( 'Prix par kg (hors paliers)', 'gestionnaire-colis-pro' ); ?></label></th>
						<td><input type="number" id="gcp-price-kg" name="price_per_kg" min="0" step="0.01" value="<?php echo esc_attr( (string) $settings['price_per_kg'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Transporteurs', 'gestionnaire-colis-pro' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Un transporteur désactivé n’est plus proposé, ni à la réception des colis, ni aux clients.', 'gestionnaire-colis-pro' ); ?></p>
				<table class="widefat fixed striped gcp-carriers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nom', 'gestionnaire-colis-pro' ); ?></th>
							<th><?php esc_html_e( 'Identifiant', 'gestionnaire-colis-pro' ); ?></th>
							<th><?php esc_html_e( 'Prix de base', 'gestionnaire-colis-pro' ); ?></th>
							<th><?php esc_html_e( 'Prix par kg', 'gestionnaire-colis-pro' ); ?></th>
							<th><?php esc_html_e( 'Actif', 'gestionnaire-colis-pro' ); ?></th>
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
									<label class="screen-reader-text" for="gcp-carrier-n-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Nom', 'gestionnaire-colis-pro' ); ?></label>
									<input type="text" id="gcp-carrier-n-<?php echo esc_attr( (string) $i ); ?>" name="carrier_name[]" value="<?php echo esc_attr( $carrier['name'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="gcp-carrier-s-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Identifiant', 'gestionnaire-colis-pro' ); ?></label>
									<input type="text" id="gcp-carrier-s-<?php echo esc_attr( (string) $i ); ?>" name="carrier_slug[]" value="<?php echo esc_attr( $carrier['slug'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="gcp-carrier-b-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Prix de base', 'gestionnaire-colis-pro' ); ?></label>
									<input type="number" step="0.01" min="0" id="gcp-carrier-b-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_base[]" value="<?php echo esc_attr( isset( $carrier['price_base'] ) ? (string) $carrier['price_base'] : '' ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text" for="gcp-carrier-k-<?php echo esc_attr( (string) $i ); ?>"><?php esc_html_e( 'Prix par kg', 'gestionnaire-colis-pro' ); ?></label>
									<input type="number" step="0.01" min="0" id="gcp-carrier-k-<?php echo esc_attr( (string) $i ); ?>" name="carrier_price_per_kg[]" value="<?php echo esc_attr( isset( $carrier['price_per_kg'] ) ? (string) $carrier['price_per_kg'] : '' ); ?>" />
								</td>
								<td>
									<input type="hidden" name="carrier_enabled[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo empty( $carrier['enabled'] ) ? '0' : '1'; ?>" class="gcp-carrier-enabled-value" />
									<input type="checkbox" class="gcp-carrier-enabled" <?php checked( ! empty( $carrier['enabled'] ) ); ?> aria-label="<?php esc_attr_e( 'Actif', 'gestionnaire-colis-pro' ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'gestionnaire-colis-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'E-mails', 'gestionnaire-colis-pro' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_client_on_parcel" value="1" <?php checked( ! empty( $settings['notify_client_on_parcel'] ) ); ?> />
								<?php esc_html_e( 'Prévenir le client à la réception d’un colis', 'gestionnaire-colis-pro' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="notify_admin_on_request" value="1" <?php checked( ! empty( $settings['notify_admin_on_request'] ) ); ?> />
								<?php esc_html_e( 'Prévenir l’administrateur lors d’une demande d’expédition', 'gestionnaire-colis-pro' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="send_invoice_on_request" value="1" <?php checked( ! empty( $settings['send_invoice_on_request'] ) ); ?> />
								<?php esc_html_e( 'Envoyer la facture WooCommerce (avec lien de paiement) lors d’une demande d’expédition', 'gestionnaire-colis-pro' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Enregistrer les réglages', 'gestionnaire-colis-pro' ) ); ?>
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
		if ( ! current_user_can( 'gcp_manage' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gestionnaire-colis-pro' ) );
		}

		check_admin_referer( 'gcp_save_settings' );

		$settings = GCP_Settings::all();

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
				'price_base'   => isset( $bases[ $i ] ) ? max( 0, GCP_Parcels::to_float( $bases[ $i ] ) ) : 0,
				'price_per_kg' => isset( $rates[ $i ] ) ? max( 0, GCP_Parcels::to_float( $rates[ $i ] ) ) : 0,
			);
		}
		$settings['carriers'] = $carriers;

		$settings['notify_client_on_parcel'] = empty( $_POST['notify_client_on_parcel'] ) ? 0 : 1;
		$settings['notify_admin_on_request'] = empty( $_POST['notify_admin_on_request'] ) ? 0 : 1;
		$settings['send_invoice_on_request'] = empty( $_POST['send_invoice_on_request'] ) ? 0 : 1;

		GCP_Settings::update( $settings );

		GCP_Admin::redirect( 'gcp-settings', array(), __( 'Réglages enregistrés.', 'gestionnaire-colis-pro' ) );
	}
}
