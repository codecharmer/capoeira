<?php
/**
 * @package Pura\Core\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use Pura\Core\Content_Migrations;

final class ContentMigrationsTest extends TestCase {

	public function test_page_header_overline_keeps_the_neighbourhood(): void {
		$in = '<!-- wp:paragraph {"className":"overline"} --><p class="overline">San Jerónimo 503 · Tlaltenango</p><!-- /wp:paragraph -->';

		$this->assertSame(
			'<!-- wp:paragraph {"className":"overline"} --><p class="overline">Tlaltenango · Cuernavaca</p><!-- /wp:paragraph -->',
			Content_Migrations::strip_street_address( $in )
		);
	}

	public function test_bound_address_paragraph_becomes_empty(): void {
		$in = '<p class="location__addr">San Jerónimo 503,<br>Tlaltenango, Cuernavaca,<br>Morelos</p>';

		$this->assertSame( '<p class="location__addr"></p>', Content_Migrations::strip_street_address( $in ) );
	}

	public function test_contact_excerpt_loses_only_the_street(): void {
		$in = 'Contacta a Pura Capoeira Cuernavaca. Clases en San Jerónimo 503, Tlaltenango, Cuernavaca, Morelos. WhatsApp, Instagram y Facebook.';

		$this->assertSame(
			'Contacta a Pura Capoeira Cuernavaca. Clases en Tlaltenango, Cuernavaca, Morelos. WhatsApp, Instagram y Facebook.',
			Content_Migrations::strip_street_address( $in )
		);
	}

	public function test_footer_short_address_and_bare_street_are_removed(): void {
		$this->assertSame( '<p></p>', Content_Migrations::strip_street_address( '<p>San Jerónimo 503, Tlaltenango</p>' ) );
		$this->assertSame( 'Nos vemos en .', Content_Migrations::strip_street_address( 'Nos vemos en San Jerónimo 503.' ) );
	}

	public function test_vadiando_organizers_are_replaced_and_supervisor_added_once(): void {
		$content = "<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n"
			. "<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">Organiza</p><!-- /wp:paragraph -->\n"
			. "<!-- wp:paragraph {\"className\":\"event-fact__value\"} --><p class=\"event-fact__value\">Pura Capoeira · Centro Esportivo Cultural Mestre Madona</p><!-- /wp:paragraph -->\n"
			. "</div>\n<!-- /wp:group -->\n"
			. "<!-- wp:group {\"className\":\"event-fact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group event-fact\">\n"
			. "<!-- wp:paragraph {\"className\":\"event-fact__label\"} --><p class=\"event-fact__label\">Para quién</p><!-- /wp:paragraph -->\n"
			. "</div>\n<!-- /wp:group -->";

		$updated = Content_Migrations::set_vadiando_organizers( $content );

		$this->assertStringNotContainsString( 'Centro Esportivo Cultural Mestre Madona</p>', $updated );
		$this->assertStringContainsString( '<p class="event-fact__value">Contramestre Pepe Mortales</p>', $updated );
		$this->assertSame( 1, substr_count( $updated, '>Supervisa</p>' ) );
		$this->assertSame( 1, substr_count( $updated, '<p class="event-fact__value">Mestre Madona</p>' ) );
		$this->assertLessThan( strpos( $updated, 'Para quién' ), strpos( $updated, 'Supervisa' ), 'Supervisa sits right after Organiza, before Para quién.' );
		$this->assertSame( $updated, Content_Migrations::set_vadiando_organizers( $updated ), 'Running it again changes nothing.' );
	}

	public function test_vadiando_payment_section_is_appended_once(): void {
		$content = "<!-- wp:group --><section>registro</section><!-- /wp:group -->\n";

		$updated = Content_Migrations::add_vadiando_payment( $content );

		$this->assertStringStartsWith( '<!-- wp:group --><section>registro</section><!-- /wp:group -->', $updated );
		$this->assertStringContainsString( '722969016003937282', $updated );
		$this->assertStringContainsString( 'Mardonio Sales Linhares', $updated );
		$this->assertSame( 1, substr_count( $updated, 'id="pago"' ) );
		$this->assertSame( $updated, Content_Migrations::add_vadiando_payment( $updated ) );
	}

	/**
	 * Markup of pure-HTML theme patterns (no PHP calls), concatenated the way the importer inlines
	 * them into a page. Skips when the theme is not next to the plugin.
	 */
	private function pattern_markup( string ...$slugs ): string {
		$dir    = dirname( __DIR__, 4 ) . '/themes/pura-capoeira/patterns/';
		$markup = '';

		foreach ( $slugs as $slug ) {
			if ( ! is_file( $dir . $slug . '.php' ) ) {
				$this->markTestSkipped( 'theme patterns not available' );
			}

			ob_start();
			try {
				include $dir . $slug . '.php';
			} finally {
				$markup .= (string) ob_get_clean() . "\n";
			}
		}

		return $markup;
	}

	/** The /clases page as it was saved before the campaign changes. */
	private function old_clases_page(): string {
		$trial = "<!-- wp:group {\"tagName\":\"section\",\"align\":\"full\",\"className\":\"section section--dark\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group alignfull section section--dark\">\n"
			. "\t<!-- wp:group {\"className\":\"reveal cta-center\",\"layout\":{\"type\":\"constrained\",\"contentSize\":\"600px\"}} -->\n\t<div class=\"wp-block-group reveal cta-center\">\n"
			. "\t\t<!-- wp:heading --><h2 class=\"wp-block-heading\">Agenda tu clase de prueba</h2><!-- /wp:heading -->\n"
			. "\t\t<!-- wp:paragraph {\"className\":\"lead\"} --><p class=\"lead\">Una clase para conocernos. Reserva en línea según tu grupo (Adultos o Kids) o escríbenos por WhatsApp.</p><!-- /wp:paragraph -->\n"
			. "\t\t<!-- wp:buttons --><div class=\"wp-block-buttons\"></div><!-- /wp:buttons -->\n"
			. "\t</div>\n\t<!-- /wp:group -->\n</section>\n<!-- /wp:group -->";

		return "<!-- wp:group {\"className\":\"info-card reveal\",\"anchor\":\"adultos\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group info-card reveal\" id=\"adultos\">\n"
			. "\t<!-- wp:heading {\"level\":3,\"className\":\"info-card__title\"} --><h3 class=\"wp-block-heading info-card__title\">Capoeira para adultos</h3><!-- /wp:heading -->\n"
			. "\t<!-- wp:pura/schedule {\"group\":\"adult\"} /-->\n</div>\n<!-- /wp:group -->\n"
			. "<!-- wp:group {\"className\":\"info-card reveal\",\"anchor\":\"kids\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group info-card reveal\" id=\"kids\">\n"
			. "\t<!-- wp:heading {\"level\":3,\"className\":\"info-card__title\"} --><h3 class=\"wp-block-heading info-card__title\">Capoeira para niños</h3><!-- /wp:heading -->\n"
			. "\t<!-- wp:pura/schedule {\"group\":\"kids\"} /-->\n</div>\n<!-- /wp:group -->\n"
			. "<!-- wp:group {\"tagName\":\"section\",\"align\":\"full\",\"className\":\"section section--dark\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group alignfull section section--dark\">\n"
			. "\t<!-- wp:group {\"className\":\"info-card reveal\",\"layout\":{\"type\":\"default\"}} -->\n\t<div class=\"wp-block-group info-card reveal\">\n"
			. "\t\t<!-- wp:paragraph {\"className\":\"info-card__sub\"} --><p class=\"info-card__sub\">La inscripción incluye el proceso inicial de integración y uniforme de entrenamiento.</p><!-- /wp:paragraph -->\n"
			. "\t</div>\n\t<!-- /wp:group -->\n</section>\n<!-- /wp:group -->\n"
			. $trial;
	}

	public function test_clases_gets_teens_line_trial_anchor_price_and_cost_card_once(): void {
		$updated = Content_Migrations::add_trial_copy_to_clases( $this->old_clases_page() );

		$this->assertSame( 1, substr_count( $updated, 'id="adultos"' ) );
		$this->assertSame( 1, substr_count( $updated, 'id="kids"' ) );
		$this->assertSame( 1, substr_count( $updated, 'id="prueba"' ), 'only the trial CTA section gets the anchor, not Costos' );
		$this->assertStringContainsString( '"anchor":"prueba"', $updated );
		$this->assertSame( 1, substr_count( $updated, 'Capoeira para adultos y jóvenes</h3>' ) );
		$this->assertStringNotContainsString( 'Capoeira para adultos</h3>', $updated );
		$this->assertStringContainsString( 'Capoeira para niños</h3>', $updated );
		$this->assertSame( 1, substr_count( $updated, 'Los jóvenes de 13 a 17 años se integran a este grupo.' ) );
		$this->assertLessThan( strpos( $updated, '<!-- wp:pura/schedule {"group":"adult"} /-->' ), strpos( $updated, 'Grupo mixto desde los 8 años' ), 'the mixed-group line sits between the heading and the schedule' );
		$this->assertSame( 1, substr_count( $updated, 'Clase de prueba: <strong>$200 MXN</strong>' ) );
		$this->assertSame( 1, substr_count( $updated, '¿Eres madre, padre o tutor de un joven de 13 a 17 años?' ) );
		$this->assertSame( 1, substr_count( $updated, 'Te compartimos la ubicación exacta al confirmar tu clase.' ) );
		$this->assertLessThan( strpos( $updated, '<!-- wp:buttons' ), strpos( $updated, 'Clase de prueba: <strong>' ), 'the price paragraphs come before the buttons' );
		$this->assertSame( 1, substr_count( $updated, '"key":"trial"' ) );
		$this->assertStringContainsString( '"suffix":"MXN (una clase)"', $updated );

		$this->assertSame( $updated, Content_Migrations::add_trial_copy_to_clases( $updated ), 'Running it again changes nothing.' );
	}

	public function test_clases_transform_is_a_no_op_on_the_current_pattern(): void {
		$current = $this->pattern_markup( 'clases-cards', 'costs', 'cta-trial' );

		$this->assertStringContainsString( 'id="prueba"', $current );
		$this->assertSame( $current, Content_Migrations::add_trial_copy_to_clases( $current ) );
	}

	public function test_home_gets_the_trial_price_before_the_whatsapp_button_once(): void {
		$in = "<!-- wp:paragraph {\"className\":\"info-card__sub\",\"style\":{\"spacing\":{\"margin\":{\"top\":\"1rem\"}}}} --><p class=\"info-card__sub\" style=\"margin-top:1rem\">Incluye el proceso inicial de integración y uniforme de entrenamiento.</p><!-- /wp:paragraph -->\n"
			. "\t\t\t<!-- wp:pura/contact-link {\"channel\":\"whatsapp\",\"label\":\"Agendar clase de prueba\"} /-->";

		$updated = Content_Migrations::add_trial_price_to_home( $in );

		$this->assertSame( 1, substr_count( $updated, '>Clase de prueba</h3>' ) );
		$this->assertSame( 1, substr_count( $updated, '"key":"trial"' ) );
		$this->assertLessThan( strpos( $updated, 'wp:pura/contact-link' ), strpos( $updated, '"key":"trial"' ) );
		$this->assertSame( $updated, Content_Migrations::add_trial_price_to_home( $updated ) );
		$this->assertSame( $this->pattern_markup( 'home-schedule-prices' ), Content_Migrations::add_trial_price_to_home( $this->pattern_markup( 'home-schedule-prices' ) ) );
	}

	public function test_adult_group_heading_is_renamed_everywhere_and_only_there(): void {
		$in = '<h3 class="wp-block-heading info-card__title">Capoeira para adultos</h3> <h3>Capoeira para niños</h3> <p>Capoeira para adultos</p>';

		$this->assertSame(
			'<h3 class="wp-block-heading info-card__title">Capoeira para adultos y jóvenes</h3> <h3>Capoeira para niños</h3> <p>Capoeira para adultos</p>',
			Content_Migrations::rename_adult_group_heading( $in )
		);
		$this->assertSame( $this->pattern_markup( 'inscripciones-schedule' ), Content_Migrations::rename_adult_group_heading( $this->pattern_markup( 'inscripciones-schedule' ) ) );
	}

	public function test_text_without_the_address_is_untouched(): void {
		$in = 'Capoeira para adultos y niños en Tlaltenango, Cuernavaca.';

		$this->assertSame( $in, Content_Migrations::strip_street_address( $in ) );
		$this->assertStringNotContainsString( 'Jerónimo', Content_Migrations::strip_street_address( $in ) );
	}

	/**
	 * parse_blocks()-shaped fixture: a flex group holding WhatsApp + two Calendly links, then the
	 * booking section with two pura/calendly blocks, then an unrelated paragraph.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function calendly_page(): array {
		$link = static fn ( string $channel ): array => array(
			'blockName'    => 'pura/contact-link',
			'attrs'        => array( 'channel' => $channel ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		$embed = static fn ( string $group ): array => array(
			'blockName'    => 'pura/calendly',
			'attrs'        => array( 'group' => $group ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		return array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( 'className' => 'contact-actions' ),
				'innerBlocks'  => array( $link( 'whatsapp' ), $link( 'calendly_adults' ), $link( 'calendly_kids' ) ),
				'innerHTML'    => '<div class="wp-block-group contact-actions"></div>',
				'innerContent' => array( '<div class="wp-block-group contact-actions">', null, "\n", null, "\n", null, '</div>' ),
			),
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( 'anchor' => 'agendar-clase' ),
				'innerBlocks'  => array( $embed( 'adult' ), $embed( 'kids' ) ),
				'innerHTML'    => '<section id="agendar-clase"></section>',
				'innerContent' => array( '<section id="agendar-clase">', null, null, '</section>' ),
			),
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>Hola</p>',
				'innerContent' => array( '<p>Hola</p>' ),
			),
		);
	}

	public function test_calendly_links_are_dropped_and_inner_content_stays_aligned(): void {
		$out = Content_Migrations::strip_calendly_blocks( $this->calendly_page(), null );

		$actions = $out[0];
		$this->assertCount( 1, $actions['innerBlocks'] );
		$this->assertSame( 'whatsapp', $actions['innerBlocks'][0]['attrs']['channel'] );
		$this->assertSame( 1, count( array_filter( $actions['innerContent'], 'is_null' ) ), 'one null slot per surviving inner block' );
		$this->assertSame( '</div>', end( $actions['innerContent'] ) );
	}

	public function test_booking_section_is_replaced_when_a_replacement_is_given(): void {
		$replacement = array(
			'blockName'    => 'core/group',
			'attrs'        => array( 'anchor' => 'agendar-clase' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<section id="agendar-clase">nuevo</section>',
			'innerContent' => array( '<section id="agendar-clase">nuevo</section>' ),
		);

		$out = Content_Migrations::strip_calendly_blocks( $this->calendly_page(), $replacement );

		$this->assertSame( $replacement, $out[1] );
		$this->assertSame( 'core/paragraph', $out[2]['blockName'], 'unrelated blocks are untouched' );
	}

	public function test_booking_section_loses_its_embeds_without_a_replacement(): void {
		$out = Content_Migrations::strip_calendly_blocks( $this->calendly_page(), null );

		$this->assertSame( array(), $out[1]['innerBlocks'] );
		$this->assertSame( array( '<section id="agendar-clase">', '</section>' ), $out[1]['innerContent'] );
	}
}
