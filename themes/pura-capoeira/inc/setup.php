<?php
/**
 * Theme supports, assets, and editor styles.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports. Block themes get most of these implicitly; the explicit ones matter.
 */
function pura_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'script', 'style', 'search-form', 'gallery', 'caption' ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/theme.css', 'assets/css/editor.css' ) );

	load_theme_textdomain( 'pura', PURA_THEME_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'pura_theme_setup' );

/**
 * Version string for theme assets: file mtime locally, theme version in production.
 */
function pura_theme_asset_version( string $relative_path ): string {
	$file = PURA_THEME_DIR . '/' . ltrim( $relative_path, '/' );

	if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && file_exists( $file ) ) {
		return (string) filemtime( $file );
	}

	return PURA_THEME_VERSION;
}

/**
 * Front-end stylesheet and the small behaviour module.
 */
function pura_theme_enqueue_assets(): void {
	wp_enqueue_style(
		'pura-theme',
		PURA_THEME_URI . '/assets/css/theme.css',
		array(),
		pura_theme_asset_version( 'assets/css/theme.css' )
	);

	wp_enqueue_script_module(
		'pura-theme',
		PURA_THEME_URI . '/assets/js/theme.js',
		array(),
		pura_theme_asset_version( 'assets/js/theme.js' )
	);
}
add_action( 'wp_enqueue_scripts', 'pura_theme_enqueue_assets' );

/**
 * Mark the document as JS-capable as early as possible so `.js .reveal` can hide content
 * without a flash for no-JS visitors or crawlers.
 */
function pura_theme_js_class(): void {
	echo "<script>document.documentElement.classList.add('js');</script>\n";
}
add_action( 'wp_head', 'pura_theme_js_class', 0 );

/**
 * Remove the core block-library "classic" stylesheet noise we don't need on the front end.
 * Keep the block styles themselves.
 */
function pura_theme_dequeue_global_styles_noise(): void {
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'pura_theme_dequeue_global_styles_noise', 20 );
