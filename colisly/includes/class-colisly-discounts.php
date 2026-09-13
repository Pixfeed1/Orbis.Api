<?php
/**
 * Discounts on the handling and storage fees.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides what discount a client gets on a shipment.
 *
 * Three sources, each a percentage of the fees the forwarder charges for
 * his own work: a personal rate on the client record, a shop-wide promotion
 * between two dates, optionally behind a code the client has to type, and a
 * loyalty rate once the client has had enough shipments done. Each one says
 * what it applies to: the handling fees (the price of the parcels), the
 * storage fees, or both. The one that saves the client the most applies
 * alone: discounts never add up. Nothing else on the shipment is ever
 * discounted, not the transport, not the fees advanced, not the insurance.
 */
class COLISLY_Discounts {

	/**
	 * What a discount can apply to.
	 *
	 * @return array Map of scope key => label.
	 */
	public static function scopes() {
		return array(
			'handling' => __( 'Handling fees', 'colisly' ),
			'storage'  => __( 'Storage fees', 'colisly' ),
			'both'     => __( 'Handling and storage fees', 'colisly' ),
		);
	}

	/**
	 * Keeps a scope only when it is a known one.
	 *
	 * @param mixed $scope Raw value.
	 * @return string
	 */
	public static function scope( $scope ) {
		$scope = sanitize_key( (string) $scope );

		return array_key_exists( $scope, self::scopes() ) ? $scope : 'handling';
	}

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
	 * Whether a promotion is running today, whatever its code.
	 *
	 * A promotion with no start date has already started, one with no end
	 * date never ends: an empty bound is an open one.
	 *
	 * @param string $today Optional date (Y-m-d) to evaluate against, for tests.
	 * @return bool
	 */
	public static function promo_running( $today = '' ) {
		if ( self::rate( COLISLY_Settings::get( 'promo_rate', 0 ) ) <= 0 ) {
			return false;
		}

		$today = '' === $today ? current_time( 'Y-m-d' ) : $today;
		$start = (string) COLISLY_Settings::get( 'promo_start', '' );
		$end   = (string) COLISLY_Settings::get( 'promo_end', '' );

		if ( '' !== $start && $today < $start ) {
			return false;
		}
		if ( '' !== $end && $today > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * The code the promotion asks for, empty when it asks for none.
	 *
	 * @return string
	 */
	public static function promo_code() {
		return self::normalize_code( COLISLY_Settings::get( 'promo_code', '' ) );
	}

	/**
	 * Whether a code typed by a client unlocks the promotion.
	 *
	 * Compared without regard to case or surrounding spaces: a code is read
	 * off a mailing and typed by hand, not pasted from a form.
	 *
	 * @param string $code Code typed by the client.
	 * @return bool True when the promotion asks for no code, or for this one.
	 */
	public static function code_matches( $code ) {
		$expected = self::promo_code();

		return '' === $expected || self::normalize_code( $code ) === $expected;
	}

	/**
	 * Trims and uppercases a code so two spellings of it compare equal.
	 *
	 * @param mixed $code Raw value.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		return strtoupper( trim( sanitize_text_field( (string) $code ) ) );
	}

	/**
	 * The shop-wide promotion rate, when a promotion is running today and
	 * the code, if it asks for one, was given.
	 *
	 * @param string $today Optional date (Y-m-d) to evaluate against, for tests.
	 * @param string $code  Code typed by the client, if any.
	 * @return float Percentage, 0 outside the promotion or without the code.
	 */
	public static function promo_rate( $today = '', $code = '' ) {
		if ( ! self::promo_running( $today ) || ! self::code_matches( $code ) ) {
			return 0.0;
		}

		return self::rate( COLISLY_Settings::get( 'promo_rate', 0 ) );
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
	 * Every discount a client could get, each with its rate and scope.
	 *
	 * Which one actually applies depends on the fees of the shipment, so the
	 * request form receives them all and the same rule as best() picks one
	 * as the client ticks parcels.
	 *
	 * @param object $client Client row.
	 * @param string $code   Promotion code typed by the client, if any.
	 * @return array[] Each with kind, rate, scope and label; empty when none.
	 */
	public static function candidates( $client, $code = '' ) {
		$candidates = array();

		$rate = self::client_rate( $client );
		if ( $rate > 0 ) {
			$candidates[] = self::candidate( 'client', $rate, isset( $client->discount_scope ) ? $client->discount_scope : 'handling' );
		}

		$rate = self::promo_rate( '', $code );
		if ( $rate > 0 ) {
			$candidates[] = self::candidate( 'promo', $rate, COLISLY_Settings::get( 'promo_scope', 'handling' ) );
		}

		$rate = self::loyalty_rate( $client );
		if ( $rate > 0 ) {
			$candidates[] = self::candidate( 'loyalty', $rate, COLISLY_Settings::get( 'loyalty_scope', 'handling' ) );
		}

		/**
		 * Filters the discounts a client could get on a shipment.
		 *
		 * @param array[] $candidates Each with kind, rate, scope and label.
		 * @param object  $client     Client row.
		 * @param string  $code       Promotion code typed by the client.
		 */
		return apply_filters( 'colisly_client_discounts', $candidates, $client, $code );
	}

	/**
	 * Builds one candidate.
	 *
	 * @param string $kind  'client', 'promo' or 'loyalty'.
	 * @param float  $rate  Percentage.
	 * @param string $scope What it applies to.
	 * @return array
	 */
	private static function candidate( $kind, $rate, $scope ) {
		$scope = self::scope( $scope );

		return array(
			'kind'  => $kind,
			'rate'  => self::rate( $rate ),
			'scope' => $scope,
			'label' => self::label( $kind, $rate, $scope ),
		);
	}

	/**
	 * The discount applied to a shipment: the candidate that takes the most off.
	 *
	 * On a tie the first one wins, so the personal rate goes before the
	 * promotion, and the promotion before the loyalty rate.
	 *
	 * @param object $client   Client row.
	 * @param float  $handling Sum of the parcel prices.
	 * @param float  $storage  Storage fees of the shipment.
	 * @param string $code     Promotion code typed by the client, if any.
	 * @return array {
	 *     @type float  $amount Money taken off, 0 when nothing applies.
	 *     @type float  $rate   Percentage.
	 *     @type string $kind   'client', 'promo', 'loyalty' or ''.
	 *     @type string $scope  What it applies to, '' when nothing applies.
	 *     @type string $label  Human label naming the discount, its rate and its scope.
	 * }
	 */
	public static function best( $client, $handling, $storage, $code = '' ) {
		$best = array(
			'amount' => 0.0,
			'rate'   => 0.0,
			'kind'   => '',
			'scope'  => '',
			'label'  => '',
		);

		foreach ( self::candidates( $client, $code ) as $candidate ) {
			$amount = self::amount( self::base( $candidate['scope'], $handling, $storage ), $candidate['rate'] );

			if ( $amount > $best['amount'] ) {
				$best = array_merge( $candidate, array( 'amount' => $amount ) );
			}
		}

		return $best;
	}

	/**
	 * What a scope is a percentage of.
	 *
	 * @param string $scope    Scope key.
	 * @param float  $handling Sum of the parcel prices.
	 * @param float  $storage  Storage fees.
	 * @return float
	 */
	public static function base( $scope, $handling, $storage ) {
		switch ( self::scope( $scope ) ) {
			case 'storage':
				return max( 0.0, (float) $storage );
			case 'both':
				return max( 0.0, (float) $handling ) + max( 0.0, (float) $storage );
			default:
				return max( 0.0, (float) $handling );
		}
	}

	/**
	 * Names a discount for the order line and the request form.
	 *
	 * @param string $kind  Source of the discount.
	 * @param float  $rate  Percentage.
	 * @param string $scope What it applies to.
	 * @return string
	 */
	public static function label( $kind, $rate, $scope = 'handling' ) {
		$rate = self::format_rate( $rate );

		switch ( $kind ) {
			case 'promo':
				/* translators: %s: discount rate. */
				$label = sprintf( __( 'Promotion %s%%', 'colisly' ), $rate );
				break;
			case 'loyalty':
				/* translators: %s: discount rate. */
				$label = sprintf( __( 'Loyalty discount %s%%', 'colisly' ), $rate );
				break;
			default:
				/* translators: %s: discount rate. */
				$label = sprintf( __( 'Client discount %s%%', 'colisly' ), $rate );
		}

		// The handling fees are the usual case and stay unsaid; the others
		// are named, since a client reading "Promotion 100%" on a shipment
		// billed 30 would otherwise wonder what was free.
		switch ( self::scope( $scope ) ) {
			case 'storage':
				/* translators: %s: name of the discount and its rate, e.g. "Promotion 10%". */
				return sprintf( __( '%s on storage fees', 'colisly' ), $label );
			case 'both':
				/* translators: %s: name of the discount and its rate, e.g. "Promotion 10%". */
				return sprintf( __( '%s on handling and storage fees', 'colisly' ), $label );
			default:
				return $label;
		}
	}

	/**
	 * The amount taken off a fees total.
	 *
	 * @param float $fees Sum the discount applies to.
	 * @param float $rate Percentage.
	 * @return float Never more than the fees themselves.
	 */
	public static function amount( $fees, $rate ) {
		$fees = max( 0.0, (float) $fees );
		$rate = self::rate( $rate );

		return min( $fees, round( $fees * $rate / 100, 2 ) );
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
