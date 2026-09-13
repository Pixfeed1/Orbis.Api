<?php
/**
 * Discounts on the handling fees.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides what discount a client gets on the handling fees of a shipment.
 *
 * Three sources, all expressed as a percentage of the parcel handling fees:
 * a personal rate on the client record, a shop-wide promotion between two
 * dates, and a loyalty rate once the client has had enough shipments done.
 * The highest one applies alone: rates never add up. Nothing else on the
 * shipment is ever discounted, not the transport, not the fees advanced,
 * not the storage, not the insurance.
 */
class COLISLY_Discounts {

	/**
	 * The personal rate written on the client record.
	 *
	 * @param object $client Client row.
	 * @return float Percentage, 0 when none.
	 */
	public static function client_rate( $client ) {
		return $client && isset( $client->discount_rate ) ? self::rate( $client->discount_rate ) : 0.0;
	}

	/**
	 * The shop-wide promotion rate, when a promotion is running today.
	 *
	 * A promotion with no start date has already started, one with no end
	 * date never ends: an empty bound is an open one.
	 *
	 * @param string $today Optional date (Y-m-d) to evaluate against, for tests.
	 * @return float Percentage, 0 outside the promotion.
	 */
	public static function promo_rate( $today = '' ) {
		$rate = self::rate( COLISLY_Settings::get( 'promo_rate', 0 ) );
		if ( $rate <= 0 ) {
			return 0.0;
		}

		$today = '' === $today ? current_time( 'Y-m-d' ) : $today;
		$start = (string) COLISLY_Settings::get( 'promo_start', '' );
		$end   = (string) COLISLY_Settings::get( 'promo_end', '' );

		if ( '' !== $start && $today < $start ) {
			return 0.0;
		}
		if ( '' !== $end && $today > $end ) {
			return 0.0;
		}

		return $rate;
	}

	/**
	 * The loyalty rate, once the client has had enough shipments done.
	 *
	 * @param object $client Client row.
	 * @return float Percentage, 0 below the threshold or when loyalty is off.
	 */
	public static function loyalty_rate( $client ) {
		$threshold = (int) COLISLY_Settings::get( 'loyalty_shipments', 0 );
		$rate      = self::rate( COLISLY_Settings::get( 'loyalty_rate', 0 ) );

		if ( $threshold <= 0 || $rate <= 0 || ! $client ) {
			return 0.0;
		}

		return self::shipments_done( (int) $client->id ) >= $threshold ? $rate : 0.0;
	}

	/**
	 * Counts the shipments done for a client: those that left the warehouse.
	 *
	 * @param int $client_id Client ID.
	 * @return int
	 */
	public static function shipments_done( $client_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}colisly_shipments WHERE client_id = %d AND status = 'shipped'",
				(int) $client_id
			)
		);
	}

	/**
	 * The discount in force for a client: the best of the three sources.
	 *
	 * @param object $client Client row.
	 * @return array {
	 *     @type float  $rate  Percentage, 0 when nothing applies.
	 *     @type string $kind  'client', 'promo', 'loyalty' or ''.
	 *     @type string $label Human label naming the discount and its rate.
	 * }
	 */
	public static function for_client( $client ) {
		$candidates = array(
			'client'  => self::client_rate( $client ),
			'promo'   => self::promo_rate(),
			'loyalty' => self::loyalty_rate( $client ),
		);

		$kind = '';
		$rate = 0.0;
		foreach ( $candidates as $candidate_kind => $candidate_rate ) {
			if ( $candidate_rate > $rate ) {
				$rate = $candidate_rate;
				$kind = $candidate_kind;
			}
		}

		$discount = array(
			'rate'  => $rate,
			'kind'  => $kind,
			'label' => $rate > 0 ? self::label( $kind, $rate ) : '',
		);

		/**
		 * Filters the discount applied to a client's handling fees.
		 *
		 * @param array  $discount Rate, kind and label.
		 * @param object $client   Client row.
		 */
		return apply_filters( 'colisly_client_discount', $discount, $client );
	}

	/**
	 * Names a discount for the order line and the request form.
	 *
	 * @param string $kind Source of the discount.
	 * @param float  $rate Percentage.
	 * @return string
	 */
	public static function label( $kind, $rate ) {
		$rate = self::format_rate( $rate );

		switch ( $kind ) {
			case 'promo':
				/* translators: %s: discount rate. */
				return sprintf( __( 'Promotion %s%%', 'colisly' ), $rate );
			case 'loyalty':
				/* translators: %s: discount rate. */
				return sprintf( __( 'Loyalty discount %s%%', 'colisly' ), $rate );
			default:
				/* translators: %s: discount rate. */
				return sprintf( __( 'Client discount %s%%', 'colisly' ), $rate );
		}
	}

	/**
	 * The amount taken off a handling fees total.
	 *
	 * @param float $handling_fees Sum of the parcel prices.
	 * @param float $rate          Percentage.
	 * @return float Never more than the fees themselves.
	 */
	public static function amount( $handling_fees, $rate ) {
		$handling_fees = max( 0.0, (float) $handling_fees );
		$rate          = self::rate( $rate );

		return min( $handling_fees, round( $handling_fees * $rate / 100, 2 ) );
	}

	/**
	 * A rate as displayed: "10" or "12.5", never "10.00".
	 *
	 * @param float $rate Percentage.
	 * @return string
	 */
	public static function format_rate( $rate ) {
		$text = rtrim( rtrim( number_format( self::rate( $rate ), 2, '.', '' ), '0' ), '.' );

		return function_exists( 'wc_format_localized_decimal' ) ? wc_format_localized_decimal( $text ) : $text;
	}

	/**
	 * Clamps a rate between 0 and 100.
	 *
	 * @param mixed $rate Raw value.
	 * @return float
	 */
	public static function rate( $rate ) {
		$rate = is_string( $rate ) ? COLISLY_Parcels::to_float( $rate ) : (float) $rate;

		return round( min( 100.0, max( 0.0, $rate ) ), 2 );
	}
}
