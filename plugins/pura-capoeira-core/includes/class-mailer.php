<?php
/**
 * Notification mail via wp_mail. Plain text, Spanish, same shape as the legacy notification.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

use Pura\Core\Data\Event_Registration_Repository;
use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/** @var string|null Last wp_mail error message. */
	private static ?string $last_error = null;

	/**
	 * @return string[]
	 */
	public static function recipients(): array {
		$raw = (string) Settings::get( 'notify_emails', '' );

		return array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );
	}

	/**
	 * Notify admins (and CC the student once) about an inscription post.
	 */
	public static function notify_inscription( int $post_id ): bool {
		$data = Inscription_Repository::to_array( $post_id );
		if ( ! $data ) {
			return false;
		}

		return self::notify( $data );
	}

	/**
	 * @param array<string, mixed> $data Inscription data (see Inscription_Repository::to_array()).
	 */
	public static function notify( array $data ): bool {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			return false;
		}

		$name    = trim( (string) ( $data['student_name'] ?? '' ) ) ?: (string) ( $data['email'] ?? '' );
		$status  = (string) ( $data['status'] ?? '' );
		$subject = sprintf( 'Nueva inscripción (%s) — %s', Inscription_Post_Type::status_label( $status ), $name );
		$body    = self::build_body( $data );
		$headers = self::headers();

		$student_email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( is_email( $student_email ) ) {
			$headers[] = 'Reply-To: ' . $student_email;
			if ( Settings::get( 'cc_student', true ) && ! in_array( strtolower( $student_email ), array_map( 'strtolower', $recipients ), true ) ) {
				$headers[] = 'Cc: ' . $student_email;
			}
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * Notify admins about an event registration, with a copy to the person who registered.
	 */
	public static function notify_event_registration( int $post_id ): bool {
		$data = Event_Registration_Repository::to_array( $post_id );
		if ( ! $data ) {
			return false;
		}

		$recipients = self::recipients();
		if ( ! $recipients ) {
			return false;
		}

		$event   = '' !== $data['event_name'] ? $data['event_name'] : $data['event'];
		$name    = '' !== $data['name'] ? $data['name'] : $data['email'];
		$subject = sprintf( 'Registro al evento %s — %s', $event, $name );
		$body    = self::build_event_body( $data );
		$headers = self::headers();

		$email = sanitize_email( $data['email'] );
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
			if ( Settings::get( 'cc_student', true ) && ! in_array( strtolower( $email ), array_map( 'strtolower', $recipients ), true ) ) {
				$headers[] = 'Cc: ' . $email;
			}
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * @param array<string, mixed> $data Event registration data (see Event_Registration_Repository::to_array()).
	 */
	public static function build_event_body( array $data ): string {
		$event = '' !== (string) $data['event_name'] ? (string) $data['event_name'] : (string) $data['event'];
		$lines = array( 'Nuevo registro al evento ' . $event . ' recibido en ' . home_url( '/' ), '' );

		$rows = array(
			'Evento'                 => $event,
			'Nombre'                 => (string) ( $data['name'] ?? '' ),
			'Correo'                 => (string) ( $data['email'] ?? '' ),
			'Teléfono / WhatsApp'    => (string) ( $data['phone'] ?? '' ),
			'Fecha de nacimiento'    => (string) ( $data['dob'] ?? '' ),
			'Padre/madre/tutor'      => (string) ( $data['parent_name'] ?? '' ),
			'Teléfono del tutor'     => (string) ( $data['parent_phone'] ?? '' ),
			'Ciudad'                 => (string) ( $data['city'] ?? '' ),
			'Grupo / academia'       => (string) ( $data['academy'] ?? '' ),
			'Mestre / Professor'     => (string) ( $data['teacher'] ?? '' ),
			'Graduación'             => (string) ( $data['graduation'] ?? '' ),
			'Empezó capoeira en'     => (string) ( $data['started_year'] ?? '' ),
			'Años de entrenamiento'  => (string) ( $data['years_training'] ?? '' ),
			'Días'                   => (string) ( $data['days'] ?? '' ),
			'Talla de playera'       => (string) ( $data['shirt_size'] ?? '' ),
			'Contacto de emergencia' => (string) ( $data['emergency_name'] ?? '' ),
			'Teléfono de emergencia' => (string) ( $data['emergency_phone'] ?? '' ),
			'Comentarios'            => (string) ( $data['notes'] ?? '' ),
			'Fecha de registro'      => (string) ( $data['created_at'] ?? wp_date( 'Y-m-d H:i:s' ) ),
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		if ( ! empty( $data['admin_url'] ) ) {
			$lines[] = '';
			$lines[] = 'Ver en el panel: ' . $data['admin_url'];
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return array{ok:bool,sent_count:int,total:int,message:string}
	 */
	public static function send_test(): array {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			return array(
				'ok'         => false,
				'sent_count' => 0,
				'total'      => 0,
				'message'    => 'No hay destinatarios configurados.',
			);
		}

		$subject = 'Prueba de notificaciones — Pura Capoeira';
		$body    = 'Este es un correo de prueba enviado desde ' . home_url( '/' ) . ' el ' . wp_date( 'Y-m-d H:i:s' ) . ".\n\nSi lo recibes, las notificaciones de inscripción funcionan.";
		$ok      = self::send( $recipients, $subject, $body, self::headers() );

		return array(
			'ok'         => $ok,
			'sent_count' => $ok ? count( $recipients ) : 0,
			'total'      => count( $recipients ),
			'message'    => $ok ? 'OK' : (string) ( self::$last_error ?? 'wp_mail devolvió false' ),
		);
	}

	/**
	 * @param array<string, mixed> $data Inscription data.
	 */
	public static function build_body( array $data ): string {
		$lines = array( 'Nueva inscripción recibida en ' . home_url( '/' ), '' );

		$rows = array(
			'Tipo'                     => ! empty( $data['member'] ) ? 'Alumno existente (pago con correo)' : 'Alumno nuevo',
			'Grupo'                    => 'kids' === ( $data['group'] ?? '' ) ? 'Niños' : 'Adultos',
			'Estado'                   => Inscription_Post_Type::status_label( (string) ( $data['status'] ?? '' ) ),
			'Paquete'                  => (string) ( $data['label'] ?? '' ),
			'Monto'                    => Pricing::format_pesos( (int) ( $data['amount_cents'] ?? 0 ) ) . ' ' . (string) ( $data['currency'] ?? 'MXN' ),
			'Alumno'                   => (string) ( $data['student_name'] ?? '' ),
			'Correo'                   => (string) ( $data['email'] ?? '' ),
			'Teléfono'                 => (string) ( $data['phone'] ?? '' ),
			'Fecha de clase de prueba' => (string) ( $data['trial_date'] ?? '' ),
			'Código promocional'       => (string) ( $data['promocode'] ?? '' ),
			'Padre/madre/tutor'        => (string) ( $data['parent_name'] ?? '' ),
			'Teléfono del tutor'       => (string) ( $data['parent_phone'] ?? '' ),
			'Teléfono de emergencia'   => (string) ( $data['emergency_phone'] ?? '' ),
			'Dirección'                => (string) ( $data['address'] ?? '' ),
			'Fecha de nacimiento'      => (string) ( $data['dob'] ?? '' ),
			'Stripe session'           => (string) ( $data['stripe_session_id'] ?? '' ),
			'Fecha de registro'        => (string) ( $data['created_at'] ?? wp_date( 'Y-m-d H:i:s' ) ),
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		if ( ! empty( $data['admin_url'] ) ) {
			$lines[] = '';
			$lines[] = 'Ver en el panel: ' . $data['admin_url'];
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return string[]
	 */
	private static function headers(): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from_email = sanitize_email( (string) Settings::get( 'notify_from_email', '' ) );
		$from_name  = (string) Settings::get( 'notify_from_name', 'Pura Capoeira' );
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( "\r", "\n" ), '', $from_name ), $from_email );
		}

		return $headers;
	}

	/**
	 * @param string[] $to      Recipients.
	 * @param string[] $headers Headers.
	 */
	private static function send( array $to, string $subject, string $body, array $headers ): bool {
		self::$last_error = null;
		$capture          = static function ( \WP_Error $error ): void {
			self::$last_error = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );
		$ok = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );

		if ( ! $ok ) {
			error_log( '[pura] wp_mail failed: ' . ( self::$last_error ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $ok;
	}
}
