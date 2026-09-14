<?php
/**
 * /pura/v1/inscriptions/* — plan config, promo validation, and registration.
 *
 * Response shapes are the legacy contract consumed by the inscription form's view script.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Student_Repository;
use Pura\Core\Http\Stripe_Client;
use Pura\Core\Mailer;
use Pura\Core\Pricing;
use Pura\Core\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Inscriptions_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/inscriptions/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'group' => $this->group_arg() ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/inscriptions/validate-promo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_promo' ),
				'permission_callback' => $this->public_permission( 'validate-promo', 30 ),
				'args'                => array(
					'group'     => $this->group_arg(),
					'promocode' => $this->text_arg( 64 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/inscriptions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => $this->public_permission( 'inscriptions', 5 ),
				'args'                => array(
					'plan'            => array(
						'type'     => 'string',
						'enum'     => Pricing::plan_ids(),
						'required' => true,
					),
					'group'           => $this->group_arg(),
					'add_inscription' => array(
						'type'    => array( 'boolean', 'integer', 'string' ),
						'default' => 0,
					),
					'promocode'       => $this->text_arg( 64 ),
					'payment_mode'    => array(
						'type'    => 'string',
						'enum'    => array( 'now', 'later' ),
						'default' => 'now',
					),
					'trial_date'      => array(
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2}-\d{2})?$',
						'default' => '',
					),
					'member'          => array(
						'type'    => array( 'boolean', 'integer', 'string' ),
						'default' => false,
					),
					'email'           => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
					),
					'first_name'      => $this->text_arg( 100 ),
					'last_name'       => $this->text_arg( 100 ),
					'parent_name'     => $this->text_arg( 150 ),
					'address'         => $this->text_arg( 300 ),
					'phone'           => $this->text_arg( 40 ),
					'parent_phone'    => $this->text_arg( 40 ),
					'emergency_phone' => $this->text_arg( 40 ),
					'dob'             => array(
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2}-\d{2})?$',
						'default' => '',
					),
					'website'         => $this->text_arg( 200 ), // Honeypot.
				),
			)
		);
	}

	public function config( WP_REST_Request $request ): WP_REST_Response {
		$group = Pricing::group( (string) $request->get_param( 'group' ) );

		return $this->ok(
			array(
				'currency'     => (string) Settings::get( 'currency', 'MXN' ),
				'group'        => $group,
				'addon_amount' => Pricing::to_pesos( Pricing::addon_cents() ),
				'monthly'      => Pricing::to_pesos( Pricing::monthly_cents( $group ) ),
				'plans'        => Pricing::plans_in_pesos( $group ),
			),
			200,
			array( 'Cache-Control' => 'public, max-age=300' )
		);
	}

	public function validate_promo( WP_REST_Request $request ): WP_REST_Response {
		$group = Pricing::group( (string) $request->get_param( 'group' ) );
		$promo = Pricing::resolve_promo( (string) $request->get_param( 'promocode' ), $group );

		if ( Pricing::PROMO_NONE === $promo['type'] ) {
			return $this->ok( array( 'valid' => false ) );
		}

		return $this->ok(
			array(
				'valid'            => true,
				'type'             => $promo['type'],
				'free'             => $promo['free'],
				'payment_optional' => $promo['payment_optional'],
				'monthly'          => Pricing::to_pesos( Pricing::monthly_cents( $group, $promo['type'] ) ),
				'group'            => $group,
				'addon_amount'     => Pricing::to_pesos( Pricing::addon_cents() ),
				'plans'            => Pricing::plans_in_pesos( $group, $promo['type'] ),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$group        = Pricing::group( (string) $request->get_param( 'group' ) );
		$plan_id      = (string) $request->get_param( 'plan' );
		$is_member    = rest_sanitize_boolean( $request->get_param( 'member' ) );
		$add_addon    = rest_sanitize_boolean( $request->get_param( 'add_inscription' ) );
		$promocode    = trim( (string) $request->get_param( 'promocode' ) );
		$payment_mode = (string) $request->get_param( 'payment_mode' );
		$email        = Student_Repository::normalize_email( (string) $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return $this->error( 'Escribe un correo electrónico válido.', 400 );
		}

		// Resolve the student profile.
		if ( $is_member ) {
			$student_id = Student_Repository::find_id_by_email( $email );
			if ( ! $student_id ) {
				return $this->error(
					'No encontramos un registro con ese correo. Completa tu inscripción como alumno nuevo.',
					404,
					array( 'not_registered' => true )
				);
			}
			$profile = array(
				'email' => $email,
				'group' => $group,
			);
		} else {
			$profile  = array(
				'email'           => $email,
				'first_name'      => (string) $request->get_param( 'first_name' ),
				'last_name'       => (string) $request->get_param( 'last_name' ),
				'parent_name'     => (string) $request->get_param( 'parent_name' ),
				'address'         => (string) $request->get_param( 'address' ),
				'phone'           => (string) $request->get_param( 'phone' ),
				'parent_phone'    => (string) $request->get_param( 'parent_phone' ),
				'emergency_phone' => (string) $request->get_param( 'emergency_phone' ),
				'dob'             => (string) $request->get_param( 'dob' ),
				'group'           => $group,
			);
			$required = array(
				'first_name'      => 'Escribe tu nombre.',
				'last_name'       => 'Escribe tus apellidos.',
				'phone'           => 'Escribe un teléfono de contacto.',
				'emergency_phone' => 'Escribe un teléfono de emergencia.',
				'address'         => 'Escribe tu dirección.',
				'dob'             => 'Indica la fecha de nacimiento.',
			);
			foreach ( $required as $field => $message ) {
				if ( '' === trim( $profile[ $field ] ) ) {
					return $this->error( $message, 400 );
				}
			}
			if ( ! $this->valid_date( $profile['dob'] ) ) {
				return $this->error( 'La fecha de nacimiento no es válida.', 400 );
			}
		}

		// Pricing is always resolved server-side.
		$promo = Pricing::resolve_promo( $promocode, $group );
		$plan  = Pricing::plan( $plan_id, $group, $promo['type'] );
		if ( ! $plan ) {
			return $this->error( 'Paquete no válido.', 400 );
		}

		$amount = (int) $plan['amount'];
		$label  = (string) $plan['label'];
		if ( $add_addon && $plan['allow_addon'] ) {
			$amount += Pricing::addon_cents();
			$label  .= ' + inscripción';
		}
		if ( $promo['free'] ) {
			$amount = 0;
			$label .= ' (beca 100%)';
		}

		$trial_date = '';
		if ( 'trial' === $plan_id ) {
			$trial_date = (string) $request->get_param( 'trial_date' );
			if ( ! $this->valid_date( $trial_date ) ) {
				return $this->error( 'Elige la fecha de tu clase de prueba.', 400 );
			}
			$today = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
			if ( $trial_date < $today ) {
				return $this->error( 'La fecha de la clase de prueba no puede ser anterior a hoy.', 400 );
			}
		}

		$student_id = Student_Repository::upsert( $profile );
		if ( ! $student_id ) {
			return $this->error( 'No se pudo guardar el registro. Intenta de nuevo.', 500 );
		}

		$base = array(
			'plan'         => $plan_id,
			'label'        => $label,
			'amount_cents' => $amount,
			'currency'     => (string) Settings::get( 'currency', 'MXN' ),
			'group'        => $group,
			'promocode'    => $promocode,
			'promo_type'   => $promo['type'],
			'trial_date'   => $trial_date,
			'member'       => $is_member,
			'student_id'   => $student_id,
			'email'        => $email,
		);

		// Branch A: nothing to charge now (beca, or pay in person under a promo).
		$pay_later = $promo['payment_optional'] && ( 'later' === $payment_mode || $promo['free'] );
		if ( $amount <= 0 || $pay_later ) {
			$status  = ( $amount <= 0 ) ? 'free' : 'pending_payment_offline';
			$post_id = Inscription_Repository::create( array_merge( $base, array( 'status' => $status ) ) );
			if ( ! $post_id ) {
				return $this->error( 'No se pudo guardar la inscripción. Intenta de nuevo.', 500 );
			}
			Mailer::notify_inscription( $post_id );

			$message = 'free' === $status
				? 'Inscripción registrada con beca del 100%. Te contactaremos para confirmar tu lugar.'
				: 'Inscripción registrada. Te contactaremos para coordinar el pago de ' . number_format( $amount / 100, 2 ) . ' ' . $base['currency'] . '.';

			// `free: true` is returned for both cases; the form keys off it (legacy contract).
			return $this->ok(
				array(
					'free'    => true,
					'message' => $message,
					'status'  => $status,
				)
			);
		}

		// Branch B: Stripe Checkout. Create the record first so the session can reference it.
		$post_id = Inscription_Repository::create( array_merge( $base, array( 'status' => 'pending_payment' ) ) );
		if ( ! $post_id ) {
			return $this->error( 'No se pudo guardar la inscripción. Intenta de nuevo.', 500 );
		}

		$stripe = new Stripe_Client();
		if ( ! $stripe->is_configured() ) {
			Inscription_Repository::delete( $post_id );
			return $this->error( 'Los pagos en línea no están disponibles por el momento. Escríbenos por WhatsApp.', 503 );
		}

		$page_url = Settings::page_url( 'inscriptions' );
		$sep      = str_contains( $page_url, '?' ) ? '&' : '?';
		$name     = Student_Repository::full_name( $student_id );

		$session = $stripe->create_checkout_session(
			array(
				'mode'                 => 'payment',
				'success_url'          => $page_url . $sep . 'inscription=success&session_id={CHECKOUT_SESSION_ID}',
				'cancel_url'           => $page_url . $sep . 'inscription=cancel',
				'customer_email'       => $email,
				'client_reference_id'  => (string) $post_id,
				'payment_method_types' => array( 'card' ),
				'line_items'           => array(
					array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => strtolower( (string) Settings::get( 'currency', 'MXN' ) ),
							'unit_amount'  => $amount,
							'product_data' => array( 'name' => 'Pura Capoeira — ' . $label ),
						),
					),
				),
				'metadata'             => array(
					'type'           => 'inscription',
					'inscription_id' => (string) $post_id,
					'plan'           => $plan_id,
					'label'          => $label,
					'student_name'   => $name,
					'email'          => $email,
					'group'          => $group,
					'member'         => $is_member ? '1' : '0',
					'trial_date'     => $trial_date,
				),
			),
			'inscription-' . $post_id . '-' . wp_generate_uuid4()
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) || empty( $session['id'] ) ) {
			Inscription_Repository::delete( $post_id );
			$message = is_wp_error( $session ) ? $session->get_error_message() : 'No se pudo iniciar el pago.';

			return $this->error( $message, 502 );
		}

		Inscription_Repository::set_session( $post_id, (string) $session['id'] );

		return $this->ok(
			array(
				'url'            => (string) $session['url'],
				'inscription_id' => $post_id,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function group_arg(): array {
		return array(
			'type'              => 'string',
			'enum'              => Pricing::GROUPS,
			'default'           => 'adult',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function text_arg( int $max ): array {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => static fn ( $value ) => mb_substr( sanitize_text_field( (string) $value ), 0, $max ),
		);
	}

	private function valid_date( string $date ): bool {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return $dt instanceof \DateTimeImmutable && $dt->format( 'Y-m-d' ) === $date;
	}
}
