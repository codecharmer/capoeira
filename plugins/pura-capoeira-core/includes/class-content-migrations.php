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
		'grant_event_registration_caps',
		'create_vadiando_2026_page',
		'update_vadiando_2026_organizers',
	);

	private const VADIANDO_OLD_ORGANIZER = '<p class="event-fact__value">Pura Capoeira · Centro Esportivo Cultural Mestre Madona</p>';
	private const VADIANDO_NEW_ORGANIZER = '<p class="event-fact__value">Contramestre Pepe Mortales</p>';
	private const VADIANDO_SUPERVISOR    = "<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">Supervisa</p><!-- /wp:paragraph -->\n<!-- wp:paragraph {\"className\":\"event-fact__value\"} --><p class=\"event-fact__value\">Mestre Madona</p><!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->";

	/** Slug, pattern and copy of the event page published by create_vadiando_2026_page(). */
	public const VADIANDO_PAGE = array(
		'slug'    => 'vadiando-na-ladeira',
		'pattern' => 'pura-capoeira/page-evento-vadiando',
		'title'   => 'Vadiando na Ladeira 2026',
		'excerpt' => 'Vadiando na Ladeira: encuentro de capoeira del 6 al 8 de noviembre de 2026 en Guanajuato Capital, con Pura Capoeira y el Centro Esportivo Cultural Mestre Madona. Regístrate aquí.',
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
			// A migration returns false when it could not run yet (e.g. the theme that provides a
			// pattern is not active); it is retried on the next request.
			if ( false === $this->{$id}() ) {
				continue;
			}
			$done[] = $id;
			update_option( self::OPTION, array_values( $done ), true );
		}
	}

	/**
	 * The event registrations post type was added after activation; give administrators its caps.
	 */
	private function grant_event_registration_caps(): void {
		Activator::grant_capabilities();
	}

	/**
	 * Publish the Vadiando na Ladeira 2026 event page from the theme's pattern, once.
	 *
	 * @return bool False while the pattern is not registered (theme inactive or outdated).
	 */
	private function create_vadiando_2026_page(): bool {
		$page = self::VADIANDO_PAGE;

		if ( get_page_by_path( $page['slug'] ) ) {
			return true;
		}

		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $page['pattern'] );
		if ( ! $pattern ) {
			return false;
		}

		$blocks  = parse_blocks( (string) $pattern['content'] );
		$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_name'      => $page['slug'],
					'post_title'     => $page['title'],
					'post_excerpt'   => $page['excerpt'],
					'post_content'   => serialize_blocks( $blocks ),
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			),
			true
		);

		return ! is_wp_error( $post_id ) && $post_id > 0;
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

	/**
	 * The event page was published with the group as organiser; it is Contramestre Pepe Mortales,
	 * supervised by Mestre Madona.
	 */
	private function update_vadiando_2026_organizers(): void {
		$page = get_page_by_path( self::VADIANDO_PAGE['slug'] );
		if ( ! $page ) {
			return;
		}

		$content = self::set_vadiando_organizers( (string) $page->post_content );
		if ( $content !== $page->post_content ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $page->ID,
						'post_content' => $content,
					)
				)
			);
		}
	}

	/**
	 * Replace the "Organiza" value and add a "Supervisa" fact right after it. Pure, for tests.
	 */
	public static function set_vadiando_organizers( string $content ): string {
		if ( ! str_contains( $content, self::VADIANDO_OLD_ORGANIZER ) ) {
			return $content;
		}

		$content = str_replace( self::VADIANDO_OLD_ORGANIZER, self::VADIANDO_NEW_ORGANIZER, $content );

		if ( str_contains( $content, '>Supervisa</p>' ) ) {
			return $content;
		}

		// Insert the new fact after the closing of the group that holds the organiser.
		$pattern = '/(' . preg_quote( self::VADIANDO_NEW_ORGANIZER, '/' ) . '.*?<!-- \/wp:group -->)/s';

		return (string) preg_replace( $pattern, '$1' . "\n" . str_replace( '$', '\$', self::VADIANDO_SUPERVISOR ), $content, 1 );
	}
}
