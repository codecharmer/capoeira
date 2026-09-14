<?php
/**
 * Contract for the Printful client so the store can run against fixtures locally.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Http;

use WP_Error;

defined( 'ABSPATH' ) || exit;

interface Printful_Client_Interface {

	/**
	 * @return array<int, array<string, mixed>>|WP_Error Sync products.
	 */
	public function get_products();

	/**
	 * @return array<string, mixed>|WP_Error Sync product with `sync_product` and `sync_variants`.
	 */
	public function get_product( int $id );

	/**
	 * @param array<string, mixed>            $recipient Recipient.
	 * @param array<int, array<string, mixed>> $items     Items with variant_id + quantity.
	 * @return array<int, array<string, mixed>>|WP_Error Shipping rates.
	 */
	public function get_shipping_rates( array $recipient, array $items, string $currency, string $locale = 'es_ES' );

	/**
	 * @param array<string, mixed> $order Order payload (recipient, items, shipping, external_id…).
	 * @return array<string, mixed>|WP_Error Created draft order.
	 */
	public function create_draft_order( array $order );

	/**
	 * @return array<string, mixed>|WP_Error Confirmed order.
	 */
	public function confirm_order( int $order_id );
}
