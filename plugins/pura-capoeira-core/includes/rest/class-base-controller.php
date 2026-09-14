<?php
/**
 * Shared REST plumbing: namespace, legacy-compatible responses, public-route protection.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Rate_Limiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

abstract class Base_Controller {

	public const NAMESPACE = 'pura/v1';

	abstract public function register_routes(): void;

	/**
	 * `{ok:true, ...}` with optional headers.
	 *
	 * @param array<string, mixed>  $data    Payload.
	 * @param array<string, string> $headers Extra headers.
	 */
	protected function ok( array $data = array(), int $status = 200, array $headers = array() ): WP_REST_Response {
		$response = new WP_REST_Response( array_merge( array( 'ok' => true ), $data ), $status );
		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	/**
	 * `{ok:false, error}` — the legacy error shape. Extra keys (e.g. not_registered) are merged in.
	 *
	 * @param array<string, mixed> $extra Extra keys.
	 */
	protected function error( string $message, int $status = 400, array $extra = array() ): WP_REST_Response {
		return new WP_REST_Response(
			array_merge(
				array(
					'ok'    => false,
					'error' => $message,
				),
				$extra
			),
			$status
		);
	}

	/**
	 * Permission callback factory for public POST routes: JSON content type, honeypot, rate limit.
	 *
	 * @return callable(WP_REST_Request): (bool|WP_Error)
	 */
	protected function public_permission( string $bucket, int $limit, int $window = 600 ): callable {
		return static function ( WP_REST_Request $request ) use ( $bucket, $limit, $window ) {
			if ( 'POST' === $request->get_method() ) {
				$content_type = (string) $request->get_header( 'content-type' );
				if ( false === stripos( $content_type, 'application/json' ) ) {
					return new WP_Error( 'pura_bad_content_type', 'Se esperaba application/json.', array( 'status' => 415 ) );
				}

				$honeypot = $request->get_param( 'website' );
				if ( is_string( $honeypot ) && '' !== trim( $honeypot ) ) {
					return new WP_Error( 'pura_rejected', 'Solicitud rechazada.', array( 'status' => 400 ) );
				}
			}

			if ( ! Rate_Limiter::check( $bucket, Rate_Limiter::client_key(), $limit, $window ) ) {
				return new WP_Error( 'pura_rate_limited', 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.', array( 'status' => 429 ) );
			}

			return true;
		};
	}

	/**
	 * @return callable(): bool
	 */
	protected function admin_permission(): callable {
		return static fn (): bool => current_user_can( 'manage_options' );
	}

	/**
	 * Errors raised by permission callbacks or arg validation come back as WP_Error; keep the
	 * legacy `{ok:false, error}` shape on those too.
	 */
	public static function shape_wp_error( $response, $handler, WP_REST_Request $request ) {
		if ( $response instanceof WP_Error && str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/' ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => $response->get_error_message(),
					'code'  => $response->get_error_code(),
				),
				$status
			);
		}

		return $response;
	}
}
