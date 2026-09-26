<?php
/**
 * POST /pura/v1/stripe/webhook — signature-verified, idempotent.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Http\Stripe_Signature;
use Pura\Core\Settings;
use Pura\Core\Webhook_Handler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Webhook_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/stripe/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'show_in_index'       => false,
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$payload   = (string) $request->get_body();
		$signature = (string) $request->get_header( 'stripe-signature' );
		$secret    = Settings::get_secret( 'stripe_webhook_secret' );

		if ( ! Stripe_Signature::verify( $payload, $signature, $secret ) ) {
			return new WP_REST_Response(
				array(
					'received' => false,
					'error'    => 'Firma inválida.',
				),
				400
			);
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) ) {
			return new WP_REST_Response(
				array(
					'received' => false,
					'error'    => 'Evento inválido.',
				),
				400
			);
		}

		try {
			$result = ( new Webhook_Handler() )->handle( $event );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response(
				array(
					'received' => false,
					'error'    => $e->getMessage(),
				),
				500
			);
		}

		$body = array( 'received' => true );
		if ( Webhook_Handler::RESULT_DUPLICATE === $result ) {
			$body['duplicate'] = true;
		} elseif ( Webhook_Handler::RESULT_IGNORED === $result ) {
			$body['ignored'] = true;
		}

		return new WP_REST_Response( $body, 200 );
	}
}
