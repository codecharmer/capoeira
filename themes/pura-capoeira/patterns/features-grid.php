<?php
/**
 * Title: Clases — Qué se trabaja
 * Slug: pura-capoeira/features-grid
 * Categories: pura-capoeira
 * Description: Cuadrícula de ocho aspectos que se trabajan en clase.
 * Viewport Width: 1400
 *
 * @package Pura
 */

$pura_features = array(
	array( '01', 'Movimiento y coordinación', 'Base, ginga y desplazamiento. El primer reto es hacer sencillo lo que hoy se siente torpe.' ),
	array( '02', 'Esquivas, ataques y defensa', 'El juego te pide leer al otro y responder a tiempo. Aprendes a medir, no solo a reaccionar.' ),
	array( '03', 'Música, canto y berimbau', 'Tocar y cantar sostienen la roda. Tu voz también tiene un lugar que hay que atreverse a ocupar.' ),
	array( '04', 'Roda de capoeira', 'El círculo donde todo se pone a prueba: técnica, atención y respeto por quien juega contigo.' ),
	array( '05', 'Condición física', 'Fuerza, resistencia, flexibilidad y movilidad. El cuerpo cambia cuando vuelves, semana tras semana.' ),
	array( '06', 'Disciplina y confianza', 'La constancia convierte una intención en práctica. La confianza llega después, como resultado.' ),
	array( '07', 'Cultura afrobrasileña', 'Historia, contexto y raíces. Saber de dónde viene la capoeira cambia la forma en que la juegas.' ),
	array( '08', 'Juego, estrategia y expresión', 'Malicia, creatividad y diálogo corporal. En la roda descubres cómo respondes cuando no hay guion.' ),
);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">En clase</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Qué se trabaja</h2><!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"features","layout":{"type":"default"}} -->
	<div class="wp-block-group features">
		<?php foreach ( $pura_features as [ $pura_num, $pura_title, $pura_text ] ) : ?>
		<!-- wp:group {"className":"feature reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group feature reveal">
			<!-- wp:paragraph {"className":"feature__num"} --><p class="feature__num"><?php echo esc_html( $pura_num ); ?></p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"className":"feature__title"} --><h3 class="wp-block-heading feature__title"><?php echo esc_html( $pura_title ); ?></h3><!-- /wp:heading -->
			<!-- wp:paragraph {"className":"feature__text"} --><p class="feature__text"><?php echo esc_html( $pura_text ); ?></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
