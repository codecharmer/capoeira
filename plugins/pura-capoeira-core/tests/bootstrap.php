<?php
/**
 * Unit test bootstrap: no WordPress. Pure classes (Pricing, Stripe_Signature) are exercised with
 * a tiny set of WordPress function stubs and an in-memory options store.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'PURA_CORE_DIR', dirname( __DIR__ ) . '/' );
define( 'PURA_CORE_VERSION', 'test' );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-autoloader.php';

Pura\Core\Autoloader::register( PURA_CORE_DIR . 'includes' );

/** @var array<string, mixed> In-memory options. */
$GLOBALS['pura_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['pura_test_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['pura_test_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $flags = 0 ): string {
		return (string) json_encode( $data, $flags );
	}
}

/**
 * Reset settings to the plugin defaults with optional overrides.
 *
 * @param array<string, mixed> $overrides Overrides.
 */
function pura_test_settings( array $overrides = array() ): void {
	$GLOBALS['pura_test_options']['pura_settings'] = array_merge( Pura\Core\Settings::defaults(), $overrides );
}
