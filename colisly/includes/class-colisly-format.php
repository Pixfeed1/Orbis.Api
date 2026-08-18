<?php
/**
 * Shared formatting helpers.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date and price formatting used by both admin and frontend.
 */
class COLISLY_Format {

	/**
	 * Formats a stored UTC datetime for display in the site timezone.
	 *
	 * @param string $datetime  MySQL datetime (UTC).
	 * @param bool   $with_time Whether to include the time.
	 * @return string
	 */
	public static function date( $datetime, $with_time = false ) {
		$timestamp = strtotime( $datetime . ' UTC' );

		if ( ! $timestamp ) {
			return '—';
		}

		$format = get_option( 'date_format' );
		if ( $with_time ) {
			$format .= ' ' . get_option( 'time_format' );
		}

		return wp_date( $format, $timestamp );
	}

	/**
	 * Formats a price using the WooCommerce currency when available.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function price( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}

		return number_format_i18n( (float) $amount, 2 );
	}
}
