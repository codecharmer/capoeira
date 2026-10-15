<?php
/**
 * Student accounts: WordPress users with the `pura_student` role, linked to a `pura_student`
 * record and to one or more provider identities.
 *
 * User meta:  _pura_student_id, _pura_identity_{provider} (= sub), _pura_apple_private_email.
 * Student meta: _pura_user_id.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Student_Post_Type;
use Pura\Core\Data\Student_Repository;
use Pura\Core\Settings;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class Student_Accounts {

	public const ROLE = 'pura_student';

	public const META_STUDENT       = '_pura_student_id';
	public const META_USER          = '_pura_user_id';
	public const META_PRIVATE_EMAIL = '_pura_apple_private_email';

	/**
	 * Role with the bare `read` capability. Idempotent; called on activation and on init.
	 */
	public static function register_role(): void {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Alumno', 'pura' ), array( 'read' => true ) );
		}
	}

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_role' ) );
		add_action( 'admin_init', array( self::class, 'block_wp_admin' ) );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'logout_redirect', array( self::class, 'logout_redirect' ), 10, 3 );
	}

	/**
	 * Students have nothing to do in wp-admin: send them to their portal.
	 */
	public static function block_wp_admin(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( self::is_student_user( wp_get_current_user() ) ) {
			wp_safe_redirect( Settings::page_url( 'account' ) );
			exit;
		}
	}

	public static function hide_admin_bar( bool $show ): bool {
		return is_user_logged_in() && self::is_student_user( wp_get_current_user() ) ? false : $show;
	}

	/**
	 * @param string              $redirect_to Requested redirect.
	 * @param string              $requested   Raw requested redirect.
	 * @param WP_User|WP_Error    $user        User.
	 */
	public static function login_redirect( string $redirect_to, string $requested, $user ): string {
		return $user instanceof WP_User && self::is_student_user( $user ) ? Settings::page_url( 'account' ) : $redirect_to;
	}

	/**
	 * @param string              $redirect_to Requested redirect.
	 * @param string              $requested   Raw requested redirect.
	 * @param WP_User|WP_Error    $user        User.
	 */
	public static function logout_redirect( string $redirect_to, string $requested, $user ): string {
		return $user instanceof WP_User && self::is_student_user( $user ) ? home_url( '/' ) : $redirect_to;
	}

	public static function is_student_user( WP_User $user ): bool {
		return in_array( self::ROLE, (array) $user->roles, true );
	}

	public static function identity_meta_key( string $provider ): string {
		return '_pura_identity_' . sanitize_key( $provider );
	}

	public static function find_user_by_identity( string $provider, string $sub ): ?WP_User {
		if ( '' === $sub ) {
			return null;
		}
		$users = get_users(
			array(
				'meta_key'    => self::identity_meta_key( $provider ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single indexed lookup at login.
				'meta_value'  => $sub, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'      => 1,
				'count_total' => false,
			)
		);

		return $users ? $users[0] : null;
	}

	/**
	 * Resolve the signed-in identity to a WordPress user, creating one when needed, and link the
	 * student record by verified email.
	 *
	 * @param array{sub:string,email:string,email_verified:bool,first_name:string,last_name:string,display_name:string,is_private_email:bool} $identity Identity.
	 * @return WP_User|WP_Error
	 */
	public static function find_or_create_user( array $identity, string $provider ) {
		$email    = $identity['email_verified'] && is_email( $identity['email'] ) ? $identity['email'] : '';
		$user     = self::find_user_by_identity( $provider, $identity['sub'] );
		$existing = null;

		if ( ! $user && '' !== $email ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing instanceof WP_User ) {
				// Only ever attach a provider identity to a student account: an email asserted by a
				// provider must never open a staff account.
				if ( array( self::ROLE ) !== array_values( (array) $existing->roles ) ) {
					return new WP_Error( 'admin_account', 'Esta cuenta pertenece al equipo de Pura Capoeira; entra por el acceso de administración.' );
				}
				$user = $existing;
			}
		}

		if ( ! $user ) {
			$user = self::create_user( $identity, $email );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		update_user_meta( $user->ID, self::identity_meta_key( $provider ), $identity['sub'] );
		if ( $identity['is_private_email'] ) {
			update_user_meta( $user->ID, self::META_PRIVATE_EMAIL, 1 );
		}

		// Names: fill in, never blank (Apple only sends them on the first authorization).
		$updates = array();
		if ( '' !== $identity['first_name'] && '' === (string) get_user_meta( $user->ID, 'first_name', true ) ) {
			$updates['first_name'] = $identity['first_name'];
		}
		if ( '' !== $identity['last_name'] && '' === (string) get_user_meta( $user->ID, 'last_name', true ) ) {
			$updates['last_name'] = $identity['last_name'];
		}
		if ( '' !== $identity['display_name'] && $user->display_name === $user->user_login ) {
			$updates['display_name'] = $identity['display_name'];
		}
		if ( '' === $user->user_email && '' !== $email && ! email_exists( $email ) ) {
			$updates['user_email'] = $email;
		}
		if ( $updates ) {
			wp_update_user( array_merge( array( 'ID' => $user->ID ), $updates ) );
			$user = get_user_by( 'id', $user->ID ) ?: $user;
		}

		if ( ! self::student_id_for_user( $user->ID ) ) {
			self::auto_link_by_email( $user->ID, $email ?: $user->user_email );
		}

		return $user;
	}

	/**
	 * Link by the student record's email when that record is not already someone else's.
	 */
	public static function auto_link_by_email( int $user_id, string $email ): int {
		if ( ! is_email( $email ) ) {
			return 0;
		}
		$student_id = Student_Repository::find_id_by_email( $email );
		if ( ! $student_id ) {
			return 0;
		}
		$owner = self::user_id_for_student( $student_id );
		if ( $owner && $owner !== $user_id ) {
			return 0;
		}

		self::link_student( $user_id, $student_id );

		return $student_id;
	}

	public static function link_student( int $user_id, int $student_id ): void {
		$previous = self::student_id_for_user( $user_id );
		if ( $previous && $previous !== $student_id ) {
			delete_post_meta( $previous, self::META_USER );
		}
		update_user_meta( $user_id, self::META_STUDENT, $student_id );
		update_post_meta( $student_id, self::META_USER, $user_id );
	}

	public static function unlink_student( int $user_id ): void {
		$student_id = (int) get_user_meta( $user_id, self::META_STUDENT, true );
		delete_user_meta( $user_id, self::META_STUDENT );
		if ( $student_id ) {
			delete_post_meta( $student_id, self::META_USER );
		}
	}

	/**
	 * Linked student post ID, or 0 when none or the record no longer exists.
	 */
	public static function student_id_for_user( int $user_id ): int {
		$student_id = (int) get_user_meta( $user_id, self::META_STUDENT, true );
		if ( ! $student_id || Student_Post_Type::POST_TYPE !== get_post_type( $student_id ) ) {
			return 0;
		}

		return $student_id;
	}

	public static function user_id_for_student( int $student_id ): int {
		$user_id = (int) get_post_meta( $student_id, self::META_USER, true );

		return $user_id && get_user_by( 'id', $user_id ) ? $user_id : 0;
	}

	/**
	 * Set the auth cookie for the browser and announce the login like wp_signon() would.
	 */
	public static function login( WP_User $user ): void {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core login hook, fired on purpose like wp_signon().
	}

	/**
	 * Inscription IDs for a student, newest first.
	 *
	 * @return int[]
	 */
	public static function inscription_ids( int $student_id, int $limit = 50 ): array {
		if ( ! $student_id ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'              => Inscription_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => '_pura_student_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- per-student history, small volume.
				'meta_value'             => $student_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * A student with at least one paid or beca inscription is a current student (alumno actual).
	 */
	public static function has_paid_history( int $student_id ): bool {
		foreach ( self::inscription_ids( $student_id ) as $id ) {
			if ( in_array( (string) get_post_meta( $id, '_pura_status', true ), array( 'paid', 'free' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array{sub:string,email:string,first_name:string,last_name:string,display_name:string} $identity Identity.
	 * @return WP_User|WP_Error
	 */
	private static function create_user( array $identity, string $email ) {
		self::register_role();

		$login = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$candidate = 'alumno-' . bin2hex( random_bytes( 5 ) );
			if ( ! username_exists( $candidate ) ) {
				$login = $candidate;
				break;
			}
		}
		if ( '' === $login ) {
			return new WP_Error( 'user_create', 'No se pudo crear la cuenta. Intenta de nuevo.' );
		}

		$display = '' !== $identity['display_name'] ? $identity['display_name'] : ( '' !== $email ? strstr( $email, '@', true ) : $login );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'display_name' => (string) $display,
				'first_name'   => $identity['first_name'],
				'last_name'    => $identity['last_name'],
				'role'         => self::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'user_create', 'No se pudo crear la cuenta: ' . $user_id->get_error_message() );
		}

		$user = get_user_by( 'id', (int) $user_id );

		return $user instanceof WP_User ? $user : new WP_Error( 'user_create', 'No se pudo crear la cuenta.' );
	}
}
