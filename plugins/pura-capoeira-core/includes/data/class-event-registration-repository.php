<?php
/**
 * Create and read `pura_event_reg` posts.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Event_Registration_Repository {

	/** @var string[] Fields copied verbatim into `_pura_*` meta. */
	private const TEXT_FIELDS = array(
		'event',
		'event_name',
		'first_name',
		'last_name',
		'phone',
		'city',
		'academy',
		'teacher',
		'graduation',
		'shirt_size',
		'emergency_name',
		'emergency_phone',
		'notes',
	);

	/**
	 * @param array<string, mixed> $data Keys: event, event_name, first_name, last_name, email,
	 *                                   phone, city, academy, teacher, graduation, days (string[]),
	 *                                   shirt_size, emergency_name, emergency_phone, notes.
	 * @return int Post ID, 0 on failure.
	 */
	public static function create( array $data ): int {
		$name    = trim( (string) ( $data['first_name'] ?? '' ) . ' ' . (string) ( $data['last_name'] ?? '' ) );
		$event   = (string) ( $data['event_name'] ?? $data['event'] ?? '' );
		$created = current_time( 'mysql' );

		$post_id = wp_insert_post(
			array(
				'post_type'     => Event_Registration_Post_Type::POST_TYPE,
				'post_status'   => 'private',
				'post_title'    => trim( $name . ' — ' . $event . ' — ' . substr( $created, 0, 10 ) ),
				'post_date'     => $created,
				'post_date_gmt' => get_gmt_from_date( $created ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_pura_status', 'registered' );
		update_post_meta( $post_id, '_pura_email', Student_Repository::normalize_email( (string) ( $data['email'] ?? '' ) ) );
		update_post_meta( $post_id, '_pura_days', implode( ', ', (array) ( $data['days'] ?? array() ) ) );

		foreach ( self::TEXT_FIELDS as $field ) {
			$value = trim( (string) ( $data[ $field ] ?? '' ) );
			if ( '' !== $value ) {
				update_post_meta( $post_id, '_pura_' . $field, $value );
			}
		}

		return $post_id;
	}

	/**
	 * Whether this email already holds a non-cancelled registration for the event.
	 */
	public static function exists( string $email, string $event ): bool {
		$email = Student_Repository::normalize_email( $email );
		if ( '' === $email || '' === $event ) {
			return false;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Event_Registration_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- duplicate check on submit, small volume.
					array(
						'key'   => '_pura_email',
						'value' => $email,
					),
					array(
						'key'   => '_pura_event',
						'value' => $event,
					),
					array(
						'key'     => '_pura_status',
						'value'   => 'cancelled',
						'compare' => '!=',
					),
				),
			)
		);

		return ! empty( $query->posts );
	}

	/**
	 * Distinct events that have registrations: slug => display name.
	 *
	 * @return array<string, string>
	 */
	public static function events(): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin filter, small volume.
			$wpdb->prepare(
				"SELECT e.meta_value AS slug, MAX(n.meta_value) AS name
				 FROM {$wpdb->postmeta} e
				 LEFT JOIN {$wpdb->postmeta} n ON n.post_id = e.post_id AND n.meta_key = %s
				 WHERE e.meta_key = %s
				 GROUP BY e.meta_value
				 ORDER BY MAX(e.post_id) DESC",
				'_pura_event_name',
				'_pura_event'
			),
			ARRAY_A
		);

		$events = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$slug            = (string) $row['slug'];
			$events[ $slug ] = '' !== (string) $row['name'] ? (string) $row['name'] : $slug;
		}

		return $events;
	}

	/**
	 * Keep only the submitted days that the form offered, in the form's order, without repeats.
	 *
	 * @param mixed    $submitted Raw request value.
	 * @param string[] $allowed   Days the form offered.
	 * @return string[]
	 */
	public static function normalize_days( $submitted, array $allowed ): array {
		$submitted = is_array( $submitted ) ? $submitted : array( $submitted );
		$submitted = array_map( static fn ( $day ) => trim( (string) $day ), $submitted );

		return array_values( array_intersect( $allowed, $submitted ) );
	}

	/**
	 * Flat array for mail, the admin details box and CSV export.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function to_array( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || Event_Registration_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$meta = static fn ( string $key ): string => (string) get_post_meta( $post_id, '_pura_' . $key, true );

		return array(
			'id'              => $post_id,
			'status'          => $meta( 'status' ),
			'event'           => $meta( 'event' ),
			'event_name'      => $meta( 'event_name' ),
			'first_name'      => $meta( 'first_name' ),
			'last_name'       => $meta( 'last_name' ),
			'name'            => trim( $meta( 'first_name' ) . ' ' . $meta( 'last_name' ) ),
			'email'           => $meta( 'email' ),
			'phone'           => $meta( 'phone' ),
			'city'            => $meta( 'city' ),
			'academy'         => $meta( 'academy' ),
			'teacher'         => $meta( 'teacher' ),
			'graduation'      => $meta( 'graduation' ),
			'days'            => $meta( 'days' ),
			'shirt_size'      => $meta( 'shirt_size' ),
			'emergency_name'  => $meta( 'emergency_name' ),
			'emergency_phone' => $meta( 'emergency_phone' ),
			'notes'           => $meta( 'notes' ),
			'created_at'      => $post->post_date,
			'admin_url'       => (string) get_edit_post_link( $post_id, 'raw' ),
		);
	}
}
