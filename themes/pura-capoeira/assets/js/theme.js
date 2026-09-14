/**
 * Pura Capoeira theme behaviour.
 *
 * Only reveal-on-scroll lives here. Navigation, overlay menu, active link, and the footer year
 * are handled by core blocks; interactive features are per-block view scripts.
 */

const REVEAL_SELECTOR = '.reveal';
const VISIBLE_CLASS = 'is-visible';

function revealAll( elements ) {
	elements.forEach( ( el ) => el.classList.add( VISIBLE_CLASS ) );
}

function initReveal() {
	const elements = Array.from( document.querySelectorAll( REVEAL_SELECTOR ) );
	if ( ! elements.length ) {
		return;
	}

	const reduceMotion = window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches;
	if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
		revealAll( elements );
		return;
	}

	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( VISIBLE_CLASS );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.12 }
	);

	elements.forEach( ( el ) => observer.observe( el ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initReveal );
} else {
	initReveal();
}
