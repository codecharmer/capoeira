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

	public function test_text_without_the_address_is_untouched(): void {
		$in = 'Capoeira para adultos y niños en Tlaltenango, Cuernavaca.';

		$this->assertSame( $in, Content_Migrations::strip_street_address( $in ) );
		$this->assertStringNotContainsString( 'Jerónimo', Content_Migrations::strip_street_address( $in ) );
	}
}
