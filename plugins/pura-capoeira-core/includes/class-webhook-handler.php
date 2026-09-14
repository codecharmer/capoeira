<?php
/**
 * Processes a verified Stripe event exactly once.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Stripe_Events_Table;
use Pura\Core\Http\Printful_Client;

defined( 'ABSPATH' ) || exit;

final class Webhook_Handler {

	public const RESULT_PROCESSED = 'processed';
	public const RESULT_DUPLICATE = 'duplicate';
	public const RESULT_IGNORED   = 'ignored';

	/**
	 * @param array<string, mixed> $event Decoded Stripe event.
	 * @return string One of the RESULT_* constants.
	 * @throws \RuntimeException When processing fails (caller answers 500 so Stripe retries).
	 */
	public function handle( array $event ): string {
		$event_id = (string) ( $event['id'] ?? '' );
		$type     = (string) ( $event['type'] ?? '' );
		if ( '' === $event_id ) {
			throw new \RuntimeException( 'Evento sin id.' );
		}

		$claim = Stripe_Events_Table::claim( $event_id, $type );
		if ( Stripe_Events_Table::CLAIM_DUPLICATE === $claim ) {
			return self::RESULT_DUPLICATE;
		}

		try {
			$result = $this->dispatch( $type, (array) ( $event['data']['object'] ?? array() ) );
			Stripe_Events_Table::mark_done( $event_id );

			return $result;
		} catch ( \Throwable $e ) {
			Stripe_Events_Table::mark_failed( $event_id );
			error_log( '[pura] webhook ' . $event_id . ' failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			throw new \RuntimeException( esc_html( $e->getMessage() ), 0, $e );
		}
	}

	/**
	 * @param array<string, mixed> $session Checkout Session object.
	 */
	private function dispatch( string $type, array $session ): string {
		if ( 'checkout.session.expired' === $type ) {
			$this->expire_inscription( $session );
			return self::RESULT_PROCESSED;
		}

		if ( ! in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			return self::RESULT_IGNORED;
		}
		if ( 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return self::RESULT_IGNORED;
		}

		$kind = (string) ( $session['metadata']['type'] ?? '' );

		if ( 'store' === $kind ) {
			$order_id = (int) ( $session['metadata']['printful_order_id'] ?? 0 );
			if ( $order_id > 0 ) {
				$result = Printful_Client::resolve()->confirm_order( $order_id );
				if ( is_wp_error( $result ) ) {
					throw new \RuntimeException( esc_html( 'Printful confirm failed: ' . $result->get_error_message() ) );
				}
			}
			return self::RESULT_PROCESSED;
		}

		if ( 'inscription' === $kind ) {
			$post_id = (int) ( $session['metadata']['inscription_id'] ?? 0 );
			if ( ! $post_id ) {
				$post_id = Inscription_Repository::find_by_session( (string) ( $session['id'] ?? '' ) );
			}
			if ( ! $post_id || ! get_post( $post_id ) ) {
				throw new \RuntimeException( esc_html( 'Inscripción no encontrada para la sesión ' . (string) ( $session['id'] ?? '' ) ) );
			}

			$stored = (string) get_post_meta( $post_id, '_pura_stripe_session_id', true );
			if ( '' !== $stored && ! empty( $session['id'] ) && $stored !== $session['id'] ) {
				throw new \RuntimeException( esc_html( 'La sesión no coincide con la inscripción ' . $post_id ) );
			}

			if ( 'paid' !== get_post_meta( $post_id, '_pura_status', true ) ) {
				Inscription_Repository::set_paid( $post_id, $session );
				Mailer::notify_inscription( $post_id );
			}

			return self::RESULT_PROCESSED;
		}

		return self::RESULT_IGNORED;
	}

	/**
	 * @param array<string, mixed> $session Checkout Session object.
	 */
	private function expire_inscription( array $session ): void {
		if ( 'inscription' !== ( $session['metadata']['type'] ?? '' ) ) {
			return;
		}
		$post_id = (int) ( $session['metadata']['inscription_id'] ?? 0 );
		if ( $post_id && 'pending_payment' === get_post_meta( $post_id, '_pura_status', true ) ) {
			Inscription_Repository::set_status( $post_id, 'expired' );
		}
	}
}
