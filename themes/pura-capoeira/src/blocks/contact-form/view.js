/**
 * Contact form → WhatsApp. Builds the message from the fields and opens wa.me in a new tab.
 */

function initContactForm( form ) {
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		if ( ! form.reportValidity() ) {
			return;
		}

		const number =
			form.dataset.whatsapp ||
			( window.puraConfig && window.puraConfig.whatsapp ) ||
			'';
		const intro = form.dataset.intro || '';
		const name = ( form.elements.name?.value || '' ).trim();
		const phone = ( form.elements.phone?.value || '' ).trim();
		const message = ( form.elements.message?.value || '' ).trim();

		const lines = [ intro ];
		if ( name ) {
			lines.push( `Nombre: ${ name }` );
		}
		if ( phone ) {
			lines.push( `Teléfono / WhatsApp: ${ phone }` );
		}
		if ( message ) {
			lines.push( `Mensaje: ${ message }` );
		}

		const url = `https://wa.me/${ number }?text=${ encodeURIComponent(
			lines.filter( Boolean ).join( '\n' )
		) }`;
		window.open( url, '_blank', 'noopener' );
	} );
}

document
	.querySelectorAll( '[data-js="contact-form"]' )
	.forEach( initContactForm );
