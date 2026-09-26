<?php
/**
 * CSV export of inscriptions from the admin list ("Exportar CSV" button).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;

defined( 'ABSPATH' ) || exit;

final class Csv_Export {

	private const ACTION = 'pura_export_inscriptions';
	private const NONCE  = 'pura_export';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'button' ) );
	}

	public function button( string $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Inscription_Post_Type::POST_TYPE !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Carry the current list filters into the export.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$args = array(
			'action'      => self::ACTION,
			'pura_status' => isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '',
			'pura_group'  => isset( $_GET['pura_group'] ) ? sanitize_key( wp_unslash( $_GET['pura_group'] ) ) : '',
		);
		// phpcs:enable
		$url = wp_nonce_url( add_query_arg( array_filter( $args ), admin_url( 'admin-post.php' ) ), self::NONCE );

		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Exportar CSV', 'pura' ) . '</a></div>';
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
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

		$query_args = array(
			'post_type'      => Inscription_Post_Type::POST_TYPE,
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

		$ids     = ( new \WP_Query( $query_args ) )->posts;
		$columns = array( 'id', 'created_at', 'status', 'group', 'plan', 'label', 'amount', 'currency', 'promocode', 'trial_date', 'member', 'student_name', 'email', 'phone', 'emergency_phone', 'parent_name', 'parent_phone', 'address', 'dob', 'stripe_session_id', 'paid_at' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="inscripciones-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheets.
		fputcsv( $out, $columns );
		foreach ( $ids as $id ) {
			$row = Inscription_Repository::to_array( (int) $id );
			if ( ! $row ) {
				continue;
			}
			$row['amount'] = number_format( $row['amount_cents'] / 100, 2, '.', '' );
			$row['member'] = $row['member'] ? '1' : '0';
			$row['status'] = Inscription_Post_Type::status_label( (string) $row['status'] );
			fputcsv( $out, array_map( static fn ( $col ) => (string) ( $row[ $col ] ?? '' ), $columns ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
