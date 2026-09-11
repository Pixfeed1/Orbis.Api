<?php
/**
 * Shortcodes for the pages a forwarder builds themselves.
 *
 * @package Colisly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [colisly_client_reference] and [colisly_shipping_address].
 *
 * The account tab already shows both, but many forwarders write their own
 * "how to order" page and want the client's address right there.
 */
class COLISLY_Shortcodes {

	/**
	 * Registers the shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'colisly_client_reference', array( __CLASS__, 'client_reference' ) );
		add_shortcode( 'colisly_shipping_address', array( __CLASS__, 'shipping_address' ) );
	}

	/**
	 * The current user's client record, or null.
	 *
	 * @return object|null
	 */
	private static function client() {
		return is_user_logged_in() ? COLISLY_Clients::get_by_user( get_current_user_id() ) : null;
	}

	/**
	 * What a visitor without a client record sees instead.
	 *
	 * @return string HTML.
	 */
	private static function fallback() {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p class="colisly-shortcode-login"><a href="%s">%s</a></p>',
				esc_url( wp_login_url( (string) get_permalink() ) ),
				esc_html__( 'Log in to see your delivery address.', 'colisly' )
			);
		}

		return '<p class="colisly-shortcode-login">' . esc_html__( 'No client record is linked to your account yet.', 'colisly' ) . '</p>';
	}

	/**
	 * [colisly_client_reference]: the reference alone, inline.
	 *
	 * @return string
	 */
	public static function client_reference() {
		$client = self::client();

		return $client ? '<span class="colisly-client-reference">' . esc_html( $client->reference ) . '</span>' : '';
	}

	/**
	 * [colisly_shipping_address]: the full block with the copy button.
	 *
	 * @return string
	 */
	public static function shipping_address() {
		$client = self::client();

		if ( ! $client ) {
			return self::fallback();
		}

		COLISLY_Account::enqueue_front();

		return COLISLY_Account::address_block( $client );
	}
}
