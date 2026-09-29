<?php
/**
 * pura/contact-link — server render.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_setting' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar este bloque.', 'pura' ) . '</p>';
	}
	return;
}

$channel    = (string) ( $attributes['channel'] ?? 'whatsapp' );
$label      = (string) ( $attributes['label'] ?? '' );
$show_value = ! empty( $attributes['showValue'] );
$variant    = (string) ( $attributes['variant'] ?? 'link' );
$message    = (string) ( $attributes['message'] ?? '' );

$href  = '';
$value = '';
switch ( $channel ) {
	case 'whatsapp':
		$href  = pura_whatsapp_url( $message );
		$value = (string) pura_setting( 'whatsapp_display', '' );
		break;
	case 'instagram':
		$href  = (string) pura_setting( 'instagram_url', '' );
		$value = (string) pura_setting( 'instagram_handle', '' );
		break;
	case 'facebook':
		$href  = (string) pura_setting( 'facebook_url', '' );
		$value = (string) pura_setting( 'facebook_label', '' );
		break;
	case 'calendly_adults':
		$href = (string) pura_setting( 'calendly_trial_adult_url', '' );
		break;
	case 'calendly_kids':
		$href = (string) pura_setting( 'calendly_trial_kids_url', '' );
		break;
}

if ( '' === $href ) {
	return;
}

$class_map = array(
	'btn-primary'   => 'btn btn--primary',
	'btn-secondary' => 'btn btn--secondary',
	'btn-whatsapp'  => 'btn btn--whatsapp',
	'btn-blue'      => 'btn btn--blue',
	'btn-ghost'     => 'btn btn--ghost',
);
$classes   = $class_map[ $variant ] ?? 'contact-link';

$text = $label;
if ( $show_value && '' !== $value ) {
	$text = '' !== $label ? $label . ': ' . $value : $value;
}
if ( '' === $text ) {
	$text = $href;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => $classes ) );
?>
<a <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $text ); ?></a>
