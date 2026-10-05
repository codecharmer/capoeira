<?php
/**
 * Activation and deactivation.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public const DB_VERSION = '1';

	public static function activate(): void {
		Data\Stripe_Events_Table::install();

		add_option( 'pura_settings', Settings::defaults(), '', true );
		add_option( 'pura_secrets', array(), '', false );
		update_option( 'pura_db_version', self::DB_VERSION, false );

		( new Data\Gallery_Post_Type() )->register();
		( new Data\Inscription_Post_Type() )->register();
		( new Data\Student_Post_Type() )->register();
		( new Data\Event_Registration_Post_Type() )->register();

		self::grant_capabilities();

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Administrators manage inscriptions, students and event registrations; nobody else.
	 */
	public static function grant_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( array( 'pura_inscription', 'pura_student', Data\Event_Registration_Post_Type::POST_TYPE ) as $type ) {
			foreach ( Data\Capabilities::for_type( $type ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}
