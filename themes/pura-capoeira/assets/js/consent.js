/**
 * Cookie notice. Classic script (not a module) so block view scripts can depend on it.
 *
 * Nothing on the site needs consent (no analytics, no third-party embeds); the notice only informs
 * and is dismissed once. Acknowledgement: localStorage `pura_consent` = { v: 2, ts: ISO string }.
 * API: window.puraConsent = { acknowledged(), acknowledge(), open() }.
 */
( function () {
	const KEY = 'pura_consent';
	const VERSION = 2;

	let memory = false;

	function acknowledged() {
		try {
			const raw = window.localStorage.getItem( KEY );
			if ( raw ) {
				const parsed = JSON.parse( raw );
				if ( parsed && parsed.v === VERSION ) {
					return true;
				}
			}
		} catch ( _err ) {
			// Storage blocked or corrupt: fall through to memory.
		}
		return memory;
	}

	const root = document.querySelector( '[data-consent-root]' );
	const acceptBtn = root && root.querySelector( '[data-consent-accept]' );

	function hide() {
		if ( root ) {
			root.hidden = true;
			root.classList.remove( 'is-open' );
		}
	}

	function show( focus ) {
		if ( ! root ) {
			return;
		}
		root.hidden = false;
		// Next frame so the transition runs from the hidden state.
		window.requestAnimationFrame( () => root.classList.add( 'is-open' ) );
		if ( focus ) {
			root.focus( { preventScroll: true } );
		}
	}

	function acknowledge() {
		memory = true;
		try {
			window.localStorage.setItem(
				KEY,
				JSON.stringify( { v: VERSION, ts: new Date().toISOString() } )
			);
		} catch ( _err ) {
			// Private mode or blocked storage: the notice shows again next load.
		}
		hide();
	}

	window.puraConsent = {
		acknowledged,
		acknowledge,
		open: () => show( true ),
	};

	if ( root ) {
		if ( acceptBtn ) {
			acceptBtn.addEventListener( 'click', acknowledge );
		}
		root.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' ) {
				hide();
			}
		} );
		if ( ! acknowledged() ) {
			show( false );
		}
	}
} )();
