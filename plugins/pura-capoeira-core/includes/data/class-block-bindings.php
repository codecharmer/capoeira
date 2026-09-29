<?php
/**
 * Block Bindings source `pura/setting`: bind core paragraph/heading content to a setting.
 *
 * Usage in block markup:
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pura/setting","args":{"key":"tagline"}}}}} -->
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

use Pura\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Block_Bindings {

	public const SOURCE = 'pura/setting';

	/** @var string[] Setting keys that may be bound. Everything else resolves to null. */
	public const ALLOWED_KEYS = array(
		'tagline',
		'copyright',
		'whatsapp_display',
		'instagram_handle',
		'facebook_label',
		'notify_from_name',
	);

	/**
	 * Keys that used to be bindable and now render as empty. Returning null instead would make
	 * pages saved before the change fall back to the address text still stored in their markup.
	 *
	 * @var string[]
	 */
	public const RETIRED_KEYS = array(
		'address',
		'address_short',
	);

	public function register(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Ajuste de Pura Capoeira', 'pura' ),
				'get_value_callback' => array( $this, 'get_value' ),
				'uses_context'       => array(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $source_args Binding args (expects `key`).
	 * @param \WP_Block            $block_instance Block.
	 * @param string               $attribute_name Bound attribute.
	 * @return string|null
	 */
	public function get_value( array $source_args, \WP_Block $block_instance, string $attribute_name ): ?string {
		$key = isset( $source_args['key'] ) ? sanitize_key( (string) $source_args['key'] ) : '';

		if ( in_array( $key, self::RETIRED_KEYS, true ) ) {
			return '';
		}

		if ( ! in_array( $key, self::ALLOWED_KEYS, true ) ) {
			return null;
		}

		if ( 'copyright' === $key ) {
			return sprintf( '© %s %s', gmdate( 'Y' ), get_bloginfo( 'name' ) );
		}

		return esc_html( (string) Settings::get( $key, '' ) );
	}
}
