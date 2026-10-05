<?php
/**
 * Title: Página — Evento Vadiando na Ladeira 2026
 * Slug: pura-capoeira/page-evento-vadiando
 * Categories: pura-capoeira
 * Block Types: core/post-content
 * Post Types: page
 * Description: Página del evento Vadiando na Ladeira (Guanajuato, 6–8 de noviembre de 2026): cartel, datos y formulario de registro.
 * Viewport Width: 1400
 *
 * @package Pura
 */

$pura_poster = esc_url( get_theme_file_uri( 'assets/images/vadiando-na-ladeira-2026.jpg' ) );
?>
<!-- wp:group {"tagName":"section","align":"full","className":"page-header","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull page-header">
	<!-- wp:paragraph {"className":"overline"} --><p class="overline">6 · 7 · 8 de noviembre · Guanajuato Capital</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Vadiando na Ladeira<br><span class="text-yellow">Guanajuato 2026</span></h1><!-- /wp:heading -->
	<!-- wp:paragraph {"className":"lead"} --><p class="lead">Vamos jogar capoeira. Tres días de rodas, entrenamientos y música por las calles y ladeiras de Guanajuato, con Pura Capoeira y el Centro Esportivo Cultural Mestre Madona.</p><!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"split event-intro reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group split event-intro reveal">
		<!-- wp:image {"className":"event-poster"} -->
		<figure class="wp-block-image event-poster"><img src="<?php echo $pura_poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" alt="Cartel de Vadiando na Ladeira: 6, 7 y 8 de noviembre, Guanajuato Capital. Vamos jogar capoeira."/></figure>
		<!-- /wp:image -->

		<!-- wp:group {"className":"event-facts","layout":{"type":"default"}} -->
		<div class="wp-block-group event-facts">
			<!-- wp:group {"className":"event-fact","layout":{"type":"default"}} -->
			<div class="wp-block-group event-fact">
				<!-- wp:paragraph {"className":"event-fact__label"} --><p class="event-fact__label">Fechas</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"event-fact__value"} --><p class="event-fact__value">Viernes 6, sábado 7 y domingo 8 de noviembre de 2026</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"event-fact","layout":{"type":"default"}} -->
			<div class="wp-block-group event-fact">
				<!-- wp:paragraph {"className":"event-fact__label"} --><p class="event-fact__label">Lugar</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"event-fact__value"} --><p class="event-fact__value">Guanajuato Capital, Guanajuato, México</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"event-fact","layout":{"type":"default"}} -->
			<div class="wp-block-group event-fact">
				<!-- wp:paragraph {"className":"event-fact__label"} --><p class="event-fact__label">Organiza</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"event-fact__value"} --><p class="event-fact__value">Contramestre Pepe Mortales</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"event-fact","layout":{"type":"default"}} -->
			<div class="wp-block-group event-fact">
				<!-- wp:paragraph {"className":"event-fact__label"} --><p class="event-fact__label">Supervisa</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"event-fact__value"} --><p class="event-fact__value">Mestre Madona</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"event-fact","layout":{"type":"default"}} -->
			<div class="wp-block-group event-fact">
				<!-- wp:paragraph {"className":"event-fact__label"} --><p class="event-fact__label">Para quién</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"event-fact__value"} --><p class="event-fact__value">Capoeiristas de cualquier grupo, edad y graduación</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
			<!-- wp:paragraph {"className":"is-style-muted"} --><p class="is-style-muted">El programa, el punto de encuentro y las opciones de hospedaje se envían a las personas registradas por correo y WhatsApp.</p><!-- /wp:paragraph -->
			<!-- wp:pura/contact-link {"channel":"whatsapp","label":"Preguntas por WhatsApp","variant":"btn-whatsapp","message":"Hola, tengo una pregunta sobre Vadiando na Ladeira 2026."} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","anchor":"registro","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section section--dark" id="registro">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Registro</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Reserva tu lugar en la ladeira</h2><!-- /wp:heading -->
		<!-- wp:paragraph {"className":"lead"} --><p class="lead">Un registro por persona. Al enviarlo recibirás una copia en tu correo y la organización te contactará con los detalles.</p><!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:pura/event-registration {"eventSlug":"vadiando-na-ladeira-2026","eventName":"Vadiando na Ladeira 2026","days":"6 de noviembre|7 de noviembre|8 de noviembre"} /-->
</section>
<!-- /wp:group -->
