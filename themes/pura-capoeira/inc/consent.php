<?php
/**
 * Cookie notice.
 *
 * The site sets no analytics or advertising cookies and loads no third-party content, so there is
 * nothing to consent to: the banner only informs (student session cookie, store cart in the
 * browser) and is dismissed once. The acknowledgement lives in localStorage because the production
 * page cache serves identical HTML to every anonymous visitor.
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
 * Classic (non-module) footer script; `window.puraConsent` is available to block view scripts.
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
 * Notice markup. Hidden until consent.js decides whether it was already acknowledged.
 */
function pura_theme_consent_markup(): void {
	$privacy_url = pura_theme_privacy_url();
	?>
	<div id="pura-consent" class="consent" role="region" aria-labelledby="pura-consent-title" tabindex="-1" hidden data-consent-root>
		<div class="consent__inner">
			<div class="consent__text">
				<p id="pura-consent-title" class="consent__title"><?php esc_html_e( 'Cookies', 'pura' ); ?></p>
				<p class="consent__desc">
					<?php esc_html_e( 'Este sitio no usa cookies de analítica ni publicidad. Solo guarda lo necesario para funcionar: tu sesión si entras a Mi cuenta y el carrito de la tienda en tu navegador.', 'pura' ); ?>
					<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Aviso de Privacidad', 'pura' ); ?></a>
				</p>
			</div>
			<div class="consent__actions">
				<button type="button" class="btn btn--primary" data-consent-accept><?php esc_html_e( 'Entendido', 'pura' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'pura_theme_consent_markup', 10 );
