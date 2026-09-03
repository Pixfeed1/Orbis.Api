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
	 * Returns the physical limits of a carrier, 0 for none.
	 *
	 * @param array|null $carrier Carrier row.
	 * @return array { max_weight: float, max_length: float, max_girth: float }
	 */
	public static function limits( $carrier ) {
		return array(
			'max_weight' => $carrier && ! empty( $carrier['max_weight'] ) ? max( 0, (float) $carrier['max_weight'] ) : 0.0,
			'max_length' => $carrier && ! empty( $carrier['max_length'] ) ? max( 0, (float) $carrier['max_length'] ) : 0.0,
			'max_girth'  => $carrier && ! empty( $carrier['max_girth'] ) ? max( 0, (float) $carrier['max_girth'] ) : 0.0,
		);
	}

	/**
	 * Whether one parcel fits a carrier's dimension limits.
	 *
	 * Carriers publish a longest side and a girth, length plus twice the
	 * width plus twice the height, past which a parcel is refused at the
	 * counter whatever it weighs. A parcel whose dimensions were never
	 * entered is not refused on a measurement nobody took.
	 *
	 * @param string $slug   Carrier slug.
	 * @param object $parcel Parcel row.
	 * @return true|WP_Error
	 */
	public static function parcel_fits( $slug, $parcel ) {
		$carrier = self::get( $slug );
		$limits  = self::limits( $carrier );

		$dims = array( (float) $parcel->length, (float) $parcel->width, (float) $parcel->height );
		if ( min( $dims ) <= 0 ) {
			return true;
		}

		rsort( $dims );

		if ( $limits['max_length'] > 0 && $dims[0] > $limits['max_length'] ) {
			return new WP_Error(
				'colisly_parcel_too_long',
				sprintf(
					/* translators: 1: parcel reference, 2: carrier name, 3: parcel length, 4: limit. */
					__( 'Parcel %1$s is too long for %2$s: %3$s cm, limit %4$s cm.', 'colisly' ),
					$parcel->reference,
					self::name( $slug ),
					number_format_i18n( $dims[0], 1 ),
					number_format_i18n( $limits['max_length'], 1 )
				)
			);
		}

		$girth = $dims[0] + 2 * $dims[1] + 2 * $dims[2];
		if ( $limits['max_girth'] > 0 && $girth > $limits['max_girth'] ) {
			return new WP_Error(
				'colisly_parcel_too_big',
				sprintf(
					/* translators: 1: parcel reference, 2: carrier name, 3: parcel girth, 4: limit. */
					__( 'Parcel %1$s is too big for %2$s: length plus twice the width and height is %3$s cm, limit %4$s cm.', 'colisly' ),
					$parcel->reference,
					self::name( $slug ),
					number_format_i18n( $girth, 1 ),
					number_format_i18n( $limits['max_girth'], 1 )
				)
			);
		}

		return true;
	}

	/**
	 * Whether a set of parcels can travel with a carrier.
	 *
	 * The weight limit is checked on the whole shipment, since grouped
	 * parcels leave in one carton; the dimensions on each parcel, since the
	 * carton they will share is not known yet.
	 *
	 * @param string   $slug    Carrier slug.
	 * @param object[] $parcels Parcel rows.
	 * @return true|WP_Error
	 */
	public static function fits( $slug, $parcels ) {
		$carrier = self::get( $slug );
		$limits  = self::limits( $carrier );
		$weight  = 0.0;

		foreach ( (array) $parcels as $parcel ) {
			$fit = self::parcel_fits( $slug, $parcel );
			if ( is_wp_error( $fit ) ) {
				return $fit;
			}
			$weight += max( 0, (float) $parcel->weight );
		}

		if ( $limits['max_weight'] > 0 && $weight > $limits['max_weight'] + 0.0005 ) {
			return new WP_Error(
				'colisly_shipment_too_heavy',
				sprintf(
					/* translators: 1: carrier name, 2: shipment weight, 3: limit. */
					__( '%1$s does not take shipments over %3$s kg; this one weighs %2$s kg. Choose another carrier or split the shipment.', 'colisly' ),
					self::name( $slug ),
					number_format_i18n( $weight, 3 ),
					number_format_i18n( $limits['max_weight'], 3 )
				)
			);
		}

		return true;
	}

	/**
	 * Returns the enabled carriers whose dimension limits a parcel exceeds.
	 *
	 * @param object $parcel Parcel row.
	 * @return string[] Carrier slugs.
	 */
	public static function too_small_for( $parcel ) {
		$out = array();

		foreach ( self::all( true ) as $carrier ) {
			if ( is_wp_error( self::parcel_fits( $carrier['slug'], $parcel ) ) ) {
				$out[] = $carrier['slug'];
			}
		}

		return $out;
	}

	/**
	 * Whether a carrier has any rate at all for a destination.
	 *
	 * A carrier added to the list and never priced used to be offered at
	 * 0.00 and the order went through at that price: the fallback formula
	 * with a base of zero and a rate of zero is simply zero. Nothing is priced
	 * when no bracket applies to the destination and both fallback prices are
	 * empty. A bracket explicitly set to zero still counts as a price, since
	 * a forwarder who includes transport in his service typed it on purpose.
	 *
	 * @param string $slug    Carrier slug.
	 * @param string $country Destination ISO country code, empty for none.
	 * @return bool
	 */
	public static function is_priced( $slug, $country = '' ) {
		$carrier = self::get( $slug );

		if ( ! $carrier ) {
			return false;
		}

		if ( self::tiers( $carrier, $country ) ) {
			return true;
		}

		$base = isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0.0;
		$rate = isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0.0;

		return $base > 0 || $rate > 0;
	}

	/**
	 * Returns the enabled carriers that have no rate anywhere.
	 *
	 * @return array[] Carrier rows.
	 */
	public static function unpriced() {
		$list = array();

		foreach ( self::all( true ) as $carrier ) {
			$has_grid = ! empty( $carrier['tiers'] ) && is_array( $carrier['tiers'] );
			$has_zone = ! empty( $carrier['zone_tiers'] ) && is_array( $carrier['zone_tiers'] ) && array_filter( $carrier['zone_tiers'] );
			$base     = isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0.0;
			$rate     = isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0.0;

			if ( ! $has_grid && ! $has_zone && $base <= 0 && $rate <= 0 ) {
				$list[] = $carrier;
			}
		}

		return $list;
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
