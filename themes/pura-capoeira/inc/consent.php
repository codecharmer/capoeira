<?php
/**
 * Cookie / third-party content consent banner.
 *
 * Purely client-side: the preference lives in localStorage (`pura_consent`) because the
 * production page cache serves identical HTML to every anonymous visitor. The banner only gates
 * the Calendly embed; the site sets no analytics or advertising cookies.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * URL of the Aviso de Privacidad (WordPress privacy page, slug fallback).
 */
function pura_theme_privacy_url(): string {
	if ( function_exists( 'pura_privacy_url' ) ) {
		return pura_privacy_url();
	}

	$url = get_privacy_policy_url();
	if ( '' !== $url ) {
		return $url;
	}

	return home_url( '/aviso-de-privacidad/' );
}

/**
 * Classic (non-module) footer script so block view scripts can list `pura-consent` as a dependency.
 */
function pura_theme_enqueue_consent_script(): void {
	wp_register_script(
		'pura-consent',
		PURA_THEME_URI . '/assets/js/consent.js',
		array(),
		pura_theme_asset_version( 'assets/js/consent.js' ),
		array( 'in_footer' => true )
	);
	wp_enqueue_script( 'pura-consent' );
}
add_action( 'wp_enqueue_scripts', 'pura_theme_enqueue_consent_script' );

/**
 * Banner markup. Hidden until consent.js decides whether a choice is pending.
 */
function pura_theme_consent_markup(): void {
	$privacy_url = pura_theme_privacy_url();
	?>
	<div id="pura-consent" class="consent" role="dialog" aria-labelledby="pura-consent-title" aria-describedby="pura-consent-desc" tabindex="-1" hidden data-consent-root>
		<div class="consent__inner">
			<div class="consent__text">
				<p id="pura-consent-title" class="consent__title"><?php esc_html_e( 'Cookies y contenido de terceros', 'pura' ); ?></p>
				<p id="pura-consent-desc" class="consent__desc">
					<?php esc_html_e( 'Este sitio no usa cookies de analítica ni publicidad. Solo guardamos lo necesario para que funcione y, si lo aceptas, cargamos el calendario de Calendly, que coloca sus propias cookies.', 'pura' ); ?>
					<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Aviso de Privacidad', 'pura' ); ?></a>
				</p>
			</div>

			<div class="consent__panel" data-consent-panel hidden>
				<label class="consent__option">
					<input type="checkbox" checked disabled />
					<span>
						<strong><?php esc_html_e( 'Estrictamente necesarias', 'pura' ); ?></strong>
						<small><?php esc_html_e( 'Sesión al iniciar sesión como alumno, esta preferencia y el carrito de la tienda (en tu navegador).', 'pura' ); ?></small>
					</span>
				</label>
				<label class="consent__option">
					<input type="checkbox" role="switch" data-consent-embeds />
					<span>
						<strong><?php esc_html_e( 'Contenido de terceros (Calendly)', 'pura' ); ?></strong>
						<small><?php esc_html_e( 'Carga el calendario para agendar clases de prueba. Calendly puede colocar cookies propias.', 'pura' ); ?></small>
					</span>
				</label>
			</div>

			<div class="consent__actions">
				<button type="button" class="btn btn--primary" data-consent-accept><?php esc_html_e( 'Aceptar todo', 'pura' ); ?></button>
				<button type="button" class="btn btn--ghost" data-consent-reject><?php esc_html_e( 'Solo necesarias', 'pura' ); ?></button>
				<button type="button" class="btn btn--ghost" data-consent-save hidden><?php esc_html_e( 'Guardar preferencias', 'pura' ); ?></button>
				<button type="button" class="consent__link" data-consent-toggle aria-expanded="false"><?php esc_html_e( 'Preferencias', 'pura' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'pura_theme_consent_markup', 10 );
