<?php
/**
 * pura/price — one formatted price from the plugin's pricing.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_inscription_plans' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar este bloque.', 'pura' ) . '</p>';
	}
	return;
}

$key    = (string) ( $attributes['key'] ?? 'month' );
$group  = 'kids' === ( $attributes['group'] ?? 'adult' ) ? 'kids' : 'adult';
$suffix = (string) ( $attributes['suffix'] ?? '' );
$tag    = in_array( $attributes['tagName'] ?? 'div', array( 'div', 'span', 'p' ), true ) ? (string) $attributes['tagName'] : 'div';

$amount = null;
foreach ( pura_inscription_plans( $group ) as $plan ) {
	if ( $plan['id'] === $key ) {
		$amount = (float) $plan['amount'];
		break;
	}
}

if ( null === $amount ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'price' ) );
?>
<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above. ?> <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( pura_format_pesos( $amount ) ); ?><?php if ( '' !== $suffix ) : ?> <span class="price__suffix"><?php echo esc_html( $suffix ); ?></span><?php endif; ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
