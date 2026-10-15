/**
 * Calendly block: the widget script only loads after the visitor accepts third-party content
 * (consent banner) or clicks "Cargar calendario". Depends on the `pura-consent` handle.
 */

/* global Calendly */

const WIDGET_SRC = 'https://assets.calendly.com/assets/external/widget.js';

let scriptPromise = null;

function loadWidgetScript() {
	if ( window.Calendly ) {
		return Promise.resolve();
	}
	if ( ! scriptPromise ) {
		scriptPromise = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = WIDGET_SRC;
			script.async = true;
			script.onload = () => resolve();
			script.onerror = () => {
				scriptPromise = null;
				reject( new Error( 'Calendly widget failed to load' ) );
			};
			document.head.appendChild( script );
		} );
	}
	return scriptPromise;
}

function consent() {
	return window.puraConsent ? window.puraConsent.get() : { embeds: false };
}

function mount( root ) {
	const host = root.querySelector( '[data-calendly-embed]' );
	if ( ! host || host.dataset.mounted === '1' ) {
		return;
	}
	host.dataset.mounted = '1';
	const placeholder = root.querySelector( '[data-calendly-placeholder]' );

	loadWidgetScript()
		.then( () => {
			const widget = document.createElement( 'div' );
			widget.className = 'calendly-inline-widget';
			widget.setAttribute( 'data-auto-load', 'false' );
			widget.style.minWidth = '320px';
			widget.style.height = `${ root.dataset.calendlyHeight || 700 }px`;
			host.appendChild( widget );
			if ( placeholder ) {
				placeholder.hidden = true;
			}
			Calendly.initInlineWidget( {
				url: root.dataset.calendlyUrl,
				parentElement: widget,
			} );
		} )
		.catch( () => {
			host.dataset.mounted = '0';
		} );
}

function unmount( root ) {
	const host = root.querySelector( '[data-calendly-embed]' );
	if ( ! host ) {
		return;
	}
	const placeholder = root.querySelector( '[data-calendly-placeholder]' );
	host.querySelectorAll( '.calendly-inline-widget' ).forEach( ( el ) =>
		el.remove()
	);
	host.dataset.mounted = '0';
	if ( placeholder ) {
		placeholder.hidden = false;
	}
}

const roots = Array.from( document.querySelectorAll( '[data-calendly-root]' ) );

function sync() {
	const allowed = !! consent().embeds;
	roots.forEach( ( root ) => ( allowed ? mount( root ) : unmount( root ) ) );
}

roots.forEach( ( root ) => {
	const button = root.querySelector( '[data-calendly-load]' );
	if ( button ) {
		button.addEventListener( 'click', () => {
			if ( window.puraConsent ) {
				window.puraConsent.set( { embeds: true } );
			} else {
				mount( root );
			}
		} );
	}
} );

document.addEventListener( 'pura:consent', sync );
sync();
