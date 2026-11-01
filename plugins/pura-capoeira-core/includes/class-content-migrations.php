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
		'create_trial_thanks_page',
		'trial_campaign_2026_11',
	);

	/** Slug, pattern and copy of the page a student lands on after booking a trial class. */
	public const TRIAL_THANKS_PAGE = array(
		'slug'    => 'gracias-clase-de-prueba',
		'pattern' => 'pura-capoeira/page-gracias-clase-de-prueba',
		'title'   => 'Gracias por reservar tu clase de prueba',
		'excerpt' => 'Tu clase de prueba en Pura Capoeira Cuernavaca está reservada. Revisa tu correo: ahí van la ubicación de la academia y lo que necesitas traer.',
	);

	/**
	 * Meta campaign (November 2026): the trial-class price, the teens line and the privacy notice
	 * update. Fragments mirror the theme patterns (clases-cards, cta-trial, costs,
	 * home-schedule-prices, inscripciones-schedule) and are applied to the saved pages.
	 */
	private const TRIAL_RETENTION_PLACEHOLDER = '[X]';

	private const ADULT_HEADING_OLD = '>Capoeira para adultos</h3>';
	private const ADULT_HEADING_NEW = '>Capoeira para adultos y jóvenes</h3>';

	private const CLASES_MIXED_GROUP_LINE = '<!-- wp:paragraph {"className":"info-card__sub","style":{"spacing":{"margin":{"bottom":"1rem"}}}} --><p class="info-card__sub" style="margin-bottom:1rem">Grupo mixto desde los 8 años · Martes y jueves, 17:30 a 19:30. No se requiere experiencia previa. Los jóvenes de 13 a 17 años se integran a este grupo.</p><!-- /wp:paragraph -->';

	private const TRIAL_SECTION_OPEN_OLD = '<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","layout":{"type":"constrained"}} -->' . "\n" . '<section class="wp-block-group alignfull section section--dark">';
	private const TRIAL_SECTION_OPEN_NEW = '<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","anchor":"prueba","layout":{"type":"constrained"}} -->' . "\n" . '<section class="wp-block-group alignfull section section--dark" id="prueba">';

	private const TRIAL_PARAGRAPHS = '<!-- wp:paragraph {"className":"lead","style":{"spacing":{"margin":{"top":"0","bottom":"1rem"}}}} --><p class="lead" style="margin-top:0;margin-bottom:1rem">Clase de prueba: <strong>$200 MXN</strong>. Ven a una clase de tu grupo para conocer la práctica y al profesor. Resérvala y págala en línea, o escríbenos por WhatsApp para elegir fecha.</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:paragraph {"className":"lead","style":{"spacing":{"margin":{"top":"0","bottom":"1rem"}}}} --><p class="lead" style="margin-top:0;margin-bottom:1rem">¿Eres madre, padre o tutor de un joven de 13 a 17 años? Agenda su clase de prueba en el grupo de martes y jueves.</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:paragraph {"className":"lead","style":{"spacing":{"margin":{"top":"0","bottom":"2rem"}}}} --><p class="lead" style="margin-top:0;margin-bottom:2rem">Te compartimos la ubicación exacta al confirmar tu clase.</p><!-- /wp:paragraph -->';

	private const TRIAL_COST_CARD = '<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group info-card reveal">' . "\n"
		. '<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Clase de prueba</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:pura/price {"key":"trial","group":"adult","suffix":"MXN (una clase)","className":"info-card__price"} /-->' . "\n"
		. '<!-- wp:paragraph {"className":"info-card__sub"} --><p class="info-card__sub">Ven a una clase de tu grupo para conocer la práctica y al profesor.</p><!-- /wp:paragraph -->' . "\n"
		. '</div>' . "\n"
		. '<!-- /wp:group -->';

	/** The /clases booking buttons preselect the trial package for their group on /inscripciones. */
	private const TRIAL_BUTTON_LINKS = array(
		'href="/inscripciones/">Agendar Adultos</a>' => 'href="/inscripciones/?plan=trial&amp;group=adult">Agendar Adultos</a>',
		'href="/inscripciones/">Agendar Kids</a>'    => 'href="/inscripciones/?plan=trial&amp;group=kids">Agendar Kids</a>',
	);

	private const HOME_TRIAL_PRICE = '<!-- wp:heading {"level":3,"className":"info-card__title","style":{"spacing":{"margin":{"top":"2rem"}}}} --><h3 class="wp-block-heading info-card__title" style="margin-top:2rem">Clase de prueba</h3><!-- /wp:heading -->' . "\n"
		. '<!-- wp:pura/price {"key":"trial","group":"adult","suffix":"MXN","className":"info-card__price"} /-->';

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
		return $this->publish_page_from_pattern( self::VADIANDO_PAGE );
	}

	/**
	 * Publish the thank-you page for trial-class bookings and point the plugin at it.
	 *
	 * @return bool False while the pattern is not registered (theme inactive or outdated).
	 */
	private function create_trial_thanks_page(): bool {
		if ( ! $this->publish_page_from_pattern( self::TRIAL_THANKS_PAGE ) ) {
			return false;
		}

		$page = get_page_by_path( self::TRIAL_THANKS_PAGE['slug'] );
		if ( $page ) {
			Settings::update( array( 'trial_thanks_page_id' => (int) $page->ID ) );
		}

		return true;
	}

	/**
	 * Create a published page from a theme pattern unless its slug already exists.
	 *
	 * @param array{slug:string,pattern:string,title:string,excerpt:string} $page Page spec.
	 * @return bool False while the pattern is not registered (theme inactive or outdated).
	 */
	private function publish_page_from_pattern( array $page ): bool {
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
	 * Meta campaign, November 2026: show the trial price and the teens line on /clases, the trial
	 * price on the home page, the shared group heading on /inscripciones, regenerate the Aviso de
	 * Privacidad from its pattern (never edited by hand) and stamp it with today's date.
	 *
	 * @return bool False while the Aviso pattern is not registered or still carries the retention
	 *              placeholder: the notice must not go live with "[X] meses".
	 */
	private function trial_campaign_2026_11(): bool {
		$aviso = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'pura-capoeira/page-aviso-de-privacidad' );
		if ( ! $aviso || str_contains( (string) $aviso['content'], self::TRIAL_RETENTION_PLACEHOLDER ) ) {
			return false;
		}

		$this->transform_page( get_page_by_path( 'clases' ), array( self::class, 'add_trial_copy_to_clases' ) );
		$this->transform_page( get_page_by_path( 'inscripciones' ), array( self::class, 'rename_adult_group_heading' ) );

		$front_id = (int) get_option( 'page_on_front', 0 );
		$home     = $front_id ? get_post( $front_id ) : null;
		$this->transform_page( $home ?: get_page_by_path( 'inicio' ), array( self::class, 'add_trial_price_to_home' ) );

		$page = get_page_by_path( 'aviso-de-privacidad' );
		if ( $page ) {
			$blocks  = parse_blocks( (string) $aviso['content'] );
			$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
			$content = serialize_blocks( $blocks );
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

		Settings::update( array( 'privacy_updated' => wp_date( 'Y-m-d' ) ) );

		return true;
	}

	/**
	 * Apply a pure content transform to a saved page and store it when something changed.
	 *
	 * @param \WP_Post|null $page      Page, or null when it does not exist.
	 * @param callable      $transform string → string.
	 */
	private function transform_page( ?\WP_Post $page, callable $transform ): void {
		if ( ! $page ) {
			return;
		}

		$content = (string) $transform( (string) $page->post_content );
		if ( $content === $page->post_content ) {
			return;
		}

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $page->ID,
					'post_content' => $content,
				)
			)
		);
	}

	/**
	 * "Capoeira para adultos" headings become "Capoeira para adultos y jóvenes". Pure, for tests.
	 */
	public static function rename_adult_group_heading( string $content ): string {
		return str_replace( self::ADULT_HEADING_OLD, self::ADULT_HEADING_NEW, $content );
	}

	/**
	 * /clases: rename the adults heading and add the mixed-group line under it, give the trial
	 * section its anchor and the price paragraphs, and add the trial card to Costos. Each step is
	 * skipped when its marker is already present, so running it again changes nothing. Pure.
	 */
	public static function add_trial_copy_to_clases( string $content ): string {
		$content = self::rename_adult_group_heading( $content );

		if ( ! str_contains( $content, 'Los jóvenes de 13 a 17 años se integran a este grupo.' ) ) {
			$content = (string) preg_replace(
				'/(' . preg_quote( self::ADULT_HEADING_NEW, '/' ) . '<!-- \/wp:heading -->)(\s*)(<!-- wp:pura\/schedule \{"group":"adult"\} \/-->)/u',
				'$1$2' . str_replace( '$', '\$', self::CLASES_MIXED_GROUP_LINE ) . '$2$3',
				$content,
				1
			);
		}

		if ( ! str_contains( $content, 'id="prueba"' ) ) {
			// Two sections share the dark opener (Costos and the trial CTA); the CTA is the one whose
			// first child is the centred group.
			$content = (string) preg_replace(
				'/' . preg_quote( self::TRIAL_SECTION_OPEN_OLD, '/' ) . '(?=\s*<!-- wp:group \{"className":"reveal cta-center")/u',
				str_replace( '$', '\$', self::TRIAL_SECTION_OPEN_NEW ),
				$content,
				1
			);
		}

		if ( ! str_contains( $content, 'Clase de prueba: <strong>$200 MXN</strong>' ) ) {
			$content = (string) preg_replace(
				'/(o escríbenos por WhatsApp\.<\/p><!-- \/wp:paragraph -->)(\s*)(?=<!-- wp:buttons )/u',
				'$1$2' . str_replace( '$', '\$', self::TRIAL_PARAGRAPHS ) . '$2',
				$content,
				1
			);
		}

		$content = strtr( $content, self::TRIAL_BUTTON_LINKS );

		if ( ! str_contains( $content, '"key":"trial"' ) ) {
			$content = (string) preg_replace(
				'/(La inscripción incluye el proceso inicial de integración y uniforme de entrenamiento\.<\/p><!-- \/wp:paragraph -->\s*<\/div>\s*<!-- \/wp:group -->)(\s*)/u',
				'$1' . "\n\n" . str_replace( '$', '\$', self::TRIAL_COST_CARD ) . '$2',
				$content,
				1
			);
		}

		return $content;
	}

	/**
	 * Home page: the trial price under "Inscripción / Uniforme" in the Costos card, once. Pure.
	 */
	public static function add_trial_price_to_home( string $content ): string {
		if ( str_contains( $content, '"key":"trial"' ) ) {
			return $content;
		}

		return (string) preg_replace(
			'/(Incluye el proceso inicial de integración y uniforme de entrenamiento\.<\/p><!-- \/wp:paragraph -->)(\s*)(?=<!-- wp:pura\/contact-link )/u',
			'$1$2' . str_replace( '$', '\$', self::HOME_TRIAL_PRICE ) . '$2',
			$content,
			1
		);
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
