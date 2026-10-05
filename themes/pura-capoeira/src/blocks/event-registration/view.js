/**
 * Event registration form: validates, posts JSON to /pura/v1/events/register and shows the result.
 */

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );

function restUrl( path ) {
	const base =
		( window.puraConfig && window.puraConfig.restUrl ) ||
		'/wp-json/pura/v1/';
	return base.replace( /\/?$/, '/' ) + path;
}

async function postJson( path, payload ) {
	const res = await fetch( restUrl( path ), {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
		},
		body: JSON.stringify( payload ),
	} );
	let json = {};
	try {
		json = await res.json();
	} catch ( _err ) {
		json = {};
	}
	return { res, json };
}

const TEXT_FIELDS = [
	'first_name',
	'last_name',
	'email',
	'phone',
	'dob',
	'parent_name',
	'parent_phone',
	'started_year',
	'years_training',
	'city',
	'academy',
	'teacher',
	'graduation',
	'shirt_size',
	'emergency_name',
	'emergency_phone',
	'notes',
	'website',
];

function initEventForm( root ) {
	const form = $( '[data-event-form]', root );
	if ( ! form ) {
		return;
	}

	let config = { event: '', event_name: '', days: [] };
	try {
		config = Object.assign(
			config,
			JSON.parse( root.dataset.config || '{}' )
		);
	} catch ( _err ) {
		// keep defaults
	}

	const resultEl = $( '[data-event-result]', root );
	const submitBtn = $( '[data-event-submit]', root );
	const dayInputs = $$( 'input[name="days"]', form );
	const dobInput = $( '[data-event-dob]', form );
	const parentWrap = $( '[data-event-parent]', form );
	const parentPhone = $( '[data-event-parent-phone]', form );

	// Under 18 on the day of the form: ask for a parent or guardian's phone.
	const isMinor = ( dob ) => {
		const born = new Date( dob + 'T00:00:00' );
		if ( Number.isNaN( born.getTime() ) ) {
			return false;
		}
		const now = new Date();
		let age = now.getFullYear() - born.getFullYear();
		const beforeBirthday =
			now.getMonth() < born.getMonth() ||
			( now.getMonth() === born.getMonth() &&
				now.getDate() < born.getDate() );
		if ( beforeBirthday ) {
			age -= 1;
		}
		return age < 18;
	};

	function updateParentUi() {
		if ( ! dobInput || ! parentWrap || ! parentPhone ) {
			return;
		}
		const minor = dobInput.value !== '' && isMinor( dobInput.value );
		parentWrap.hidden = ! minor;
		parentPhone.required = minor;
	}

	if ( dobInput ) {
		dobInput.max = new Date().toISOString().slice( 0, 10 );
		dobInput.addEventListener( 'change', updateParentUi );
		dobInput.addEventListener( 'input', updateParentUi );
	}

	const showResult = ( msg, ok ) => {
		if ( ! resultEl ) {
			return;
		}
		resultEl.hidden = false;
		resultEl.textContent = msg;
		resultEl.classList.toggle( 'is-success', !! ok );
		resultEl.classList.toggle( 'is-error', ! ok );
		resultEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	};

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		if ( ! form.reportValidity() ) {
			return;
		}

		const days = dayInputs
			.filter( ( input ) => input.checked )
			.map( ( input ) => input.value );
		if ( dayInputs.length && ! days.length ) {
			showResult( 'Elige al menos un día para asistir.', false );
			dayInputs[ 0 ].focus();
			return;
		}

		const fd = new FormData( form );
		const payload = {
			event: config.event,
			event_name: config.event_name,
			days,
			days_offered: config.days,
		};
		TEXT_FIELDS.forEach( ( key ) => {
			payload[ key ] = ( fd.get( key ) || '' ).toString().trim();
		} );

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}
		showResult( 'Enviando tu registro...', true );

		try {
			const { res, json } = await postJson( 'events/register', payload );
			if ( ! res.ok || ! json.ok ) {
				throw new Error(
					json.error ||
						json.message ||
						'No se pudo enviar el registro'
				);
			}
			showResult( json.message || '¡Registro recibido!', true );
			form.reset();
			updateParentUi();
		} catch ( err ) {
			showResult(
				err.message ||
					'No se pudo enviar el registro. Intenta de nuevo.',
				false
			);
		} finally {
			if ( submitBtn ) {
				submitBtn.disabled = false;
			}
		}
	} );
}

document.querySelectorAll( '[data-event-root]' ).forEach( initEventForm );
