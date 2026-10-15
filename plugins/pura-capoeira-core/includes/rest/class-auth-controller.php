<?php
/**
 * /pura/v1/auth/{provider}/start|callback — Sign in with Google / Apple.
 *
 * Both routes answer with redirects. `start` is POST-only (the buttons are small forms) so a page
 * cache can never replay a `state`; `callback` accepts GET (Google) and POST (Apple form_post).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Auth\Jwt;
use Pura\Core\Auth\Providers;
use Pura\Core\Auth\State_Store;
use Pura\Core\Auth\Student_Accounts;
use Pura\Core\Rate_Limiter;
use Pura\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Auth_Controller extends Base_Controller {

	public function register_routes(): void {
		$provider_arg = array(
			'type'     => 'string',
			'enum'     => Providers::IDS,
			'required' => true,
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/(?P<provider>[a-z]+)/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => $this->rate_limited( 'auth-start', 20 ),
				'args'                => array(
					'provider'    => $provider_arg,
					'redirect_to' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/(?P<provider>[a-z]+)/callback',
			array(
				'methods'             => array( WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ),
				'callback'            => array( $this, 'callback' ),
				'permission_callback' => $this->rate_limited( 'auth-callback', 30 ),
				'args'                => array(
					'provider' => $provider_arg,
					'code'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'state'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'error'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'user'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public function start( WP_REST_Request $request ): WP_REST_Response {
		$provider = Providers::get( (string) $request->get_param( 'provider' ) );
		if ( ! $provider || ! $provider->is_configured() ) {
			return $this->error( 'Este método de acceso no está disponible.', 404 );
		}

		$account_url = Settings::page_url( 'account' );
		$redirect_to = wp_validate_redirect( (string) $request->get_param( 'redirect_to' ), $account_url );

		$code_verifier  = Jwt::base64url_encode( random_bytes( 32 ) );
		$code_challenge = Jwt::base64url_encode( hash( 'sha256', $code_verifier, true ) );
		$nonce          = bin2hex( random_bytes( 16 ) );

		$state = State_Store::create(
			array(
				'provider'      => $provider->id(),
				'nonce'         => $nonce,
				'code_verifier' => $code_verifier,
				'redirect_to'   => $redirect_to,
			)
		);

		return $this->redirect( $provider->authorization_url( $state, $nonce, $code_challenge ), 302 );
	}

	public function callback( WP_REST_Request $request ): WP_REST_Response {
		$provider_id = (string) $request->get_param( 'provider' );
		$provider    = Providers::get( $provider_id );
		if ( ! $provider ) {
			return $this->fail( 'provider' );
		}

		if ( '' !== (string) $request->get_param( 'error' ) ) {
			// The user cancelled on the provider's screen (Apple sends user_cancelled_authorize).
			return $this->fail( 'denied' );
		}

		$state = State_Store::consume( (string) $request->get_param( 'state' ) );
		if ( ! $state || ( $state['provider'] ?? '' ) !== $provider_id ) {
			return $this->fail( 'state' );
		}
		$redirect_to = (string) ( $state['redirect_to'] ?? Settings::page_url( 'account' ) );

		$code = (string) $request->get_param( 'code' );
		if ( '' === $code || ! $provider->is_configured() ) {
			return $this->fail( 'code', $redirect_to );
		}

		$tokens = $provider->exchange_code( $code, (string) ( $state['code_verifier'] ?? '' ) );
		if ( is_wp_error( $tokens ) ) {
			$this->log( $provider_id, $tokens );

			return $this->fail( 'exchange', $redirect_to );
		}

		$identity = $provider->identity( $tokens, $request->get_params(), (string) ( $state['nonce'] ?? '' ) );
		if ( is_wp_error( $identity ) ) {
			$this->log( $provider_id, $identity );

			return $this->fail( 'token', $redirect_to );
		}

		$user = Student_Accounts::find_or_create_user( $identity, $provider_id );
		if ( is_wp_error( $user ) ) {
			$this->log( $provider_id, $user );

			return $this->fail( (string) $user->get_error_code(), $redirect_to );
		}

		Student_Accounts::login( $user );

		return $this->redirect( $redirect_to, 303 );
	}

	/**
	 * Rate limit only: these routes receive browser form posts, not JSON.
	 *
	 * @return callable(WP_REST_Request): (bool|WP_Error)
	 */
	private function rate_limited( string $bucket, int $limit ): callable {
		return static function () use ( $bucket, $limit ) {
			if ( ! Rate_Limiter::check( $bucket, Rate_Limiter::client_key(), $limit, 600 ) ) {
				return new WP_Error( 'pura_rate_limited', 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.', array( 'status' => 429 ) );
			}

			return true;
		};
	}

	private function redirect( string $url, int $status ): WP_REST_Response {
		$response = new WP_REST_Response( null, $status );
		$response->header( 'Location', $url );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	private function fail( string $code, string $redirect_to = '' ): WP_REST_Response {
		$base = Settings::page_url( 'account' );
		$url  = add_query_arg( 'auth_error', sanitize_key( $code ), $base );
		if ( '' !== $redirect_to && $redirect_to !== $base ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
		}

		return $this->redirect( $url, 303 );
	}

	private function log( string $provider, WP_Error $error ): void {
		error_log( '[pura] auth ' . $provider . ': ' . $error->get_error_code() . ' — ' . $error->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
