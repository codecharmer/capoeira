<?php
/**
 * Title: Clases — Costos
 * Slug: pura-capoeira/costs
 * Categories: pura-capoeira
 * Description: Mensualidad e inscripción con botón a inscripciones.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section section--dark">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Inversión</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Costos</h2><!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"info-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group info-grid">
		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Mensualidad</p><!-- /wp:paragraph -->
			<!-- wp:pura/price {"key":"month","group":"adult","suffix":"MXN / mes","className":"info-card__price"} /-->
			<!-- wp:paragraph {"className":"info-card__sub"} --><p class="info-card__sub">Acceso a las clases regulares según el grupo (Adultos o Kids).</p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Inscripción / Uniforme</p><!-- /wp:paragraph -->
			<!-- wp:pura/price {"key":"inscription","group":"adult","suffix":"MXN","className":"info-card__price"} /-->
			<!-- wp:paragraph {"className":"info-card__sub"} --><p class="info-card__sub">La inscripción incluye el proceso inicial de integración y uniforme de entrenamiento.</p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons {"className":"reveal","layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
	<div class="wp-block-buttons reveal" style="margin-top:2rem">
		<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Inscríbete en línea</a></div><!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
