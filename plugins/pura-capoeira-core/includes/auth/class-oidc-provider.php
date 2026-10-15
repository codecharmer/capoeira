<?php
/**
 * Shared OpenID Connect plumbing: token endpoint call and id_token verification.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class Oidc_Provider implements Provider_Interface {

	abstract protected function token_endpoint(): string;

	abstract protected function jwks_url(): string;

	/** @return string[] Accepted `iss` values. */
	abstract protected function issuers(): array;

	/** Our client id, i.e. the expected `aud`. */
	abstract protected function client_id(): string;

	public function redirect_uri(): string {
		return rest_url( 'pura/v1/auth/' . $this->id() . '/callback' );
	}

	/**
	 * Form-encoded POST to the token endpoint.
	 *
	 * @param array<string, string> $params Body.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function post_token( array $params ) {
		$response = wp_remote_post(
			$this->token_endpoint(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pura_auth_http', 'No se pudo contactar al proveedor: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['id_token'] ) || ! is_string( $body['id_token'] ) ) {
			$detail = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';

			return new WP_Error( 'pura_auth_exchange', 'El proveedor rechazó el código de acceso.' . ( '' !== $detail ? ' (' . $detail . ')' : '' ) );
		}

		return $body;
	}

	/**
	 * Signature (RS256 against the provider JWKS) + iss/aud/exp/nonce checks.
	 *
	 * @return array<string, mixed>|WP_Error Verified claims.
	 */
	protected function verify_id_token( string $id_token, string $nonce ) {
		try {
			$parts = Jwt::decode( $id_token );
		} catch ( Jwt_Exception $e ) {
			return new WP_Error( 'pura_auth_token', 'El token de identidad no es válido.' );
		}

		$header = $parts['header'];
		if ( 'RS256' !== ( $header['alg'] ?? '' ) || empty( $header['kid'] ) || ! is_string( $header['kid'] ) ) {
			return new WP_Error( 'pura_auth_token', 'El token de identidad usa un algoritmo no admitido.' );
		}

		$pem = Jwks_Cache::public_key_pem( $this->id(), $this->jwks_url(), $header['kid'] );
		if ( null === $pem || ! Jwt::verify_rs256( $parts['signing_input'], $parts['signature'], $pem ) ) {
			return new WP_Error( 'pura_auth_token', 'La firma del token de identidad no es válida.' );
		}

		try {
			Jwt::validate_claims( $parts['claims'], $this->issuers(), $this->client_id(), $nonce, time() );
		} catch ( Jwt_Exception $e ) {
			return new WP_Error( 'pura_auth_token', $e->getMessage() );
		}

		return $parts['claims'];
	}

	/**
	 * Normalise Google/Apple boolean-ish claim values ("true", true, 1).
	 */
	protected static function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}
}
