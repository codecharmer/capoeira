<?php
/**
 * PSR-4-ish autoloader mapping Pura\Core\* to WordPress-style file names.
 *
 * Pura\Core\Rest\Store_Controller → includes/rest/class-store-controller.php
 * Pura\Core\Http\Printful_Client_Interface → includes/http/interface-printful-client.php
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private const PREFIX = 'Pura\\Core\\';

	public static function register( string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $base_dir ): void {
				if ( 0 !== strpos( $class, self::PREFIX ) ) {
					return;
				}

				$relative = substr( $class, strlen( self::PREFIX ) );
				$parts    = explode( '\\', $relative );
				$name     = array_pop( $parts );
				$dir      = strtolower( implode( '/', $parts ) );

				$slug = strtolower( str_replace( '_', '-', $name ) );
				if ( str_ends_with( $slug, '-interface' ) ) {
					$file = 'interface-' . substr( $slug, 0, -strlen( '-interface' ) ) . '.php';
				} else {
					$file = 'class-' . $slug . '.php';
				}

				$path = rtrim( $base_dir, '/' ) . '/' . ( $dir ? $dir . '/' : '' ) . $file;
				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
