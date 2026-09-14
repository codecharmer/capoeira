<?php
/**
 * Stripe-Signature verification.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Http\Stripe_Signature;

final class StripeSignatureTest extends TestCase {

	private const SECRET  = 'whsec_test_secret_123';
	private const PAYLOAD = '{"id":"evt_1","type":"checkout.session.completed"}';

	public function test_valid_signature_passes(): void {
		$now    = 1_700_000_000;
		$header = Stripe_Signature::sign( self::PAYLOAD, self::SECRET, $now );

		self::assertTrue( Stripe_Signature::verify( self::PAYLOAD, $header, self::SECRET, 300, $now + 10 ) );
	}

	public function test_expired_timestamp_fails(): void {
		$now    = 1_700_000_000;
		$header = Stripe_Signature::sign( self::PAYLOAD, self::SECRET, $now );

		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, $header, self::SECRET, 300, $now + 301 ) );
	}

	public function test_tampered_payload_fails(): void {
		$now    = 1_700_000_000;
		$header = Stripe_Signature::sign( self::PAYLOAD, self::SECRET, $now );

		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD . ' ', $header, self::SECRET, 300, $now ) );
	}

	public function test_wrong_secret_fails(): void {
		$now    = 1_700_000_000;
		$header = Stripe_Signature::sign( self::PAYLOAD, self::SECRET, $now );

		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, $header, 'whsec_other', 300, $now ) );
	}

	public function test_malformed_or_missing_header_fails(): void {
		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, '', self::SECRET ) );
		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, 't=123', self::SECRET ) );
		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, 'v1=abc', self::SECRET ) );
		self::assertFalse( Stripe_Signature::verify( self::PAYLOAD, 'garbage', '' ) );
	}

	public function test_any_matching_v1_candidate_passes(): void {
		$now    = 1_700_000_000;
		$good   = hash_hmac( 'sha256', $now . '.' . self::PAYLOAD, self::SECRET );
		$header = 't=' . $now . ',v1=deadbeef,v1=' . $good;

		self::assertTrue( Stripe_Signature::verify( self::PAYLOAD, $header, self::SECRET, 300, $now ) );
	}
}
