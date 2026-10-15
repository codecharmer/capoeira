<?php
/**
 * Apple client secret (ES256 JWT) and private key normalisation.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\Apple_Provider;
use Pura\Core\Auth\Jwt;

final class AppleClientSecretTest extends TestCase {

	private static string $private_pem = '';
	private static string $public_pem  = '';

	public static function setUpBeforeClass(): void {
		$key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		openssl_pkey_export( $key, self::$private_pem );
		self::$public_pem = (string) openssl_pkey_get_details( $key )['key'];
	}

	public function test_secret_has_apple_claims_and_verifies(): void {
		$now = 1_800_000_000;
		$jwt = Apple_Provider::build_client_secret( self::$private_pem, 'TEAM123456', 'com.example.web', 'KEYID12345', $now );

		$parts = Jwt::decode( $jwt );
		self::assertSame( 'ES256', $parts['header']['alg'] );
		self::assertSame( 'KEYID12345', $parts['header']['kid'] );
		self::assertSame( 'TEAM123456', $parts['claims']['iss'] );
		self::assertSame( 'com.example.web', $parts['claims']['sub'] );
		self::assertSame( Apple_Provider::ISSUER, $parts['claims']['aud'] );
		self::assertSame( $now, $parts['claims']['iat'] );
		self::assertSame( $now + 300, $parts['claims']['exp'] );

		$der = Jwt::raw_to_der( $parts['signature'] );
		self::assertSame( 1, openssl_verify( $parts['signing_input'], $der, self::$public_pem, OPENSSL_ALGO_SHA256 ) );
	}

	public function test_normalize_accepts_escaped_newlines_and_base64(): void {
		$pem = self::$private_pem;

		self::assertSame( trim( $pem ) . "\n", Apple_Provider::normalize_private_key( $pem ) );
		self::assertSame( trim( $pem ) . "\n", Apple_Provider::normalize_private_key( str_replace( "\n", '\\n', trim( $pem ) ) ) );
		self::assertSame( trim( $pem ) . "\n", Apple_Provider::normalize_private_key( base64_encode( $pem ) ) );
		self::assertSame( '', Apple_Provider::normalize_private_key( '' ) );
		self::assertSame( '', Apple_Provider::normalize_private_key( 'not a key' ) );
	}

	public function test_normalized_escaped_key_still_signs(): void {
		$escaped = str_replace( "\n", '\\n', trim( self::$private_pem ) );
		$jwt     = Apple_Provider::build_client_secret( Apple_Provider::normalize_private_key( $escaped ), 'T', 'S', 'K', 1_800_000_000 );
		$parts   = Jwt::decode( $jwt );

		self::assertSame( 1, openssl_verify( $parts['signing_input'], Jwt::raw_to_der( $parts['signature'] ), self::$public_pem, OPENSSL_ALGO_SHA256 ) );
	}
}
