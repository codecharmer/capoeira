<?php
/**
 * /pura/v1/account/* — the signed-in student's portal data.
 *
 * Cookie authentication: WordPress only sets the current user when a valid `X-WP-Nonce` is sent,
 * so `is_user_logged_in()` doubles as the CSRF check.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Auth\Student_Accounts;
use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Student_Repository;
use Pura\Core\Inscription_Checkout;
use Pura\Core\Pricing;
use Pura\Core\Rate_Limiter;
use Pura\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Account_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/account/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => $this->logged_in_permission(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/account/profile',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'profile' ),
				'permission_callback' => $this->logged_in_permission( 'account-profile', 20 ),
				'args'                => array(
					'phone'           => $this->text_arg( 40 ),
					'parent_name'     => $this->text_arg( 150 ),
					'parent_phone'    => $this->text_arg( 40 ),
					'emergency_phone' => $this->text_arg( 40 ),
					'address'         => $this->text_arg( 300 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/account/pay-monthly',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pay_monthly' ),
				'permission_callback' => $this->logged_in_permission( 'account-pay', 10 ),
			)
		);
	}

	public function me( WP_REST_Request $request ): WP_REST_Response {
		$user       = wp_get_current_user();
		$student_id = Student_Accounts::student_id_for_user( $user->ID );

		return $this->ok( $this->payload( $user->ID, $student_id ), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	public function profile( WP_REST_Request $request ): WP_REST_Response {
		$student_id = Student_Accounts::student_id_for_user( get_current_user_id() );
		if ( ! $student_id ) {
			return $this->error( 'Tu cuenta aún no está vinculada a una ficha de alumno.', 409, array( 'not_linked' => true ) );
		}

		$student = Student_Repository::to_array( $student_id );
		$profile = array( 'email' => $student['email'] );
		foreach ( array( 'phone', 'parent_name', 'parent_phone', 'emergency_phone', 'address' ) as $field ) {
			$profile[ $field ] = (string) $request->get_param( $field );
		}

		if ( ! Student_Repository::upsert( $profile ) ) {
			return $this->error( 'No se pudieron guardar tus datos. Intenta de nuevo.', 500 );
		}

		return $this->ok( $this->payload( get_current_user_id(), $student_id ) );
	}

	public function pay_monthly( WP_REST_Request $request ): WP_REST_Response {
		$student_id = Student_Accounts::student_id_for_user( get_current_user_id() );
		if ( ! $student_id ) {
			return $this->error( 'Tu cuenta aún no está vinculada a una ficha de alumno.', 409, array( 'not_linked' => true ) );
		}

		$student = Student_Repository::to_array( $student_id );
		$monthly = $this->monthly( $student_id, $student );
		if ( ! $monthly ) {
			return $this->error( 'No hay una mensualidad configurada.', 500 );
		}

		$account_url = Settings::page_url( 'account' );
		$result      = Inscription_Checkout::start(
			array(
				'plan'                => 'month',
				'label'               => $monthly['label'],
				'amount_cents'        => $monthly['amount_cents'],
				'currency'            => $monthly['currency'],
				'group'               => $monthly['group'],
				'promocode'           => '',
				'promo_type'          => $monthly['promo_type'],
				'trial_date'          => '',
				'member'              => true,
				'student_id'          => $student_id,
				'email'               => $student['email'],
				'privacy_accepted_at' => current_time( 'mysql' ),
				'source'              => 'portal',
			),
			Inscription_Checkout::return_url( $account_url, 'pago', 'success' ),
			Inscription_Checkout::return_url( $account_url, 'pago', 'cancel' )
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();

			return $this->error( $result->get_error_message(), is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 502 );
		}

		return $this->ok( $result );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload( int $user_id, int $student_id ): array {
		$user    = get_user_by( 'id', $user_id );
		$student = $student_id ? Student_Repository::to_array( $student_id ) : null;

		$inscriptions = array();
		if ( $student_id ) {
			foreach ( Student_Accounts::inscription_ids( $student_id ) as $id ) {
				$row = Inscription_Repository::to_array( $id );
				if ( ! $row ) {
					continue;
				}
				$inscriptions[] = array(
					'id'          => $row['id'],
					'createdAt'   => $row['created_at'],
					'label'       => $row['label'],
					'status'      => $row['status'],
					'statusLabel' => Inscription_Post_Type::status_label( $row['status'] ),
					'amount'      => Pricing::to_pesos( (int) $row['amount_cents'] ),
					'currency'    => $row['currency'],
					'paidAt'      => $row['paid_at'],
					'trialDate'   => $row['trial_date'],
				);
			}
		}

		$monthly = $student_id ? $this->monthly( $student_id, $student ) : null;

		return array(
			'user'         => array(
				'displayName'  => $user ? $user->display_name : '',
				'email'        => $user ? $user->user_email : '',
				'privateEmail' => $user && get_user_meta( $user->ID, Student_Accounts::META_PRIVATE_EMAIL, true ) ? true : false,
			),
			'student'      => $student ? array(
				'firstName'      => $student['first_name'],
				'lastName'       => $student['last_name'],
				'email'          => $student['email'],
				'phone'          => $student['phone'],
				'parentName'     => $student['parent_name'],
				'parentPhone'    => $student['parent_phone'],
				'emergencyPhone' => $student['emergency_phone'],
				'address'        => $student['address'],
				'group'          => $student['group'] ?: 'adult',
				'lastPaidAt'     => (string) get_post_meta( $student_id, '_pura_last_paid_at', true ),
			) : null,
			'inscriptions' => $inscriptions,
			'monthly'      => $monthly ? array(
				'amount'         => Pricing::to_pesos( $monthly['amount_cents'] ),
				'currency'       => $monthly['currency'],
				'group'          => $monthly['group'],
				'label'          => $monthly['label'],
				'currentStudent' => Pricing::PROMO_CURRENT === $monthly['promo_type'],
			) : null,
			// wp_logout_url() returns an HTML-escaped URL (&amp;); this one travels as JSON into a href.
			'logoutUrl'    => html_entity_decode( wp_logout_url( home_url( '/' ) ), ENT_QUOTES, 'UTF-8' ),
			'whatsappUrl'  => Settings::whatsapp_url( 'Hola, inicié sesión en Mi cuenta pero no encuentro mi ficha de alumno.' ),
		);
	}

	/**
	 * Monthly fee for the student: alumno-actual price once they have paid (or beca) history.
	 *
	 * @param array<string, string>|null $student Student profile.
	 * @return array{label:string,amount_cents:int,currency:string,group:string,promo_type:string}|null
	 */
	private function monthly( int $student_id, ?array $student ): ?array {
		$group = Pricing::group( (string) ( $student['group'] ?? 'adult' ) );
		$promo = Student_Accounts::has_paid_history( $student_id ) ? Pricing::PROMO_CURRENT : Pricing::PROMO_NONE;
		$plan  = Pricing::plan( 'month', $group, $promo );
		if ( ! $plan ) {
			return null;
		}

		return array(
			'label'        => 'Mensualidad' . ( Pricing::PROMO_CURRENT === $promo ? ' (alumno actual)' : '' ),
			'amount_cents' => (int) $plan['amount'],
			'currency'     => (string) Settings::get( 'currency', 'MXN' ),
			'group'        => $group,
			'promo_type'   => $promo,
		);
	}

	/**
	 * @return callable(WP_REST_Request): (bool|WP_Error)
	 */
	private function logged_in_permission( string $bucket = '', int $limit = 0 ): callable {
		return static function ( WP_REST_Request $request ) use ( $bucket, $limit ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'pura_login_required', 'Inicia sesión para continuar.', array( 'status' => 401 ) );
			}
			if ( 'POST' === $request->get_method() ) {
				$content_type = (string) $request->get_header( 'content-type' );
				if ( false === stripos( $content_type, 'application/json' ) ) {
					return new WP_Error( 'pura_bad_content_type', 'Se esperaba application/json.', array( 'status' => 415 ) );
				}
			}
			if ( '' !== $bucket && ! Rate_Limiter::check( $bucket, 'user-' . get_current_user_id(), $limit, 600 ) ) {
				return new WP_Error( 'pura_rate_limited', 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.', array( 'status' => 429 ) );
			}

			return true;
		};
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
}
