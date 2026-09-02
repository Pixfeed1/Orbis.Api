<?php
/**
 * Plugin settings accessor.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central access to plugin settings stored in the colisly_settings option.
 */
class COLISLY_Settings {

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'free_storage_days'       => 15,
			'storage_fee_per_day'     => 1.0,
			'price_base'              => 5.0,
			'price_per_kg'            => 2.5,
			'pricing_tiers'           => array(
				array(
					'max_weight' => 1,
					'price'      => 7.5,
				),
				array(
					'max_weight' => 5,
					'price'      => 15.0,
				),
				array(
					'max_weight' => 10,
					'price'      => 25.0,
				),
			),
			'carriers'                => array(
				array(
					'slug'         => 'dhl',
					'name'         => 'DHL',
					'enabled'      => 1,
					'price_base'   => 15.0,
					'price_per_kg' => 2.5,
					'tiers'        => array(),
				),
				array(
					'slug'         => 'ups',
					'name'         => 'UPS',
					'enabled'      => 1,
					'price_base'   => 14.0,
					'price_per_kg' => 2.2,
					'tiers'        => array(),
				),
				array(
					'slug'         => 'fedex',
					'name'         => 'FedEx',
					'enabled'      => 1,
					'price_base'   => 14.0,
					'price_per_kg' => 2.3,
					'tiers'        => array(),
				),
				array(
					'slug'         => 'colissimo',
					'name'         => 'Colissimo',
					'enabled'      => 1,
					'price_base'   => 8.0,
					'price_per_kg' => 1.5,
					'tiers'        => array(),
				),
			),
			'insurance_options'       => array(),
			'notify_client_on_parcel' => 1,
			'notify_admin_on_request' => 1,
			'send_invoice_on_request' => 1,
			'orders_taxable'          => 0,
		);
	}

	/**
	 * Returns all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$settings ) {
			$saved          = get_option( 'colisly_settings', array() );
			self::$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}

		return self::$settings;
	}

	/**
	 * Returns a single setting value.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$settings = self::all();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Persists settings and clears the cache.
	 *
	 * @param array $settings New settings.
	 * @return void
	 */
	public static function update( $settings ) {
		update_option( 'colisly_settings', $settings );
		self::$settings = null;
	}
}
