<?php
/**
 * /pura/v1/store/* — Printful catalog, shipping quotes, and Stripe Checkout for the store.
 *
 * Prices are never taken from the client: every sync variant's retail price is looked up in the
 * cached Printful detail, and the shipping rate is re-quoted and matched by id.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Http\Printful_Client;
use Pura\Core\Http\Printful_Client_Interface;
use Pura\Core\Http\Stripe_Client;
use Pura\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Store_Controller extends Base_Controller {

	private const TRANSIENT_PRODUCTS = 'pura_printful_products';
	private const TRANSIENT_PRODUCT  = 'pura_printful_product_';
	private const TRANSIENT_VARIANTS = 'pura_printful_variant_index';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/store/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/store/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'products' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'refresh' => array( 'type' => 'boolean', 'default' => false ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/store/products/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'product' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/store/shipping',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'shipping' ),
				'permission_callback' => $this->public_permission( 'shipping', 20 ),
				'args'                => array(
					'recipient' => $this->recipient_arg(),
					'items'     => $this->items_arg( 'variant_id' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/store/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => $this->public_permission( 'checkout', 10 ),
				'args'                => array(
					'recipient'     => $this->recipient_arg(),
					'items'         => $this->items_arg( 'sync_variant_id' ),
					'shipping_id'   => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'shipping_name' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					'shipping_rate' => array( 'type' => array( 'number', 'string' ), 'default' => 0 ),
					'website'       => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	public function config(): WP_REST_Response {
		return $this->ok(
			array(
				'currency'         => (string) Settings::get( 'currency', 'MXN' ),
				'price_multiplier' => $this->multiplier(),
			)
		);
	}

	public function products( WP_REST_Request $request ): WP_REST_Response {
		$products = $this->cached_products( (bool) $request->get_param( 'refresh' ) );
		if ( is_wp_error( $products ) ) {
			return $this->from_error( $products );
		}

		return $this->ok( array( 'products' => $products ) );
	}

	public function product( WP_REST_Request $request ): WP_REST_Response {
		$product = $this->cached_product( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $product ) ) {
			return $this->from_error( $product );
		}

		return $this->ok( array( 'product' => $product ) );
	}

	public function shipping( WP_REST_Request $request ): WP_REST_Response {
		$recipient = $this->sanitize_recipient( (array) $request->get_param( 'recipient' ) );
		$items     = $this->sanitize_items( (array) $request->get_param( 'items' ), 'variant_id' );
		if ( ! $items ) {
			return $this->error( 'El carrito está vacío.', 400 );
		}

		$currency = (string) Settings::get( 'currency', 'MXN' );
		$rates    = $this->client()->get_shipping_rates( $recipient, $items, $currency );
		if ( is_wp_error( $rates ) ) {
			return $this->from_error( $rates );
		}

		return $this->ok( array( 'currency' => $currency, 'rates' => array_values( (array) $rates ) ) );
	}

	public function checkout( WP_REST_Request $request ): WP_REST_Response {
		$recipient   = $this->sanitize_recipient( (array) $request->get_param( 'recipient' ) );
		$items       = $this->sanitize_items( (array) $request->get_param( 'items' ), 'sync_variant_id' );
		$shipping_id = (string) $request->get_param( 'shipping_id' );
		$currency    = (string) Settings::get( 'currency', 'MXN' );
		$multiplier  = $this->multiplier();

		if ( ! $items ) {
			return $this->error( 'El carrito está vacío.', 400 );
		}
		foreach ( array( 'name', 'email', 'address1', 'city', 'country_code', 'zip' ) as $field ) {
			if ( '' === $recipient[ $field ] ) {
				return $this->error( 'Completa los datos de envío.', 400 );
			}
		}
		if ( ! is_email( $recipient['email'] ) ) {
			return $this->error( 'Escribe un correo electrónico válido.', 400 );
		}

		$stripe = new Stripe_Client();
		if ( ! $stripe->is_configured() ) {
			return $this->error( 'Los pagos en línea no están disponibles por el momento.', 503 );
		}

		// Server-side prices for every variant.
		$line_items    = array();
		$printful_items = array();
		$rate_items    = array();
		foreach ( $items as $item ) {
			$variant = $this->find_variant( (int) $item['sync_variant_id'] );
			if ( is_wp_error( $variant ) ) {
				return $this->from_error( $variant );
			}
			$unit_cents = (int) round( (float) $variant['retail_price'] * $multiplier * 100 );

			$line_items[] = array(
				'quantity'   => (int) $item['quantity'],
				'price_data' => array(
					'currency'     => strtolower( $currency ),
					'unit_amount'  => $unit_cents,
					'product_data' => array( 'name' => $variant['name'] ),
				),
			);
			$printful_items[] = array(
				'sync_variant_id' => (int) $item['sync_variant_id'],
				'quantity'        => (int) $item['quantity'],
				'retail_price'    => number_format( (float) $variant['retail_price'] * $multiplier, 2, '.', '' ),
			);
			$rate_items[] = array(
				'variant_id' => (int) $variant['variant_id'],
				'quantity'   => (int) $item['quantity'],
			);
		}

		// Re-quote shipping and match the chosen rate by id; never trust the client's amount.
		$rates = $this->client()->get_shipping_rates( $recipient, $rate_items, $currency );
		if ( is_wp_error( $rates ) ) {
			return $this->from_error( $rates );
		}
		$rate = null;
		foreach ( (array) $rates as $candidate ) {
			if ( is_array( $candidate ) && (string) ( $candidate['id'] ?? '' ) === $shipping_id ) {
				$rate = $candidate;
				break;
			}
		}
		if ( ! $rate ) {
			return $this->error( 'El método de envío ya no está disponible. Calcula el envío de nuevo.', 400 );
		}
		$shipping_cents = (int) round( (float) ( $rate['rate'] ?? 0 ) * 100 );
		$shipping_name  = (string) ( $rate['name'] ?? 'Envío' );
		if ( $shipping_cents > 0 ) {
			$line_items[] = array(
				'quantity'   => 1,
				'price_data' => array(
					'currency'     => strtolower( $currency ),
					'unit_amount'  => $shipping_cents,
					'product_data' => array( 'name' => 'Envío: ' . $shipping_name ),
				),
			);
		}

		// Printful draft order (confirmed by the webhook after payment).
		$external_id = 'capoeira-' . wp_generate_password( 12, false, false );
		$draft       = $this->client()->create_draft_order(
			array(
				'external_id' => $external_id,
				'recipient'   => $recipient,
				'items'       => $printful_items,
				'shipping'    => $shipping_id,
				'retail_costs' => array( 'currency' => $currency, 'shipping' => number_format( $shipping_cents / 100, 2, '.', '' ) ),
			)
		);
		if ( is_wp_error( $draft ) ) {
			return $this->from_error( $draft );
		}
		$printful_order_id = (int) ( $draft['id'] ?? 0 );
		if ( ! $printful_order_id ) {
			return $this->error( 'Printful no devolvió un número de orden.', 502 );
		}

		$page_url = Settings::page_url( 'store' );
		$sep      = str_contains( $page_url, '?' ) ? '&' : '?';

		$session = $stripe->create_checkout_session(
			array(
				'mode'                 => 'payment',
				'success_url'          => $page_url . $sep . 'checkout=success&session_id={CHECKOUT_SESSION_ID}',
				'cancel_url'           => $page_url . $sep . 'checkout=cancel',
				'customer_email'       => $recipient['email'],
				'client_reference_id'  => (string) $printful_order_id,
				'payment_method_types' => array( 'card' ),
				'line_items'           => $line_items,
				'metadata'             => array(
					'type'              => 'store',
					'printful_order_id' => (string) $printful_order_id,
					'external_id'       => $external_id,
				),
			),
			'store-' . $printful_order_id
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			return $this->error( is_wp_error( $session ) ? $session->get_error_message() : 'No se pudo iniciar el pago.', 502 );
		}

		return $this->ok( array( 'url' => (string) $session['url'], 'printful_order_id' => $printful_order_id ) );
	}

	// ---- Catalog cache -------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function cached_products( bool $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT_PRODUCTS );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$products = $this->client()->get_products();
		if ( is_wp_error( $products ) ) {
			return $products;
		}
		$products = array_values( (array) $products );
		set_transient( self::TRANSIENT_PRODUCTS, $products, $this->ttl() );

		return $products;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function cached_product( int $id ) {
		$cached = get_transient( self::TRANSIENT_PRODUCT . $id );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$product = $this->client()->get_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$product = (array) $product;
		set_transient( self::TRANSIENT_PRODUCT . $id, $product, $this->ttl() );
		$this->index_variants( $id, $product );

		return $product;
	}

	/**
	 * Remember sync_variant_id → price/name/catalog variant for server-side pricing.
	 *
	 * @param array<string, mixed> $product Product detail.
	 */
	private function index_variants( int $product_id, array $product ): void {
		$index = get_transient( self::TRANSIENT_VARIANTS );
		$index = is_array( $index ) ? $index : array();
		$title = (string) ( $product['sync_product']['name'] ?? 'Producto' );

		foreach ( (array) ( $product['sync_variants'] ?? array() ) as $variant ) {
			if ( ! is_array( $variant ) || empty( $variant['id'] ) ) {
				continue;
			}
			$index[ (int) $variant['id'] ] = array(
				'product_id'   => $product_id,
				'variant_id'   => (int) ( $variant['variant_id'] ?? 0 ),
				'retail_price' => (string) ( $variant['retail_price'] ?? $variant['price'] ?? '0' ),
				'name'         => $title . ' — ' . (string) ( $variant['name'] ?? '' ),
			);
		}

		set_transient( self::TRANSIENT_VARIANTS, $index, DAY_IN_SECONDS );
	}

	/**
	 * @return array{product_id:int,variant_id:int,retail_price:string,name:string}|WP_Error
	 */
	private function find_variant( int $sync_variant_id ) {
		$index = get_transient( self::TRANSIENT_VARIANTS );
		if ( is_array( $index ) && isset( $index[ $sync_variant_id ] ) ) {
			return $index[ $sync_variant_id ];
		}

		// Cold cache: walk the catalog until the variant shows up.
		$products = $this->cached_products();
		if ( is_wp_error( $products ) ) {
			return $products;
		}
		foreach ( $products as $product ) {
			$id = (int) ( $product['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$detail = $this->cached_product( $id );
			if ( is_wp_error( $detail ) ) {
				continue;
			}
			$index = get_transient( self::TRANSIENT_VARIANTS );
			if ( is_array( $index ) && isset( $index[ $sync_variant_id ] ) ) {
				return $index[ $sync_variant_id ];
			}
		}

		return new WP_Error( 'pura_variant_not_found', 'Uno de los productos ya no está disponible.', array( 'status' => 400 ) );
	}

	// ---- Helpers -------------------------------------------------------------------------

	private function client(): Printful_Client_Interface {
		return Printful_Client::resolve();
	}

	private function ttl(): int {
		return max( 60, (int) Settings::get( 'catalog_cache_ttl', 300 ) );
	}

	private function multiplier(): float {
		$m = (float) Settings::get( 'price_multiplier', 1.0 );

		return $m > 0 ? $m : 1.0;
	}

	private function from_error( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 502;

		return $this->error( $error->get_error_message(), $status );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function recipient_arg(): array {
		return array(
			'type'       => 'object',
			'required'   => true,
			'properties' => array(
				'name'         => array( 'type' => 'string' ),
				'email'        => array( 'type' => 'string' ),
				'address1'     => array( 'type' => 'string' ),
				'address2'     => array( 'type' => 'string' ),
				'city'         => array( 'type' => 'string' ),
				'state_code'   => array( 'type' => 'string' ),
				'country_code' => array( 'type' => 'string' ),
				'zip'          => array( 'type' => 'string' ),
				'phone'        => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function items_arg( string $id_key ): array {
		return array(
			'type'     => 'array',
			'required' => true,
			'minItems' => 1,
			'maxItems' => 50,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					$id_key    => array( 'type' => array( 'integer', 'string' ) ),
					'quantity' => array( 'type' => array( 'integer', 'string' ) ),
				),
			),
		);
	}

	/**
	 * @param array<string, mixed> $raw Recipient.
	 * @return array<string, string>
	 */
	private function sanitize_recipient( array $raw ): array {
		$out = array();
		foreach ( array( 'name', 'email', 'address1', 'address2', 'city', 'state_code', 'country_code', 'zip', 'phone' ) as $field ) {
			$value = isset( $raw[ $field ] ) ? sanitize_text_field( (string) $raw[ $field ] ) : '';
			if ( 'email' === $field ) {
				$value = sanitize_email( $value );
			}
			if ( in_array( $field, array( 'country_code', 'state_code' ), true ) ) {
				$value = strtoupper( substr( $value, 0, 'country_code' === $field ? 2 : 10 ) );
			}
			$out[ $field ] = mb_substr( $value, 0, 120 );
		}

		return $out;
	}

	/**
	 * @param array<int, mixed> $raw Items.
	 * @return array<int, array<string, int>>
	 */
	private function sanitize_items( array $raw, string $id_key ): array {
		$items = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id  = (int) ( $item[ $id_key ] ?? 0 );
			$qty = (int) ( $item['quantity'] ?? 0 );
			if ( $id <= 0 || $qty <= 0 ) {
				continue;
			}
			$items[] = array( $id_key => $id, 'quantity' => min( 20, $qty ) );
		}

		return $items;
	}
}
