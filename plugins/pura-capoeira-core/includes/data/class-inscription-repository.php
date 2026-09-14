<?php
/**
 * Create and read `pura_inscription` posts.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Inscription_Repository {

	/**
	 * @param array<string, mixed> $data Keys: status, plan, label, amount_cents, currency, group,
	 *                                   promocode, promo_type, trial_date, member, student_id,
	 *                                   email, stripe_session_id, legacy_hash, created_at (mysql).
	 * @return int Post ID, 0 on failure.
	 */
	public static function create( array $data ): int {
		$student_id = (int) ( $data['student_id'] ?? 0 );
		$name       = $student_id ? Student_Repository::full_name( $student_id ) : (string) ( $data['email'] ?? '' );
		$label      = (string) ( $data['label'] ?? '' );
		$created    = (string) ( $data['created_at'] ?? current_time( 'mysql' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'     => Inscription_Post_Type::POST_TYPE,
				'post_status'   => 'private',
				'post_title'    => trim( $name . ' — ' . $label . ' — ' . substr( $created, 0, 10 ) ),
				'post_date'     => $created,
				'post_date_gmt' => get_gmt_from_date( $created ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		$meta = array(
			'_pura_status'                => (string) ( $data['status'] ?? 'pending_payment' ),
			'_pura_plan'                  => (string) ( $data['plan'] ?? '' ),
			'_pura_label'                 => $label,
			'_pura_amount_cents'          => (int) ( $data['amount_cents'] ?? 0 ),
			'_pura_currency'              => (string) ( $data['currency'] ?? 'MXN' ),
			'_pura_group'                 => (string) ( $data['group'] ?? 'adult' ),
			'_pura_promocode'             => (string) ( $data['promocode'] ?? '' ),
			'_pura_promo_type'            => (string) ( $data['promo_type'] ?? 'none' ),
			'_pura_trial_date'            => (string) ( $data['trial_date'] ?? '' ),
			'_pura_member'                => ! empty( $data['member'] ) ? 1 : 0,
			'_pura_student_id'            => $student_id,
			'_pura_email'                 => Student_Repository::normalize_email( (string) ( $data['email'] ?? '' ) ),
			'_pura_stripe_session_id'     => (string) ( $data['stripe_session_id'] ?? '' ),
			'_pura_stripe_payment_intent' => (string) ( $data['stripe_payment_intent'] ?? '' ),
			'_pura_paid_at'               => (string) ( $data['paid_at'] ?? '' ),
			'_pura_legacy_hash'           => (string) ( $data['legacy_hash'] ?? '' ),
		);

		foreach ( $meta as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}

		if ( $student_id ) {
			Student_Repository::increment_inscriptions( $student_id );
		}

		return $post_id;
	}

	public static function set_session( int $post_id, string $session_id ): void {
		update_post_meta( $post_id, '_pura_stripe_session_id', $session_id );
	}

	public static function set_status( int $post_id, string $status ): void {
		update_post_meta( $post_id, '_pura_status', $status );
	}

	/**
	 * @param array<string, mixed> $session Stripe Checkout Session object (decoded).
	 */
	public static function set_paid( int $post_id, array $session ): void {
		update_post_meta( $post_id, '_pura_status', 'paid' );
		update_post_meta( $post_id, '_pura_paid_at', current_time( 'mysql' ) );

		if ( isset( $session['amount_total'] ) ) {
			update_post_meta( $post_id, '_pura_amount_cents', (int) $session['amount_total'] );
		}
		if ( ! empty( $session['payment_intent'] ) && is_string( $session['payment_intent'] ) ) {
			update_post_meta( $post_id, '_pura_stripe_payment_intent', $session['payment_intent'] );
		}
		if ( ! empty( $session['id'] ) && is_string( $session['id'] ) ) {
			update_post_meta( $post_id, '_pura_stripe_session_id', $session['id'] );
		}

		$student_id = (int) get_post_meta( $post_id, '_pura_student_id', true );
		if ( $student_id ) {
			Student_Repository::touch_paid( $student_id );
		}
	}

	public static function delete( int $post_id ): void {
		$student_id = (int) get_post_meta( $post_id, '_pura_student_id', true );
		wp_delete_post( $post_id, true );
		if ( $student_id ) {
			$count = (int) get_post_meta( $student_id, '_pura_inscription_count', true );
			update_post_meta( $student_id, '_pura_inscription_count', max( 0, $count - 1 ) );
		}
	}

	public static function find_by_session( string $session_id ): int {
		if ( '' === $session_id ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Inscription_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_key'               => '_pura_stripe_session_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single indexed lookup from the webhook.
				'meta_value'             => $session_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $query->posts ? (int) $query->posts[0] : 0;
	}

	public static function legacy_hash_exists( string $hash ): bool {
		if ( '' === $hash ) {
			return false;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Inscription_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_key'               => '_pura_legacy_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off migration.
				'meta_value'             => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! empty( $query->posts );
	}

	/**
	 * Flat array for mail and export. Includes the student's profile when linked.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function to_array( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || Inscription_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$student_id = (int) get_post_meta( $post_id, '_pura_student_id', true );
		$student    = $student_id ? Student_Repository::to_array( $student_id ) : array();

		return array(
			'id'                    => $post_id,
			'status'                => (string) get_post_meta( $post_id, '_pura_status', true ),
			'plan'                  => (string) get_post_meta( $post_id, '_pura_plan', true ),
			'label'                 => (string) get_post_meta( $post_id, '_pura_label', true ),
			'amount_cents'          => (int) get_post_meta( $post_id, '_pura_amount_cents', true ),
			'currency'              => (string) ( get_post_meta( $post_id, '_pura_currency', true ) ?: 'MXN' ),
			'group'                 => (string) get_post_meta( $post_id, '_pura_group', true ),
			'promocode'             => (string) get_post_meta( $post_id, '_pura_promocode', true ),
			'promo_type'            => (string) get_post_meta( $post_id, '_pura_promo_type', true ),
			'trial_date'            => (string) get_post_meta( $post_id, '_pura_trial_date', true ),
			'member'                => (bool) get_post_meta( $post_id, '_pura_member', true ),
			'student_id'            => $student_id,
			'student_name'          => $student_id ? Student_Repository::full_name( $student_id ) : '',
			'email'                 => (string) get_post_meta( $post_id, '_pura_email', true ),
			'phone'                 => (string) ( $student['phone'] ?? '' ),
			'parent_name'           => (string) ( $student['parent_name'] ?? '' ),
			'parent_phone'          => (string) ( $student['parent_phone'] ?? '' ),
			'emergency_phone'       => (string) ( $student['emergency_phone'] ?? '' ),
			'address'               => (string) ( $student['address'] ?? '' ),
			'dob'                   => (string) ( $student['dob'] ?? '' ),
			'stripe_session_id'     => (string) get_post_meta( $post_id, '_pura_stripe_session_id', true ),
			'stripe_payment_intent' => (string) get_post_meta( $post_id, '_pura_stripe_payment_intent', true ),
			'paid_at'               => (string) get_post_meta( $post_id, '_pura_paid_at', true ),
			'created_at'            => $post->post_date,
			'admin_url'             => (string) get_edit_post_link( $post_id, 'raw' ),
		);
	}
}
