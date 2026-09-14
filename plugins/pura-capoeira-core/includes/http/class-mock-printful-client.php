<?php
/**
 * Fixture-backed Printful client for local development and tests (Printful has no sandbox).
 *
 * Fixtures live in tests/fixtures/printful/: products.json, product-{id}.json,
 * shipping-rates.json, order-draft.json. Capture real ones with `wp pura printful-fixtures`.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Http;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Mock_Printful_Client implements Printful_Client_Interface {

	private string $dir;

	public function __construct( ?string $dir = null ) {
		$this->dir = rtrim( $dir ?? PURA_CORE_DIR . 'tests/fixtures/printful', '/' );
	}

	public function get_products() {
		return $this->load( 'products.json' );
	}

	public function get_product( int $id ) {
		$specific = $this->load( 'product-' . $id . '.json', false );
		if ( ! is_wp_error( $specific ) ) {
			return $specific;
		}

		// Fall back to a generic product fixture with the requested id.
		$generic = $this->load( 'product.json' );
		if ( is_wp_error( $generic ) ) {
			return $generic;
		}
		$generic['sync_product']['id'] = $id;

		return $generic;
	}

	public function get_shipping_rates( array $recipient, array $items, string $currency, string $locale = 'es_ES' ) {
		return $this->load( 'shipping-rates.json' );
	}

	public function create_draft_order( array $order ) {
		$draft = $this->load( 'order-draft.json' );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}
		$draft['id']          = wp_rand( 100000, 999999 );
		$draft['external_id'] = $order['external_id'] ?? '';
		$draft['status']      = 'draft';

		return $draft;
	}

	public function confirm_order( int $order_id ) {
		return array( 'id' => $order_id, 'status' => 'pending', 'mock' => true );
	}

	/**
	 * @return mixed|WP_Error
	 */
	private function load( string $file, bool $required = true ) {
		$path = $this->dir . '/' . $file;
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'pura_printful_fixture', 'Fixture no encontrado: ' . $file, array( 'status' => 500 ) );
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local fixture.
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'pura_printful_fixture', 'Fixture inválido: ' . $file, array( 'status' => 500 ) );
		}

		return $decoded['result'] ?? $decoded;
	}
}
