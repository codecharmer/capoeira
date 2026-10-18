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
		'set_notification_recipient_2026_10',
		'add_vadiando_2026_payment',
		'remove_calendly',
		'set_inscription_fee_2026_10',
	);

	/** @var string[] Settings that pointed at Calendly and no longer exist. */
	private const CALENDLY_SETTINGS = array( 'calendly_trial_adult_url', 'calendly_trial_kids_url' );

	/** Anchor of the contact-page section whose Calendly embeds became inscription buttons. */
	public const BOOKING_SECTION_ANCHOR = 'agendar-clase';

	/** Payment section appended to the event page; mirrors the theme pattern. */
	private const VADIANDO_PAYMENT = "<!-- wp:group {\"tagName\":\"section\",\"align\":\"full\",\"className\":\"section\",\"anchor\":\"pago\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group alignfull section\" id=\"pago\">\n<!-- wp:group {\"className\":\"section__head reveal\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group section__head reveal\">\n<!-- wp:paragraph {\"className\":\"overline\"} --><p class=\"overline\">Pago</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2 class=\"wp-block-heading\">Cuota del evento</h2><!-- /wp:heading -->\n<!-- wp:paragraph {\"className\":\"lead\"} --><p class=\"lead\">La cuota se paga por transferencia a la cuenta de Mercado Pago de la organización. Después de pagar, envía tu comprobante por WhatsApp con tu nombre completo.</p><!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->\n<!-- wp:group {\"className\":\"payment-card reveal\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group payment-card reveal\">\n<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">CLABE</p><!-- /wp:paragraph -->\n<!-- wp:paragraph {\"className\":\"event-fact__value event-fact__value--mono\"} --><p class=\"event-fact__value event-fact__value--mono\">722969016003937282</p><!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->\n<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">Titular</p><!-- /wp:paragraph -->\n<!-- wp:paragraph {\"className\":\"event-fact__value\"} --><p class=\"event-fact__value\">Mardonio Sales Linhares</p><!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->\n<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">Banco / app</p><!-- /wp:paragraph -->\n<!-- wp:paragraph {\"className\":\"event-fact__value\"} --><p class=\"event-fact__value\">Mercado Pago</p><!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->\n<!-- wp:pura/contact-link {\"channel\":\"whatsapp\",\"label\":\"Enviar comprobante por WhatsApp\",\"variant\":\"btn-whatsapp\",\"message\":\"Hola, envío mi comprobante de pago de Vadiando na Ladeira 2026. Mi nombre es: \"} /-->\n</div>\n<!-- /wp:group -->\n</section>\n<!-- /wp:group -->";

	/** Address that receives inscription and event notifications from October 2026. */
	private const NOTIFY_EMAIL = 'codecharmer@codecharmer.io';

	/** Inscription (uniform) fee in cents from October 2026. */
	private const INSCRIPTION_FEE_CENTS = 170000;

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
	 * Calendly is gone: drop its settings, remove its blocks from saved content, swap the contact
	 * page's booking section for the pattern's current one, and regenerate the Aviso de
	 * Privacidad (never edited by hand) when it still names Calendly.
	 *
	 * @return bool False while the theme patterns are not registered (theme inactive or outdated).
	 */
	private function remove_calendly(): bool {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$contacto = $registry->get_registered( 'pura-capoeira/contacto' );
		$aviso    = $registry->get_registered( 'pura-capoeira/page-aviso-de-privacidad' );
		if ( ! $contacto || ! $aviso ) {
			return false;
		}

		$section = null;
		foreach ( parse_blocks( (string) $contacto['content'] ) as $block ) {
			if ( self::BOOKING_SECTION_ANCHOR === ( $block['attrs']['anchor'] ?? '' ) ) {
				$section = $block;
				break;
			}
		}
		if ( null === $section ) {
			return false;
		}

		$stored = get_option( Settings::OPTION, array() );
		if ( is_array( $stored ) ) {
			$before = $stored;
			foreach ( self::CALENDLY_SETTINGS as $key ) {
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
				's'                => 'calendly',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			if ( 'aviso-de-privacidad' === $post->post_name ) {
				$blocks  = parse_blocks( (string) $aviso['content'] );
				$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
				$content = serialize_blocks( $blocks );
			} else {
				$content = serialize_blocks( self::strip_calendly_blocks( parse_blocks( (string) $post->post_content ), $section ) );
			}

			if ( $content === $post->post_content ) {
				continue;
			}

			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $content,
					)
				)
			);
		}

		return true;
	}

	/**
	 * Remove `pura/calendly` blocks and Calendly contact links from a parsed block tree, and
	 * replace the booking section (by anchor) with `$section` when given. Pure: no WordPress calls,
	 * so it can be unit-tested. Keeps `innerContent` in step with `innerBlocks`.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Output of parse_blocks().
	 * @param array<string, mixed>|null        $section Replacement for the booking section.
	 * @return array<int, array<string, mixed>>
	 */
	public static function strip_calendly_blocks( array $blocks, ?array $section ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			$name    = (string) ( $block['blockName'] ?? '' );
			$channel = (string) ( $block['attrs']['channel'] ?? '' );

			if ( 'pura/calendly' === $name || ( 'pura/contact-link' === $name && str_starts_with( $channel, 'calendly' ) ) ) {
				continue;
			}
			if ( null !== $section && self::BOOKING_SECTION_ANCHOR === ( $block['attrs']['anchor'] ?? '' ) ) {
				$out[] = $section;
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_before  = $block['innerBlocks'];
				$inner_after   = self::strip_calendly_blocks( $inner_before, $section );
				$kept          = array();
				$inner_content = array();
				$slot          = 0;

				// Which original slots survived (replacements keep their slot).
				$survivors = array();
				$j         = 0;
				foreach ( $inner_before as $i => $child ) {
					$child_name      = (string) ( $child['blockName'] ?? '' );
					$child_channel   = (string) ( $child['attrs']['channel'] ?? '' );
					$dropped         = 'pura/calendly' === $child_name || ( 'pura/contact-link' === $child_name && str_starts_with( $child_channel, 'calendly' ) );
					$survivors[ $i ] = ! $dropped;
					if ( ! $dropped ) {
						$kept[] = $inner_after[ $j ];
						++$j;
					}
				}

				foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
					if ( null !== $chunk ) {
						$inner_content[] = $chunk;
						continue;
					}
					if ( ! empty( $survivors[ $slot ] ) ) {
						$inner_content[] = null;
					}
					++$slot;
				}

				$block['innerBlocks']  = $kept;
				$block['innerContent'] = $inner_content;
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Pure text transform, kept separate so it can be unit-tested without WordPress.
	 */
	public static function strip_street_address( string $text ): string {
		return strtr( $text, self::ADDRESS_REPLACEMENTS );
	}

	/**
	 * Point the notification recipients at the owner's address, once. It stays editable in
	 * Pura Capoeira → Ajustes → Notificaciones afterwards.
	 */
	private function set_notification_recipient_2026_10(): void {
		Settings::update( array( 'notify_emails' => self::NOTIFY_EMAIL ) );
	}

	/**
	 * Inscription fee goes from $1,500 to $1,700 MXN, once. It stays editable in
	 * Pura Capoeira → Ajustes → Precios afterwards.
	 */
	private function set_inscription_fee_2026_10(): void {
		Settings::update( array( 'inscription_fee' => self::INSCRIPTION_FEE_CENTS ) );
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
	 * Append the Mercado Pago payment section to the published event page, once.
	 */
	private function add_vadiando_2026_payment(): void {
		$page = get_page_by_path( self::VADIANDO_PAGE['slug'] );
		if ( ! $page ) {
			return;
		}

		$content = self::add_vadiando_payment( (string) $page->post_content );
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
	 * Add the payment section at the end of the page unless it is already there. Pure, for tests.
	 */
	public static function add_vadiando_payment( string $content ): string {
		if ( str_contains( $content, 'payment-card' ) ) {
			return $content;
		}

		return rtrim( $content ) . "\n\n" . self::VADIANDO_PAYMENT;
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
