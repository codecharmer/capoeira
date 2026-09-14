<?php
/**
 * `wp pura …` commands: legacy migration, export, diagnostics, fixtures, webhook replay.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Cli;

use Pura\Core\Data\Inscription_Post_Type;
use Pura\Core\Data\Inscription_Repository;
use Pura\Core\Data\Student_Repository;
use Pura\Core\Http\Printful_Client;
use Pura\Core\Http\Stripe_Client;
use Pura\Core\Mailer;
use Pura\Core\Settings;
use Pura\Core\Webhook_Handler;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class Cli {

	public static function register(): void {
		WP_CLI::add_command( 'pura', self::class );
	}

	/**
	 * Import the legacy inscriptions.jsonl into pura_student / pura_inscription. Re-runnable.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to inscriptions.jsonl.
	 *
	 * [--dry-run]
	 * : Report what would be imported without writing.
	 *
	 * [--since=<date>]
	 * : Only import rows on or after this date (YYYY-MM-DD).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pura migrate-inscriptions /home/user/inscriptions.jsonl --dry-run
	 *
	 * @subcommand migrate-inscriptions
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function migrate_inscriptions( array $args, array $assoc_args ): void {
		$file    = $args[0];
		$dry_run = isset( $assoc_args['dry-run'] );
		$since   = (string) ( $assoc_args['since'] ?? '' );

		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "No se puede leer {$file}" );
		}

		$counts    = array(
			'imported'          => 0,
			'skipped_duplicate' => 0,
			'skipped_invalid'   => 0,
			'skipped_date'      => 0,
			'students_upserted' => 0,
		);
		$by_status = array();

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI migration of a local file.
		if ( ! $handle ) {
			WP_CLI::error( 'No se pudo abrir el archivo.' );
		}

		while ( ! feof( $handle ) ) {
			$raw = fgets( $handle );
			if ( false === $raw ) {
				break;
			}
			$line = trim( $raw );
			if ( '' === $line ) {
				continue;
			}
			$entry = json_decode( $line, true );
			if ( ! is_array( $entry ) || empty( $entry['status'] ) ) {
				++$counts['skipped_invalid'];
				continue;
			}

			$ts = (string) ( $entry['ts'] ?? '' );
			if ( '' !== $since && '' !== $ts && substr( $ts, 0, 10 ) < $since ) {
				++$counts['skipped_date'];
				continue;
			}

			$hash = md5( $line );
			if ( Inscription_Repository::legacy_hash_exists( $hash ) ) {
				++$counts['skipped_duplicate'];
				continue;
			}

			$student = isset( $entry['student'] ) && is_array( $entry['student'] ) ? $entry['student'] : array();
			$email   = Student_Repository::normalize_email( (string) ( $student['email'] ?? $entry['email'] ?? '' ) );
			if ( '' === $email ) {
				++$counts['skipped_invalid'];
				continue;
			}

			$profile = array(
				'email' => $email,
				'group' => (string) ( $entry['group'] ?? 'adult' ),
			);
			if ( $student ) {
				foreach ( array( 'first_name', 'last_name', 'parent_name', 'address', 'phone', 'parent_phone', 'emergency_phone', 'dob' ) as $field ) {
					$profile[ $field ] = (string) ( $student[ $field ] ?? '' );
				}
			} elseif ( ! empty( $entry['student_name'] ) ) {
				$parts                 = explode( ' ', trim( (string) $entry['student_name'] ), 2 );
				$profile['first_name'] = $parts[0];
				$profile['last_name']  = $parts[1] ?? '';
			}

			$status               = (string) $entry['status'];
			$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;

			if ( $dry_run ) {
				++$counts['imported'];
				continue;
			}

			$student_id = Student_Repository::upsert( $profile );
			if ( ! $student_id ) {
				++$counts['skipped_invalid'];
				continue;
			}
			++$counts['students_upserted'];

			$created = '' !== $ts ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $ts ) ?: time() ) ) : current_time( 'mysql' );

			$post_id = Inscription_Repository::create(
				array(
					'status'            => $status,
					'plan'              => (string) ( $entry['plan'] ?? '' ),
					'label'             => (string) ( $entry['label'] ?? '' ),
					'amount_cents'      => (int) round( (float) ( $entry['amount'] ?? 0 ) * 100 ),
					'currency'          => (string) ( $entry['currency'] ?? 'MXN' ),
					'group'             => (string) ( $entry['group'] ?? 'adult' ),
					'promocode'         => (string) ( $entry['promocode'] ?? '' ),
					'promo_type'        => (string) ( $entry['promo_type'] ?? 'none' ),
					'trial_date'        => (string) ( $entry['trial_date'] ?? '' ),
					'member'            => ! empty( $entry['member'] ),
					'student_id'        => $student_id,
					'email'             => $email,
					'stripe_session_id' => (string) ( $entry['session_id'] ?? '' ),
					'paid_at'           => 'paid' === $status ? $created : '',
					'legacy_hash'       => $hash,
					'created_at'        => $created,
				)
			);

			if ( $post_id ) {
				++$counts['imported'];
				if ( 'paid' === $status ) {
					Student_Repository::touch_paid( $student_id, $created );
				}
			} else {
				++$counts['skipped_invalid'];
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		foreach ( $counts as $key => $value ) {
			WP_CLI::log( sprintf( '%-20s %d', $key, $value ) );
		}
		foreach ( $by_status as $status => $value ) {
			WP_CLI::log( sprintf( '  %-18s %d', $status, $value ) );
		}
		WP_CLI::success( $dry_run ? 'Dry run complete (nothing written).' : 'Migration complete.' );
	}

	/**
	 * Export inscriptions as CSV to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status (free, pending_payment, pending_payment_offline, paid, expired).
	 *
	 * [--from=<date>]
	 * : Only inscriptions on or after YYYY-MM-DD.
	 *
	 * [--to=<date>]
	 * : Only inscriptions on or before YYYY-MM-DD.
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function export( array $args, array $assoc_args ): void {
		$query_args = array(
			'post_type'      => Inscription_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( ! empty( $assoc_args['status'] ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_pura_status',
					'value' => sanitize_key( $assoc_args['status'] ),
				),
			); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI export.
		}
		$date_query = array();
		if ( ! empty( $assoc_args['from'] ) ) {
			$date_query['after'] = $assoc_args['from'];
		}
		if ( ! empty( $assoc_args['to'] ) ) {
			$date_query['before'] = $assoc_args['to'] . ' 23:59:59';
		}
		if ( $date_query ) {
			$date_query['inclusive']  = true;
			$query_args['date_query'] = array( $date_query );
		}

		$ids     = ( new \WP_Query( $query_args ) )->posts;
		$columns = array( 'id', 'created_at', 'status', 'group', 'plan', 'label', 'amount', 'currency', 'promocode', 'trial_date', 'member', 'student_name', 'email', 'phone', 'emergency_phone', 'parent_name', 'parent_phone', 'address', 'dob', 'stripe_session_id', 'paid_at' );

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
			fputcsv( $out, array_map( static fn ( $col ) => (string) ( $row[ $col ] ?? '' ), $columns ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Check configuration: secrets, pages, mail recipients, Printful mode, permalinks.
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function doctor( array $args, array $assoc_args ): void {
		$checks = array();

		foreach ( Settings::SECRET_KEYS as $key ) {
			$source         = Settings::secret_source( $key );
			$checks[ $key ] = 'missing' === $source ? array( false, 'no configurado' ) : array( true, $source );
		}

		$checks['store_page']        = array( (bool) get_page_by_path( 'tienda' ) || (int) Settings::get( 'store_page_id' ) > 0, Settings::page_url( 'store' ) );
		$checks['inscriptions_page'] = array( (bool) get_page_by_path( 'inscripciones' ) || (int) Settings::get( 'inscriptions_page_id' ) > 0, Settings::page_url( 'inscriptions' ) );
		$checks['notify_emails']     = array( count( Mailer::recipients() ) > 0, implode( ', ', Mailer::recipients() ) ?: 'ninguno' );
		$checks['permalinks']        = array( '' !== (string) get_option( 'permalink_structure' ), (string) get_option( 'permalink_structure' ) ?: 'plain' );
		$checks['printful_mode']     = array( true, ( defined( 'PURA_PRINTFUL_MOCK' ) && PURA_PRINTFUL_MOCK ) ? 'mock' : 'real' );
		$checks['printful_confirm']  = array( ! ( defined( 'PURA_PRINTFUL_CONFIRM_DISABLED' ) && PURA_PRINTFUL_CONFIRM_DISABLED ), ( defined( 'PURA_PRINTFUL_CONFIRM_DISABLED' ) && PURA_PRINTFUL_CONFIRM_DISABLED ) ? 'DESACTIVADO' : 'activo' );
		$checks['webhook_url']       = array( true, rest_url( 'pura/v1/stripe/webhook' ) );
		$checks['smtp_plugin']       = array( function_exists( 'is_plugin_active' ) ? is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) : false, 'fluent-smtp' );

		$failures = 0;
		foreach ( $checks as $name => [ $ok, $detail ] ) {
			WP_CLI::log( sprintf( '%s %-20s %s', $ok ? WP_CLI::colorize( '%G✔%n' ) : WP_CLI::colorize( '%R✘%n' ), $name, $detail ) );
			if ( ! $ok ) {
				++$failures;
			}
		}

		if ( $failures ) {
			WP_CLI::warning( "{$failures} comprobación(es) requieren atención." );
		} else {
			WP_CLI::success( 'Todo en orden.' );
		}
	}

	/**
	 * Capture Printful fixtures from the real store for the mock client.
	 *
	 * @subcommand printful-fixtures
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function printful_fixtures( array $args, array $assoc_args ): void {
		$client = new \Pura\Core\Http\Printful_Client();
		if ( ! $client->is_configured() ) {
			WP_CLI::error( 'Configura PURA_PRINTFUL_API_KEY primero.' );
		}

		$dir = PURA_CORE_DIR . 'tests/fixtures/printful';
		wp_mkdir_p( $dir );

		$products = $client->get_products();
		if ( is_wp_error( $products ) ) {
			WP_CLI::error( $products->get_error_message() );
		}
		file_put_contents( $dir . '/products.json', wp_json_encode( $products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		WP_CLI::log( 'products.json (' . count( (array) $products ) . ')' );

		foreach ( (array) $products as $product ) {
			$id = (int) ( $product['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$detail = $client->get_product( $id );
			if ( is_wp_error( $detail ) ) {
				WP_CLI::warning( "product {$id}: " . $detail->get_error_message() );
				continue;
			}
			file_put_contents( $dir . "/product-{$id}.json", wp_json_encode( $detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::log( "product-{$id}.json" );
		}

		WP_CLI::success( 'Fixtures guardados en ' . $dir );
	}

	/**
	 * Fetch a Stripe event by id and run it through the webhook handler (for replays/ops).
	 *
	 * ## OPTIONS
	 *
	 * <event_id>
	 * : Stripe event id (evt_…).
	 *
	 * @subcommand stripe-replay
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function stripe_replay( array $args, array $assoc_args ): void {
		$stripe = new Stripe_Client();
		$event  = $stripe->get_event( $args[0] );
		if ( is_wp_error( $event ) ) {
			WP_CLI::error( $event->get_error_message() );
		}

		try {
			$result = ( new Webhook_Handler() )->handle( $event );
		} catch ( \RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( 'Resultado: ' . $result );
	}
}
