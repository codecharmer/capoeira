<?php
/**
 * Minimal JWT toolkit for OIDC sign-in (Google / Apple id_tokens, Apple client secret).
 *
 * Pure PHP (openssl, json, hash) with no WordPress calls so it stays unit-testable:
 * RS256 verification of provider id_tokens, ES256 signing of the Apple client secret,
 * RSA JWK → PEM conversion for provider key sets, ECDSA DER ⇄ raw signature conversion
 * and claim validation with clock leeway.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class Jwt {

	/** DER encoding of OID 1.2.840.113549.1.1.1 (rsaEncryption). */
	private const OID_RSA_ENCRYPTION = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

	/*
	 * Base64url (RFC 7515 §2).
	 */

	public static function base64url_encode( string $bin ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT wire format, not obfuscation.
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Strict decode: rejects characters outside the base64url alphabet and impossible lengths.
	 *
	 * @throws Jwt_Exception On invalid input.
	 */
	public static function base64url_decode( string $s ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]*$/', $s ) ) {
			throw new Jwt_Exception( 'El valor base64url contiene caracteres inválidos.' );
		}

		$remainder = strlen( $s ) % 4;
		if ( 1 === $remainder ) {
			throw new Jwt_Exception( 'El valor base64url tiene una longitud inválida.' );
		}

		$padded = strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - $remainder ) % 4 );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT wire format, not obfuscation.
		$bin = base64_decode( $padded, true );
		if ( false === $bin ) {
			throw new Jwt_Exception( 'El valor base64url no se pudo decodificar.' );
		}

		return $bin;
	}

	/*
	 * Compact serialization.
	 */

	/**
	 * Split a compact JWT into its parts without verifying anything.
	 *
	 * @return array{header: array, claims: array, signature: string, signing_input: string}
	 *         `signature` is raw bytes; `signing_input` is "header.claims" as transmitted.
	 * @throws Jwt_Exception When the token is not three base64url segments with JSON objects.
	 */
	public static function decode( string $jwt ): array {
		$segments = explode( '.', $jwt );
		if ( 3 !== count( $segments ) ) {
			throw new Jwt_Exception( 'El token debe tener exactamente tres segmentos.' );
		}

		[ $header_b64, $claims_b64, $signature_b64 ] = $segments;

		$header = self::decode_json_object( $header_b64 );
		if ( null === $header ) {
			throw new Jwt_Exception( 'El encabezado del token no es un objeto JSON.' );
		}

		$claims = self::decode_json_object( $claims_b64 );
		if ( null === $claims ) {
			throw new Jwt_Exception( 'Los claims del token no son un objeto JSON.' );
		}

		return array(
			'header'        => $header,
			'claims'        => $claims,
			'signature'     => self::base64url_decode( $signature_b64 ),
			'signing_input' => $header_b64 . '.' . $claims_b64,
		);
	}

	/**
	 * Verify an RS256 signature. Never throws: a bad key or non-RSA key simply fails.
	 */
	public static function verify_rs256( string $signing_input, string $signature, string $public_key_pem ): bool {
		if ( '' === $signature || '' === $public_key_pem ) {
			return false;
		}

		$key = openssl_pkey_get_public( $public_key_pem );
		if ( false === $key ) {
			return false;
		}

		$details = openssl_pkey_get_details( $key );
		if ( false === $details || OPENSSL_KEYTYPE_RSA !== ( $details['type'] ?? null ) ) {
			return false;
		}

		return 1 === openssl_verify( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );
	}

	/**
	 * Build and sign a compact ES256 JWT (P-256 + SHA-256), e.g. the Apple client secret.
	 *
	 * `alg` and `typ` are forced; callers typically pass `kid` in $header.
	 *
	 * @throws Jwt_Exception When the key is not a usable EC private key or signing fails.
	 */
	public static function sign_es256( array $header, array $claims, string $private_key_pem ): string {
		$header = array_merge(
			$header,
			array(
				'alg' => 'ES256',
				'typ' => 'JWT',
			)
		);

		$signing_input = self::base64url_encode( self::encode_json( $header ) )
			. '.' . self::base64url_encode( self::encode_json( $claims ) );

		$key = '' === $private_key_pem ? false : openssl_pkey_get_private( $private_key_pem );
		if ( false === $key ) {
			throw new Jwt_Exception( 'La clave privada ES256 no es válida.' );
		}

		$details = openssl_pkey_get_details( $key );
		if ( false === $details || OPENSSL_KEYTYPE_EC !== ( $details['type'] ?? null ) ) {
			throw new Jwt_Exception( 'La clave privada ES256 debe ser una clave EC.' );
		}

		$der = '';
		if ( ! openssl_sign( $signing_input, $der, $key, OPENSSL_ALGO_SHA256 ) ) {
			throw new Jwt_Exception( 'No se pudo firmar el token ES256.' );
		}

		return $signing_input . '.' . self::base64url_encode( self::der_to_raw( $der ) );
	}

	/*
	 * ECDSA signature encodings: DER SEQUENCE { INTEGER r, INTEGER s } ⇄ raw r||s (JWS).
	 */

	/**
	 * @param string $der      OpenSSL / X.509 ECDSA signature.
	 * @param int    $part_len Byte length of each of r and s (32 for P-256).
	 * @throws Jwt_Exception On malformed DER or integers wider than $part_len.
	 */
	public static function der_to_raw( string $der, int $part_len = 32 ): string {
		$offset = 0;
		if ( ! isset( $der[ $offset ] ) || "\x30" !== $der[ $offset ] ) {
			throw new Jwt_Exception( 'La firma DER no comienza con una secuencia.' );
		}
		++$offset;

		$sequence_len = self::read_der_length( $der, $offset );
		if ( strlen( $der ) !== $offset + $sequence_len ) {
			throw new Jwt_Exception( 'La longitud de la secuencia DER no coincide con la firma.' );
		}

		$r = self::read_der_integer( $der, $offset );
		$s = self::read_der_integer( $der, $offset );

		if ( strlen( $der ) !== $offset ) {
			throw new Jwt_Exception( 'La firma DER contiene bytes sobrantes.' );
		}

		return self::fit_unsigned( $r, $part_len ) . self::fit_unsigned( $s, $part_len );
	}

	/**
	 * @param string $raw r||s, each half the same fixed width.
	 * @throws Jwt_Exception When the raw signature is empty or has odd length.
	 */
	public static function raw_to_der( string $raw ): string {
		$len = strlen( $raw );
		if ( 0 === $len || 0 !== $len % 2 ) {
			throw new Jwt_Exception( 'La firma en bruto debe tener una longitud par distinta de cero.' );
		}

		$half = intdiv( $len, 2 );
		$body = self::der_integer( substr( $raw, 0, $half ) ) . self::der_integer( substr( $raw, $half ) );

		return self::der_sequence( $body );
	}

	/*
	 * Keys.
	 */

	/**
	 * RSA JWK (kty=RSA, base64url n/e) → PEM SubjectPublicKeyInfo.
	 *
	 * SEQUENCE { SEQUENCE { OID rsaEncryption, NULL }, BIT STRING { SEQUENCE { INTEGER n, INTEGER e } } }
	 *
	 * @throws Jwt_Exception When the JWK is not an RSA key with n and e.
	 */
	public static function jwk_to_pem( array $jwk ): string {
		if ( 'RSA' !== ( $jwk['kty'] ?? null ) || ! is_string( $jwk['n'] ?? null ) || ! is_string( $jwk['e'] ?? null ) ) {
			throw new Jwt_Exception( 'La JWK debe ser una clave RSA con los parámetros n y e.' );
		}

		$n = self::base64url_decode( $jwk['n'] );
		$e = self::base64url_decode( $jwk['e'] );
		if ( '' === $n || '' === $e ) {
			throw new Jwt_Exception( 'Los parámetros n y e de la JWK no pueden estar vacíos.' );
		}

		$rsa_public_key = self::der_sequence( self::der_integer( $n ) . self::der_integer( $e ) );
		$algorithm      = self::der_sequence( self::OID_RSA_ENCRYPTION . "\x05\x00" );
		$bit_string     = "\x03" . self::der_length( strlen( $rsa_public_key ) + 1 ) . "\x00" . $rsa_public_key;
		$spki           = self::der_sequence( $algorithm . $bit_string );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM encoding.
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/*
	 * Claims.
	 */

	/**
	 * Validate the registered claims of a verified id_token.
	 *
	 * @param array    $claims   Decoded payload.
	 * @param string[] $issuers  Accepted `iss` values.
	 * @param string   $audience Expected `aud` (our client id); `aud` may be a string or a list.
	 * @param string   $nonce    Expected `nonce`, or '' to skip the check.
	 * @param int      $now      Current Unix time (injectable for tests).
	 * @param int      $leeway   Clock skew tolerance in seconds.
	 * @throws Jwt_Exception On the first failing claim.
	 */
	public static function validate_claims( array $claims, array $issuers, string $audience, string $nonce, int $now, int $leeway = 120 ): void {
		if ( ! in_array( $claims['iss'] ?? null, $issuers, true ) ) {
			throw new Jwt_Exception( 'El emisor (iss) del token no es de confianza.' );
		}

		$aud = $claims['aud'] ?? null;
		if ( is_string( $aud ) ) {
			$aud_ok = $aud === $audience;
		} else {
			$aud_ok = is_array( $aud ) && in_array( $audience, $aud, true );
		}
		if ( ! $aud_ok ) {
			throw new Jwt_Exception( 'La audiencia (aud) del token no coincide.' );
		}

		if ( ! isset( $claims['exp'] ) || ! is_numeric( $claims['exp'] ) ) {
			throw new Jwt_Exception( 'El token no tiene fecha de expiración (exp).' );
		}
		if ( (int) $claims['exp'] + $leeway < $now ) {
			throw new Jwt_Exception( 'El token ha expirado.' );
		}

		if ( isset( $claims['iat'] ) && ( ! is_numeric( $claims['iat'] ) || (int) $claims['iat'] - $leeway > $now ) ) {
			throw new Jwt_Exception( 'La fecha de emisión (iat) del token es inválida o futura.' );
		}

		if ( isset( $claims['nbf'] ) && ( ! is_numeric( $claims['nbf'] ) || (int) $claims['nbf'] - $leeway > $now ) ) {
			throw new Jwt_Exception( 'El token aún no es válido (nbf).' );
		}

		if ( '' !== $nonce && ( $claims['nonce'] ?? null ) !== $nonce ) {
			throw new Jwt_Exception( 'El nonce del token no coincide.' );
		}
	}

	/*
	 * Internals.
	 */

	/**
	 * Decode a base64url segment that must hold a JSON object; null when it does not.
	 *
	 * @throws Jwt_Exception When the segment is not valid base64url.
	 */
	private static function decode_json_object( string $segment ): ?array {
		$json = self::base64url_decode( $segment );
		if ( ! json_decode( $json ) instanceof \stdClass ) {
			return null;
		}

		return (array) json_decode( $json, true );
	}

	/**
	 * @throws Jwt_Exception When the data cannot be serialised.
	 */
	private static function encode_json( array $data ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure PHP class; wp_json_encode() is unavailable in unit tests.
		$json = json_encode( $data, JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			throw new Jwt_Exception( 'No se pudo serializar el token a JSON.' );
		}

		return $json;
	}

	/**
	 * Read a DER length at $offset (short form, or long form 0x81…0x84) and advance.
	 *
	 * @throws Jwt_Exception On truncated or unsupported length encodings.
	 */
	private static function read_der_length( string $der, int &$offset ): int {
		if ( ! isset( $der[ $offset ] ) ) {
			throw new Jwt_Exception( 'La firma DER está truncada.' );
		}

		$first = ord( $der[ $offset ] );
		++$offset;
		if ( $first < 0x80 ) {
			return $first;
		}

		$bytes = $first & 0x7F;
		if ( 0 === $bytes || $bytes > 4 || strlen( $der ) < $offset + $bytes ) {
			throw new Jwt_Exception( 'La longitud DER no es válida.' );
		}

		$length = 0;
		for ( $i = 0; $i < $bytes; $i++ ) {
			$length = ( $length << 8 ) | ord( $der[ $offset ] );
			++$offset;
		}

		return $length;
	}

	/**
	 * Read a DER INTEGER at $offset, returning its content bytes (sign padding intact) and advance.
	 *
	 * @throws Jwt_Exception When the tag is not INTEGER or the content is truncated.
	 */
	private static function read_der_integer( string $der, int &$offset ): string {
		if ( ! isset( $der[ $offset ] ) || "\x02" !== $der[ $offset ] ) {
			throw new Jwt_Exception( 'Se esperaba un entero DER en la firma.' );
		}
		++$offset;

		$length = self::read_der_length( $der, $offset );
		if ( 0 === $length || strlen( $der ) < $offset + $length ) {
			throw new Jwt_Exception( 'El entero DER de la firma está truncado.' );
		}

		$value   = substr( $der, $offset, $length );
		$offset += $length;

		return $value;
	}

	/**
	 * Strip DER sign padding and left-pad with zeros to a fixed width.
	 *
	 * @throws Jwt_Exception When the magnitude does not fit.
	 */
	private static function fit_unsigned( string $value, int $part_len ): string {
		$value = ltrim( $value, "\x00" );
		if ( strlen( $value ) > $part_len ) {
			throw new Jwt_Exception( 'Un entero de la firma excede el tamaño de la curva.' );
		}

		return str_pad( $value, $part_len, "\x00", STR_PAD_LEFT );
	}

	/**
	 * Encode an unsigned big-endian magnitude as a DER INTEGER (0x00 prefix when the high bit is set).
	 */
	private static function der_integer( string $magnitude ): string {
		$magnitude = ltrim( $magnitude, "\x00" );
		if ( '' === $magnitude ) {
			$magnitude = "\x00";
		} elseif ( ord( $magnitude[0] ) & 0x80 ) {
			$magnitude = "\x00" . $magnitude;
		}

		return "\x02" . self::der_length( strlen( $magnitude ) ) . $magnitude;
	}

	private static function der_sequence( string $body ): string {
		return "\x30" . self::der_length( strlen( $body ) ) . $body;
	}

	/**
	 * DER length octets: short form below 128, otherwise 0x80|n followed by n big-endian bytes.
	 */
	private static function der_length( int $length ): string {
		if ( $length < 0x80 ) {
			return chr( $length );
		}

		$bytes = ltrim( pack( 'N', $length ), "\x00" );

		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}
}
