<?php
/**
 * pura/event-registration — registration form for an event. Posts to /pura/v1/events/register.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_setting' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar el formulario de registro.', 'pura' ) . '</p>';
	}
	return;
}

$pura_event_slug = sanitize_title( (string) ( $attributes['eventSlug'] ?? 'evento' ) );
$pura_event_name = trim( (string) ( $attributes['eventName'] ?? '' ) );
$pura_days       = array_values( array_filter( array_map( 'trim', explode( '|', (string) ( $attributes['days'] ?? '' ) ) ) ) );
$pura_ask_shirt  = ! empty( $attributes['askShirtSize'] );
$pura_footnote   = trim( (string) ( $attributes['footnote'] ?? '' ) );
$pura_sizes      = array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' );
$pura_config     = array(
	'event'      => $pura_event_slug,
	'event_name' => $pura_event_name,
	'days'       => $pura_days,
);

$wrapper = get_block_wrapper_attributes( array( 'class' => 'inscription-layout event-registration' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-event-root data-config="<?php echo esc_attr( (string) wp_json_encode( $pura_config ) ); ?>">
	<form class="inscription-form event-form" data-event-form novalidate>
		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '1. Tus datos', 'pura' ); ?></legend>
			<div class="form-grid">
				<label class="field"><span><?php esc_html_e( 'Nombre(s) *', 'pura' ); ?></span><input type="text" name="first_name" autocomplete="given-name" required /></label>
				<label class="field"><span><?php esc_html_e( 'Apellidos *', 'pura' ); ?></span><input type="text" name="last_name" autocomplete="family-name" required /></label>
				<label class="field"><span><?php esc_html_e( 'Correo electrónico *', 'pura' ); ?></span><input type="email" name="email" autocomplete="email" required /></label>
				<label class="field"><span><?php esc_html_e( 'WhatsApp / teléfono *', 'pura' ); ?></span><input type="tel" name="phone" autocomplete="tel" required /></label>
				<label class="field"><span><?php esc_html_e( 'Fecha de nacimiento *', 'pura' ); ?></span><input type="date" name="dob" autocomplete="bday" required data-event-dob /></label>
				<label class="field"><span><?php esc_html_e( 'Ciudad de origen', 'pura' ); ?></span><input type="text" name="city" autocomplete="address-level2" /></label>
			</div>
			<div class="form-grid event-form__extras event-form__parent" data-event-parent hidden>
				<p class="field-label field--full"><?php esc_html_e( 'Menor de edad: datos del padre, madre o tutor', 'pura' ); ?></p>
				<label class="field"><span><?php esc_html_e( 'Nombre del padre/madre/tutor', 'pura' ); ?></span><input type="text" name="parent_name" autocomplete="off" /></label>
				<label class="field"><span><?php esc_html_e( 'Teléfono del padre/madre/tutor *', 'pura' ); ?></span><input type="tel" name="parent_phone" autocomplete="off" data-event-parent-phone /></label>
			</div>
			<label class="field" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true" tabindex="-1"><span>Sitio web</span><input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
		</fieldset>

		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '2. Tu camino en la capoeira', 'pura' ); ?></legend>
			<div class="form-grid">
				<label class="field"><span><?php esc_html_e( 'Grupo / academia', 'pura' ); ?></span><input type="text" name="academy" autocomplete="organization" /></label>
				<label class="field"><span><?php esc_html_e( 'Mestre / Professor', 'pura' ); ?></span><input type="text" name="teacher" /></label>
				<label class="field"><span><?php esc_html_e( 'Graduación (cuerda)', 'pura' ); ?></span><input type="text" name="graduation" placeholder="<?php esc_attr_e( 'Ej. verde-amarilla', 'pura' ); ?>" /></label>
				<label class="field"><span><?php esc_html_e( 'Año en que empezaste capoeira *', 'pura' ); ?></span><input type="number" name="started_year" inputmode="numeric" min="1900" max="<?php echo esc_attr( wp_date( 'Y' ) ); ?>" step="1" placeholder="<?php esc_attr_e( 'Ej. 2016', 'pura' ); ?>" required /></label>
				<label class="field"><span><?php esc_html_e( 'Años de entrenamiento constante *', 'pura' ); ?></span><input type="number" name="years_training" inputmode="numeric" min="0" max="100" step="1" placeholder="<?php esc_attr_e( 'Ej. 5', 'pura' ); ?>" required /></label>
			</div>
			<p class="promo-note"><?php esc_html_e( 'No es lo mismo: puedes haber empezado hace diez años y haber entrenado de forma constante solo cinco. Cuenta los años en que entrenaste con regularidad.', 'pura' ); ?></p>
		</fieldset>

		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '3. Tu participación', 'pura' ); ?></legend>
			<?php if ( $pura_days ) : ?>
				<p class="field-label"><?php esc_html_e( '¿Qué días asistes? *', 'pura' ); ?></p>
				<div class="day-options" data-event-days>
					<?php foreach ( $pura_days as $pura_day ) : ?>
						<label class="day-option"><input type="checkbox" name="days" value="<?php echo esc_attr( $pura_day ); ?>" /><span><?php echo esc_html( $pura_day ); ?></span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="form-grid event-form__extras">
				<?php if ( $pura_ask_shirt ) : ?>
					<label class="field"><span><?php esc_html_e( 'Talla de playera', 'pura' ); ?></span>
						<select name="shirt_size">
							<option value=""><?php esc_html_e( 'Sin especificar', 'pura' ); ?></option>
							<?php foreach ( $pura_sizes as $pura_size ) : ?>
								<option value="<?php echo esc_attr( $pura_size ); ?>"><?php echo esc_html( $pura_size ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<label class="field field--full"><span><?php esc_html_e( 'Comentarios (alergias, hospedaje, dudas…)', 'pura' ); ?></span><textarea name="notes" rows="3" maxlength="1000"></textarea></label>
			</div>
		</fieldset>

		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '4. Contacto de emergencia', 'pura' ); ?></legend>
			<div class="form-grid">
				<label class="field"><span><?php esc_html_e( 'Nombre', 'pura' ); ?></span><input type="text" name="emergency_name" /></label>
				<label class="field"><span><?php esc_html_e( 'Teléfono de emergencia *', 'pura' ); ?></span><input type="tel" name="emergency_phone" required /></label>
			</div>
		</fieldset>

		<div class="inscription-result" data-event-result hidden role="status" aria-live="polite"></div>

		<button type="submit" class="btn btn--primary inscription-submit" data-event-submit><?php esc_html_e( 'Registrarme', 'pura' ); ?></button>
		<?php if ( '' !== $pura_footnote ) : ?>
			<p class="store-footnote"><?php echo esc_html( $pura_footnote ); ?></p>
		<?php endif; ?>
	</form>
</div>
