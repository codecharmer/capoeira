<?php
/**
 * Capability sets for the plugin's private post types.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	/**
	 * Full capability list for a post type registered with map_meta_cap => true.
	 *
	 * @return string[]
	 */
	public static function for_type( string $type ): array {
		$plural = $type . 's';

		return array(
			"edit_{$type}",
			"read_{$type}",
			"delete_{$type}",
			"edit_{$plural}",
			"edit_others_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
			"delete_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
			"create_{$plural}",
		);
	}
}
