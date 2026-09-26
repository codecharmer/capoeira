<?php
/**
 * Pricing must reproduce the legacy checkout.php amounts exactly.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Pricing;

final class PricingTest extends TestCase {

	protected function setUp(): void {
		pura_test_settings();
	}

	/**
	 * @return array<string, array{string, string, array<string, int>}>
	 */
	public static function legacy_amounts(): array {
		return array(
			'adult base'    => array( 'adult', Pricing::PROMO_NONE, array( 'trial' => 20000, 'inscription' => 150000, 'inscription_month' => 265000, 'month' => 115000, 'quarter' => 293250, 'year' => 1104000 ) ),
			'kids base'     => array( 'kids', Pricing::PROMO_NONE, array( 'trial' => 20000, 'inscription' => 150000, 'inscription_month' => 250000, 'month' => 100000, 'quarter' => 255000, 'year' => 960000 ) ),
			'adult current' => array( 'adult', Pricing::PROMO_CURRENT, array( 'trial' => 20000, 'inscription' => 150000, 'inscription_month' => 225000, 'month' => 75000, 'quarter' => 191250, 'year' => 720000 ) ),
			'kids current'  => array( 'kids', Pricing::PROMO_CURRENT, array( 'trial' => 20000, 'inscription' => 150000, 'inscription_month' => 250000, 'month' => 100000, 'quarter' => 255000, 'year' => 960000 ) ),
		);
	}

	/**
	 * @dataProvider legacy_amounts
	 * @param array<string, int> $expected Expected cents by plan id.
	 */
	public function test_plans_match_legacy_amounts( string $group, string $promo, array $expected ): void {
		$actual = array();
		foreach ( Pricing::plans( $group, $promo ) as $plan ) {
			$actual[ $plan['id'] ] = $plan['amount'];
		}
		ksort( $actual );
		ksort( $expected );

		self::assertSame( $expected, $actual );
		self::assertSame( array( 'trial', 'inscription_month', 'inscription', 'month', 'quarter', 'year' ), array_column( Pricing::plans( $group, $promo ), 'id' ) );
	}

	public function test_addon_allowed_only_on_recurring_plans(): void {
		$allowed = array();
		foreach ( Pricing::plans( 'adult' ) as $plan ) {
			if ( $plan['allow_addon'] ) {
				$allowed[] = $plan['id'];
			}
		}

		self::assertSame( array( 'month', 'quarter', 'year' ), $allowed );
		self::assertSame( 150000, Pricing::addon_cents() );
	}

	public function test_promo_resolution_is_case_insensitive(): void {
		self::assertSame( Pricing::PROMO_BECA, Pricing::resolve_promo( 'becadopc26', 'adult' )['type'] );
		self::assertSame( Pricing::PROMO_CURRENT, Pricing::resolve_promo( 'ActualPC26', 'kids' )['type'] );
		self::assertSame( Pricing::PROMO_NONE, Pricing::resolve_promo( 'PCPC2026', 'adult' )['type'] );
		self::assertSame( Pricing::PROMO_NONE, Pricing::resolve_promo( '', 'adult' )['type'] );
	}

	public function test_beca_is_free_and_current_is_payment_optional(): void {
		$beca = Pricing::resolve_promo( 'BECADOPC26', 'adult' );
		self::assertTrue( $beca['free'] );
		self::assertTrue( $beca['payment_optional'] );

		$current = Pricing::resolve_promo( 'ACTUALPC26', 'adult' );
		self::assertFalse( $current['free'] );
		self::assertTrue( $current['payment_optional'] );

		$none = Pricing::resolve_promo( 'nope', 'adult' );
		self::assertFalse( $none['free'] );
		self::assertFalse( $none['payment_optional'] );
	}

	public function test_empty_codes_in_settings_never_match(): void {
		pura_test_settings( array( 'promo_beca_code' => '', 'promo_current_code' => '' ) );

		self::assertSame( Pricing::PROMO_NONE, Pricing::resolve_promo( '', 'adult' )['type'] );
		self::assertSame( Pricing::PROMO_NONE, Pricing::resolve_promo( 'BECADOPC26', 'adult' )['type'] );
	}

	public function test_settings_drive_prices(): void {
		pura_test_settings( array( 'monthly_adult' => 120000, 'quarter_discount_pct' => 10 ) );

		self::assertSame( 324000, Pricing::plan( 'quarter', 'adult' )['amount'] );
		self::assertSame( 270000, Pricing::plan( 'inscription_month', 'adult' )['amount'] );
	}

	public function test_pesos_conversion_and_formatting(): void {
		self::assertSame( 2932.5, Pricing::to_pesos( 293250 ) );
		self::assertSame( '$2,932.50', Pricing::format_pesos( 293250 ) );
		self::assertSame( '$1,150', Pricing::format_pesos( 115000 ) );
		self::assertSame( 'Inscripción ($1,500) + primera mensualidad ($1,150).', Pricing::plan( 'inscription_month', 'adult' )['note'] );
	}
}
