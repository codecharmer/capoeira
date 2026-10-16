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
