<?php
/**
 * @package Pura\Core\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use Pura\Core\Data\Event_Registration_Repository;

final class EventRegistrationTest extends TestCase {

	private const OFFERED = array( '6 de noviembre', '7 de noviembre', '8 de noviembre' );

	public function test_days_keep_the_form_order_regardless_of_submission_order(): void {
		$days = Event_Registration_Repository::normalize_days( array( '8 de noviembre', '6 de noviembre' ), self::OFFERED );

		$this->assertSame( array( '6 de noviembre', '8 de noviembre' ), $days );
	}

	public function test_days_not_offered_by_the_form_are_dropped(): void {
		$days = Event_Registration_Repository::normalize_days( array( '9 de noviembre', '<b>7 de noviembre</b>', ' 7 de noviembre ' ), self::OFFERED );

		$this->assertSame( array( '7 de noviembre' ), $days );
	}

	public function test_a_single_string_and_an_empty_submission_are_handled(): void {
		$this->assertSame( array( '6 de noviembre' ), Event_Registration_Repository::normalize_days( '6 de noviembre', self::OFFERED ) );
		$this->assertSame( array(), Event_Registration_Repository::normalize_days( array(), self::OFFERED ) );
		$this->assertSame( array(), Event_Registration_Repository::normalize_days( null, self::OFFERED ) );
	}
}
