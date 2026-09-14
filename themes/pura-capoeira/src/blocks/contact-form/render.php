<?php
/**
 * pura/contact-form — builds a wa.me link from the fields; nothing is stored.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

$whatsapp = function_exists( 'pura_setting' ) ? preg_replace( '/\D+/', '', (string) pura_setting( 'whatsapp_number', '' ) ) : '';
$intro    = (string) ( $attributes['intro'] ?? '' );
$submit   = (string) ( $attributes['submitLabel'] ?? 'Enviar mensaje por WhatsApp' );
$uid      = wp_unique_id( 'pura-contact-' );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'contact-form-block' ) );
?>
<form <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="contact-form" data-js="contact-form" data-whatsapp="<?php echo esc_attr( $whatsapp ); ?>" data-intro="<?php echo esc_attr( $intro ); ?>" autocomplete="on">
	<div class="form-field">
		<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Nombre', 'pura' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-name" name="name" type="text" required placeholder="<?php esc_attr_e( 'Tu nombre', 'pura' ); ?>" />
	</div>
	<div class="form-field">
		<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Teléfono o WhatsApp', 'pura' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" type="tel" placeholder="<?php esc_attr_e( 'Ej. 777 123 4567', 'pura' ); ?>" />
	</div>
	<div class="form-field">
		<label for="<?php echo esc_attr( $uid ); ?>-message"><?php esc_html_e( 'Mensaje', 'pura' ); ?></label>
		<textarea id="<?php echo esc_attr( $uid ); ?>-message" name="message" required placeholder="<?php esc_attr_e( 'Cuéntanos tu interés...', 'pura' ); ?>"></textarea>
	</div>
	<button type="submit" class="btn btn--whatsapp"><?php echo esc_html( $submit ); ?></button>
</form>
