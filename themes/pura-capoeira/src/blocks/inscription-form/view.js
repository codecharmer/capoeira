/**
 * Inscription form. Port of the static site's initInscription(): plans, group, add-on, trial
 * date, promo code, member-by-email, summary, submit, and Stripe return handling.
 *
 * Prices come from the block's data-config (both groups) and from validate-promo responses;
 * nothing is hard-coded here. The server re-resolves pricing on submit.
 */

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );

function restUrl( path ) {
	const base = ( window.puraConfig && window.puraConfig.restUrl ) || '/wp-json/pura/v1/';
	return base.replace( /\/?$/, '/' ) + path;
}

async function postJson( path, payload ) {
	const res = await fetch( restUrl( path ), {
		method: 'POST',
		headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
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

function initInscription( root ) {
	const form = $( '[data-inscription-form]', root );
	if ( ! form ) {
		return;
	}

	let config = { currency: 'MXN', addon_amount: 1500, plans: { adult: [], kids: [] } };
	try {
		config = Object.assign( config, JSON.parse( root.dataset.config || '{}' ) );
	} catch ( _err ) {
		// keep defaults
	}

	const addonWrap = $( '[data-inscription-addon]', root );
	const addonInput = addonWrap ? $( 'input', addonWrap ) : null;
	const promoInput = $( '[data-inscription-promo]', root );
	const promoApplyBtn = $( '[data-inscription-promo-apply]', root );
	const promoNote = $( '[data-inscription-promo-note]', root );
	const paymodeWrap = $( '[data-inscription-paymode]', root );
	const trialWrap = $( '[data-inscription-trial]', root );
	const trialInput = trialWrap ? $( '[data-inscription-trial-input]', trialWrap ) : null;
	const studentWrap = $( '[data-inscription-student]', root );
	const memberWrap = $( '[data-inscription-member]', root );
	const memberEmailInput = memberWrap ? $( '[data-inscription-member-email]', memberWrap ) : null;
	const summaryLabel = $( '[data-inscription-summary-label]', root );
	const summaryTotal = $( '[data-inscription-summary-total]', root );
	const resultEl = $( '[data-inscription-result]', root );
	const submitBtn = $( '[data-inscription-submit]', root );

	const currency = config.currency || 'MXN';
	const money = ( n ) =>
		new Intl.NumberFormat( 'es-MX', { style: 'currency', currency } ).format( n ) + ' ' + currency;

	const currentGroup = () => $( 'input[name="inscription_group"]:checked', form )?.value || 'adult';
	const currentMode = () => $( 'input[name="inscription_mode"]:checked', form )?.value || 'new';
	const selectedPlan = () => $( 'input[name="plan"]:checked', form );

	// Promo state for the current session. `plans` holds promo-priced plans for the group.
	const promoState = { type: 'none', free: false, paymentOptional: false, plans: null, monthly: null };

	const basePlans = () => config.plans[ currentGroup() ] || config.plans.adult || [];
	const effectivePlans = () => ( promoState.type === 'current' && promoState.plans ? promoState.plans : basePlans() );

	function applyPlanPrices( plans ) {
		plans.forEach( ( plan ) => {
			const input = $( `input[name="plan"][value="${ plan.id }"]`, form );
			if ( input ) {
				input.setAttribute( 'data-amount', String( plan.amount ) );
				input.setAttribute( 'data-allow-addon', plan.allow_addon ? '1' : '0' );
			}
			const priceEl = $( `[data-plan-price="${ plan.id }"]`, form );
			if ( priceEl ) {
				priceEl.textContent = money( plan.amount );
			}
			const noteEl = $( `[data-plan-note="${ plan.id }"]`, form );
			if ( noteEl && plan.note ) {
				noteEl.textContent = plan.note;
			}
		} );
	}

	function resetPromoPricing() {
		promoState.type = 'none';
		promoState.free = false;
		promoState.paymentOptional = false;
		promoState.plans = null;
		promoState.monthly = null;
		applyPlanPrices( effectivePlans() );
	}

	const payLaterSelected = () =>
		!! paymodeWrap && ! paymodeWrap.hidden && $( 'input[name="payment_mode"]:checked', form )?.value === 'later';

	const requiredStudentInputs = studentWrap ? $$( 'input[required], select[required], textarea[required]', studentWrap ) : [];

	function updateModeUi() {
		const isMember = currentMode() === 'member';
		if ( studentWrap ) {
			studentWrap.hidden = isMember;
			requiredStudentInputs.forEach( ( input ) => {
				input.required = ! isMember;
			} );
		}
		if ( memberWrap ) {
			memberWrap.hidden = ! isMember;
		}
		if ( memberEmailInput ) {
			memberEmailInput.required = isMember;
			if ( ! isMember ) {
				memberEmailInput.value = '';
			}
		}
	}

	if ( trialInput ) {
		trialInput.min = new Date().toISOString().slice( 0, 10 );
	}

	function updateTrialUi() {
		if ( ! trialWrap || ! trialInput ) {
			return;
		}
		const isTrial = selectedPlan()?.value === 'trial';
		trialWrap.hidden = ! isTrial;
		trialInput.required = isTrial;
		if ( ! isTrial ) {
			trialInput.value = '';
		}
	}

	function updatePaymodeUi() {
		if ( paymodeWrap ) {
			paymodeWrap.hidden = ! promoState.paymentOptional;
		}
		if ( submitBtn ) {
			submitBtn.textContent = promoState.free || payLaterSelected() ? 'Registrar inscripción' : 'Continuar al pago';
		}
	}

	function updateSummary() {
		const plan = selectedPlan();
		updateTrialUi();
		if ( ! plan ) {
			if ( summaryLabel ) {
				summaryLabel.textContent = '—';
			}
			if ( summaryTotal ) {
				summaryTotal.textContent = money( 0 );
			}
			updatePaymodeUi();
			return;
		}
		const allowAddon = plan.getAttribute( 'data-allow-addon' ) === '1';
		if ( addonWrap ) {
			addonWrap.hidden = ! allowAddon;
			if ( ! allowAddon && addonInput ) {
				addonInput.checked = false;
			}
		}
		let amount = parseFloat( plan.getAttribute( 'data-amount' ) ) || 0;
		let label = $( '.plan-card__title', plan.closest( '.plan-card' ) )?.textContent || 'Paquete';
		if ( allowAddon && addonInput && addonInput.checked ) {
			amount += Number( config.addon_amount ) || 0;
			label += ' + inscripción';
		}
		if ( promoState.free ) {
			amount = 0;
			label += ' (beca 100%)';
		}
		if ( summaryLabel ) {
			summaryLabel.textContent = label;
		}
		if ( summaryTotal ) {
			summaryTotal.textContent = money( amount );
		}
		updatePaymodeUi();
	}

	function updateGroup() {
		const code = ( promoInput?.value || '' ).trim();
		if ( code !== '' && promoState.type !== 'none' ) {
			applyPromo();
		} else {
			applyPlanPrices( effectivePlans() );
			updateSummary();
		}
	}

	form.addEventListener( 'change', ( e ) => {
		const name = e.target.name;
		if ( name === 'plan' || name === 'add_inscription' || name === 'payment_mode' ) {
			updateSummary();
		}
		if ( name === 'inscription_mode' ) {
			updateModeUi();
		}
		if ( name === 'inscription_group' ) {
			updateGroup();
		}
	} );

	async function applyPromo() {
		const code = ( promoInput?.value || '' ).trim();
		if ( ! promoNote ) {
			return;
		}
		promoNote.hidden = false;
		promoNote.classList.remove( 'is-success' );

		if ( code === '' ) {
			resetPromoPricing();
			promoNote.textContent = 'Escribe un código para aplicarlo.';
			updateSummary();
			return;
		}

		promoNote.textContent = 'Validando código...';
		try {
			const { res, json } = await postJson( 'inscriptions/validate-promo', { promocode: code, group: currentGroup() } );
			if ( ! res.ok || ! json.ok ) {
				throw new Error( json.error || json.message || 'No se pudo validar el código' );
			}

			if ( ! json.valid ) {
				resetPromoPricing();
				promoNote.textContent = 'Código no válido. Se aplican los precios normales.';
				updateSummary();
				return;
			}

			promoState.type = json.type;
			promoState.free = !! json.free;
			promoState.paymentOptional = !! json.payment_optional;
			promoState.plans = json.type === 'current' && Array.isArray( json.plans ) ? json.plans : null;
			promoState.monthly = json.type === 'current' ? json.monthly : null;

			applyPlanPrices( effectivePlans() );
			if ( json.type === 'beca' ) {
				promoNote.textContent = '¡Beca del 100% aplicada! Tu inscripción será gratuita.';
			} else if ( json.type === 'current' ) {
				promoNote.textContent =
					'¡Código de alumno aplicado! Mensualidad de ' + money( json.monthly ) + '. El pago es opcional: puedes pagar ahora o después.';
			}
			promoNote.classList.add( 'is-success' );
			updateSummary();
		} catch ( err ) {
			promoNote.textContent = 'No se pudo validar el código. Intenta de nuevo.';
		}
	}

	if ( promoApplyBtn ) {
		promoApplyBtn.addEventListener( 'click', applyPromo );
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

		const plan = selectedPlan();
		if ( ! plan ) {
			showResult( 'Selecciona un paquete para continuar.', false );
			return;
		}

		const isMember = currentMode() === 'member';
		const fd = new FormData( form );
		const payload = {
			plan: fd.get( 'plan' ),
			group: currentGroup(),
			add_inscription: addonWrap && ! addonWrap.hidden && addonInput && addonInput.checked ? 1 : 0,
			promocode: ( fd.get( 'promocode' ) || '' ).toString().trim(),
			payment_mode: payLaterSelected() ? 'later' : 'now',
			trial_date: ( fd.get( 'trial_date' ) || '' ).toString().trim(),
			website: ( fd.get( 'website' ) || '' ).toString(),
		};

		if ( isMember ) {
			payload.member = true;
			payload.email = ( memberEmailInput?.value || '' ).toString().trim();
		} else {
			[ 'first_name', 'last_name', 'parent_name', 'address', 'email', 'phone', 'parent_phone', 'emergency_phone', 'dob' ].forEach( ( key ) => {
				payload[ key ] = ( fd.get( key ) || '' ).toString().trim();
			} );
		}

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}
		showResult( isMember ? 'Buscando tu registro...' : 'Procesando tu inscripción...', true );

		try {
			const { res, json } = await postJson( 'inscriptions', payload );

			if ( json && json.not_registered ) {
				const newModeRadio = $( 'input[name="inscription_mode"][value="new"]', form );
				if ( newModeRadio ) {
					newModeRadio.checked = true;
				}
				updateModeUi();
				const emailField = studentWrap ? $( 'input[name="email"]', studentWrap ) : null;
				if ( emailField ) {
					emailField.value = payload.email || '';
				}
				showResult( json.error || 'No encontramos tu registro. Completa el formulario para inscribirte.', false );
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
				return;
			}

			if ( ! res.ok || ! json.ok ) {
				throw new Error( json.error || json.message || 'No se pudo procesar la solicitud' );
			}
			if ( json.free ) {
				showResult( json.message || '¡Inscripción registrada!', true );
				form.reset();
				resetPromoPricing();
				updateModeUi();
				updateSummary();
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
				return;
			}
			if ( json.url ) {
				window.location.href = json.url;
				return;
			}
			throw new Error( 'Respuesta inesperada del servidor' );
		} catch ( err ) {
			showResult( err.message || 'No se pudo procesar la solicitud. Intenta de nuevo.', false );
			if ( submitBtn ) {
				submitBtn.disabled = false;
			}
		}
	} );

	// Return from Stripe Checkout.
	const params = new URLSearchParams( window.location.search );
	const status = params.get( 'inscription' );
	if ( status === 'success' ) {
		showResult( '¡Pago recibido! Tu inscripción quedó registrada. Te contactaremos pronto.', true );
	} else if ( status === 'cancel' ) {
		showResult( 'El pago fue cancelado. Puedes intentarlo de nuevo cuando quieras.', false );
	}
	if ( status ) {
		params.delete( 'inscription' );
		params.delete( 'session_id' );
		const query = params.toString();
		window.history.replaceState( {}, '', window.location.pathname + ( query ? `?${ query }` : '' ) );
	}

	updateModeUi();
	applyPlanPrices( effectivePlans() );
	updateSummary();
}

document.querySelectorAll( '[data-inscription-root]' ).forEach( initInscription );
