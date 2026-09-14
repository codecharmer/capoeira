/**
 * Gallery category filter: toggles cards by data-category. No fetch; cards are server-rendered.
 */

function initGallery( root ) {
	const buttons = Array.from( root.querySelectorAll( '[data-js="gallery-filter"]' ) );
	const cards = Array.from( root.querySelectorAll( '.video-card' ) );
	if ( ! buttons.length || ! cards.length ) {
		return;
	}

	function apply( category ) {
		cards.forEach( ( card ) => {
			const cats = ( card.dataset.category || '' ).split( /\s+/ );
			card.hidden = category !== '' && ! cats.includes( category );
		} );
		buttons.forEach( ( btn ) => {
			const active = ( btn.dataset.category || '' ) === category;
			btn.classList.toggle( 'is-active', active );
			btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	buttons.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => apply( btn.dataset.category || '' ) );
	} );
}

document.querySelectorAll( '.wp-block-pura-gallery' ).forEach( initGallery );
