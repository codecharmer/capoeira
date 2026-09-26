<?php
/**
 * Block style variations that map to the site's existing component classes.
 *
 * The CSS for each style lives in assets/css/theme.css (see the "Block styles" section).
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

function pura_theme_register_block_styles(): void {
	$styles = array(
		'core/button'    => array(
			'secondary' => __( 'Secundario', 'pura' ),
			'whatsapp'  => __( 'WhatsApp', 'pura' ),
			'blue'      => __( 'Azul', 'pura' ),
			'ghost'     => __( 'Enlace', 'pura' ),
		),
		'core/group'     => array(
			'dark-section' => __( 'Sección oscura', 'pura' ),
			'info-card'    => __( 'Tarjeta', 'pura' ),
			'feature'      => __( 'Característica', 'pura' ),
			'location'     => __( 'Ubicación', 'pura' ),
		),
		'core/paragraph' => array(
			'overline' => __( 'Antetítulo', 'pura' ),
			'lead'     => __( 'Entrada', 'pura' ),
			'muted'    => __( 'Apagado', 'pura' ),
		),
	);

	foreach ( $styles as $block => $variations ) {
		foreach ( $variations as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'pura_theme_register_block_styles' );
