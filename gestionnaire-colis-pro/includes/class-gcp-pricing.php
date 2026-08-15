<?php
/**
 * Weight based pricing.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the price of a parcel from its weight.
 */
class GCP_Pricing {

	/**
	 * Calculates the price of a parcel based on its weight.
	 *
	 * Uses the configured pricing tiers first; when the weight exceeds the last
	 * tier, falls back to base price + price per kg.
	 *
	 * @param float $weight Weight in kg.
	 * @return float
	 */
	public static function price_for_weight( $weight ) {
		$weight = max( 0, (float) $weight );
		$tiers  = GCP_Settings::get( 'pricing_tiers', array() );
		$price  = null;

		if ( is_array( $tiers ) && ! empty( $tiers ) ) {
			usort(
				$tiers,
				static function ( $a, $b ) {
					return (float) $a['max_weight'] <=> (float) $b['max_weight'];
				}
			);

			foreach ( $tiers as $tier ) {
				if ( $weight <= (float) $tier['max_weight'] ) {
					$price = (float) $tier['price'];
					break;
				}
			}
		}

		if ( null === $price ) {
			$base  = (float) GCP_Settings::get( 'price_base', 0 );
			$rate  = (float) GCP_Settings::get( 'price_per_kg', 0 );
			$price = $base + ( $rate * $weight );
		}

		/**
		 * Filters the computed price of a parcel.
		 *
		 * @param float $price  Computed price.
		 * @param float $weight Parcel weight in kg.
		 */
		return (float) apply_filters( 'gcp_parcel_price', round( $price, 2 ), $weight );
	}
}
