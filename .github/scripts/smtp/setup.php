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
$host   = isset( $args[1] ) ? (string) $args[1] : '';
$name   = 'Pura Capoeira Cuernavaca';
$port   = 465;

if ( '' === $sender || '' === $host ) {
	WP_CLI::error( 'usage: wp eval-file setup.php <sender_email> <smtp_host>' );
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

$probe = new PHPMailer\PHPMailer\PHPMailer( true );
$probe->isSMTP();
$probe->Host       = $host;
$probe->Port       = $port;
$probe->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
$probe->SMTPAuth   = true;
$probe->Username   = FLUENTMAIL_SMTP_USERNAME;
$probe->Password   = FLUENTMAIL_SMTP_PASSWORD;
$probe->Timeout    = 20;

try {
	if ( ! $probe->smtpConnect() ) {
		WP_CLI::error( "SMTP login to {$host}:{$port} failed (no exception)." );
	}
	$probe->smtpClose();
} catch ( \Throwable $e ) {
	WP_CLI::error( "SMTP login to {$host}:{$port} failed: " . $e->getMessage() );
}
WP_CLI::log( "SMTP login OK: {$sender} on {$host}:{$port} (SSL)" );

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

$misc                       = $settings->getMisc();
$misc['default_connection'] = $key;
$misc['log_emails']         = 'yes';
$settings->updateMiscSettings( $misc );

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
