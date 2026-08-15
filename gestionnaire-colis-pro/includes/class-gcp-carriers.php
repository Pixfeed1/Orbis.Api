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
class GCP_Carriers {

	/**
	 * Returns all configured carriers.
	 *
	 * @param bool $enabled_only Whether to return enabled carriers only.
	 * @return array[] List of carriers: slug, name, enabled.
	 */
	public static function all( $enabled_only = false ) {
		$carriers = GCP_Settings::get( 'carriers', array() );
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
		return apply_filters( 'gcp_carriers', $carriers, $enabled_only );
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
	 * Returns the enabled carriers allowed for a given parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return array[] Carriers allowed for this parcel.
	 */
	public static function for_parcel( $parcel ) {
		$allowed = GCP_Parcels::allowed_carrier_slugs( $parcel );

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
