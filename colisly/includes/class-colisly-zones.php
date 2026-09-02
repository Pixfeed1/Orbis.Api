<?php
/**
 * Destination zones.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups destination countries into zones a carrier can be priced against.
 *
 * A forwarder does not charge the same to reship to mainland France, to the
 * overseas departments and to Madagascar, so a single grid per carrier could
 * never hold real tariffs. A zone is a name and the countries it covers; a
 * carrier then gets one grid per zone. A country in no zone falls back to the
 * carrier's default grid, which is what every carrier configured before zones
 * existed already has.
 */
class COLISLY_Zones {

	/**
	 * Returns the configured zones.
	 *
	 * @return array[] Zones: slug, name, countries (array of ISO codes).
	 */
	public static function all() {
		$zones = COLISLY_Settings::get( 'zones', array() );

		if ( ! is_array( $zones ) ) {
			return array();
		}

		$clean = array();
		foreach ( $zones as $zone ) {
			$slug = isset( $zone['slug'] ) ? sanitize_key( $zone['slug'] ) : '';
			$name = isset( $zone['name'] ) ? sanitize_text_field( $zone['name'] ) : '';

			if ( '' === $slug && '' === $name ) {
				continue;
			}
			if ( '' === $slug ) {
				$slug = sanitize_key( sanitize_title( $name ) );
			}
			if ( '' === $name ) {
				$name = $slug;
			}

			$clean[] = array(
				'slug'      => $slug,
				'name'      => $name,
				'countries' => self::parse_countries( isset( $zone['countries'] ) ? $zone['countries'] : '' ),
				'customs'   => empty( $zone['customs'] ) ? 0 : 1,
			);
		}

		return $clean;
	}

	/**
	 * Turns a free-form country list into ISO codes.
	 *
	 * Accepts commas, spaces or line breaks, since a list of destinations is
	 * typed by hand and no two people type it the same way.
	 *
	 * @param string|array $countries Country codes.
	 * @return string[] Upper-case two letter codes.
	 */
	public static function parse_countries( $countries ) {
		if ( is_array( $countries ) ) {
			$countries = implode( ',', $countries );
		}

		$parts = preg_split( '/[\s,;]+/', (string) $countries );
		$codes = array();

		foreach ( (array) $parts as $part ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', $part ) );
			if ( 2 === strlen( $code ) && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
		}

		return $codes;
	}

	/**
	 * Returns the zone covering a country, or null.
	 *
	 * The first zone listing the country wins, so the order in the settings
	 * decides when someone puts a country in two zones.
	 *
	 * @param string $country ISO country code.
	 * @return array|null Zone.
	 */
	public static function for_country( $country ) {
		$country = strtoupper( substr( (string) $country, 0, 2 ) );

		if ( '' === $country ) {
			return null;
		}

		foreach ( self::all() as $zone ) {
			if ( in_array( $country, $zone['countries'], true ) ) {
				return $zone;
			}
		}

		return null;
	}

	/**
	 * Returns a zone by slug, or null.
	 *
	 * @param string $slug Zone slug.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$slug = sanitize_key( $slug );

		foreach ( self::all() as $zone ) {
			if ( $zone['slug'] === $slug ) {
				return $zone;
			}
		}

		return null;
	}
}
