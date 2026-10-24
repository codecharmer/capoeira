<?php
/**
 * @package Pura\Core\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use Pura\Core\Mailer;

final class TrialConfirmationTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function vars(): array {
		return array(
			'first_name'   => 'Ana',
			'date'         => 'martes 3 de noviembre de 2026',
			'time'         => '17:30 a 19:30',
			'schedule'     => array( 'Martes 17:30 a 19:30', 'Jueves 17:30 a 19:30' ),
			'location'     => "Academia Pura Capoeira\nSan Jerónimo 503, Tlaltenango\n62170 Cuernavaca, Morelos",
			'maps_url'     => 'https://maps.example/academia',
			'instructions' => "Llega 10 minutos antes.\n\nTrae agua.",
			'whatsapp'     => '+1 (805) 638-5603',
			'whatsapp_url' => 'https://wa.me/18056385603',
			'site_name'    => 'Pura Capoeira Cuernavaca',
		);
	}

	public function test_body_greets_by_name_and_carries_date_place_and_instructions(): void {
		$body = Mailer::build_trial_body( $this->vars() );

		$this->assertStringStartsWith( "¡Hola, Ana!\n", $body );
		$this->assertStringContainsString( 'Fecha: Martes 3 de noviembre de 2026, 17:30 a 19:30', $body );
		$this->assertStringContainsString( "DÓNDE\nAcademia Pura Capoeira\nSan Jerónimo 503, Tlaltenango\n62170 Cuernavaca, Morelos\nMapa: https://maps.example/academia", $body );
		$this->assertStringContainsString( "ANTES DE VENIR\n  · Llega 10 minutos antes.\n  · Trae agua.", $body );
		$this->assertStringContainsString( 'WhatsApp al +1 (805) 638-5603 (https://wa.me/18056385603)', $body );
		$this->assertStringEndsWith( "Nos vemos en la roda.\nPura Capoeira Cuernavaca\n", $body );
	}

	public function test_schedule_is_listed_only_when_the_date_has_no_class_time(): void {
		$with_time = Mailer::build_trial_body( $this->vars() );
		$this->assertStringNotContainsString( 'Horario de clases', $with_time );

		$without = Mailer::build_trial_body( array_merge( $this->vars(), array( 'time' => '' ) ) );
		$this->assertStringContainsString( "Fecha: Martes 3 de noviembre de 2026\nHorario de clases:\n  · Martes 17:30 a 19:30\n  · Jueves 17:30 a 19:30", $without );
	}

	public function test_empty_optional_parts_are_left_out(): void {
		$body = Mailer::build_trial_body(
			array(
				'first_name' => '',
				'date'       => '2026-11-03',
				'site_name'  => 'Pura Capoeira',
			)
		);

		$this->assertStringStartsWith( "¡Hola!\n", $body );
		$this->assertStringNotContainsString( 'DÓNDE', $body );
		$this->assertStringNotContainsString( 'ANTES DE VENIR', $body );
		$this->assertStringNotContainsString( 'WhatsApp', $body );
		$this->assertStringContainsString( 'Fecha: 2026-11-03', $body );
	}
}
