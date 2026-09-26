<?php
/**
 * Title: Horarios y costos
 * Slug: pura-capoeira/home-schedule-prices
 * Categories: pura-capoeira
 * Description: Horarios de adultos y niños junto a los costos, tomados de los ajustes.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section section--dark">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Información</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Horarios y costos</h2><!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"info-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group info-grid">
		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Horarios</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Adultos</h3><!-- /wp:heading -->
			<!-- wp:pura/schedule {"group":"adult"} /-->
			<!-- wp:heading {"level":3,"className":"info-card__title","style":{"spacing":{"margin":{"top":"2rem"}}}} --><h3 class="wp-block-heading info-card__title" style="margin-top:2rem">Kids</h3><!-- /wp:heading -->
			<!-- wp:pura/schedule {"group":"kids"} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Costos</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Mensualidad</h3><!-- /wp:heading -->
			<!-- wp:pura/price {"key":"month","group":"adult","suffix":"MXN / mes","className":"info-card__price"} /-->
			<!-- wp:heading {"level":3,"className":"info-card__title","style":{"spacing":{"margin":{"top":"2rem"}}}} --><h3 class="wp-block-heading info-card__title" style="margin-top:2rem">Inscripción / Uniforme</h3><!-- /wp:heading -->
			<!-- wp:pura/price {"key":"inscription","group":"adult","suffix":"MXN","className":"info-card__price"} /-->
			<!-- wp:paragraph {"className":"info-card__sub","style":{"spacing":{"margin":{"top":"1rem"}}}} --><p class="info-card__sub" style="margin-top:1rem">Incluye el proceso inicial de integración y uniforme de entrenamiento.</p><!-- /wp:paragraph -->
			<!-- wp:pura/contact-link {"channel":"whatsapp","label":"Agendar clase de prueba","variant":"btn-primary","message":"Hola, quiero agendar una clase de prueba en Pura Capoeira Cuernavaca."} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
