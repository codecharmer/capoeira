<?php
/**
 * Title: Contacto — Datos, formulario y agenda
 * Slug: pura-capoeira/contacto
 * Categories: pura-capoeira
 * Description: Datos de contacto, formulario a WhatsApp y calendarios de clase de prueba.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"contact-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group contact-grid">
		<!-- wp:group {"className":"contact-info reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group contact-info reveal">
			<!-- wp:group {"className":"contact-item","layout":{"type":"default"}} -->
			<div class="wp-block-group contact-item">
				<!-- wp:paragraph {"className":"contact-item__label"} --><p class="contact-item__label">WhatsApp</p><!-- /wp:paragraph -->
				<!-- wp:group {"className":"contact-item__value","layout":{"type":"default"}} --><div class="wp-block-group contact-item__value"><!-- wp:pura/contact-link {"channel":"whatsapp","label":"","showValue":true} /--></div><!-- /wp:group -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"contact-item","layout":{"type":"default"}} -->
			<div class="wp-block-group contact-item">
				<!-- wp:paragraph {"className":"contact-item__label"} --><p class="contact-item__label">Instagram</p><!-- /wp:paragraph -->
				<!-- wp:group {"className":"contact-item__value","layout":{"type":"default"}} --><div class="wp-block-group contact-item__value"><!-- wp:pura/contact-link {"channel":"instagram","label":"","showValue":true} /--></div><!-- /wp:group -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"contact-item","layout":{"type":"default"}} -->
			<div class="wp-block-group contact-item">
				<!-- wp:paragraph {"className":"contact-item__label"} --><p class="contact-item__label">Facebook</p><!-- /wp:paragraph -->
				<!-- wp:group {"className":"contact-item__value","layout":{"type":"default"}} --><div class="wp-block-group contact-item__value"><!-- wp:pura/contact-link {"channel":"facebook","label":"","showValue":true} /--></div><!-- /wp:group -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"contact-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
			<div class="wp-block-group contact-actions">
				<!-- wp:pura/contact-link {"channel":"calendly_adults","label":"Agendar Adultos","variant":"btn-blue"} /-->
				<!-- wp:pura/contact-link {"channel":"calendly_kids","label":"Agendar Kids","variant":"btn-blue"} /-->
				<!-- wp:pura/contact-link {"channel":"whatsapp","label":"Enviar WhatsApp","variant":"btn-whatsapp"} /-->
				<!-- wp:pura/contact-link {"channel":"instagram","label":"Ver Instagram","variant":"btn-blue"} /-->
				<!-- wp:pura/contact-link {"channel":"facebook","label":"Ver Facebook","variant":"btn-secondary"} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group reveal">
			<!-- wp:heading {"style":{"spacing":{"margin":{"bottom":"1.5rem"}}}} --><h2 class="wp-block-heading" style="margin-bottom:1.5rem">Envíanos un mensaje</h2><!-- /wp:heading -->
			<!-- wp:paragraph {"className":"is-style-muted"} --><p class="is-style-muted">Completa el formulario y te abriremos WhatsApp con tu mensaje listo para enviar.</p><!-- /wp:paragraph -->
			<!-- wp:pura/contact-form /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","anchor":"agendar-clase","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section section--dark" id="agendar-clase">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Reserva en línea</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Agendar una clase</h2><!-- /wp:heading -->
		<!-- wp:paragraph {"className":"lead"} --><p class="lead">Selecciona tu grupo y reserva fecha/hora disponibles.</p><!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"scheduler-card reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group scheduler-card reveal">
		<!-- wp:pura/calendly {"group":"adult"} /-->
		<!-- wp:pura/calendly {"group":"kids"} /-->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
