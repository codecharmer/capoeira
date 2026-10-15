<?php
/**
 * Provider JSON Web Key Sets, cached in a transient. An unknown `kid` triggers one refetch
 * (key rotation), throttled so a flood of bad tokens cannot hammer the provider.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class Jwks_Cache {

	private const TTL           = 6 * HOUR_IN_SECONDS;
	private const REFETCH_GUARD = 60;

	/**
	 * PEM public key for `$kid`, or null when the provider does not publish it.
	 */
	public static function public_key_pem( string $provider, string $url, string $kid ): ?string {
		$keys = self::keys( $provider, $url, false );
		$jwk  = self::find( $keys, $kid );

		if ( null === $jwk && ! get_transient( self::guard_key( $provider ) ) ) {
			set_transient( self::guard_key( $provider ), 1, self::REFETCH_GUARD );
			$keys = self::keys( $provider, $url, true );
			$jwk  = self::find( $keys, $kid );
		}

		if ( null === $jwk ) {
			return null;
		}

		try {
			return Jwt::jwk_to_pem( $jwk );
		} catch ( Jwt_Exception $e ) {
			return null;
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function keys( string $provider, string $url, bool $force ): array {
		$cache_key = 'pura_jwks_' . sanitize_key( $provider );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$keys = is_array( $body ) && isset( $body['keys'] ) && is_array( $body['keys'] ) ? array_values( $body['keys'] ) : array();

		if ( $keys ) {
			set_transient( $cache_key, $keys, self::TTL );
		}

		return $keys;
	}

	/**
	 * @param array<int, array<string, mixed>> $keys JWKs.
	 * @return array<string, mixed>|null
	 */
	private static function find( array $keys, string $kid ): ?array {
		foreach ( $keys as $jwk ) {
			if ( is_array( $jwk ) && ( $jwk['kid'] ?? null ) === $kid && 'RSA' === ( $jwk['kty'] ?? '' ) ) {
				return $jwk;
			}
		}

		return null;
	}

	private static function guard_key( string $provider ): string {
		return 'pura_jwks_' . sanitize_key( $provider ) . '_refetch';
	}
}
