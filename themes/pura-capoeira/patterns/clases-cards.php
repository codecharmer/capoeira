<?php
/**
 * Title: Clases — Adultos, jóvenes y niños
 * Slug: pura-capoeira/clases-cards
 * Categories: pura-capoeira
 * Description: Tarjetas del grupo mixto (adultos y jóvenes) y de niños con horario y botón de inscripción.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"info-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group info-grid">
		<!-- wp:group {"className":"info-card reveal","anchor":"adultos","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal" id="adultos">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Adultos</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Capoeira para adultos y jóvenes</h3><!-- /wp:heading -->
			<!-- wp:paragraph {"className":"info-card__sub","style":{"spacing":{"margin":{"bottom":"1rem"}}}} --><p class="info-card__sub" style="margin-bottom:1rem">Grupo mixto desde los 8 años · Martes y jueves, 17:30 a 19:30. No se requiere experiencia previa. Los jóvenes de 13 a 17 años se integran a este grupo.</p><!-- /wp:paragraph -->
			<!-- wp:pura/schedule {"group":"adult"} /-->
			<!-- wp:paragraph {"className":"info-card__sub","style":{"spacing":{"margin":{"top":"1rem"}}}} --><p class="info-card__sub" style="margin-top:1rem">Cada clase te pone frente a algo que todavía no sabes hacer: una esquiva, una secuencia, un canto. Ahí entrenas la fuerza y también la paciencia para insistir.</p><!-- /wp:paragraph -->
			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.25rem"}}}} -->
			<div class="wp-block-buttons" style="margin-top:1.25rem">
				<!-- wp:button {"className":"is-style-blue"} --><div class="wp-block-button is-style-blue"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Agendar Adultos</a></div><!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"info-card reveal","anchor":"kids","layout":{"type":"default"}} -->
		<div class="wp-block-group info-card reveal" id="kids">
			<!-- wp:paragraph {"className":"info-card__tag"} --><p class="info-card__tag">Kids</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"info-card__title"} --><h3 class="wp-block-heading info-card__title">Capoeira para niños</h3><!-- /wp:heading -->
			<!-- wp:pura/schedule {"group":"kids"} /-->
			<!-- wp:paragraph {"className":"info-card__sub","style":{"spacing":{"margin":{"top":"1rem"}}}} --><p class="info-card__sub" style="margin-top:1rem">Un espacio para moverse, cantar y jugar con otros niños, donde intentar cuenta tanto como lograrlo. Cada avance se nota, y cada niño aprende a reconocer el suyo.</p><!-- /wp:paragraph -->
			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.25rem"}}}} -->
			<div class="wp-block-buttons" style="margin-top:1.25rem">
				<!-- wp:button {"className":"is-style-blue"} --><div class="wp-block-button is-style-blue"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Agendar Kids</a></div><!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
