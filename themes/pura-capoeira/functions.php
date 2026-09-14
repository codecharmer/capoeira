<?php
/**
 * Pura Capoeira block theme bootstrap.
 *
 * Keep this file small: each concern lives in inc/.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'PURA_THEME_VERSION', wp_get_theme()->get( 'Version' ) ?: '1.0.0' );
define( 'PURA_THEME_DIR', get_template_directory() );
define( 'PURA_THEME_URI', get_template_directory_uri() );

require_once PURA_THEME_DIR . '/inc/setup.php';
require_once PURA_THEME_DIR . '/inc/blocks.php';
require_once PURA_THEME_DIR . '/inc/block-styles.php';
require_once PURA_THEME_DIR . '/inc/seo.php';
require_once PURA_THEME_DIR . '/inc/speculation.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PURA_THEME_DIR . '/inc/cli/class-import-command.php';
}
