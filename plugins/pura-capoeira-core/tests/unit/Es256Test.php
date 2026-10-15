<?php
/**
 * ES256 signing and ECDSA DER ⇄ raw signature conversion.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\Jwt;
use Pura\Core\Auth\Jwt_Exception;

final class Es256Test extends TestCase {

	private static \OpenSSLAsymmetricKey $key;
	private static string $private_pem;
	private static string $public_pem;

	public static function setUpBeforeClass(): void {
		self::$key = self::generate_ec_key();

		$private_pem = '';
		self::assertTrue( openssl_pkey_export( self::$key, $private_pem ) );
		self::$private_pem = $private_pem;
		self::$public_pem  = openssl_pkey_get_details( self::$key )['key'];
	}

	private static function generate_ec_key(): \OpenSSLAsymmetricKey {
		$key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		self::assertNotFalse( $key );

		return $key;
	}

	private static function apple_claims(): array {
		return array(
			'iss' => 'TEAMID1234',
			'iat' => 1_700_000_000,
			'exp' => 1_700_000_000 + 15_777_000,
			'aud' => 'https://appleid.apple.com',
			'sub' => 'mx.capoeiracuernavaca.web',
		);
	}

	/*
	 * sign_es256
	 */

	public function test_sign_es256_produces_verifiable_compact_jwt(): void {
		$token = Jwt::sign_es256( array( 'kid' => 'ABC123' ), self::apple_claims(), self::$private_pem );

		self::assertMatchesRegularExpression( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token );

		$decoded = Jwt::decode( $token );

		self::assertSame( 'ES256', $decoded['header']['alg'] );
		self::assertSame( 'JWT', $decoded['header']['typ'] );
		self::assertSame( 'ABC123', $decoded['header']['kid'] );
		self::assertSame( self::apple_claims(), $decoded['claims'] );
		self::assertSame( 64, strlen( $decoded['signature'] ) );

		$der = Jwt::raw_to_der( $decoded['signature'] );

		self::assertSame( 1, openssl_verify( $decoded['signing_input'], $der, self::$public_pem, OPENSSL_ALGO_SHA256 ) );
	}

	public function test_sign_es256_forces_alg_and_typ_over_caller_values(): void {
		$token   = Jwt::sign_es256(
			array(
				'alg' => 'none',
				'typ' => 'nope',
				'kid' => 'K',
			),
			self::apple_claims(),
			self::$private_pem
		);
		$decoded = Jwt::decode( $token );

		self::assertSame( 'ES256', $decoded['header']['alg'] );
		self::assertSame( 'JWT', $decoded['header']['typ'] );
		self::assertSame( 'K', $decoded['header']['kid'] );
	}

	public function test_sign_es256_does_not_escape_slashes_in_json(): void {
		$token   = Jwt::sign_es256( array(), self::apple_claims(), self::$private_pem );
		$decoded = Jwt::decode( $token );
		$payload = Jwt::base64url_decode( explode( '.', $token )[1] );

		self::assertStringContainsString( '"aud":"https://appleid.apple.com"', $payload );
		self::assertSame( 'https://appleid.apple.com', $decoded['claims']['aud'] );
	}

	public function test_tampered_claims_fail_verification(): void {
		$token   = Jwt::sign_es256( array( 'kid' => 'ABC123' ), self::apple_claims(), self::$private_pem );
		$decoded = Jwt::decode( $token );

		$tampered_claims = Jwt::base64url_encode( json_encode( array_merge( self::apple_claims(), array( 'sub' => 'someone-else' ) ), JSON_UNESCAPED_SLASHES ) );
		$signing_input   = explode( '.', $token )[0] . '.' . $tampered_claims;
		$der             = Jwt::raw_to_der( $decoded['signature'] );

		self::assertSame( 0, openssl_verify( $signing_input, $der, self::$public_pem, OPENSSL_ALGO_SHA256 ) );
	}

	public function test_sign_es256_rejects_invalid_or_non_ec_key(): void {
		try {
			Jwt::sign_es256( array(), self::apple_claims(), 'not a pem' );
			self::fail( 'Expected Jwt_Exception for a garbage key.' );
		} catch ( Jwt_Exception $e ) {
			self::assertNotSame( '', $e->getMessage() );
		}

		$rsa_pem = '';
		$rsa     = openssl_pkey_new(
			array(
				'private_key_bits' => 1024,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		self::assertNotFalse( $rsa );
		self::assertTrue( openssl_pkey_export( $rsa, $rsa_pem ) );

		$this->expectException( Jwt_Exception::class );
		Jwt::sign_es256( array(), self::apple_claims(), $rsa_pem );
	}

	/*
	 * der_to_raw / raw_to_der
	 */

	public function test_der_raw_round_trip_for_random_keys_and_signatures(): void {
		for ( $i = 0; $i < 20; $i++ ) {
			$key     = self::generate_ec_key();
			$public  = openssl_pkey_get_details( $key )['key'];
			$message = random_bytes( 48 );
			$der     = '';
			self::assertTrue( openssl_sign( $message, $der, $key, OPENSSL_ALGO_SHA256 ) );

			$raw = Jwt::der_to_raw( $der );

			self::assertSame( 64, strlen( $raw ), 'Iteration ' . $i );
			self::assertSame( $der, Jwt::raw_to_der( $raw ), 'Iteration ' . $i );
			self::assertSame( 1, openssl_verify( $message, Jwt::raw_to_der( $raw ), $public, OPENSSL_ALGO_SHA256 ), 'Iteration ' . $i );
		}
	}

	public function test_der_integer_with_high_bit_uses_zero_padding(): void {
		// r has the high bit set → DER needs a 0x00 sign byte and length 0x21; s is a plain 32 bytes.
		$r = "\x80" . str_repeat( "\x11", 31 );
		$s = "\x7f" . str_repeat( "\x22", 31 );

		$raw = $r . $s;
		$der = "\x30\x45" . "\x02\x21\x00" . $r . "\x02\x20" . $s;

		self::assertSame( $raw, Jwt::der_to_raw( $der ) );
		self::assertSame( $der, Jwt::raw_to_der( $raw ) );
	}

	public function test_der_integer_shorter_than_part_len_is_left_padded(): void {
		// r is only 31 bytes in DER (leading zero byte of the magnitude is dropped by DER's minimal encoding).
		$r_short = "\x01" . str_repeat( "\x33", 30 );
		$s       = "\x40" . str_repeat( "\x44", 31 );

		$raw = "\x00" . $r_short . $s;
		$der = "\x30\x43" . "\x02\x1f" . $r_short . "\x02\x20" . $s;

		self::assertSame( 64, strlen( $raw ) );
		self::assertSame( $raw, Jwt::der_to_raw( $der ) );
		self::assertSame( $der, Jwt::raw_to_der( $raw ) );
	}

	public function test_der_long_form_length_is_parsed_and_emitted(): void {
		// P-521 sized halves (66 bytes) push the SEQUENCE body past 127 bytes → 0x81 length prefix.
		$r = "\x80" . str_repeat( "\x55", 65 );
		$s = "\x80" . str_repeat( "\x66", 65 );

		$raw = $r . $s;
		$der = Jwt::raw_to_der( $raw );

		self::assertSame( "\x30\x81", substr( $der, 0, 2 ) );
		self::assertSame( 2 + 2 * ( 2 + 1 + 66 ), strlen( $der ) - 1 );
		self::assertSame( $raw, Jwt::der_to_raw( $der, 66 ) );
	}

	public function test_der_to_raw_rejects_malformed_input(): void {
		$cases = array(
			''                                    => 'empty',
			"\x02\x01\x01"                        => 'not a sequence',
			"\x30\x06\x02\x01\x01\x02\x01"        => 'truncated second integer',
			"\x30\x06\x02\x01\x01\x04\x01\x01"    => 'second element not an integer',
			"\x30\x06\x02\x01\x01\x02\x01\x01\xff" => 'trailing bytes',
			"\x30\x04\x02\x02\x01\x02"            => 'missing s',
		);

		foreach ( $cases as $der => $label ) {
			try {
				Jwt::der_to_raw( (string) $der );
				self::fail( 'Expected Jwt_Exception: ' . $label );
			} catch ( Jwt_Exception $e ) {
				self::assertNotSame( '', $e->getMessage(), $label );
			}
		}
	}

	public function test_der_to_raw_rejects_integer_wider_than_part_len(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::der_to_raw( Jwt::raw_to_der( str_repeat( "\x7f", 66 ) ), 32 );
	}

	public function test_raw_to_der_rejects_odd_or_empty_input(): void {
		foreach ( array( '', "\x01", str_repeat( "\x01", 63 ) ) as $raw ) {
			try {
				Jwt::raw_to_der( $raw );
				self::fail( 'Expected Jwt_Exception for raw length ' . strlen( $raw ) );
			} catch ( Jwt_Exception $e ) {
				self::assertNotSame( '', $e->getMessage() );
			}
		}
	}
}
