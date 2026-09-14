<?php
/**
 * /pura/v1/admin/* — diagnostics for administrators only.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Mailer;
use Pura\Core\Settings;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Admin_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/notify-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'notify_status' ),
				'permission_callback' => $this->admin_permission(),
				'show_in_index'       => false,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/test-notification',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_notification' ),
				'permission_callback' => $this->admin_permission(),
				'show_in_index'       => false,
			)
		);
	}

	public function notify_status(): WP_REST_Response {
		$recipients = Mailer::recipients();

		return $this->ok(
			array(
				'recipients_configured' => count( $recipients ),
				'recipients_masked'     => array_map( array( $this, 'mask' ), $recipients ),
				'notify_from_masked'    => $this->mask( (string) Settings::get( 'notify_from_email', '' ) ),
				'smtp_plugin'           => is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) ? 'fluent-smtp' : null,
				'pages'                 => array(
					'store'        => Settings::page_url( 'store' ),
					'inscriptions' => Settings::page_url( 'inscriptions' ),
				),
				'secrets'               => array(
					'stripe_secret_key'     => Settings::secret_source( 'stripe_secret_key' ),
					'stripe_webhook_secret' => Settings::secret_source( 'stripe_webhook_secret' ),
					'printful_api_key'      => Settings::secret_source( 'printful_api_key' ),
				),
				'printful_mock'         => defined( 'PURA_PRINTFUL_MOCK' ) && PURA_PRINTFUL_MOCK,
				'printful_confirm'      => ! ( defined( 'PURA_PRINTFUL_CONFIRM_DISABLED' ) && PURA_PRINTFUL_CONFIRM_DISABLED ),
			)
		);
	}

	public function test_notification(): WP_REST_Response {
		$result = Mailer::send_test();

		return $this->ok( $result, $result['ok'] ? 200 : 500 );
	}

	private function mask( string $email ): string {
		if ( ! is_email( $email ) ) {
			return '';
		}
		[ $user, $domain ] = explode( '@', $email, 2 );

		return substr( $user, 0, 2 ) . str_repeat( '*', max( 1, strlen( $user ) - 2 ) ) . '@' . $domain;
	}
}
