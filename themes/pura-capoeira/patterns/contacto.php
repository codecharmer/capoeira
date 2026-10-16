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
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Clase de prueba</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Agendar una clase</h2><!-- /wp:heading -->
		<!-- wp:paragraph {"className":"lead"} --><p class="lead">Elige el paquete «Clase de prueba» en Inscripciones, indica el día que vendrás y reserva tu lugar en línea.</p><!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"className":"reveal"} -->
	<div class="wp-block-buttons reveal">
		<!-- wp:button {"className":"is-style-blue"} --><div class="wp-block-button is-style-blue"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Agendar Adultos</a></div><!-- /wp:button -->
		<!-- wp:button {"className":"is-style-blue"} --><div class="wp-block-button is-style-blue"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Agendar Kids</a></div><!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
