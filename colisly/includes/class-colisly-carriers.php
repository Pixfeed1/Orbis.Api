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
	 * Returns the weight a carrier actually bills for a set of parcels.
	 *
	 * Express carriers price on volume rather than mass, because a light bulky
	 * parcel takes the room of a heavy one. They do not bill the volumetric
	 * weight instead of the real one though, they bill whichever is greater,
	 * parcel by parcel: a dense 20 kg box in a small carton would otherwise be
	 * charged as 3 kg.
	 *
	 * A parcel whose dimensions were not entered contributes its real weight.
	 * Treating a missing dimension as a zero volume would have billed the
	 * transport of that parcel at nothing.
	 *
	 * @param string   $slug    Carrier slug.
	 * @param object[] $parcels Parcel rows.
	 * @return float Weight to price on, in kg.
	 */
	public static function chargeable_weight( $slug, $parcels ) {
		$carrier = self::get( $slug );
		$total   = 0.0;

		$volumetric = $carrier && ! empty( $carrier['volumetric'] );
		$divisor    = $carrier && ! empty( $carrier['volumetric_divisor'] ) ? (float) $carrier['volumetric_divisor'] : 5000.0;

		if ( $divisor <= 0 ) {
			$divisor = 5000.0;
		}

		foreach ( (array) $parcels as $parcel ) {
			$real = max( 0, (float) $parcel->weight );

			if ( ! $volumetric ) {
				$total += $real;
				continue;
			}

			$length = (float) $parcel->length;
			$width  = (float) $parcel->width;
			$height = (float) $parcel->height;

			$volume = ( $length > 0 && $width > 0 && $height > 0 )
				? ( $length * $width * $height ) / $divisor
				: 0.0;

			$total += max( $real, $volume );
		}

		/**
		 * Filters the weight a carrier is priced on.
		 *
		 * @param float    $total   Chargeable weight in kg.
		 * @param string   $slug    Carrier slug.
		 * @param object[] $parcels Parcel rows.
		 */
		return (float) apply_filters( 'colisly_chargeable_weight', round( $total, 3 ), $slug, $parcels );
	}

	/**
	 * Returns the weight brackets a carrier applies to a destination, sorted.
	 *
	 * A destination falling in a zone the carrier has a grid for uses that
	 * grid. Everything else, including a country in no zone and a zone the
	 * carrier was never priced for, uses the carrier's default grid, which is
	 * the only one that existed before zones.
	 *
	 * @param array|null $carrier Carrier row.
	 * @param string     $country Destination ISO country code, empty for none.
	 * @return array[] Brackets: max_weight, price.
	 */
	private static function tiers( $carrier, $country = '' ) {
		$tiers = $carrier && isset( $carrier['tiers'] ) && is_array( $carrier['tiers'] ) ? $carrier['tiers'] : array();

		$zone = '' !== (string) $country ? COLISLY_Zones::for_country( $country ) : null;
		if ( $zone && ! empty( $carrier['zone_tiers'][ $zone['slug'] ] ) && is_array( $carrier['zone_tiers'][ $zone['slug'] ] ) ) {
			$tiers = $carrier['zone_tiers'][ $zone['slug'] ];
		}

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
	 * @param string $slug    Carrier slug.
	 * @param float  $weight  Total weight in kg.
	 * @param string $country Destination ISO country code, empty to use the
	 *                        carrier's default grid.
	 * @return float
	 */
	public static function price_for( $slug, $weight, $country = '' ) {
		$carrier = self::get( $slug );
		$weight  = max( 0, (float) $weight );
		$price   = null;

		foreach ( self::tiers( $carrier, $country ) as $tier ) {
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
			$tiers = self::tiers( $carrier, $country );
			if ( $tiers ) {
				$last  = end( $tiers );
				$price = max( $price, (float) $last['price'] );
			}
		}

		/**
		 * Filters the transport price of a carrier.
		 *
		 * @param float  $price   Computed price.
		 * @param string $slug    Carrier slug.
		 * @param float  $weight  Total weight in kg.
		 * @param string $country Destination ISO country code.
		 */
		return (float) apply_filters( 'colisly_carrier_price', round( $price, 2 ), $slug, (float) $weight, (string) $country );
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
