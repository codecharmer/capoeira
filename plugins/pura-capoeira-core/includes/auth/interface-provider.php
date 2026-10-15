<?php
/**
 * An OpenID Connect identity provider (Google, Apple) used for student sign-in.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use WP_Error;

defined( 'ABSPATH' ) || exit;

interface Provider_Interface {

	/** Short id used in routes and meta keys: 'google' | 'apple'. */
	public function id(): string;

	/** Button label, e.g. "Continuar con Google". */
	public function label(): string;

	/** Whether the admin configured every value the flow needs. */
	public function is_configured(): bool;

	/** Absolute callback URL registered with the provider. */
	public function redirect_uri(): string;

	/**
	 * URL to send the browser to.
	 *
	 * @param string $state          Opaque single-use state.
	 * @param string $nonce          OIDC nonce, echoed in the id_token.
	 * @param string $code_challenge PKCE S256 challenge (ignored by providers without PKCE).
	 */
	public function authorization_url( string $state, string $nonce, string $code_challenge ): string;

	/**
	 * Exchange the authorization code for tokens.
	 *
	 * @return array<string, mixed>|WP_Error Decoded token response (must contain id_token).
	 */
	public function exchange_code( string $code, string $code_verifier );

	/**
	 * Verify the id_token and normalise the identity.
	 *
	 * @param array<string, mixed> $tokens          Token response.
	 * @param array<string, mixed> $callback_params Raw callback params (Apple sends `user`).
	 * @param string               $nonce           Expected nonce.
	 * @return array{sub:string,email:string,email_verified:bool,first_name:string,last_name:string,display_name:string,is_private_email:bool}|WP_Error
	 */
	public function identity( array $tokens, array $callback_params, string $nonce );
}
