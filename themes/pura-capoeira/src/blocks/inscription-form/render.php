<?php
/**
 * pura/inscription-form — the registration form. Plan cards and amounts come from the plugin
 * (both groups are inlined as data-config so the first render needs no request).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_inscription_plans' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar el formulario de inscripción.', 'pura' ) . '</p>';
	}
	return;
}

$pura_currency = (string) pura_setting( 'currency', 'MXN' );
$pura_plans    = array(
	'adult' => pura_inscription_plans( 'adult' ),
	'kids'  => pura_inscription_plans( 'kids' ),
);
$pura_addon    = (float) Pura\Core\Pricing::to_pesos( Pura\Core\Pricing::addon_cents() );
$pura_config   = array(
	'currency'     => $pura_currency,
	'addon_amount' => $pura_addon,
	'plans'        => $pura_plans,
);
$pura_uid      = wp_unique_id( 'pura-ins-' );
$pura_text     = static fn ( string $key, string $default ) => (string) ( $attributes[ $key ] ?? $default );

$pura_money = static function ( float $pesos ) use ( $pura_currency ): string {
	return pura_format_pesos( $pesos ) . ' ' . $pura_currency;
};

$wrapper = get_block_wrapper_attributes( array( 'class' => 'inscription-layout' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-inscription-root data-config="<?php echo esc_attr( (string) wp_json_encode( $pura_config ) ); ?>">
	<form class="inscription-form" data-inscription-form novalidate>
		<fieldset class="inscription-block inscription-mode">
			<legend><?php esc_html_e( '¿Qué deseas hacer?', 'pura' ); ?></legend>
			<div class="mode-options">
				<label class="mode-option">
					<input type="radio" name="inscription_mode" value="new" checked />
					<span class="mode-option__body">
						<span class="mode-option__title"><?php echo esc_html( $pura_text( 'modeNewTitle', 'Quiero inscribirme' ) ); ?></span>
						<span class="mode-option__note"><?php echo esc_html( $pura_text( 'modeNewNote', '' ) ); ?></span>
					</span>
				</label>
				<label class="mode-option">
					<input type="radio" name="inscription_mode" value="member" />
					<span class="mode-option__body">
						<span class="mode-option__title"><?php echo esc_html( $pura_text( 'modeMemberTitle', 'Ya estoy inscrito' ) ); ?></span>
						<span class="mode-option__note"><?php echo esc_html( $pura_text( 'modeMemberNote', '' ) ); ?></span>
					</span>
				</label>
			</div>
		</fieldset>

		<fieldset class="inscription-block inscription-mode">
			<legend><?php esc_html_e( '¿Quién se inscribe?', 'pura' ); ?></legend>
			<div class="mode-options">
				<label class="mode-option">
					<input type="radio" name="inscription_group" value="adult" checked />
					<span class="mode-option__body">
						<span class="mode-option__title"><?php echo esc_html( $pura_text( 'groupAdultTitle', 'Adultos / mixto' ) ); ?></span>
						<span class="mode-option__note"><?php echo esc_html( $pura_text( 'groupAdultNote', '' ) ); ?></span>
					</span>
				</label>
				<label class="mode-option">
					<input type="radio" name="inscription_group" value="kids" />
					<span class="mode-option__body">
						<span class="mode-option__title"><?php echo esc_html( $pura_text( 'groupKidsTitle', 'Niños' ) ); ?></span>
						<span class="mode-option__note"><?php echo esc_html( $pura_text( 'groupKidsNote', '' ) ); ?></span>
					</span>
				</label>
			</div>
		</fieldset>

		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '1. Elige tu paquete', 'pura' ); ?></legend>
			<div class="plan-grid">
				<?php foreach ( $pura_plans['adult'] as $pura_i => $pura_plan ) : ?>
					<label class="plan-card">
						<input type="radio" name="plan" value="<?php echo esc_attr( $pura_plan['id'] ); ?>" data-amount="<?php echo esc_attr( (string) $pura_plan['amount'] ); ?>" data-allow-addon="<?php echo $pura_plan['allow_addon'] ? '1' : '0'; ?>" <?php echo 0 === $pura_i ? 'required' : ''; ?> />
						<span class="plan-card__body">
							<span class="plan-card__title"><?php echo esc_html( $pura_plan['label'] ); ?></span>
							<span class="plan-card__price" data-plan-price="<?php echo esc_attr( $pura_plan['id'] ); ?>"><?php echo esc_html( $pura_money( (float) $pura_plan['amount'] ) ); ?></span>
							<span class="plan-card__note" data-plan-note="<?php echo esc_attr( $pura_plan['id'] ); ?>"><?php echo esc_html( $pura_plan['note'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<label class="inscription-addon" data-inscription-addon hidden>
				<input type="checkbox" name="add_inscription" value="1" />
				<span><?php echo esc_html( sprintf( __( 'Agregar inscripción (+%s) — para alumnos de nuevo ingreso.', 'pura' ), $pura_money( $pura_addon ) ) ); ?></span>
			</label>
			<label class="field field--full inscription-trial-date" data-inscription-trial hidden>
				<span><?php esc_html_e( 'Fecha de tu Clase de Prueba *', 'pura' ); ?></span>
				<input type="date" name="trial_date" data-inscription-trial-input />
			</label>
		</fieldset>

		<fieldset class="inscription-block" data-inscription-member hidden>
			<legend><?php esc_html_e( 'Tu correo', 'pura' ); ?></legend>
			<label class="field field--full">
				<span><?php esc_html_e( 'Correo electrónico registrado *', 'pura' ); ?></span>
				<input type="email" name="member_email" autocomplete="email" placeholder="tu@correo.com" data-inscription-member-email />
			</label>
			<p class="promo-note"><?php esc_html_e( 'Usaremos el correo con el que te inscribiste para identificar tu cuenta. Si no encontramos un registro, te pediremos completar la inscripción.', 'pura' ); ?></p>
		</fieldset>

		<fieldset class="inscription-block" data-inscription-student>
			<legend><?php esc_html_e( '2. Datos del alumno', 'pura' ); ?></legend>
			<div class="form-grid">
				<label class="field"><span><?php esc_html_e( 'Nombre(s) *', 'pura' ); ?></span><input type="text" name="first_name" autocomplete="given-name" required /></label>
				<label class="field"><span><?php esc_html_e( 'Apellidos *', 'pura' ); ?></span><input type="text" name="last_name" autocomplete="family-name" required /></label>
				<label class="field"><span><?php esc_html_e( 'Fecha de nacimiento *', 'pura' ); ?></span><input type="date" name="dob" required /></label>
				<label class="field"><span><?php esc_html_e( 'Nombre del padre/madre/tutor (si es menor de edad)', 'pura' ); ?></span><input type="text" name="parent_name" autocomplete="name" /></label>
				<label class="field field--full"><span><?php esc_html_e( 'Dirección *', 'pura' ); ?></span><input type="text" name="address" autocomplete="street-address" required /></label>
				<label class="field"><span><?php esc_html_e( 'Correo electrónico *', 'pura' ); ?></span><input type="email" name="email" autocomplete="email" required /></label>
				<label class="field"><span><?php esc_html_e( 'Teléfono *', 'pura' ); ?></span><input type="tel" name="phone" autocomplete="tel" required /></label>
				<label class="field"><span><?php esc_html_e( 'Teléfono del padre/tutor', 'pura' ); ?></span><input type="tel" name="parent_phone" /></label>
				<label class="field"><span><?php esc_html_e( 'Teléfono de emergencia *', 'pura' ); ?></span><input type="tel" name="emergency_phone" required /></label>
			</div>
			<label class="field" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true" tabindex="-1"><span>Sitio web</span><input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
		</fieldset>

		<fieldset class="inscription-block">
			<legend><?php esc_html_e( '3. Código promocional (opcional)', 'pura' ); ?></legend>
			<div class="promo-row">
				<input type="text" name="promocode" placeholder="<?php esc_attr_e( 'Ej. PCPC2026', 'pura' ); ?>" autocomplete="off" data-inscription-promo />
				<button type="button" class="btn btn--ghost" data-inscription-promo-apply><?php esc_html_e( 'Aplicar', 'pura' ); ?></button>
			</div>
			<p class="promo-note" data-inscription-promo-note hidden></p>
		</fieldset>

		<fieldset class="inscription-block inscription-paymode" data-inscription-paymode hidden>
			<legend><?php esc_html_e( '4. Forma de pago', 'pura' ); ?></legend>
			<div class="paymode-options">
				<label class="paymode-option"><input type="radio" name="payment_mode" value="now" checked /><span><?php esc_html_e( 'Pagar ahora con tarjeta (Stripe)', 'pura' ); ?></span></label>
				<label class="paymode-option"><input type="radio" name="payment_mode" value="later" /><span><?php esc_html_e( 'Registrarme y pagar después (en persona)', 'pura' ); ?></span></label>
			</div>
		</fieldset>

		<div class="inscription-summary">
			<div class="inscription-summary__line"><span><?php esc_html_e( 'Paquete', 'pura' ); ?></span><strong data-inscription-summary-label>—</strong></div>
			<div class="inscription-summary__line inscription-summary__line--total"><span><?php esc_html_e( 'Total a pagar', 'pura' ); ?></span><strong data-inscription-summary-total>$0.00 <?php echo esc_html( $pura_currency ); ?></strong></div>
		</div>

		<div class="inscription-result" data-inscription-result hidden role="status" aria-live="polite"></div>

		<button type="submit" class="btn btn--primary inscription-submit" data-inscription-submit><?php esc_html_e( 'Continuar al pago', 'pura' ); ?></button>
		<p class="store-footnote"><?php echo esc_html( $pura_text( 'footnote', '' ) ); ?></p>
	</form>
</div>
