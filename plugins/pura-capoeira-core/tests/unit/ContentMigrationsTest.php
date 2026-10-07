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
}
