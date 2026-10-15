<?php
/**
 * Single-use OAuth `state` records, stored server-side (transients) so the flow does not depend on
 * a cookie — Apple's callback is a cross-site POST.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class State_Store {

	public const TTL = 600;

	private const PREFIX = 'pura_auth_state_';

	/**
	 * @param array<string, mixed> $data Anything the callback needs (provider, nonce, verifier, redirect).
	 * @return string The state token to send to the provider.
	 */
	public static function create( array $data, int $ttl = self::TTL ): string {
		$state           = Jwt::base64url_encode( random_bytes( 32 ) );
		$data['created'] = time();

		set_transient( self::key( $state ), $data, $ttl );

		return $state;
	}

	/**
	 * Read and delete. Null when unknown, expired, or already used.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function consume( string $state ): ?array {
		if ( '' === $state || strlen( $state ) > 128 ) {
			return null;
		}

		$key  = self::key( $state );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return null;
		}

		delete_transient( $key );

		return $data;
	}

	private static function key( string $state ): string {
		return self::PREFIX . hash( 'sha256', $state );
	}
}
