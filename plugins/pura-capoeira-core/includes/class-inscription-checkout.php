<?php
/**
 * Create a pending inscription and its Stripe Checkout Session. Shared by the public inscription
 * form and the student portal.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Student_Repository;
use Pura\Core\Http\Stripe_Client;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Inscription_Checkout {

	/**
	 * @param array<string, mixed> $base        Inscription data (see Inscription_Repository::create()):
	 *                                          plan, label, amount_cents, currency, group, promocode,
	 *                                          promo_type, trial_date, member, student_id, email, source…
	 * @param string               $success_url Where Stripe sends the browser after paying.
	 * @param string               $cancel_url  Where Stripe sends the browser on cancel.
	 * @return array{url:string,inscription_id:int}|WP_Error
	 */
	public static function start( array $base, string $success_url, string $cancel_url ) {
		$post_id = Inscription_Repository::create( array_merge( $base, array( 'status' => 'pending_payment' ) ) );
		if ( ! $post_id ) {
			return new WP_Error( 'pura_inscription_save', 'No se pudo guardar la inscripción. Intenta de nuevo.', array( 'status' => 500 ) );
		}

		$stripe = new Stripe_Client();
		if ( ! $stripe->is_configured() ) {
			Inscription_Repository::delete( $post_id );

			return new WP_Error( 'pura_stripe_unconfigured', 'Los pagos en línea no están disponibles por el momento. Escríbenos por WhatsApp.', array( 'status' => 503 ) );
		}

		$student_id = (int) ( $base['student_id'] ?? 0 );
		$email      = (string) ( $base['email'] ?? '' );
		$label      = (string) ( $base['label'] ?? '' );
		$amount     = (int) ( $base['amount_cents'] ?? 0 );
		$currency   = (string) ( $base['currency'] ?? Settings::get( 'currency', 'MXN' ) );
		$name       = $student_id ? Student_Repository::full_name( $student_id ) : '';

		$session = $stripe->create_checkout_session(
			array(
				'mode'                 => 'payment',
				'success_url'          => $success_url,
				'cancel_url'           => $cancel_url,
				'customer_email'       => $email,
				'client_reference_id'  => (string) $post_id,
				'payment_method_types' => array( 'card' ),
				'line_items'           => array(
					array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => strtolower( $currency ),
							'unit_amount'  => $amount,
							'product_data' => array( 'name' => 'Pura Capoeira — ' . $label ),
						),
					),
				),
				'metadata'             => array(
					'type'           => 'inscription',
					'inscription_id' => (string) $post_id,
					'plan'           => (string) ( $base['plan'] ?? '' ),
					'label'          => $label,
					'student_name'   => $name,
					'email'          => $email,
					'group'          => (string) ( $base['group'] ?? '' ),
					'member'         => ! empty( $base['member'] ) ? '1' : '0',
					'trial_date'     => (string) ( $base['trial_date'] ?? '' ),
					'source'         => (string) ( $base['source'] ?? 'form' ),
				),
			),
			'inscription-' . $post_id . '-' . wp_generate_uuid4()
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) || empty( $session['id'] ) ) {
			Inscription_Repository::delete( $post_id );
			$message = is_wp_error( $session ) ? $session->get_error_message() : 'No se pudo iniciar el pago.';

			return new WP_Error( 'pura_stripe_session', $message, array( 'status' => 502 ) );
		}

		Inscription_Repository::set_session( $post_id, (string) $session['id'] );

		return array(
			'url'            => (string) $session['url'],
			'inscription_id' => $post_id,
		);
	}

	/**
	 * Append `?key=value` to a page URL that may already carry a query string.
	 */
	public static function return_url( string $page_url, string $key, string $value ): string {
		$sep = str_contains( $page_url, '?' ) ? '&' : '?';

		return $page_url . $sep . rawurlencode( $key ) . '=' . $value;
	}
}
