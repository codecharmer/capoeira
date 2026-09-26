<?php
/**
 * pura/store — catalog grid + cart aside; everything else happens in view.js.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_setting' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar la tienda.', 'pura' ) . '</p>';
	}
	return;
}

$pura_note        = (string) ( $attributes['note'] ?? '' );
$pura_footnote    = (string) ( $attributes['footnote'] ?? '' );
$pura_placeholder = esc_url( get_theme_file_uri( 'assets/images/gallery-placeholder.jpg' ) );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'store-layout' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-store-root data-placeholder="<?php echo $pura_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
	<div>
		<div class="store-toolbar reveal">
			<p class="store-note"><?php echo esc_html( $pura_note ); ?></p>
			<button class="btn btn--secondary" type="button" data-store-refresh><?php esc_html_e( 'Actualizar productos', 'pura' ); ?></button>
		</div>
		<div class="store-feedback" data-store-feedback role="status" aria-live="polite"><?php esc_html_e( 'Consultando catálogo...', 'pura' ); ?></div>
		<div class="store-grid" data-store-grid></div>
	</div>
	<aside class="store-cart reveal" data-store-cart>
		<h2><?php esc_html_e( 'Carrito', 'pura' ); ?></h2>
		<div class="store-cart-result" data-store-result hidden role="status"></div>
		<div class="store-cart__items" data-store-cart-items></div>
		<h3><?php esc_html_e( 'Datos de envío', 'pura' ); ?></h3>
		<form class="store-form" data-store-order-form>
			<input type="text" name="name" placeholder="<?php esc_attr_e( 'Nombre completo', 'pura' ); ?>" autocomplete="name" required />
			<input type="email" name="email" placeholder="Email" autocomplete="email" required />
			<input type="text" name="address1" placeholder="<?php esc_attr_e( 'Dirección', 'pura' ); ?>" autocomplete="street-address" required />
			<input type="text" name="city" placeholder="<?php esc_attr_e( 'Ciudad', 'pura' ); ?>" autocomplete="address-level2" required />
			<input type="text" name="state_code" placeholder="<?php esc_attr_e( 'Estado (ej. MOR)', 'pura' ); ?>" autocomplete="address-level1" required />
			<input type="text" name="country_code" placeholder="<?php esc_attr_e( 'País (ej. MX)', 'pura' ); ?>" value="MX" required maxlength="2" autocomplete="country" />
			<input type="text" name="zip" placeholder="<?php esc_attr_e( 'Código postal', 'pura' ); ?>" autocomplete="postal-code" required />
			<button class="btn btn--secondary" type="submit"><?php esc_html_e( 'Calcular envío', 'pura' ); ?></button>
		</form>
		<div class="store-shipping" data-store-shipping hidden>
			<h3><?php esc_html_e( 'Método de envío', 'pura' ); ?></h3>
			<div class="store-shipping__options" data-store-shipping-options></div>
		</div>
		<div class="store-cart__summary">
			<div class="store-cart__line"><span>Subtotal</span><strong data-store-subtotal>$0.00</strong></div>
			<div class="store-cart__line"><span><?php esc_html_e( 'Envío', 'pura' ); ?></span><strong data-store-shipping-cost>—</strong></div>
			<div class="store-cart__line store-cart__line--total"><span>Total</span><strong data-store-total>$0.00</strong></div>
		</div>
		<button class="btn btn--primary store-pay-btn" type="button" data-store-pay disabled><?php esc_html_e( 'Pagar con tarjeta', 'pura' ); ?></button>
		<p class="store-footnote"><?php echo esc_html( $pura_footnote ); ?></p>
	</aside>
</div>
