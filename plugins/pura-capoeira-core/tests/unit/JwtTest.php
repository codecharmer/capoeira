<?php
/**
 * JWT helpers: base64url, JWK → PEM, RS256 verification, decoding and claim validation.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\Jwt;
use Pura\Core\Auth\Jwt_Exception;

final class JwtTest extends TestCase {

	private const ISSUERS  = array( 'https://accounts.google.com', 'accounts.google.com' );
	private const AUDIENCE = 'client-id-123.apps.googleusercontent.com';
	private const NONCE    = 'n-0S6_WzA2Mj';
	private const NOW      = 1_700_000_000;

	private static \OpenSSLAsymmetricKey $key;
	private static \OpenSSLAsymmetricKey $other_key;

	public static function setUpBeforeClass(): void {
		self::$key       = self::generate_rsa_key();
		self::$other_key = self::generate_rsa_key();
	}

	private static function generate_rsa_key(): \OpenSSLAsymmetricKey {
		$key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		self::assertNotFalse( $key );

		return $key;
	}

	private static function public_pem( \OpenSSLAsymmetricKey $key ): string {
		return openssl_pkey_get_details( $key )['key'];
	}

	/**
	 * Sign "header.claims" with RS256 and return the three-segment token.
	 */
	private static function rs256_token( array $header, array $claims, \OpenSSLAsymmetricKey $key ): string {
		$signing_input = Jwt::base64url_encode( json_encode( $header, JSON_UNESCAPED_SLASHES ) )
			. '.' . Jwt::base64url_encode( json_encode( $claims, JSON_UNESCAPED_SLASHES ) );
		$signature     = '';
		self::assertTrue( openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 ) );

		return $signing_input . '.' . Jwt::base64url_encode( $signature );
	}

	private static function valid_claims(): array {
		return array(
			'iss'   => 'https://accounts.google.com',
			'aud'   => self::AUDIENCE,
			'sub'   => '10769150350006150715113082367',
			'exp'   => self::NOW + 3600,
			'iat'   => self::NOW - 60,
			'nonce' => self::NONCE,
		);
	}

	/*
	 * base64url
	 */

	public function test_base64url_round_trip_covers_plus_slash_and_padding(): void {
		// 0xfb 0xff 0xbf → "+/+/" in standard base64; lengths 1..3 exercise every padding case.
		$samples = array( '', "\xfb", "\xfb\xff", "\xfb\xff\xbf", "\xfb\xff\xbf\x00\x7f", random_bytes( 33 ) );

		foreach ( $samples as $bin ) {
			$encoded = Jwt::base64url_encode( $bin );

			self::assertMatchesRegularExpression( '/^[A-Za-z0-9_-]*$/', $encoded );
			self::assertStringNotContainsString( '=', $encoded );
			self::assertSame( $bin, Jwt::base64url_decode( $encoded ) );
		}

		self::assertSame( '-_-_', Jwt::base64url_encode( "\xfb\xff\xbf" ) );
	}

	public function test_base64url_decode_rejects_invalid_input(): void {
		foreach ( array( 'ab+c', 'ab/c', 'abc=', 'a b', 'abc.d', 'a' ) as $invalid ) {
			try {
				Jwt::base64url_decode( $invalid );
				self::fail( 'Expected Jwt_Exception for ' . var_export( $invalid, true ) );
			} catch ( Jwt_Exception $e ) {
				self::assertNotSame( '', $e->getMessage() );
			}
		}
	}

	/*
	 * JWK → PEM
	 */

	public function test_jwk_to_pem_matches_openssl_export(): void {
		$details = openssl_pkey_get_details( self::$key );
		$jwk     = array(
			'kty' => 'RSA',
			'kid' => 'test-kid',
			'use' => 'sig',
			'alg' => 'RS256',
			'n'   => Jwt::base64url_encode( $details['rsa']['n'] ),
			'e'   => Jwt::base64url_encode( $details['rsa']['e'] ),
		);

		$pem = Jwt::jwk_to_pem( $jwk );

		self::assertSame( self::normalise_pem( $details['key'] ), self::normalise_pem( $pem ) );
		self::assertNotFalse( openssl_pkey_get_public( $pem ) );
	}

	public function test_jwk_to_pem_rejects_non_rsa_or_incomplete_keys(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::jwk_to_pem(
			array(
				'kty' => 'EC',
				'crv' => 'P-256',
				'x'   => 'AQAB',
				'y'   => 'AQAB',
			)
		);
	}

	private static function normalise_pem( string $pem ): string {
		return rtrim( str_replace( "\r\n", "\n", $pem ) );
	}

	/*
	 * RS256
	 */

	public function test_verify_rs256_accepts_valid_signature(): void {
		$token   = self::rs256_token(
			array(
				'alg' => 'RS256',
				'typ' => 'JWT',
				'kid' => 'k1',
			),
			self::valid_claims(),
			self::$key
		);
		$decoded = Jwt::decode( $token );

		self::assertTrue( Jwt::verify_rs256( $decoded['signing_input'], $decoded['signature'], self::public_pem( self::$key ) ) );
	}

	public function test_verify_rs256_rejects_flipped_signature_byte(): void {
		$decoded   = Jwt::decode( self::rs256_token( array( 'alg' => 'RS256' ), self::valid_claims(), self::$key ) );
		$signature = $decoded['signature'];

		$signature[10] = chr( ord( $signature[10] ) ^ 0x01 );

		self::assertFalse( Jwt::verify_rs256( $decoded['signing_input'], $signature, self::public_pem( self::$key ) ) );
	}

	public function test_verify_rs256_rejects_tampered_signing_input(): void {
		$decoded = Jwt::decode( self::rs256_token( array( 'alg' => 'RS256' ), self::valid_claims(), self::$key ) );

		$tampered = $decoded['signing_input'] . 'x';

		self::assertFalse( Jwt::verify_rs256( $tampered, $decoded['signature'], self::public_pem( self::$key ) ) );
	}

	public function test_verify_rs256_rejects_wrong_key(): void {
		$decoded = Jwt::decode( self::rs256_token( array( 'alg' => 'RS256' ), self::valid_claims(), self::$key ) );

		self::assertFalse( Jwt::verify_rs256( $decoded['signing_input'], $decoded['signature'], self::public_pem( self::$other_key ) ) );
	}

	public function test_verify_rs256_returns_false_on_bad_key_without_throwing(): void {
		$decoded = Jwt::decode( self::rs256_token( array( 'alg' => 'RS256' ), self::valid_claims(), self::$key ) );

		self::assertFalse( Jwt::verify_rs256( $decoded['signing_input'], $decoded['signature'], 'not a pem' ) );
		self::assertFalse( Jwt::verify_rs256( $decoded['signing_input'], $decoded['signature'], '' ) );
		self::assertFalse( Jwt::verify_rs256( $decoded['signing_input'], '', self::public_pem( self::$key ) ) );
	}

	/*
	 * decode
	 */

	public function test_decode_returns_header_claims_signature_and_signing_input(): void {
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
			'kid' => 'abc',
		);
		$claims = self::valid_claims();
		$token  = self::rs256_token( $header, $claims, self::$key );

		$decoded = Jwt::decode( $token );

		self::assertSame( $header, $decoded['header'] );
		self::assertSame( $claims, $decoded['claims'] );
		self::assertSame( 256, strlen( $decoded['signature'] ) );
		self::assertSame( substr( $token, 0, strrpos( $token, '.' ) ), $decoded['signing_input'] );
	}

	public function test_decode_rejects_two_segment_token(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::decode( Jwt::base64url_encode( '{"alg":"none"}' ) . '.' . Jwt::base64url_encode( '{}' ) );
	}

	public function test_decode_rejects_non_json_header(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::decode( Jwt::base64url_encode( 'not json' ) . '.' . Jwt::base64url_encode( '{}' ) . '.c2ln' );
	}

	public function test_decode_rejects_json_array_claims(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::decode( Jwt::base64url_encode( '{"alg":"RS256"}' ) . '.' . Jwt::base64url_encode( '[1,2]' ) . '.c2ln' );
	}

	/*
	 * validate_claims
	 */

	public function test_validate_claims_accepts_valid_token(): void {
		Jwt::validate_claims( self::valid_claims(), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );

		$this->addToAssertionCount( 1 );
	}

	public function test_validate_claims_rejects_unknown_issuer(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/iss/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'iss' => 'https://evil.example' ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_rejects_wrong_audience(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/aud/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'aud' => 'other-client' ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_accepts_audience_as_array(): void {
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'aud' => array( 'other-client', self::AUDIENCE ) ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );

		$this->addToAssertionCount( 1 );
	}

	public function test_validate_claims_rejects_audience_array_without_match(): void {
		$this->expectException( Jwt_Exception::class );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'aud' => array( 'other-client' ) ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_rejects_missing_exp(): void {
		$claims = self::valid_claims();
		unset( $claims['exp'] );

		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/exp/' );
		Jwt::validate_claims( $claims, self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_rejects_expired_beyond_leeway(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/expirado/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'exp' => self::NOW - 121 ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW, 120 );
	}

	public function test_validate_claims_accepts_expired_within_leeway(): void {
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'exp' => self::NOW - 119 ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW, 120 );

		$this->addToAssertionCount( 1 );
	}

	public function test_validate_claims_rejects_future_iat_beyond_leeway(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/iat/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'iat' => self::NOW + 121 ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW, 120 );
	}

	public function test_validate_claims_accepts_future_iat_within_leeway(): void {
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'iat' => self::NOW + 119 ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW, 120 );

		$this->addToAssertionCount( 1 );
	}

	public function test_validate_claims_rejects_future_nbf_beyond_leeway(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/nbf/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'nbf' => self::NOW + 121 ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW, 120 );
	}

	public function test_validate_claims_rejects_nonce_mismatch(): void {
		$this->expectException( Jwt_Exception::class );
		$this->expectExceptionMessageMatches( '/nonce/' );
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'nonce' => 'other' ) ), self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_rejects_missing_nonce_when_expected(): void {
		$claims = self::valid_claims();
		unset( $claims['nonce'] );

		$this->expectException( Jwt_Exception::class );
		Jwt::validate_claims( $claims, self::ISSUERS, self::AUDIENCE, self::NONCE, self::NOW );
	}

	public function test_validate_claims_skips_nonce_check_when_expected_nonce_is_empty(): void {
		Jwt::validate_claims( array_merge( self::valid_claims(), array( 'nonce' => 'anything' ) ), self::ISSUERS, self::AUDIENCE, '', self::NOW );

		$claims = self::valid_claims();
		unset( $claims['nonce'] );
		Jwt::validate_claims( $claims, self::ISSUERS, self::AUDIENCE, '', self::NOW );

		$this->addToAssertionCount( 1 );
	}
}
