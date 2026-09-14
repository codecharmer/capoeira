<?php
/**
 * Minimal Stripe REST client over wp_remote_*. Only what the site needs: Checkout Sessions and
 * event retrieval. Form-encoded nested params exactly like stripe-php would send them.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Http;

use Pura\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Stripe_Client {

	private const BASE = 'https://api.stripe.com/v1/';

	/** Pin the API version so a Stripe-side default bump cannot change behaviour. */
	public const API_VERSION = '2024-06-20';

	private string $secret_key;

	public function __construct( ?string $secret_key = null ) {
		$this->secret_key = $secret_key ?? Settings::get_secret( 'stripe_secret_key' );
	}

	public function is_configured(): bool {
		return '' !== $this->secret_key;
	}

	/**
	 * @param array<string, mixed> $params Session params (nested arrays allowed).
	 * @return array<string, mixed>|WP_Error Decoded session or error.
	 */
	public function create_checkout_session( array $params, string $idempotency_key = '' ) {
		return $this->request( 'POST', 'checkout/sessions', $params, $idempotency_key );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_event( string $event_id ) {
		return $this->request( 'GET', 'events/' . rawurlencode( $event_id ) );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_checkout_session( string $session_id ) {
		return $this->request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
	}

	/**
	 * @param array<string, mixed> $params Body params.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $path, array $params = array(), string $idempotency_key = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'pura_stripe_unconfigured', 'Stripe no está configurado.', array( 'status' => 500 ) );
		}

		$headers = array(
			'Authorization'  => 'Bearer ' . $this->secret_key,
			'Stripe-Version' => self::API_VERSION,
			'Content-Type'   => 'application/x-www-form-urlencoded',
		);
		if ( '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => $headers,
		);
		if ( 'POST' === $method ) {
			$args['body'] = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		}

		$response = wp_remote_request( self::BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pura_stripe_http', 'No se pudo contactar a Stripe: ' . $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'Respuesta inesperada de Stripe.';

			return new WP_Error(
				'pura_stripe_api',
				$message,
				array(
					'status'        => 502,
					'stripe_status' => $code,
				)
			);
		}

		return $body;
	}
}
