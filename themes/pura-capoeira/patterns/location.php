<?php
/**
 * Title: Ubicación
 * Slug: pura-capoeira/location
 * Categories: pura-capoeira
 * Description: Dirección y botón a Google Maps, tomados de los ajustes.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull section">
	<!-- wp:group {"className":"location reveal","layout":{"type":"default"}} -->
	<div class="wp-block-group location reveal">
		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"location__tag"} --><p class="location__tag">Ubicación</p><!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"location__addr","metadata":{"bindings":{"content":{"source":"pura/setting","args":{"key":"address"}}}}} --><p class="location__addr">San Jerónimo 503,<br>Tlaltenango, Cuernavaca,<br>Morelos</p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<!-- wp:pura/contact-link {"channel":"maps","label":"Ver ubicación","variant":"btn-blue"} /-->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
