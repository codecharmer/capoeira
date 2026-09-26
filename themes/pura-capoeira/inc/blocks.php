<?php
/**
 * Register the theme's custom blocks from the wp-scripts build output.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Register every block under build/blocks/<name>/block.json.
 */
function pura_theme_register_blocks(): void {
	$build_dir = PURA_THEME_DIR . '/build/blocks';

	if ( ! is_dir( $build_dir ) ) {
		return;
	}

	$manifest = PURA_THEME_DIR . '/build/blocks-manifest.php';
	if ( function_exists( 'wp_register_block_metadata_collection' ) && file_exists( $manifest ) ) {
		wp_register_block_metadata_collection( $build_dir, $manifest );
	}

	foreach ( glob( $build_dir . '/*/block.json' ) ?: array() as $block_json ) {
		register_block_type( dirname( $block_json ) );
	}
}
add_action( 'init', 'pura_theme_register_blocks' );

/**
 * Custom block category so the Pura blocks group together in the inserter.
 *
 * @param array<int, array<string, string>> $categories Existing categories.
 * @return array<int, array<string, string>>
 */
function pura_theme_block_categories( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'pura',
			'title' => __( 'Pura Capoeira', 'pura' ),
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'pura_theme_block_categories' );

/**
 * Pattern category for the theme's section and page patterns.
 */
function pura_theme_register_pattern_category(): void {
	register_block_pattern_category(
		'pura-capoeira',
		array(
			'label'       => __( 'Pura Capoeira', 'pura' ),
			'description' => __( 'Secciones y páginas del sitio de Pura Capoeira Cuernavaca.', 'pura' ),
		)
	);
}
add_action( 'init', 'pura_theme_register_pattern_category', 9 );
