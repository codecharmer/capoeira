<?php
/**
 * Title: Inicio — Hero
 * Slug: pura-capoeira/home-hero
 * Categories: pura-capoeira
 * Description: Portada de la página de inicio con lema, texto de apertura y llamadas a la acción.
 * Viewport Width: 1400
 *
 * @package Pura
 */

$pura_logo = esc_url( get_theme_file_uri( 'assets/images/pura-capoeira-logo.png' ) );
?>
<!-- wp:cover {"url":"https://images.unsplash.com/photo-1641688587256-7b6549157cef?crop=entropy&cs=srgb&fm=jpg&w=2000&q=80","dimRatio":0,"minHeight":100,"minHeightUnit":"vh","isDark":true,"tagName":"section","align":"full","className":"hero","layout":{"type":"constrained"}} -->
<section class="wp-block-cover alignfull hero" style="min-height:100vh"><img class="wp-block-cover__image-background" alt="" src="https://images.unsplash.com/photo-1641688587256-7b6549157cef?crop=entropy&cs=srgb&fm=jpg&w=2000&q=80" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
	<!-- wp:group {"className":"hero__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group hero__inner">
		<!-- wp:group {"className":"hero-logo-wrap","layout":{"type":"default"}} -->
		<div class="wp-block-group hero-logo-wrap">
			<!-- wp:image {"width":"240px","className":"hero-logos"} -->
			<figure class="wp-block-image is-resized hero-logos"><img src="<?php echo $pura_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" alt="Pura Capoeira Cuernavaca" style="width:240px;height:auto"/></figure>
			<!-- /wp:image -->

			<!-- wp:paragraph {"className":"overline"} -->
			<p class="overline">Pura Capoeira · Cuernavaca · Morelos</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"className":"hero__title hero__title--statement"} -->
			<h1 class="wp-block-heading hero__title hero__title--statement"><span>Descubre tu fuerza.</span><span class="accent">Aprende a darle sentido.</span></h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"hero__sub"} -->
			<p class="hero__sub">Capoeira para adultos y niños en Cuernavaca.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"hero__support hero__support--wide"} -->
			<p class="hero__support hero__support--wide">Hay capacidades que descubrimos al intentar algo nuevo. Otras aparecen cuando aprendemos a escuchar, aceptamos una corrección o encontramos el valor para volver a empezar.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"hero__support hero__support--wide"} -->
			<p class="hero__support hero__support--wide">En Pura Capoeira Cuernavaca, la lucha, la música y el juego forman parte de un camino para desarrollar el cuerpo, conocernos mejor y actuar con mayor conciencia.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:buttons {"className":"hero__ctas"} -->
			<div class="wp-block-buttons hero__ctas">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/inscripciones/">Agenda tu primera clase</a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"is-style-secondary"} -->
				<div class="wp-block-button is-style-secondary"><a class="wp-block-button__link wp-element-button" href="/clases/">Ver clases</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->

			<!-- wp:pura/contact-link {"channel":"whatsapp","label":"Contactar por WhatsApp","variant":"btn-whatsapp","className":"hero__whatsapp"} /-->

			<!-- wp:group {"className":"hero__meta","layout":{"type":"default"}} -->
			<div class="wp-block-group hero__meta">
				<!-- wp:group {"layout":{"type":"default"}} -->
				<div class="wp-block-group">
					<!-- wp:paragraph -->
					<p>Un camino sostenido por</p>
					<!-- /wp:paragraph -->
					<!-- wp:paragraph {"className":"hero__meta-accent"} -->
					<p class="hero__meta-accent">Comunidad · Responsabilidad · Integridad</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div></section>
<!-- /wp:cover -->
