<?php
/**
 * CSV export from the admin lists ("Exportar CSV" button): inscriptions and event registrations.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Data\Event_Registration_Post_Type;
use Pura\Core\Data\Event_Registration_Repository;
use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;

defined( 'ABSPATH' ) || exit;

final class Csv_Export {

	private const ACTION_INSCRIPTIONS = 'pura_export_inscriptions';
	private const ACTION_EVENTS       = 'pura_export_event_registrations';
	private const NONCE               = 'pura_export';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_INSCRIPTIONS, array( $this, 'handle_inscriptions' ) );
		add_action( 'admin_post_' . self::ACTION_EVENTS, array( $this, 'handle_events' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'button' ) );
	}

	public function button( string $which ): void {
		if ( 'top' !== $which || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Carry the current list filters into the export.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		if ( Inscription_Post_Type::POST_TYPE === $screen->post_type ) {
			$args = array(
				'action'      => self::ACTION_INSCRIPTIONS,
				'pura_status' => isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '',
				'pura_group'  => isset( $_GET['pura_group'] ) ? sanitize_key( wp_unslash( $_GET['pura_group'] ) ) : '',
			);
		} elseif ( Event_Registration_Post_Type::POST_TYPE === $screen->post_type ) {
			$args = array(
				'action'      => self::ACTION_EVENTS,
				'pura_status' => isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '',
				'pura_event'  => isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '',
			);
		} else {
			return;
		}
		// phpcs:enable
		$url = wp_nonce_url( add_query_arg( array_filter( $args ), admin_url( 'admin-post.php' ) ), self::NONCE );

		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Exportar CSV', 'pura' ) . '</a></div>';
	}

	public function handle_inscriptions(): void {
		$this->authorize();
		check_admin_referer( self::NONCE );

		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$group  = isset( $_GET['pura_group'] ) ? sanitize_key( wp_unslash( $_GET['pura_group'] ) ) : '';

		$meta_query = array();
		if ( '' !== $status && isset( Inscription_Post_Type::STATUSES[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => '_pura_status',
				'value' => $status,
			);
		}
		if ( in_array( $group, array( 'adult', 'kids' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_pura_group',
				'value' => $group,
			);
		}

		$columns = array( 'id', 'created_at', 'status', 'group', 'plan', 'label', 'amount', 'currency', 'promocode', 'trial_date', 'member', 'student_name', 'email', 'phone', 'emergency_phone', 'parent_name', 'parent_phone', 'address', 'dob', 'stripe_session_id', 'paid_at' );

		$this->stream(
			'inscripciones',
			$this->ids( Inscription_Post_Type::POST_TYPE, $meta_query ),
			$columns,
			static function ( int $id ): ?array {
				$row = Inscription_Repository::to_array( $id );
				if ( ! $row ) {
					return null;
				}
				$row['amount'] = number_format( $row['amount_cents'] / 100, 2, '.', '' );
				$row['member'] = $row['member'] ? '1' : '0';
				$row['status'] = Inscription_Post_Type::status_label( (string) $row['status'] );

				return $row;
			}
		);
	}

	public function handle_events(): void {
		$this->authorize();
		check_admin_referer( self::NONCE );

		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$event  = isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '';

		$meta_query = array();
		if ( '' !== $status && isset( Event_Registration_Post_Type::STATUSES[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => '_pura_status',
				'value' => $status,
			);
		}
		if ( '' !== $event ) {
			$meta_query[] = array(
				'key'   => '_pura_event',
				'value' => $event,
			);
		}

		$columns = array( 'id', 'created_at', 'status', 'event', 'event_name', 'first_name', 'last_name', 'email', 'phone', 'city', 'academy', 'teacher', 'graduation', 'days', 'shirt_size', 'emergency_name', 'emergency_phone', 'notes' );

		$this->stream(
			'' !== $event ? 'registros-' . $event : 'registros-eventos',
			$this->ids( Event_Registration_Post_Type::POST_TYPE, $meta_query ),
			$columns,
			static function ( int $id ): ?array {
				$row = Event_Registration_Repository::to_array( $id );
				if ( ! $row ) {
					return null;
				}
				$row['status'] = Event_Registration_Post_Type::status_label( (string) $row['status'] );

				return $row;
			}
		);
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
	}

	/**
	 * @param array<int, array<string, string>> $meta_query Meta query clauses.
	 * @return int[]
	 */
	private function ids( string $post_type, array $meta_query ): array {
		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin export, small volume.
		}

		return array_map( 'intval', ( new \WP_Query( $query_args ) )->posts );
	}

	/**
	 * @param int[]                              $ids     Post IDs.
	 * @param string[]                           $columns Column keys, in order.
	 * @param callable(int): (array<string, mixed>|null) $row     Row loader.
	 */
	private function stream( string $basename, array $ids, array $columns, callable $row ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $basename . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheets.
		fputcsv( $out, $columns );
		foreach ( $ids as $id ) {
			$data = $row( $id );
			if ( ! $data ) {
				continue;
			}
			fputcsv( $out, array_map( static fn ( $col ) => (string) ( $data[ $col ] ?? '' ), $columns ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
