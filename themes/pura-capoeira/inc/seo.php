<?php
/**
 * Title, meta description, Open Graph, and theme-color without an SEO plugin.
 *
 * Page excerpts double as meta descriptions. If an SEO plugin is ever installed, remove this
 * file from functions.php; nothing else depends on it.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

function pura_theme_title_separator(): string {
	return '|';
}
add_filter( 'document_title_separator', 'pura_theme_title_separator' );

/**
 * "{Page title} | Pura Capoeira Cuernavaca"; the front page keeps site title + tagline.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function pura_theme_title_parts( array $parts ): array {
	if ( is_front_page() ) {
		return $parts;
	}

	$parts['site'] = get_bloginfo( 'name' );
	unset( $parts['tagline'] );

	return $parts;
}
add_filter( 'document_title_parts', 'pura_theme_title_parts' );

/**
 * Meta description for the current view.
 */
function pura_theme_meta_description(): string {
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && '' !== trim( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
	}

	return (string) get_bloginfo( 'description' );
}

/**
 * Open Graph image: featured image → plugin-configured OG image → site logo.
 */
function pura_theme_og_image_url(): string {
	if ( is_singular() && has_post_thumbnail() ) {
		$url = get_the_post_thumbnail_url( null, 'large' );
		if ( $url ) {
			return $url;
		}
	}

	$og_id = (int) ( function_exists( 'pura_setting' ) ? pura_setting( 'og_image_id', 0 ) : 0 );
	if ( $og_id ) {
		$url = wp_get_attachment_image_url( $og_id, 'large' );
		if ( $url ) {
			return $url;
		}
	}

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}

	return '';
}

function pura_theme_print_meta(): void {
	$description = pura_theme_meta_description();
	$url         = is_singular() ? get_permalink() : home_url( '/' );
	$title       = wp_get_document_title();
	$image       = pura_theme_og_image_url();

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta name="theme-color" content="#0A0A0A" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
	echo '<meta property="og:type" content="website" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:locale" content="es_MX" />' . "\n";
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
}
add_action( 'wp_head', 'pura_theme_print_meta', 1 );
