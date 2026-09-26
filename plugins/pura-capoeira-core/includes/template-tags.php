<?php
/**
 * Global helpers for themes. Guard calls with function_exists() in theme code.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pura_settings' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function pura_settings(): array {
		return Pura\Core\Settings::all();
	}
}

if ( ! function_exists( 'pura_setting' ) ) {
	/**
	 * @param mixed $fallback Fallback value.
	 * @return mixed
	 */
	function pura_setting( string $key, $fallback = null ) {
		return Pura\Core\Settings::get( $key, $fallback );
	}
}

if ( ! function_exists( 'pura_inscription_plans' ) ) {
	/**
	 * Plans for display, amounts in pesos.
	 *
	 * @return array<int, array{id:string,label:string,note:string,amount:float,allow_addon:bool}>
	 */
	function pura_inscription_plans( string $group = 'adult' ): array {
		return Pura\Core\Pricing::plans_in_pesos( Pura\Core\Pricing::group( $group ) );
	}
}

if ( ! function_exists( 'pura_format_pesos' ) ) {
	/**
	 * "$1,150" or "$2,932.50" from a peso amount.
	 */
	function pura_format_pesos( float $pesos ): string {
		return Pura\Core\Pricing::format_pesos( (int) round( $pesos * 100 ) );
	}
}

if ( ! function_exists( 'pura_schedule' ) ) {
	/**
	 * Schedule rows, optionally filtered by group.
	 *
	 * @return array<int, array{group:string,day:string,start:string,end:string}>
	 */
	function pura_schedule( ?string $group = null ): array {
		$rows = Pura\Core\Settings::get( 'schedule', array() );
		$rows = is_array( $rows ) ? $rows : array();

		if ( null === $group ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static fn ( $row ) => is_array( $row ) && ( $row['group'] ?? '' ) === $group
			)
		);
	}
}

if ( ! function_exists( 'pura_whatsapp_url' ) ) {
	function pura_whatsapp_url( string $text = '' ): string {
		return Pura\Core\Settings::whatsapp_url( $text );
	}
}
