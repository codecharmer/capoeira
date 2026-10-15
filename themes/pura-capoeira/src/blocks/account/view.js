/**
 * Mi cuenta: discover the session (puraAuth), render the portal from /account/me, edit profile,
 * start the monthly payment, show Stripe/auth return banners.
 */

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );

const AUTH_ERRORS = {
	denied: 'Cancelaste el inicio de sesión. Puedes intentarlo de nuevo cuando quieras.',
	state: 'La sesión de inicio expiró. Vuelve a intentarlo.',
	code: 'No recibimos la autorización del proveedor. Vuelve a intentarlo.',
	exchange:
		'El proveedor no confirmó tu identidad. Vuelve a intentarlo en unos minutos.',
	token: 'No pudimos verificar tu identidad con el proveedor.',
	admin_account:
		'Ese correo pertenece a una cuenta del equipo. Entra por el acceso de administración.',
	user_create: 'No se pudo crear tu cuenta. Escríbenos por WhatsApp.',
	provider: 'Este método de acceso no está disponible.',
};

function money( amount, currency ) {
	return (
		new Intl.NumberFormat( 'es-MX', {
			style: 'currency',
			currency: currency || 'MXN',
		} ).format( amount ) +
		' ' +
		( currency || 'MXN' )
	);
}

function formatDate( mysql ) {
	if ( ! mysql ) {
		return '—';
	}
	const date = new Date( mysql.replace( ' ', 'T' ) );
	if ( Number.isNaN( date.getTime() ) ) {
		return mysql.slice( 0, 10 );
	}
	return date.toLocaleDateString( 'es-MX', {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

function initAccount( root ) {
	const notice = $( '[data-account-notice]', root );
	const loading = $( '[data-account-loading]', root );
	const loggedOut = $( '[data-account-logged-out]', root );
	const portal = $( '[data-account-portal]', root );

	const show = ( message, ok ) => {
		if ( ! notice ) {
			return;
		}
		notice.hidden = false;
		notice.textContent = message;
		notice.classList.toggle( 'is-success', !! ok );
		notice.classList.toggle( 'is-error', ! ok );
	};

	const setState = ( state ) => {
		root.dataset.accountState = state;
		if ( loading ) {
			loading.hidden = state !== 'pending';
		}
		if ( loggedOut ) {
			loggedOut.hidden = state !== 'logged-out';
		}
		if ( portal ) {
			portal.hidden = state !== 'logged-in';
		}
	};

	// Return banners (Stripe, auth errors) and the redirect target for the provider forms.
	const params = new URLSearchParams( window.location.search );
	const pago = params.get( 'pago' );
	const authError = params.get( 'auth_error' );
	const redirectTo = params.get( 'redirect_to' );
	if ( pago === 'success' ) {
		show( '¡Pago recibido! Tu mensualidad quedó registrada.', true );
	} else if ( pago === 'cancel' ) {
		show(
			'El pago fue cancelado. Puedes intentarlo de nuevo cuando quieras.',
			false
		);
	} else if ( authError ) {
		show(
			AUTH_ERRORS[ authError ] ||
				'No se pudo iniciar sesión. Vuelve a intentarlo.',
			false
		);
	}
	if ( redirectTo ) {
		root.querySelectorAll( '[data-account-redirect]' ).forEach(
			( input ) => {
				input.value = redirectTo;
			}
		);
	}
	if ( pago || authError ) {
		params.delete( 'pago' );
		params.delete( 'auth_error' );
		const query = params.toString();
		window.history.replaceState(
			{},
			'',
			window.location.pathname + ( query ? `?${ query }` : '' )
		);
	}

	if ( ! window.puraAuth ) {
		setState( 'logged-out' );
		return;
	}

	function renderHistory( rows ) {
		const body = $( '[data-account-history-rows]', root );
		if ( ! body ) {
			return;
		}
		const empty = $( '[data-account-history-empty]', root );
		body.textContent = '';
		rows.forEach( ( row ) => {
			const tr = document.createElement( 'tr' );
			const cells = [
				formatDate( row.paidAt || row.createdAt ),
				row.label + ( row.trialDate ? ` (${ row.trialDate })` : '' ),
				money( row.amount, row.currency ),
			];
			cells.forEach( ( text ) => {
				const td = document.createElement( 'td' );
				td.textContent = text;
				tr.appendChild( td );
			} );
			const status = document.createElement( 'td' );
			const pill = document.createElement( 'span' );
			pill.className = `status-pill status-pill--${ row.status }`;
			pill.textContent = row.statusLabel || row.status;
			status.appendChild( pill );
			tr.appendChild( status );
			body.appendChild( tr );
		} );
		if ( empty ) {
			empty.hidden = rows.length > 0;
		}
	}

	function render( data ) {
		const name = $( '[data-account-name]', root );
		const email = $( '[data-account-email]', root );
		const logout = $( '[data-account-logout]', root );
		const unlinked = $( '[data-account-unlinked]', root );
		const pay = $( '[data-account-pay]', root );
		const profile = $( '[data-account-profile]', root );
		const history = $( '[data-account-history]', root );

		const student = data.student;
		if ( name ) {
			name.textContent =
				( student && student.firstName ) || data.user.displayName || '';
		}
		if ( email ) {
			email.textContent = data.user.email || '';
		}
		if ( logout && data.logoutUrl ) {
			logout.href = data.logoutUrl;
		}

		if ( unlinked ) {
			unlinked.hidden = !! student;
			const wa = $( '[data-account-whatsapp]', unlinked );
			if ( wa && data.whatsappUrl ) {
				wa.href = data.whatsappUrl;
			}
		}
		if ( pay ) {
			pay.hidden = ! ( student && data.monthly );
			if ( student && data.monthly ) {
				const amount = $( '[data-account-monthly-amount]', pay );
				const note = $( '[data-account-monthly-note]', pay );
				if ( amount ) {
					amount.textContent = money(
						data.monthly.amount,
						data.monthly.currency
					);
				}
				if ( note ) {
					note.textContent =
						( data.monthly.currentStudent
							? 'Precio de alumno actual. '
							: '' ) +
						( student.group === 'kids'
							? 'Grupo niños.'
							: 'Grupo adultos.' ) +
						( student.lastPaidAt
							? ` Último pago: ${ formatDate(
									student.lastPaidAt
							  ) }.`
							: '' );
				}
			}
		}
		if ( profile ) {
			profile.hidden = ! student;
			if ( student ) {
				const studentName = $( '[data-account-student-name]', profile );
				if ( studentName ) {
					studentName.textContent =
						`${ student.firstName } ${ student.lastName }`.trim() +
						( student.email ? ` · ${ student.email }` : '' );
				}
				const form = $( '[data-account-profile-form]', profile );
				if ( form ) {
					form.phone.value = student.phone || '';
					form.emergency_phone.value = student.emergencyPhone || '';
					form.parent_name.value = student.parentName || '';
					form.parent_phone.value = student.parentPhone || '';
					form.address.value = student.address || '';
				}
			}
		}
		if ( history ) {
			history.hidden = ! student;
			if ( student ) {
				renderHistory( data.inscriptions || [] );
			}
		}
		setState( 'logged-in' );
	}

	async function load() {
		const { res, json } = await window.puraAuth.me();
		if ( ! res.ok || ! json.ok ) {
			setState( 'logged-out' );
			return;
		}
		render( json );
	}

	const profileForm = $( '[data-account-profile-form]', root );
	if ( profileForm ) {
		profileForm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const button = profileForm.querySelector( 'button[type="submit"]' );
			if ( button ) {
				button.disabled = true;
			}
			const fd = new FormData( profileForm );
			const body = {};
			[
				'phone',
				'emergency_phone',
				'parent_name',
				'parent_phone',
				'address',
			].forEach( ( key ) => {
				body[ key ] = ( fd.get( key ) || '' ).toString().trim();
			} );
			try {
				const { res, json } = await window.puraAuth.request(
					'account/profile',
					{ body }
				);
				if ( ! res.ok || ! json.ok ) {
					throw new Error(
						json.error || 'No se pudieron guardar tus datos.'
					);
				}
				show( 'Datos guardados.', true );
				render( json );
			} catch ( err ) {
				show( err.message, false );
			} finally {
				if ( button ) {
					button.disabled = false;
				}
			}
		} );
	}

	const payButton = $( '[data-account-pay-button]', root );
	if ( payButton ) {
		payButton.addEventListener( 'click', async () => {
			payButton.disabled = true;
			show( 'Preparando el pago…', true );
			try {
				const { res, json } = await window.puraAuth.request(
					'account/pay-monthly',
					{ body: {} }
				);
				if ( ! res.ok || ! json.ok || ! json.url ) {
					throw new Error(
						json.error || 'No se pudo iniciar el pago.'
					);
				}
				window.location.href = json.url;
			} catch ( err ) {
				show( err.message, false );
				payButton.disabled = false;
			}
		} );
	}

	load();
}

document.querySelectorAll( '[data-account-root]' ).forEach( initAccount );
