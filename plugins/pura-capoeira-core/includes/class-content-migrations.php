<?php
/**
 * One-off content migrations: fixes applied once to data that lives in the database
 * (imported pages, stored settings) and therefore cannot be corrected by deploying files.
 *
 * Each migration runs at most once per site; the ones already applied are recorded in the
 * autoloaded `pura_content_migrations` option, so the per-request cost is one option read.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Content_Migrations {

	public const OPTION = 'pura_content_migrations';

	/** @var string[] Migration ids, in the order they run. */
	private const MIGRATIONS = array(
		'remove_street_address',
	);

	/**
	 * Street-address fragments and what replaces them, longest first. Applied to page content
	 * and excerpts saved before the address was removed from the site.
	 *
	 * @var array<string, string>
	 */
	private const ADDRESS_REPLACEMENTS = array(
		'Clases en San Jerónimo 503, Tlaltenango, Cuernavaca, Morelos.' => 'Clases en Tlaltenango, Cuernavaca, Morelos.',
		'San Jerónimo 503,<br>Tlaltenango, Cuernavaca,<br>Morelos' => '',
		'San Jerónimo 503, Tlaltenango, Cuernavaca, Morelos' => 'Cuernavaca, Morelos',
		'San Jerónimo 503 · Tlaltenango' => 'Tlaltenango · Cuernavaca',
		'San Jerónimo 503, Tlaltenango'  => '',
		'San Jerónimo 503'               => '',
	);

	/** @var string[] Settings that held the address and no longer exist. */
	private const ADDRESS_SETTINGS = array( 'address', 'address_short', 'maps_url' );

	public function register(): void {
		add_action( 'init', array( $this, 'run' ), 20 );
	}

	public function run(): void {
		$done = get_option( self::OPTION, array() );
		$done = is_array( $done ) ? $done : array();

		$pending = array_diff( self::MIGRATIONS, $done );
		if ( array() === $pending ) {
			return;
		}

		foreach ( $pending as $id ) {
			$this->{$id}();
			$done[] = $id;
			update_option( self::OPTION, array_values( $done ), true );
		}
	}

	/**
	 * Remove the street address from stored settings and from every saved page, template part
	 * or reusable block that still carries it.
	 */
	private function remove_street_address(): void {
		$stored = get_option( Settings::OPTION, array() );
		if ( is_array( $stored ) ) {
			$before = $stored;
			foreach ( self::ADDRESS_SETTINGS as $key ) {
				unset( $stored[ $key ] );
			}
			if ( $stored !== $before ) {
				update_option( Settings::OPTION, $stored, true );
			}
		}

		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post', 'wp_template', 'wp_template_part', 'wp_block' ),
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				's'                => 'San Jerónimo 503',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			$content = self::strip_street_address( (string) $post->post_content );
			$excerpt = self::strip_street_address( (string) $post->post_excerpt );

			if ( $content === $post->post_content && $excerpt === $post->post_excerpt ) {
				continue;
			}

			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $content,
						'post_excerpt' => $excerpt,
					)
				)
			);
		}
	}

	/**
	 * Pure text transform, kept separate so it can be unit-tested without WordPress.
	 */
	public static function strip_street_address( string $text ): string {
		return strtr( $text, self::ADDRESS_REPLACEMENTS );
	}
}
