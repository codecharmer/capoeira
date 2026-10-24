<?php
/**
 * Title: Página — Gracias (clase de prueba)
 * Slug: pura-capoeira/page-gracias-clase-de-prueba
 * Categories: pura-capoeira
 * Block Types: core/post-content
 * Post Types: page
 * Description: Confirmación tras reservar una clase de prueba: avisa que la ubicación y las indicaciones llegan por correo.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"page-header","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull page-header">
	<!-- wp:paragraph {"className":"overline"} --><p class="overline">Clase de prueba</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">¡Gracias! Tu lugar<br><span class="text-yellow">está reservado</span></h1><!-- /wp:heading -->
	<!-- wp:paragraph {"className":"lead"} --><p class="lead">Te enviamos un correo con la fecha de tu clase, la ubicación de la academia y lo que necesitas traer. Si no lo ves en unos minutos, revisa la carpeta de spam o promociones.</p><!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"thanks-card reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group thanks-card reveal">
		<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Qué sigue</h2><!-- /wp:heading -->
		<!-- wp:list {"className":"thanks-steps"} -->
		<ul class="wp-block-list thanks-steps">
			<!-- wp:list-item --><li>Guarda el correo: ahí están la dirección exacta y el mapa.</li><!-- /wp:list-item -->
			<!-- wp:list-item --><li>Llega diez minutos antes con ropa cómoda y agua.</li><!-- /wp:list-item -->
			<!-- wp:list-item --><li>Si necesitas cambiar la fecha, escríbenos por WhatsApp.</li><!-- /wp:list-item -->
		</ul>
		<!-- /wp:list -->
		<!-- wp:group {"className":"thanks-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-group thanks-actions">
			<!-- wp:pura/contact-link {"channel":"whatsapp","label":"Escribir por WhatsApp","variant":"btn-whatsapp","message":"Hola, acabo de reservar una clase de prueba y tengo una duda."} /-->
			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-secondary"} -->
				<div class="wp-block-button is-style-secondary"><a class="wp-block-button__link wp-element-button" href="/clases/">Conoce las clases</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
