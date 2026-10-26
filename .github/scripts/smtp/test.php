<?php
/**
 * Run through `wp eval-file test.php` on the server, in a fresh process after setup.php:
 * sends one real mail through wp_mail() to the plugin's notification recipients and
 * prints the result plus FluentSMTP's log entry for it.
 *
 * @package Pura\Ops
 */

// No declare(strict_types): wp eval-file runs the file through eval(), which rejects it.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

$to = class_exists( '\Pura\Core\Mailer' ) ? \Pura\Core\Mailer::recipients() : array();
if ( empty( $to ) ) {
	$to = array( (string) get_option( 'admin_email' ) );
}

$site = (string) get_bloginfo( 'name' );
$sent = wp_mail(
	$to,
	"Correo saliente configurado — {$site}",
	"Este mensaje confirma que {$site} ya envía correo a través de FluentSMTP.\n\n"
	. 'Enviado el ' . wp_date( 'j \d\e F \d\e Y, H:i' ) . " desde el servidor.\n"
);

WP_CLI::log( 'wp_mail to ' . implode( ', ', $to ) . ': ' . ( $sent ? 'true' : 'false' ) );

global $wpdb;
$table = $wpdb->prefix . 'fsmpt_email_logs';
$row   = $wpdb->get_row( "SELECT id, status, created_at, response FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
if ( $row ) {
	$response = maybe_unserialize( (string) $row['response'] );
	if ( is_array( $response ) ) {
		$response = isset( $response['message'] ) ? $response['message'] : wp_json_encode( $response );
	}
	WP_CLI::log( sprintf( 'FluentSMTP log #%s: %s at %s — %s', $row['id'], $row['status'], $row['created_at'], substr( (string) $response, 0, 300 ) ) );
} else {
	WP_CLI::log( 'FluentSMTP log: no rows yet (table ' . $table . ')' );
}

if ( ! $sent ) {
	WP_CLI::error( 'The test mail was not sent.' );
}
