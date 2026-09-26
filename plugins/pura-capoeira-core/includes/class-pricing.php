<?php
/**
 * Inscription pricing and promo resolution. Pure functions over Settings; all amounts in cents.
 *
 * Port of the legacy checkout.php rules (inscriptionPlans, inscriptionMonthlyAmount,
 * inscriptionAddonAmount, resolveInscriptionPromo).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Pricing {

	public const GROUPS = array( 'adult', 'kids' );

	public const PROMO_NONE    = 'none';
	public const PROMO_BECA    = 'beca';
	public const PROMO_CURRENT = 'current';

	/**
	 * @return string[]
	 */
	public static function plan_ids(): array {
		return array( 'trial', 'inscription_month', 'inscription', 'month', 'quarter', 'year' );
	}

	public static function group( ?string $group ): string {
		return in_array( $group, self::GROUPS, true ) ? $group : 'adult';
	}

	public static function inscription_fee_cents(): int {
		return max( 0, (int) Settings::get( 'inscription_fee', 150000 ) );
	}

	public static function trial_cents(): int {
		return max( 0, (int) Settings::get( 'trial_price', 20000 ) );
	}

	public static function addon_cents(): int {
		return self::inscription_fee_cents();
	}

	/**
	 * Monthly rate for a group, optionally under the current-student promo.
	 */
	public static function monthly_cents( string $group, string $promo_type = self::PROMO_NONE ): int {
		$group = self::group( $group );

		if ( self::PROMO_CURRENT === $promo_type ) {
			$key = 'kids' === $group ? 'current_monthly_kids' : 'current_monthly_adult';
		} else {
			$key = 'kids' === $group ? 'monthly_kids' : 'monthly_adult';
		}

		return max( 0, (int) Settings::get( $key, 0 ) );
	}

	/**
	 * All plans for a group with amounts in cents.
	 *
	 * @return array<int, array{id:string,label:string,note:string,amount:int,allow_addon:bool}>
	 */
	public static function plans( string $group, string $promo_type = self::PROMO_NONE ): array {
		$monthly     = self::monthly_cents( $group, $promo_type );
		$fee         = self::inscription_fee_cents();
		$quarter_pct = (float) Settings::get( 'quarter_discount_pct', 15 );
		$year_pct    = (float) Settings::get( 'year_discount_pct', 20 );

		return array(
			array(
				'id'          => 'trial',
				'label'       => 'Clase de Prueba',
				'note'        => 'Una clase para conocernos y para que descubras cómo se siente. Elige el día que vendrás.',
				'amount'      => self::trial_cents(),
				'allow_addon' => false,
			),
			array(
				'id'          => 'inscription_month',
				'label'       => 'Inscripción + primer mes',
				'note'        => sprintf(
					'Inscripción (%s) + primera mensualidad (%s).',
					self::format_pesos( $fee ),
					self::format_pesos( $monthly )
				),
				'amount'      => $fee + $monthly,
				'allow_addon' => false,
			),
			array(
				'id'          => 'inscription',
				'label'       => 'Solo inscripción',
				'note'        => 'Pago único de inscripción.',
				'amount'      => $fee,
				'allow_addon' => false,
			),
			array(
				'id'          => 'month',
				'label'       => 'Un mes',
				'note'        => 'Una mensualidad.',
				'amount'      => $monthly,
				'allow_addon' => true,
			),
			array(
				'id'          => 'quarter',
				'label'       => 'Paquete trimestral',
				'note'        => sprintf( '3 meses con %s%% de descuento.', self::format_pct( $quarter_pct ) ),
				'amount'      => (int) round( $monthly * 3 * ( 1 - $quarter_pct / 100 ) ),
				'allow_addon' => true,
			),
			array(
				'id'          => 'year',
				'label'       => 'Paquete anual',
				'note'        => sprintf( '12 meses con %s%% de descuento.', self::format_pct( $year_pct ) ),
				'amount'      => (int) round( $monthly * 12 * ( 1 - $year_pct / 100 ) ),
				'allow_addon' => true,
			),
		);
	}

	/**
	 * @return array{id:string,label:string,note:string,amount:int,allow_addon:bool}|null
	 */
	public static function plan( string $id, string $group, string $promo_type = self::PROMO_NONE ): ?array {
		foreach ( self::plans( $group, $promo_type ) as $plan ) {
			if ( $plan['id'] === $id ) {
				return $plan;
			}
		}

		return null;
	}

	/**
	 * @return array{type:string,free:bool,payment_optional:bool}
	 */
	public static function resolve_promo( ?string $code, string $group ): array {
		$code = trim( (string) $code );

		if ( '' !== $code ) {
			$beca    = trim( (string) Settings::get( 'promo_beca_code', '' ) );
			$current = trim( (string) Settings::get( 'promo_current_code', '' ) );

			if ( '' !== $beca && 0 === strcasecmp( $code, $beca ) ) {
				return array(
					'type'             => self::PROMO_BECA,
					'free'             => true,
					'payment_optional' => true,
				);
			}

			if ( '' !== $current && 0 === strcasecmp( $code, $current ) ) {
				return array(
					'type'             => self::PROMO_CURRENT,
					'free'             => false,
					'payment_optional' => true,
				);
			}
		}

		return array(
			'type'             => self::PROMO_NONE,
			'free'             => false,
			'payment_optional' => false,
		);
	}

	/**
	 * Plans as the REST/JS layer expects them: amounts in pesos (float), same order.
	 *
	 * @return array<int, array{id:string,label:string,note:string,amount:float,allow_addon:bool}>
	 */
	public static function plans_in_pesos( string $group, string $promo_type = self::PROMO_NONE ): array {
		return array_map(
			static function ( array $plan ): array {
				$plan['amount'] = self::to_pesos( $plan['amount'] );
				return $plan;
			},
			self::plans( $group, $promo_type )
		);
	}

	public static function to_pesos( int $cents ): float {
		return round( $cents / 100, 2 );
	}

	public static function format_pesos( int $cents ): string {
		$whole = 0 === $cents % 100;

		return '$' . number_format( $cents / 100, $whole ? 0 : 2, '.', ',' );
	}

	private static function format_pct( float $pct ): string {
		return floor( $pct ) === $pct ? (string) (int) $pct : rtrim( rtrim( number_format( $pct, 2, '.', '' ), '0' ), '.' );
	}
}
