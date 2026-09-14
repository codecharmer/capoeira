<?php
/**
 * Printful API client over wp_remote_*. Replaces the two duplicated printfulRequest() helpers.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Http;

use Pura\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Printful_Client implements Printful_Client_Interface {

	private string $token;
	private string $base_url;

	public function __construct( ?string $token = null, ?string $base_url = null ) {
		$this->token    = $token ?? Settings::get_secret( 'printful_api_key' );
		$this->base_url = rtrim( $base_url ?? (string) apply_filters( 'pura_printful_base_url', 'https://api.printful.com' ), '/' );
	}

	/**
	 * Resolve the client to use: the mock when PURA_PRINTFUL_MOCK is on, else the real one,
	 * overridable via the `pura_printful_client` filter.
	 */
	public static function resolve(): Printful_Client_Interface {
		$client = ( defined( 'PURA_PRINTFUL_MOCK' ) && PURA_PRINTFUL_MOCK ) ? new Mock_Printful_Client() : new self();

		$filtered = apply_filters( 'pura_printful_client', $client );

		return $filtered instanceof Printful_Client_Interface ? $filtered : $client;
	}

	public function is_configured(): bool {
		return '' !== $this->token;
	}

	public function get_products() {
		return $this->request( 'GET', '/store/products' );
	}

	public function get_product( int $id ) {
		return $this->request( 'GET', '/store/products/' . $id );
	}

	public function get_shipping_rates( array $recipient, array $items, string $currency, string $locale = 'es_ES' ) {
		return $this->request(
			'POST',
			'/shipping/rates',
			array(
				'recipient' => $recipient,
				'items'     => $items,
				'currency'  => $currency,
				'locale'    => $locale,
			)
		);
	}

	public function create_draft_order( array $order ) {
		return $this->request( 'POST', '/orders?confirm=false', $order );
	}

	public function confirm_order( int $order_id ) {
		if ( defined( 'PURA_PRINTFUL_CONFIRM_DISABLED' ) && PURA_PRINTFUL_CONFIRM_DISABLED ) {
			error_log( '[pura] Printful confirm skipped for order ' . $order_id . ' (PURA_PRINTFUL_CONFIRM_DISABLED).' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return array( 'id' => $order_id, 'status' => 'draft', 'confirm_skipped' => true );
		}

		return $this->request( 'POST', '/orders/' . $order_id . '/confirm' );
	}

	/**
	 * @param array<string, mixed>|null $body JSON body.
	 * @return mixed|WP_Error The `result` member of Printful's envelope.
	 */
	private function request( string $method, string $path, ?array $body = null ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'pura_printful_unconfigured', 'Printful no está configurado.', array( 'status' => 500 ) );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pura_printful_http', 'No se pudo contactar a Printful: ' . $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		// Legacy success heuristic: 2xx and (code === 200 or a `result` key present).
		$ok = $code >= 200 && $code < 300 && is_array( $decoded ) && ( 200 === (int) ( $decoded['code'] ?? 0 ) || array_key_exists( 'result', $decoded ) );
		if ( ! $ok ) {
			$message = is_array( $decoded ) ? (string) ( $decoded['result'] ?? $decoded['error']['message'] ?? 'Error de Printful.' ) : 'Respuesta inesperada de Printful.';

			return new WP_Error( 'pura_printful_api', $message, array( 'status' => 502, 'printful_status' => $code ) );
		}

		return $decoded['result'] ?? array();
	}
}
