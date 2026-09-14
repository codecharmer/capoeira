<?php
/**
 * pura/calendly — inline Calendly widget; loads the widget script only where used.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_setting' ) ) {
	return;
}

$group  = 'kids' === ( $attributes['group'] ?? 'adult' ) ? 'kids' : 'adult';
$height = max( 400, min( 1200, (int) ( $attributes['height'] ?? 700 ) ) );
$url    = (string) pura_setting( 'kids' === $group ? 'calendly_trial_kids_url' : 'calendly_trial_adult_url', '' );

if ( '' === $url ) {
	return;
}

wp_enqueue_script(
	'calendly-widget',
	'https://assets.calendly.com/assets/external/widget.js',
	array(),
	null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party, unversioned.
	array( 'strategy' => 'async', 'in_footer' => true )
);

$wrapper = get_block_wrapper_attributes( array( 'class' => 'calendly calendly--' . $group ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="text-muted" style="margin-bottom:0.75rem;"><strong style="color:var(--yellow);"><?php echo esc_html( 'kids' === $group ? __( 'Kids', 'pura' ) : __( 'Adultos', 'pura' ) ); ?></strong></p>
	<div class="calendly-inline-widget" data-url="<?php echo esc_url( $url ); ?>" style="min-width:320px;height:<?php echo (int) $height; ?>px;"></div>
	<p class="text-muted" style="margin-top:0.75rem;">
		<?php esc_html_e( 'Si el calendario no carga:', 'pura' ); ?>
		<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--yellow);"><?php esc_html_e( 'abrir agenda', 'pura' ); ?></a>.
	</p>
</div>
