<?php
/**
 * Speculation Rules (WordPress 6.8+): prerender internal links eagerly, except the store,
 * which would fire Printful requests on hover.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * @param array<string, string>|null $config Core configuration.
 * @return array<string, string>
 */
function pura_theme_speculation_config( $config ): array {
	return array(
		'mode'      => 'prerender',
		'eagerness' => 'moderate',
	);
}
add_filter( 'wp_speculation_rules_configuration', 'pura_theme_speculation_config' );

/**
 * @param string[] $paths Excluded path patterns.
 * @return string[]
 */
function pura_theme_speculation_exclude( array $paths ): array {
	$paths[] = '/tienda/*';
	$paths[] = '/wp-json/*';

	return $paths;
}
add_filter( 'wp_speculation_rules_href_exclude_paths', 'pura_theme_speculation_exclude' );
