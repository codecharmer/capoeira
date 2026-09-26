<?php
/**
 * Students keyed by lowercase email. Cached lookups; merge-only upserts so a payment that only
 * carries an email never blanks phone, address, or date of birth.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Student_Repository {

	private const CACHE_GROUP = 'pura_students';

	/** @var string[] Profile keys accepted by upsert(), in request-field naming. */
	private const PROFILE_FIELDS = array(
		'first_name',
		'last_name',
		'parent_name',
		'phone',
		'parent_phone',
		'emergency_phone',
		'address',
		'dob',
		'group',
	);

	public static function normalize_email( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * Post ID for an email, or 0.
	 */
	public static function find_id_by_email( string $email ): int {
		$email = self::normalize_email( $email );
		if ( '' === $email ) {
			return 0;
		}

		$key   = md5( $email );
		$found = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $found ) {
			return (int) $found;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Student_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_key'               => '_pura_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed meta_key, single row, cached.
				'meta_value'             => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$id = $query->posts ? (int) $query->posts[0] : 0;
		wp_cache_set( $key, $id, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $id;
	}

	/**
	 * Profile array (request-field naming) or null.
	 *
	 * @return array<string, string>|null
	 */
	public static function find_by_email( string $email ): ?array {
		$id = self::find_id_by_email( $email );

		return $id ? self::to_array( $id ) : null;
	}

	/**
	 * @return array<string, string>
	 */
	public static function to_array( int $id ): array {
		$out = array(
			'id'    => (string) $id,
			'email' => (string) get_post_meta( $id, '_pura_email', true ),
		);
		foreach ( self::PROFILE_FIELDS as $field ) {
			$out[ $field ] = (string) get_post_meta( $id, '_pura_' . $field, true );
		}

		return $out;
	}

	/**
	 * Create or update by email. Only non-empty incoming fields overwrite stored ones.
	 *
	 * @param array<string, mixed> $profile Keys: email + PROFILE_FIELDS.
	 * @return int Post ID (0 on failure).
	 */
	public static function upsert( array $profile ): int {
		$email = self::normalize_email( (string) ( $profile['email'] ?? '' ) );
		if ( '' === $email ) {
			return 0;
		}

		$id = self::find_id_by_email( $email );

		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'   => Student_Post_Type::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => self::title_from( $profile, $email ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				return 0;
			}
			update_post_meta( $id, '_pura_email', $email );
			update_post_meta( $id, '_pura_inscription_count', 0 );
		}

		foreach ( self::PROFILE_FIELDS as $field ) {
			$value = isset( $profile[ $field ] ) ? trim( (string) $profile[ $field ] ) : '';
			if ( '' === $value ) {
				continue;
			}
			update_post_meta( $id, '_pura_' . $field, $value );
		}

		$title = self::title_from( self::to_array( $id ), $email );
		if ( get_the_title( $id ) !== $title ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
		}

		self::flush_cache( $email );
		wp_cache_set( md5( $email ), $id, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $id;
	}

	public static function touch_paid( int $id, ?string $when = null ): void {
		update_post_meta( $id, '_pura_last_paid_at', $when ?: current_time( 'mysql' ) );
	}

	public static function increment_inscriptions( int $id ): void {
		$count = (int) get_post_meta( $id, '_pura_inscription_count', true );
		update_post_meta( $id, '_pura_inscription_count', $count + 1 );
	}

	public static function full_name( int $id ): string {
		$first = trim( (string) get_post_meta( $id, '_pura_first_name', true ) );
		$last  = trim( (string) get_post_meta( $id, '_pura_last_name', true ) );
		$name  = trim( $first . ' ' . $last );

		return '' !== $name ? $name : get_the_title( $id );
	}

	public static function flush_cache( string $email ): void {
		$email = self::normalize_email( $email );
		if ( '' !== $email ) {
			wp_cache_delete( md5( $email ), self::CACHE_GROUP );
		}
	}

	/**
	 * @param array<string, mixed> $profile Profile.
	 */
	private static function title_from( array $profile, string $email ): string {
		$name = trim( (string) ( $profile['first_name'] ?? '' ) . ' ' . (string) ( $profile['last_name'] ?? '' ) );

		return '' !== $name ? $name : $email;
	}
}
