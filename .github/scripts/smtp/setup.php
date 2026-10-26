<?php
/**
 * Run through `wp eval-file setup.php <sender_email> <smtp_host>` on the server, after the
 * FLUENTMAIL_SMTP_USERNAME / FLUENTMAIL_SMTP_PASSWORD constants exist in wp-config.php.
 *
 * Proves the SMTP login works, then stores a FluentSMTP connection through the plugin's
 * own model (so the record matches what its settings screen writes) and points the
 * Pura Capoeira notifications at the same sender. Nothing is written if the login fails.
 *
 * @package Pura\Ops
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

$sender = isset( $args[0] ) ? sanitize_email( (string) $args[0] ) : '';
$hosts  = array_values( array_filter( array_map( 'strval', array_slice( $args, 1 ) ) ) );
$name   = 'Pura Capoeira Cuernavaca';
$port   = 465;

if ( '' === $sender || empty( $hosts ) ) {
	WP_CLI::error( 'usage: wp eval-file setup.php <sender_email> <smtp_host> [<fallback_host>...]' );
}
if ( ! defined( 'FLUENTMAIL_SMTP_USERNAME' ) || ! defined( 'FLUENTMAIL_SMTP_PASSWORD' ) || '' === FLUENTMAIL_SMTP_PASSWORD ) {
	WP_CLI::error( 'FLUENTMAIL_SMTP_USERNAME / FLUENTMAIL_SMTP_PASSWORD are not defined in wp-config.php.' );
}
if ( ! class_exists( '\FluentMail\App\Models\Settings' ) || ! function_exists( 'fluentMail' ) ) {
	WP_CLI::error( 'FluentSMTP is not active.' );
}

// 1. Prove the mailbox credentials against the real server before storing anything.
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

// The first host whose certificate verifies and accepts the login wins: cPanel's mail
// certificate is often issued for the server's hostname rather than mail.<domain>.
$host     = '';
$failures = array();
foreach ( $hosts as $candidate ) {
	$probe = new PHPMailer\PHPMailer\PHPMailer( true );
	$probe->isSMTP();
	$probe->Host       = $candidate;
	$probe->Port       = $port;
	$probe->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
	$probe->SMTPAuth   = true;
	$probe->Username   = FLUENTMAIL_SMTP_USERNAME;
	$probe->Password   = FLUENTMAIL_SMTP_PASSWORD;
	$probe->Timeout    = 20;

	try {
		if ( $probe->smtpConnect() ) {
			$probe->smtpClose();
			$host = $candidate;
			break;
		}
		$failures[] = "{$candidate}: connect returned false";
	} catch ( \Throwable $e ) {
		$failures[] = "{$candidate}: " . $e->getMessage();
	}
}
if ( '' === $host ) {
	WP_CLI::error( "SMTP login as " . FLUENTMAIL_SMTP_USERNAME . " failed on every host:\n  " . implode( "\n  ", $failures ) );
}
foreach ( $failures as $failure ) {
	WP_CLI::log( "  skipped {$failure}" );
}
WP_CLI::log( "SMTP login OK: " . FLUENTMAIL_SMTP_USERNAME . " on {$host}:{$port} (SSL)" );

// 2. Store the connection the way FluentSMTP's settings screen does.
$connection = array(
	'provider'         => 'smtp',
	'sender_name'      => $name,
	'sender_email'     => $sender,
	'force_from_name'  => 'no',
	'force_from_email' => 'yes',
	'return_path'      => 'yes',
	'host'             => $host,
	'port'             => (string) $port,
	'auth'             => 'yes',
	'username'         => '',
	'password'         => '',
	'auto_tls'         => 'yes',
	'encryption'       => 'ssl',
	'key_store'        => 'wp_config',
);

try {
	$provider = fluentMail( \FluentMail\App\Services\Mailer\Providers\Factory::class )->make( 'smtp' );
	$provider->validateProviderInformation( $connection );
} catch ( \Throwable $e ) {
	WP_CLI::error( 'FluentSMTP rejected the connection: ' . $e->getMessage() );
}

$settings = new \FluentMail\App\Models\Settings();
$key      = $settings->generateUniqueKey( $sender );
$settings->store(
	array(
		'connection_key' => $key,
		'connection'     => $connection,
		'valid_senders'  => array(),
	)
);

// Every wp_config connection reads the same two constants, so any other one now carries
// our credentials under its own sender and host: retire them (and any older record of this
// sender stored under a different key). Connections with their own stored credentials stay.
// Done by hand on the settings array (one write) rather than through Settings::delete(),
// which in newer releases touches classes the installed version may not have.
$all = fluentMailGetSettings( array(), false );
foreach ( (array) $all['connections'] as $other_key => $other ) {
	if ( $other_key === $key ) {
		continue;
	}
	$ps = isset( $other['provider_settings'] ) ? $other['provider_settings'] : array();
	if ( ( isset( $ps['sender_email'] ) && $ps['sender_email'] === $sender ) || ( isset( $ps['key_store'] ) && 'wp_config' === $ps['key_store'] ) ) {
		unset( $all['connections'][ $other_key ] );
		foreach ( (array) $all['mappings'] as $map_email => $map_key ) {
			if ( $map_key === $other_key ) {
				unset( $all['mappings'][ $map_email ] );
			}
		}
		WP_CLI::log( 'Retired the previous connection for ' . ( isset( $ps['sender_email'] ) ? $ps['sender_email'] : $other_key ) . ' (' . ( isset( $ps['host'] ) ? $ps['host'] : '?' ) . ')' );
	}
}
$all['mappings'][ $sender ] = $key;

$misc                       = $settings->getMisc( $all );
$misc['default_connection'] = $key;
$misc['log_emails']         = 'yes';
if ( ! empty( $misc['fallback_connection'] ) && ! isset( $all['connections'][ $misc['fallback_connection'] ] ) ) {
	$misc['fallback_connection'] = '';
}
$all['misc'] = $misc;
fluentMailSetSettings( $all );

$stored = $settings->getConnection( $sender );
if ( empty( $stored['provider_settings']['host'] ) ) {
	WP_CLI::error( 'The connection did not persist.' );
}
WP_CLI::log( 'FluentSMTP connection stored and set as default: ' . $stored['provider_settings']['host'] . ':' . $stored['provider_settings']['port'] . ' (credentials in wp-config.php)' );

// 3. Send plugin notifications from the same address.
if ( class_exists( '\Pura\Core\Settings' ) ) {
	\Pura\Core\Settings::update(
		array(
			'notify_from_email' => $sender,
			'notify_from_name'  => $name,
		)
	);
	WP_CLI::log( "Pura Capoeira remitente: {$name} <{$sender}>" );
}

WP_CLI::success( 'SMTP configured.' );
