<?php
/**
 * Idempotency ledger for Stripe webhook events. One row per event id; a single atomic
 * INSERT … ON DUPLICATE KEY UPDATE claims an event for processing.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Stripe_Events_Table {

	public const CLAIM_NEW       = 'new';
	public const CLAIM_RECLAIMED = 'reclaimed';
	public const CLAIM_DUPLICATE = 'duplicate';

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'pura_stripe_events';
	}

	public static function install(): void {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			event_id varchar(191) NOT NULL,
			type varchar(64) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'processing',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (event_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Claim an event. Returns CLAIM_NEW for a first sighting, CLAIM_RECLAIMED when a previous
	 * attempt failed, CLAIM_DUPLICATE when it is already processing or done.
	 */
	public static function claim( string $event_id, string $type ): string {
		global $wpdb;

		$table = self::table();
		$now   = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, atomic claim; table name is internal.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (event_id, type, status, created_at, updated_at)
				 VALUES (%s, %s, 'processing', %s, %s)
				 ON DUPLICATE KEY UPDATE
					status = IF(status = 'failed', 'processing', status),
					updated_at = IF(status = 'processing' AND updated_at <> VALUES(updated_at) AND type = VALUES(type), VALUES(updated_at), updated_at)",
				$event_id,
				$type,
				$now,
				$now
			)
		);
		// phpcs:enable

		if ( false === $result ) {
			return self::CLAIM_DUPLICATE;
		}

		// MySQL reports 1 for an insert, 2 for an update that changed the row, 0 for no change.
		if ( 1 === $wpdb->rows_affected ) {
			return self::CLAIM_NEW;
		}
		if ( 2 === $wpdb->rows_affected ) {
			return self::CLAIM_RECLAIMED;
		}

		return self::CLAIM_DUPLICATE;
	}

	public static function mark_done( string $event_id ): void {
		self::set_status( $event_id, 'done' );
	}

	public static function mark_failed( string $event_id ): void {
		self::set_status( $event_id, 'failed' );
	}

	public static function status( string $event_id ): ?string {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE event_id = %s", $event_id ) );

		return null === $status ? null : (string) $status;
	}

	private static function set_status( string $event_id, string $status ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'event_id' => $event_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}
}
