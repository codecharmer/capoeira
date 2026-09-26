<?php
/**
 * Title: Inscripciones — Días y horarios
 * Slug: pura-capoeira/inscripciones-schedule
 * Categories: pura-capoeira
 * Description: Horarios de ambos grupos antes del formulario de inscripción.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Horarios</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Días y horarios de clase</h2><!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"info-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group info-grid">
		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Adultos</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Capoeira para adultos</h3><!-- /wp:heading -->
			<!-- wp:pura/schedule {"group":"adult"} /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"info-card reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Kids</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Capoeira para niños</h3><!-- /wp:heading -->
			<!-- wp:pura/schedule {"group":"kids"} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
