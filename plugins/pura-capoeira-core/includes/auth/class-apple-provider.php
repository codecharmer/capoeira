<?php
/**
 * Sign in with Apple (web). Authorization code flow with `response_mode=form_post`; the client
 * secret is an ES256 JWT signed with the Sign in with Apple key (.p8), minted per request.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use Pura\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Apple_Provider extends Oidc_Provider {

	public const ISSUER = 'https://appleid.apple.com';

	public function id(): string {
		return 'apple';
	}

	public function label(): string {
		return __( 'Continuar con Apple', 'pura' );
	}

	public function is_configured(): bool {
		return '' !== $this->client_id()
			&& '' !== $this->team_id()
			&& '' !== $this->key_id()
			&& '' !== self::normalize_private_key( Settings::get_secret( 'apple_private_key' ) );
	}

	public function authorization_url( string $state, string $nonce, string $code_challenge ): string {
		return add_query_arg(
			array(
				'client_id'     => $this->client_id(),
				'redirect_uri'  => $this->redirect_uri(),
				'response_type' => 'code',
				'response_mode' => 'form_post',
				'scope'         => 'name email',
				'state'         => $state,
				'nonce'         => $nonce,
			),
			self::ISSUER . '/auth/authorize'
		);
	}

	public function exchange_code( string $code, string $code_verifier ) {
		try {
			$secret = self::build_client_secret(
				self::normalize_private_key( Settings::get_secret( 'apple_private_key' ) ),
				$this->team_id(),
				$this->client_id(),
				$this->key_id(),
				time()
			);
		} catch ( Jwt_Exception $e ) {
			return new WP_Error( 'pura_auth_config', 'La llave privada de Apple no es válida.' );
		}

		return $this->post_token(
			array(
				'code'          => $code,
				'client_id'     => $this->client_id(),
				'client_secret' => $secret,
				'redirect_uri'  => $this->redirect_uri(),
				'grant_type'    => 'authorization_code',
			)
		);
	}

	public function identity( array $tokens, array $callback_params, string $nonce ) {
		$claims = $this->verify_id_token( (string) ( $tokens['id_token'] ?? '' ), $nonce );
		if ( is_wp_error( $claims ) ) {
			return $claims;
		}

		$sub = (string) ( $claims['sub'] ?? '' );
		if ( '' === $sub ) {
			return new WP_Error( 'pura_auth_token', 'El token de identidad no incluye un identificador.' );
		}

		// Apple only sends the name on the very first authorization, as a JSON string in `user`.
		$first = '';
		$last  = '';
		$user  = $callback_params['user'] ?? '';
		if ( is_string( $user ) && '' !== $user ) {
			$decoded = json_decode( $user, true );
			if ( is_array( $decoded ) ) {
				$first = trim( (string) ( $decoded['name']['firstName'] ?? '' ) );
				$last  = trim( (string) ( $decoded['name']['lastName'] ?? '' ) );
			}
		}

		$email = strtolower( trim( (string) ( $claims['email'] ?? '' ) ) );

		return array(
			'sub'              => $sub,
			'email'            => $email,
			// Apple emails (including private relays) are verified by Apple; the claim may be absent.
			'email_verified'   => '' !== $email && ( ! isset( $claims['email_verified'] ) || self::truthy( $claims['email_verified'] ) ),
			'first_name'       => $first,
			'last_name'        => $last,
			'display_name'     => trim( $first . ' ' . $last ),
			'is_private_email' => self::truthy( $claims['is_private_email'] ?? false ),
		);
	}

	/**
	 * The client secret Apple expects: ES256 JWT, `iss` = Team ID, `sub` = Services ID.
	 * Pure (no WordPress calls) so it can be unit-tested.
	 *
	 * @throws Jwt_Exception When the key cannot sign.
	 */
	public static function build_client_secret( string $private_key_pem, string $team_id, string $services_id, string $key_id, int $now, int $ttl = 300 ): string {
		return Jwt::sign_es256(
			array( 'kid' => $key_id ),
			array(
				'iss' => $team_id,
				'iat' => $now,
				'exp' => $now + $ttl,
				'aud' => self::ISSUER,
				'sub' => $services_id,
			),
			$private_key_pem
		);
	}

	/**
	 * Accept the .p8 contents as-is, with literal "\n" escapes (wp-config constants), or base64.
	 */
	public static function normalize_private_key( string $raw ): string {
		$key = trim( str_replace( array( "\r\n", "\r", '\\n' ), "\n", $raw ) );
		if ( '' === $key ) {
			return '';
		}
		if ( ! str_contains( $key, '-----BEGIN' ) ) {
			$decoded = base64_decode( $key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- key transport, not obfuscation.
			if ( false === $decoded || ! str_contains( $decoded, '-----BEGIN' ) ) {
				return '';
			}
			$key = trim( str_replace( array( "\r\n", "\r" ), "\n", $decoded ) );
		}

		return $key . "\n";
	}

	protected function token_endpoint(): string {
		return self::ISSUER . '/auth/token';
	}

	protected function jwks_url(): string {
		return self::ISSUER . '/auth/keys';
	}

	protected function issuers(): array {
		return array( self::ISSUER );
	}

	protected function client_id(): string {
		return trim( (string) Settings::get( 'apple_services_id', '' ) );
	}

	private function team_id(): string {
		return trim( (string) Settings::get( 'apple_team_id', '' ) );
	}

	private function key_id(): string {
		return trim( (string) Settings::get( 'apple_key_id', '' ) );
	}
}
