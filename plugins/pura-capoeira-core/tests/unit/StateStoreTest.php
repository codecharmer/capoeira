<?php
/**
 * Single-use OAuth state records.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\State_Store;

final class StateStoreTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pura_test_transients'] = array();
	}

	public function test_create_returns_unguessable_token(): void {
		$a = State_Store::create( array( 'provider' => 'google' ) );
		$b = State_Store::create( array( 'provider' => 'google' ) );

		self::assertNotSame( $a, $b );
		self::assertGreaterThanOrEqual( 43, strlen( $a ) );
		self::assertMatchesRegularExpression( '/^[A-Za-z0-9_-]+$/', $a );
	}

	public function test_consume_returns_data_once(): void {
		$state = State_Store::create(
			array(
				'provider'    => 'apple',
				'nonce'       => 'n1',
				'redirect_to' => 'https://example.com/cuenta/',
			)
		);

		$data = State_Store::consume( $state );
		self::assertIsArray( $data );
		self::assertSame( 'apple', $data['provider'] );
		self::assertSame( 'n1', $data['nonce'] );
		self::assertArrayHasKey( 'created', $data );

		self::assertNull( State_Store::consume( $state ), 'second use must fail' );
	}

	public function test_unknown_and_malformed_states_fail(): void {
		self::assertNull( State_Store::consume( '' ) );
		self::assertNull( State_Store::consume( 'nope' ) );
		self::assertNull( State_Store::consume( str_repeat( 'a', 200 ) ) );
	}

	public function test_expired_state_fails(): void {
		$state = State_Store::create( array( 'provider' => 'google' ), 1 );

		// Rewind the stored expiry instead of sleeping.
		foreach ( $GLOBALS['pura_test_transients'] as $key => $row ) {
			$GLOBALS['pura_test_transients'][ $key ]['expires'] = time() - 5;
		}

		self::assertNull( State_Store::consume( $state ) );
	}
}
