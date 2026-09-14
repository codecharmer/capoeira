<?php
/**
 * Stripe webhook signature verification (Stripe-Signature: t=…,v1=…).
 *
 * Port of the legacy verifyStripeSignature(): HMAC-SHA256 over "{timestamp}.{payload}",
 * constant-time comparison against every v1 candidate, 300 s tolerance.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Http;

defined( 'ABSPATH' ) || exit;

final class Stripe_Signature {

	public const DEFAULT_TOLERANCE = 300;

	/**
	 * @param string   $payload   Raw request body.
	 * @param string   $header    Stripe-Signature header value.
	 * @param string   $secret    Endpoint signing secret (whsec_…).
	 * @param int      $tolerance Max age in seconds.
	 * @param int|null $now       Current time (injectable for tests).
	 */
	public static function verify( string $payload, string $header, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp  = null;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			[ $key, $value ] = $pair;
			if ( 't' === $key ) {
				$timestamp = (int) $value;
			} elseif ( 'v1' === $key ) {
				$signatures[] = $value;
			}
		}

		if ( null === $timestamp || ! $signatures ) {
			return false;
		}

		$now = $now ?? time();
		if ( abs( $now - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a header for tests and local replays.
	 */
	public static function sign( string $payload, string $secret, ?int $timestamp = null ): string {
		$timestamp = $timestamp ?? time();

		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
	}
}
