<?php
/**
 * Plugin Name: Pura Capoeira Core
 * Plugin URI: https://capoeiracuernavaca.com
 * Description: Settings, inscriptions, Stripe/Printful integration, gallery videos, and REST API for Pura Capoeira Cuernavaca.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: Pura Capoeira Cuernavaca
 * License: GPL-2.0-or-later
 * Text Domain: pura
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'PURA_CORE_VERSION', '1.0.0' );
define( 'PURA_CORE_FILE', __FILE__ );
define( 'PURA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PURA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once PURA_CORE_DIR . 'includes/class-autoloader.php';
require_once PURA_CORE_DIR . 'includes/template-tags.php';

Pura\Core\Autoloader::register( PURA_CORE_DIR . 'includes' );

register_activation_hook( __FILE__, array( Pura\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Pura\Core\Activator::class, 'deactivate' ) );

Pura\Core\Plugin::instance()->boot();
