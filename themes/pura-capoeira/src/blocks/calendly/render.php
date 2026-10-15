<?php
/**
 * pura/calendly — inline Calendly widget, loaded only after the visitor accepts third-party
 * content (consent banner) or clicks "Cargar calendario". Without JS the external link works.
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

$wrapper = get_block_wrapper_attributes( array( 'class' => 'calendly calendly--' . $group ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-calendly-root data-calendly-url="<?php echo esc_url( $url ); ?>" data-calendly-height="<?php echo (int) $height; ?>">
	<p class="text-muted" style="margin-bottom:0.75rem;"><strong style="color:var(--yellow);"><?php echo esc_html( 'kids' === $group ? __( 'Kids', 'pura' ) : __( 'Adultos', 'pura' ) ); ?></strong></p>
	<div class="calendly-embed" data-calendly-embed style="min-width:320px;min-height:<?php echo (int) min( $height, 320 ); ?>px;">
		<div class="calendly-placeholder" data-calendly-placeholder>
			<p><?php esc_html_e( 'Este calendario lo provee Calendly y usa cookies de terceros. Se carga solo si lo aceptas.', 'pura' ); ?></p>
			<div class="calendly-placeholder__actions">
				<button type="button" class="btn btn--primary" data-calendly-load><?php esc_html_e( 'Cargar calendario', 'pura' ); ?></button>
				<a class="btn btn--ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abrir en Calendly', 'pura' ); ?></a>
			</div>
		</div>
	</div>
	<p class="text-muted" style="margin-top:0.75rem;">
		<?php esc_html_e( 'Si el calendario no carga:', 'pura' ); ?>
		<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--yellow);"><?php esc_html_e( 'abrir agenda', 'pura' ); ?></a>.
	</p>
</div>
