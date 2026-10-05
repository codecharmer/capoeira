<?php
/**
 * Top-level "Pura Capoeira" admin menu. Post types attach themselves via show_in_menu => 'pura'.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG = 'pura';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Pura Capoeira', 'pura' ),
			__( 'Pura Capoeira', 'pura' ),
			'manage_options',
			self::SLUG,
			array( Settings_Page::class, 'render' ),
			'dashicons-universal-access',
			25
		);

		// Rename the auto-created first submenu (which mirrors the top-level page).
		add_submenu_page(
			self::SLUG,
			__( 'Ajustes', 'pura' ),
			__( 'Ajustes', 'pura' ),
			'manage_options',
			self::SLUG,
			array( Settings_Page::class, 'render' ),
			99
		);
	}

	public function admin_styles(): void {
		$css = '
		.pura-status { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:#e5e5e5; }
		.pura-status--paid { background:#d1f5d3; color:#0b5d1e; }
		.pura-status--free { background:#dbeafe; color:#1e3a8a; }
		.pura-status--pending_payment, .pura-status--pending_payment_offline { background:#fef3c7; color:#92400e; }
		.pura-status--expired { background:#fee2e2; color:#991b1b; }
		.pura-status--registered { background:#dbeafe; color:#1e3a8a; }
		.pura-status--confirmed { background:#d1f5d3; color:#0b5d1e; }
		.pura-status--cancelled { background:#fee2e2; color:#991b1b; }
		.pura-settings .form-table th { width: 260px; }
		.pura-settings input[type=text], .pura-settings input[type=url], .pura-settings input[type=email], .pura-settings input[type=password] { width: 28em; max-width: 100%; }
		.pura-settings .pura-locked { opacity:.7 }
		';
		wp_add_inline_style( 'wp-admin', $css );
	}
}
