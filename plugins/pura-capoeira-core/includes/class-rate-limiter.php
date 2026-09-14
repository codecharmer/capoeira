<?php
/**
 * Fixed-window rate limiter on the object cache (transient fallback when no persistent cache).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	private const GROUP = 'pura_rate';

	/**
	 * @param string $bucket Logical bucket, e.g. 'inscriptions'.
	 * @param string $key    Client key (usually the IP).
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the request is allowed.
	 */
	public static function check( string $bucket, string $key, int $limit, int $window ): bool {
		$slot  = (int) floor( time() / $window );
		$cache = 'rl_' . md5( $bucket . '|' . $key ) . '_' . $slot;

		if ( wp_using_ext_object_cache() ) {
			$count = wp_cache_get( $cache, self::GROUP );
			if ( false === $count ) {
				wp_cache_add( $cache, 0, self::GROUP, $window );
			}
			$count = wp_cache_incr( $cache, 1, self::GROUP );
			if ( false === $count ) {
				$count = 1;
				wp_cache_set( $cache, $count, self::GROUP, $window );
			}
		} else {
			$count = (int) get_transient( $cache ) + 1;
			set_transient( $cache, $count, $window );
		}

		return (int) $count <= $limit;
	}

	/**
	 * Best-effort client IP. Behind cPanel's nginx proxy, REMOTE_ADDR is already the client.
	 */
	public static function client_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		return (string) apply_filters( 'pura_rate_limit_client_key', $ip );
	}
}
