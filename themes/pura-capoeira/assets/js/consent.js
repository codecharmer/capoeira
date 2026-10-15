/**
 * Consent banner. Classic script (not a module) so block view scripts can depend on it.
 *
 * Preference: localStorage `pura_consent` = { v: 1, embeds: boolean, ts: ISO string }.
 * API: window.puraConsent = { get(), set({ embeds }), open(), onChange(cb) }.
 * Event: `pura:consent` on document with `detail` = the stored preference.
 */
( function () {
	const KEY = 'pura_consent';
	const VERSION = 1;
	const EVENT = 'pura:consent';

	let memory = null;

	function read() {
		try {
			const raw = window.localStorage.getItem( KEY );
			if ( raw ) {
				const parsed = JSON.parse( raw );
				if ( parsed && parsed.v === VERSION ) {
					return {
						v: VERSION,
						embeds: !! parsed.embeds,
						ts: parsed.ts || '',
					};
				}
			}
		} catch ( _err ) {
			// Storage blocked or corrupt: fall through to memory.
		}
		return memory;
	}

	function write( pref ) {
		memory = pref;
		try {
			window.localStorage.setItem( KEY, JSON.stringify( pref ) );
		} catch ( _err ) {
			// Private mode or blocked storage: the banner will show again next load.
		}
	}

	const root = document.querySelector( '[data-consent-root]' );
	const panel = root && root.querySelector( '[data-consent-panel]' );
	const embedsInput = root && root.querySelector( '[data-consent-embeds]' );
	const toggleBtn = root && root.querySelector( '[data-consent-toggle]' );
	const saveBtn = root && root.querySelector( '[data-consent-save]' );
	const acceptBtn = root && root.querySelector( '[data-consent-accept]' );
	const rejectBtn = root && root.querySelector( '[data-consent-reject]' );

	function showPanel( open ) {
		if ( ! panel || ! toggleBtn ) {
			return;
		}
		panel.hidden = ! open;
		toggleBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( saveBtn ) {
			saveBtn.hidden = ! open;
		}
		if ( acceptBtn ) {
			acceptBtn.hidden = open;
		}
		if ( rejectBtn ) {
			rejectBtn.hidden = open;
		}
	}

	function hide() {
		if ( root ) {
			root.hidden = true;
			root.classList.remove( 'is-open' );
		}
	}

	function show( withPanel, focus ) {
		if ( ! root ) {
			return;
		}
		const current = read();
		if ( embedsInput ) {
			embedsInput.checked = !! ( current && current.embeds );
		}
		showPanel( !! withPanel );
		root.hidden = false;
		// Next frame so the transition runs from the hidden state.
		window.requestAnimationFrame( () => root.classList.add( 'is-open' ) );
		if ( focus ) {
			root.focus( { preventScroll: true } );
		}
	}

	function set( next ) {
		const pref = {
			v: VERSION,
			embeds: !! ( next && next.embeds ),
			ts: new Date().toISOString(),
		};
		write( pref );
		hide();
		document.dispatchEvent( new CustomEvent( EVENT, { detail: pref } ) );
		return pref;
	}

	const api = {
		get: () =>
			read() || { v: VERSION, embeds: false, ts: '', undecided: true },
		set,
		open: () => show( true, true ),
		onChange: ( cb ) =>
			document.addEventListener( EVENT, ( e ) => cb( e.detail ) ),
	};
	window.puraConsent = api;

	if ( root ) {
		if ( acceptBtn ) {
			acceptBtn.addEventListener( 'click', () =>
				set( { embeds: true } )
			);
		}
		if ( rejectBtn ) {
			rejectBtn.addEventListener( 'click', () =>
				set( { embeds: false } )
			);
		}
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', () =>
				set( { embeds: !! ( embedsInput && embedsInput.checked ) } )
			);
		}
		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', () =>
				showPanel( panel && panel.hidden )
			);
		}
		root.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' ) {
				hide();
			}
		} );
		document.addEventListener( 'click', ( e ) => {
			if (
				e.target.closest &&
				e.target.closest( '[data-cookie-preferences]' )
			) {
				e.preventDefault();
				api.open();
			}
		} );

		if ( ! read() ) {
			show( false, false );
		}
	}
} )();
