<?php
/**
 * Storage fee computation.
 *
 * @package GestionnaireColisPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes automatic storage fees for parcels kept in the warehouse.
 */
class PXFWD_Storage {

	/**
	 * Returns the number of billable storage days for a parcel.
	 *
	 * Every parcel gets a configurable number of free days (15 by default);
	 * days beyond the free period are billable.
	 *
	 * @param object $parcel Parcel row.
	 * @return int
	 */
	public static function billable_days( $parcel ) {
		$free_days = (int) PXFWD_Settings::get( 'free_storage_days', 15 );
		$received  = strtotime( $parcel->received_at . ' UTC' );

		if ( ! $received ) {
			return 0;
		}

		$end = ! empty( $parcel->shipped_at ) ? strtotime( $parcel->shipped_at . ' UTC' ) : time();

		$days = (int) floor( max( 0, $end - $received ) / DAY_IN_SECONDS );

		return max( 0, $days - $free_days );
	}

	/**
	 * Returns the storage fees currently due for a parcel.
	 *
	 * @param object $parcel Parcel row.
	 * @return float
	 */
	public static function fees_for_parcel( $parcel ) {
		$per_day = (float) PXFWD_Settings::get( 'storage_fee_per_day', 0 );
		$fees    = self::billable_days( $parcel ) * $per_day;

		/**
		 * Filters the storage fees of a parcel.
		 *
		 * @param float  $fees   Computed fees.
		 * @param object $parcel Parcel row.
		 */
		return (float) apply_filters( 'pxfwd_parcel_storage_fees', round( $fees, 2 ), $parcel );
	}

	/**
	 * Returns the storage fees due for a set of parcels.
	 *
	 * @param object[] $parcels Parcel rows.
	 * @return float
	 */
	public static function fees_for_parcels( $parcels ) {
		$total = 0.0;

		foreach ( $parcels as $parcel ) {
			$total += self::fees_for_parcel( $parcel );
		}

		return round( $total, 2 );
	}
}
