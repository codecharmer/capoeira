<?php
/**
 * Sign in with Google (OIDC authorization code + PKCE).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use Pura\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Google_Provider extends Oidc_Provider {

	public function id(): string {
		return 'google';
	}

	public function label(): string {
		return __( 'Continuar con Google', 'pura' );
	}

	public function is_configured(): bool {
		return '' !== $this->client_id() && '' !== Settings::get_secret( 'google_client_secret' );
	}

	public function authorization_url( string $state, string $nonce, string $code_challenge ): string {
		return add_query_arg(
			array(
				'client_id'             => $this->client_id(),
				'redirect_uri'          => $this->redirect_uri(),
				'response_type'         => 'code',
				'scope'                 => 'openid email profile',
				'state'                 => $state,
				'nonce'                 => $nonce,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => 'S256',
				'access_type'           => 'online',
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	public function exchange_code( string $code, string $code_verifier ) {
		return $this->post_token(
			array(
				'code'          => $code,
				'client_id'     => $this->client_id(),
				'client_secret' => Settings::get_secret( 'google_client_secret' ),
				'redirect_uri'  => $this->redirect_uri(),
				'grant_type'    => 'authorization_code',
				'code_verifier' => $code_verifier,
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

		$first = trim( (string) ( $claims['given_name'] ?? '' ) );
		$last  = trim( (string) ( $claims['family_name'] ?? '' ) );
		$name  = trim( (string) ( $claims['name'] ?? trim( $first . ' ' . $last ) ) );

		return array(
			'sub'              => $sub,
			'email'            => strtolower( trim( (string) ( $claims['email'] ?? '' ) ) ),
			'email_verified'   => self::truthy( $claims['email_verified'] ?? false ),
			'first_name'       => $first,
			'last_name'        => $last,
			'display_name'     => $name,
			'is_private_email' => false,
		);
	}

	protected function token_endpoint(): string {
		return 'https://oauth2.googleapis.com/token';
	}

	protected function jwks_url(): string {
		return 'https://www.googleapis.com/oauth2/v3/certs';
	}

	protected function issuers(): array {
		return array( 'https://accounts.google.com', 'accounts.google.com' );
	}

	protected function client_id(): string {
		return trim( (string) Settings::get( 'google_client_id', '' ) );
	}
}
