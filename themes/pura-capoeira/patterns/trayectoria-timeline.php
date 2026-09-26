<?php
/**
 * Title: Trayectoria — Línea de tiempo
 * Slug: pura-capoeira/trayectoria-timeline
 * Categories: pura-capoeira
 * Description: Un camino de dos décadas, año por año.
 * Viewport Width: 1400
 *
 * @package Pura
 */

$pura_timeline = array(
	array( '2005', 'Inicio en la capoeira en la University of the Virgin Islands.' ),
	array( '2009', 'Continuidad en la comunidad capoeirista de Cuernavaca, Morelos.' ),
	array( '2015', 'Inicio de enseñanza de capoeira.' ),
	array( '2016', 'Comienza su camino con Mestre Madona y Pura Capoeira.' ),
	array( '2017', 'Formación como instructor en el 2º Circuito Nacional en Cuernavaca.' ),
	array( '2022', 'Primer grado de Professor en Guanajuato.' ),
	array( '2024', 'Segundo grado de Professor en Caucaia, Ceará, Brasil.' ),
);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"section section--dark","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section section--dark">
	<!-- wp:group {"className":"section__head reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group section__head reveal">
		<!-- wp:paragraph {"className":"overline"} --><p class="overline">Línea de tiempo</p><!-- /wp:paragraph -->
		<!-- wp:heading --><h2 class="wp-block-heading">Un camino de dos décadas</h2><!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:pura/timeline -->
	<div class="wp-block-pura-timeline timeline">
		<?php foreach ( $pura_timeline as [ $pura_year, $pura_text ] ) : ?>
		<!-- wp:pura/timeline-item {"year":"<?php echo esc_attr( $pura_year ); ?>"} -->
		<div class="wp-block-pura-timeline-item tl-item reveal"><span class="tl-year"><?php echo esc_html( $pura_year ); ?></span><p class="tl-text"><?php echo esc_html( $pura_text ); ?></p></div>
		<!-- /wp:pura/timeline-item -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:pura/timeline -->
</section>
<!-- /wp:group -->
