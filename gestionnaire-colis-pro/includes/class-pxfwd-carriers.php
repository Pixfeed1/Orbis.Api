<?php
/**
 * Carriers registry.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the list of carriers configured in the plugin settings.
 */
class PXFWD_Carriers {

	/**
	 * Returns all configured carriers.
	 *
	 * @param bool $enabled_only Whether to return enabled carriers only.
	 * @return array[] List of carriers: slug, name, enabled.
	 */
	public static function all( $enabled_only = false ) {
		$carriers = PXFWD_Settings::get( 'carriers', array() );
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
		return apply_filters( 'pxfwd_carriers', $carriers, $enabled_only );
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
	 * Computes the transport price of a carrier for a total weight.
	 *
	 * Price = base price + (price per kg x weight).
	 *
	 * @param string $slug   Carrier slug.
	 * @param float  $weight Total weight in kg.
	 * @return float
	 */
	public static function price_for( $slug, $weight ) {
		$carrier = self::get( $slug );

		$base = $carrier && isset( $carrier['price_base'] ) ? (float) $carrier['price_base'] : 0.0;
		$rate = $carrier && isset( $carrier['price_per_kg'] ) ? (float) $carrier['price_per_kg'] : 0.0;

		$price = $base + ( $rate * max( 0, (float) $weight ) );

		/**
		 * Filters the transport price of a carrier.
		 *
		 * @param float  $price  Computed price.
		 * @param string $slug   Carrier slug.
		 * @param float  $weight Total weight in kg.
		 */
		return (float) apply_filters( 'pxfwd_carrier_price', round( $price, 2 ), $slug, (float) $weight );
	}

	/**
	 * Returns the enabled carriers allowed for a given parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return array[] Carriers allowed for this parcel.
	 */
	public static function for_parcel( $parcel ) {
		$allowed = PXFWD_Parcels::allowed_carrier_slugs( $parcel );

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
