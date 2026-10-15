<?php
/**
 * pura/account — sign-in buttons (Google / Apple) and the student portal shell.
 *
 * No user data is printed here: pages are cached for everyone. view.js discovers the session and
 * fills the portal from /pura/v1/account/me.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_auth_providers' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar Mi cuenta.', 'pura' ) . '</p>';
	}
	return;
}

$pura_providers   = pura_auth_providers();
$pura_privacy_url = pura_privacy_url();
$pura_account_url = pura_account_url();
$pura_text        = static fn ( string $key, string $default ) => (string) ( $attributes[ $key ] ?? $default );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'account' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-account-root data-account-state="pending">
	<p class="account__loading text-muted" data-account-loading><?php esc_html_e( 'Comprobando tu sesión…', 'pura' ); ?></p>

	<div class="account-notice" data-account-notice hidden role="status" aria-live="polite"></div>

	<section class="account-login" data-account-logged-out>
		<p class="lead"><?php echo esc_html( $pura_text( 'introLoggedOut', '' ) ); ?></p>
		<?php if ( $pura_providers ) : ?>
			<div class="account-login__providers">
				<?php foreach ( $pura_providers as $pura_provider ) : ?>
					<form method="post" action="<?php echo esc_url( $pura_provider['startUrl'] ); ?>" class="account-login__form">
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $pura_account_url ); ?>" data-account-redirect />
						<button type="submit" class="btn btn--provider btn--<?php echo esc_attr( $pura_provider['id'] ); ?>">
							<?php if ( 'google' === $pura_provider['id'] ) : ?>
								<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.3-1.6 3.8-5.5 3.8-3.3 0-6-2.7-6-6.1s2.7-6.1 6-6.1c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.8 3.1 14.6 2 12 2 6.5 2 2 6.5 2 12s4.5 10 10 10c5.8 0 9.6-4.1 9.6-9.8 0-.7-.1-1.2-.2-1.7H12z"/></svg>
							<?php else : ?>
								<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M16.4 12.7c0-2.5 2.1-3.7 2.2-3.8-1.2-1.7-3-2-3.7-2-1.6-.2-3.1.9-3.9.9-.8 0-2-.9-3.4-.9-1.7 0-3.4 1-4.3 2.6-1.8 3.2-.5 7.9 1.3 10.5.9 1.3 1.9 2.7 3.3 2.6 1.3-.1 1.8-.8 3.4-.8 1.6 0 2 .8 3.4.8 1.4 0 2.3-1.3 3.2-2.5 1-1.4 1.4-2.8 1.4-2.9-.1 0-2.9-1.1-2.9-4.5zM14 5.2c.7-.9 1.2-2.1 1.1-3.2-1 0-2.3.7-3 1.6-.7.8-1.3 2-1.1 3.1 1.1.1 2.3-.6 3-1.5z"/></svg>
							<?php endif; ?>
							<span><?php echo esc_html( $pura_provider['label'] ); ?></span>
						</button>
					</form>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="pura-notice"><?php esc_html_e( 'El inicio de sesión aún no está configurado. Escríbenos por WhatsApp si necesitas ayuda con tu inscripción.', 'pura' ); ?></p>
		<?php endif; ?>
		<p class="account-login__legal text-muted">
			<?php esc_html_e( 'Solo recibimos tu nombre y correo del proveedor. Consulta el', 'pura' ); ?>
			<a href="<?php echo esc_url( $pura_privacy_url ); ?>"><?php esc_html_e( 'Aviso de Privacidad', 'pura' ); ?></a>.
		</p>
	</section>

	<section class="account-portal" data-account-portal hidden>
		<header class="account-portal__head">
			<p class="overline"><?php esc_html_e( 'Mi cuenta', 'pura' ); ?></p>
			<h2 class="account-portal__title"><?php esc_html_e( 'Hola,', 'pura' ); ?> <span data-account-name></span></h2>
			<p class="text-muted" data-account-email></p>
			<a class="btn btn--ghost account-logout" data-account-logout href="#"><?php esc_html_e( 'Cerrar sesión', 'pura' ); ?></a>
		</header>

		<div class="account-grid">
			<section class="info-card account-card" data-account-unlinked hidden>
				<p class="info-card__tag"><?php esc_html_e( 'Ficha de alumno', 'pura' ); ?></p>
				<p><?php echo esc_html( $pura_text( 'noStudentText', '' ) ); ?></p>
				<a class="btn btn--whatsapp" data-account-whatsapp href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Escribir por WhatsApp', 'pura' ); ?></a>
			</section>

			<section class="info-card account-card" data-account-pay hidden>
				<p class="info-card__tag"><?php esc_html_e( 'Mensualidad', 'pura' ); ?></p>
				<p class="info-card__price" data-account-monthly-amount></p>
				<p class="text-muted" data-account-monthly-note></p>
				<button type="button" class="btn btn--primary" data-account-pay-button><?php esc_html_e( 'Pagar mensualidad', 'pura' ); ?></button>
				<p class="store-footnote"><?php esc_html_e( 'Pago seguro con tarjeta vía Stripe.', 'pura' ); ?></p>
			</section>

			<section class="info-card account-card" data-account-profile hidden>
				<p class="info-card__tag"><?php esc_html_e( 'Mis datos', 'pura' ); ?></p>
				<p class="account-profile__name" data-account-student-name></p>
				<form class="account-profile" data-account-profile-form>
					<div class="form-grid">
						<label class="field"><span><?php esc_html_e( 'Teléfono', 'pura' ); ?></span><input type="tel" name="phone" autocomplete="tel" /></label>
						<label class="field"><span><?php esc_html_e( 'Teléfono de emergencia', 'pura' ); ?></span><input type="tel" name="emergency_phone" /></label>
						<label class="field"><span><?php esc_html_e( 'Padre/madre/tutor', 'pura' ); ?></span><input type="text" name="parent_name" /></label>
						<label class="field"><span><?php esc_html_e( 'Teléfono del tutor', 'pura' ); ?></span><input type="tel" name="parent_phone" /></label>
						<label class="field field--full"><span><?php esc_html_e( 'Dirección', 'pura' ); ?></span><input type="text" name="address" autocomplete="street-address" /></label>
					</div>
					<button type="submit" class="btn btn--secondary"><?php esc_html_e( 'Guardar datos', 'pura' ); ?></button>
				</form>
			</section>
		</div>

		<section class="account-history" data-account-history hidden>
			<h3><?php esc_html_e( 'Historial de inscripciones y pagos', 'pura' ); ?></h3>
			<div class="account-history__table-wrap">
				<table class="account-history__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Fecha', 'pura' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Concepto', 'pura' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Monto', 'pura' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Estado', 'pura' ); ?></th>
						</tr>
					</thead>
					<tbody data-account-history-rows></tbody>
				</table>
			</div>
			<p class="text-muted" data-account-history-empty hidden><?php esc_html_e( 'Aún no hay inscripciones registradas.', 'pura' ); ?></p>
		</section>
	</section>
</div>
