<?php
/**
 * Optional shipment insurance.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the insurance cover levels offered to clients.
 *
 * A level is a cover amount and what it costs, for instance 50 covered for 2.
 * The list is empty by default, and an empty list means insurance is simply
 * not offered: nothing appears on the request form and nothing is billed.
 */
class COLISLY_Insurance {

	/**
	 * Returns the configured cover levels, cheapest cover first.
	 *
	 * @return array[] Levels: cover, price.
	 */
	public static function options() {
		$options = COLISLY_Settings::get( 'insurance_options', array() );

		if ( ! is_array( $options ) ) {
			return array();
		}

		$clean = array();
		foreach ( $options as $option ) {
			if ( ! isset( $option['cover'] ) || (float) $option['cover'] <= 0 ) {
				continue;
			}
			$clean[] = array(
				'cover' => (float) $option['cover'],
				'price' => isset( $option['price'] ) ? max( 0, (float) $option['price'] ) : 0.0,
			);
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return $a['cover'] <=> $b['cover'];
			}
		);

		return $clean;
	}

	/**
	 * Whether insurance is offered at all.
	 *
	 * @return bool
	 */
	public static function offered() {
		return ! empty( self::options() );
	}

	/**
	 * Returns the level matching a cover amount, or null.
	 *
	 * The price is always read back from the settings rather than taken from
	 * the request, so a posted amount can never set what the client is billed.
	 *
	 * @param float|string $cover Cover amount chosen by the client.
	 * @return array|null Level: cover, price.
	 */
	public static function find( $cover ) {
		$cover = (float) $cover;

		if ( $cover <= 0 ) {
			return null;
		}

		foreach ( self::options() as $option ) {
			if ( abs( $option['cover'] - $cover ) < 0.001 ) {
				return $option;
			}
		}

		return null;
	}
}
