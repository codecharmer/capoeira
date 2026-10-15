<?php
/**
 * Registry of identity providers.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class Providers {

	public const IDS = array( 'google', 'apple' );

	/**
	 * @return array<string, Provider_Interface> Keyed by id.
	 */
	public static function all(): array {
		return array(
			'google' => new Google_Provider(),
			'apple'  => new Apple_Provider(),
		);
	}

	public static function get( string $id ): ?Provider_Interface {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * @return array<int, array{id:string,label:string,startUrl:string}> Configured providers, for the front end.
	 */
	public static function configured(): array {
		$out = array();
		foreach ( self::all() as $provider ) {
			if ( ! $provider->is_configured() ) {
				continue;
			}
			$out[] = array(
				'id'       => $provider->id(),
				'label'    => $provider->label(),
				'startUrl' => rest_url( 'pura/v1/auth/' . $provider->id() . '/start' ),
			);
		}

		return $out;
	}
}
