<?php
/**
 * Carriers registry.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the list of carriers configured in the plugin settings.
 */
class COLISLY_Carriers {

	/**
	 * Returns all configured carriers.
	 *
	 * @param bool $enabled_only Whether to return enabled carriers only.
	 * @return array[] List of carriers: slug, name, enabled.
	 */
	public static function all( $enabled_only = false ) {
		$carriers = COLISLY_Settings::get( 'carriers', array() );
		$carriers = is_array( $carriers ) ? $carriers : array();

		if ( $enabled_only ) {
			$carriers = array_values(
				array_filter(
					$carriers,
					static function ( $carrier ) {
						return ! empty( $carrier['enabled'] );
					}
				)
			);
		}

		/**
		 * Filters the list of carriers.
		 *
		 * @param array $carriers     Carriers.
		 * @param bool  $enabled_only Whether disabled carriers were filtered out.
		 */
		return apply_filters( 'colisly_carriers', $carriers, $enabled_only );
	}

	/**
	 * Returns a carrier name from its slug.
	 *
	 * @param string $slug Carrier slug.
	 * @return string
	 */
	public static function name( $slug ) {
		foreach ( self::all() as $carrier ) {
			if ( $carrier['slug'] === $slug ) {
				return $carrier['name'];
			}
		}

		return $slug;
	}

	/**
	 * Returns a carrier row by slug.
	 *
	 * @param string $slug Carrier slug.
	 * @return array|null
	 */
	public static function get( $slug ) {
		foreach ( self::all() as $carrier ) {
			if ( $carrier['slug'] === $slug ) {
				return $carrier;
			}
		}

		return null;
	}

	/**
	 * Returns the weight brackets configured for a carrier, sorted.
	 *
	 * @param array|null $carrier Carrier row.
	 * @return array[] Brackets: max_weight, price.
	 */
	private static function tiers( $carrier ) {
		$tiers = $carrier && isset( $carrier['tiers'] ) && is_array( $carrier['tiers'] ) ? $carrier['tiers'] : array();

		usort(
			$tiers,
			static function ( $a, $b ) {
				return (float) $a['max_weight'] <=> (float) $b['max_weight'];
			}
		);

		return $tiers;
	}

	/**
	 * Computes the transport price of a carrier for a total weight.
	 *
	 * Carriers rarely bill per kilo: they publish a grid of weight brackets,
	 * where a 6 kg parcel and a 15 kg one can be priced far apart without any
	 * straight line joining them. So the brackets are used first, exactly like
	 * parcel pricing: the first bracket whose maximum weight is greater than or
	 * equal to the weight wins. Beyond the last bracket, or when a carrier has
	 * no brackets at all, the price falls back to base price + price per kg,
	 * which is what every carrier configured before brackets existed still uses.
	 *
	 * @param string $slug   Carrier slug.
	 * @param float  $weight Total weight in kg.
	 * @return float
	 */
	public static function price_for( $slug, $weight ) {
		$carrier = self::get( $slug );
		$weight  = max( 0, (float) $weight );
		$price   = null;

		foreach ( self::tiers( $carrier ) as $tier ) {
			if ( $weight <= (float) $tier['max_weight'] ) {
				$price = (float) $tier['price'];
				break;
			}
		}

		if ( null === $price ) {
			$base  = $carrier && isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0.0;
			$rate  = $carrier && isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0.0;
			$price = $base + ( $rate * $weight );

			// A grid stopping at 15 kg with a modest price per kg would have
			// made a 16 kg shipment cheaper than a 15 kg one. Past the last
			// bracket the formula may only ever charge more, never less.
			$tiers = self::tiers( $carrier );
			if ( $tiers ) {
				$last  = end( $tiers );
				$price = max( $price, (float) $last['price'] );
			}
		}

		/**
		 * Filters the transport price of a carrier.
		 *
		 * @param float  $price  Computed price.
		 * @param string $slug   Carrier slug.
		 * @param float  $weight Total weight in kg.
		 */
		return (float) apply_filters( 'colisly_carrier_price', round( $price, 2 ), $slug, (float) $weight );
	}

	/**
	 * Returns the enabled carriers allowed for a given parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return array[] Carriers allowed for this parcel.
	 */
	public static function for_parcel( $parcel ) {
		$allowed = COLISLY_Parcels::allowed_carrier_slugs( $parcel );

		return array_values(
			array_filter(
				self::all( true ),
				static function ( $carrier ) use ( $allowed ) {
					return empty( $allowed ) || in_array( $carrier['slug'], $allowed, true );
				}
			)
		);
	}
}
