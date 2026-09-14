<?php
/**
 * Plugin settings and secrets.
 *
 * Non-secret values live in the autoloaded `pura_settings` option. Secrets live in
 * `pura_secrets` (not autoloaded) and are overridden by PURA_* constants in wp-config.php,
 * which is the recommended production setup.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION  = 'pura_settings';
	public const SECRETS = 'pura_secrets';

	/** @var string[] Keys stored in pura_secrets and overridable by constants. */
	public const SECRET_KEYS = array(
		'stripe_secret_key',
		'stripe_webhook_secret',
		'printful_api_key',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			// Pagos.
			'currency'                 => 'MXN',
			'store_page_id'            => 0,
			'inscriptions_page_id'     => 0,

			// Printful.
			'price_multiplier'         => 1.0,
			'catalog_cache_ttl'        => 300,

			// Precios (MXN cents).
			'monthly_adult'            => 115000,
			'monthly_kids'             => 100000,
			'inscription_fee'          => 150000,
			'trial_price'              => 20000,
			'quarter_discount_pct'     => 15,
			'year_discount_pct'        => 20,
			'current_monthly_adult'    => 75000,
			'current_monthly_kids'     => 100000,

			// Códigos.
			'promo_beca_code'          => 'BECADOPC26',
			'promo_current_code'       => 'ACTUALPC26',

			// Horarios.
			'schedule'                 => array(
				array(
					'group' => 'adult',
					'day'   => 'Martes',
					'start' => '17:30',
					'end'   => '19:30',
				),
				array(
					'group' => 'adult',
					'day'   => 'Jueves',
					'start' => '17:30',
					'end'   => '19:30',
				),
				array(
					'group' => 'kids',
					'day'   => 'Miércoles',
					'start' => '17:00',
					'end'   => '18:00',
				),
				array(
					'group' => 'kids',
					'day'   => 'Viernes',
					'start' => '17:00',
					'end'   => '18:00',
				),
			),

			// Notificaciones.
			'notify_emails'            => '',
			'notify_from_email'        => '',
			'notify_from_name'         => 'Pura Capoeira',
			'cc_student'               => true,

			// Contacto y redes.
			'whatsapp_number'          => '18056385603',
			'whatsapp_display'         => '+1 (805) 638-5603',
			'calendly_trial_adult_url' => 'https://calendly.com/codecharmer/clase-de-prueba',
			'calendly_trial_kids_url'  => 'https://calendly.com/codecharmer/clase-de-prueba-kids',
			'instagram_url'            => 'https://www.instagram.com/profesor.malandro/',
			'instagram_handle'         => '@profesor.malandro',
			'facebook_url'             => 'https://www.facebook.com/PuraCapoeiraCuernavaca/',
			'facebook_label'           => 'Pura Capoeira Cuernavaca',
			'address'                  => "San Jerónimo 503,\nTlaltenango, Cuernavaca,\nMorelos",
			'address_short'            => 'San Jerónimo 503, Tlaltenango',
			'maps_url'                 => 'https://www.google.com/maps/search/?api=1&query=San%20Jer%C3%B3nimo%20503%2C%20Tlaltenango%2C%20Cuernavaca%2C%20Morelos',
			'tagline'                  => 'Descubre tu fuerza. Aprende a darle sentido. Capoeira para adultos y niños en Cuernavaca, Morelos.',
			'og_image_id'              => 0,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @param mixed $fallback Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $values Partial settings to merge and persist.
	 */
	public static function update( array $values ): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		update_option( self::OPTION, array_merge( $stored, $values ), true );
	}

	/**
	 * Constant wins over the stored option. Returns '' when neither is set.
	 */
	public static function get_secret( string $key ): string {
		$constant = self::secret_constant( $key );
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}

		$secrets = get_option( self::SECRETS, array() );

		return is_array( $secrets ) && isset( $secrets[ $key ] ) ? (string) $secrets[ $key ] : '';
	}

	public static function secret_source( string $key ): string {
		if ( defined( self::secret_constant( $key ) ) ) {
			return 'constant';
		}

		return '' !== self::get_secret( $key ) ? 'option' : 'missing';
	}

	public static function secret_constant( string $key ): string {
		return 'PURA_' . strtoupper( $key );
	}

	/**
	 * @param array<string, string> $values Secrets to store; empty strings are ignored.
	 */
	public static function update_secrets( array $values ): void {
		$secrets = get_option( self::SECRETS, array() );
		$secrets = is_array( $secrets ) ? $secrets : array();

		foreach ( $values as $key => $value ) {
			if ( ! in_array( $key, self::SECRET_KEYS, true ) ) {
				continue;
			}
			if ( '' === $value ) {
				continue;
			}
			$secrets[ $key ] = $value;
		}

		update_option( self::SECRETS, $secrets, false );
	}

	public static function delete_secret( string $key ): void {
		$secrets = get_option( self::SECRETS, array() );
		if ( is_array( $secrets ) && isset( $secrets[ $key ] ) ) {
			unset( $secrets[ $key ] );
			update_option( self::SECRETS, $secrets, false );
		}
	}

	/**
	 * Permalink of the configured store/inscriptions page, with a slug-based fallback.
	 *
	 * @param string $which 'store' or 'inscriptions'.
	 */
	public static function page_url( string $which ): string {
		$id = (int) self::get( 'store' === $which ? 'store_page_id' : 'inscriptions_page_id', 0 );

		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}

		$page = get_page_by_path( 'store' === $which ? 'tienda' : 'inscripciones' );
		if ( $page ) {
			return (string) get_permalink( $page );
		}

		return home_url( 'store' === $which ? '/tienda/' : '/inscripciones/' );
	}

	public static function whatsapp_url( string $text = '' ): string {
		$number = preg_replace( '/\D+/', '', (string) self::get( 'whatsapp_number', '' ) );
		$url    = 'https://wa.me/' . $number;

		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}

		return $url;
	}

	/**
	 * Values safe to expose to the browser (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public static function public_config(): array {
		return array(
			'restUrl'         => esc_url_raw( rest_url( 'pura/v1/' ) ),
			'currency'        => (string) self::get( 'currency', 'MXN' ),
			'storeUrl'        => self::page_url( 'store' ),
			'inscriptionsUrl' => self::page_url( 'inscriptions' ),
			'whatsapp'        => preg_replace( '/\D+/', '', (string) self::get( 'whatsapp_number', '' ) ),
			'calendly'        => array(
				'trialAdult' => (string) self::get( 'calendly_trial_adult_url', '' ),
				'trialKids'  => (string) self::get( 'calendly_trial_kids_url', '' ),
			),
		);
	}
}
