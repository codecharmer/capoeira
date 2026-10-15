/**
 * window.puraAuth — session discovery and signed REST calls for the student portal.
 *
 * Page HTML is cached for everyone, so nothing user-specific is printed in it. Instead the browser
 * asks admin-ajax (never cached, cookie-authenticated) for a fresh REST nonce: a nonce means the
 * visitor is signed in; "0"/400 means they are not.
 */
( function () {
	let noncePromise = null;

	function config() {
		return window.puraConfig || {};
	}

	function restUrl( path ) {
		const base = config().restUrl || '/wp-json/pura/v1/';
		return base.replace( /\/?$/, '/' ) + path;
	}

	function nonce() {
		if ( ! noncePromise ) {
			const url = ( config().ajaxUrl || '/wp-admin/admin-ajax.php' ) + '?action=rest-nonce';
			noncePromise = fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
				.then( ( res ) => ( res.ok ? res.text() : '' ) )
				.then( ( text ) => {
					const value = ( text || '' ).trim();
					return value && value !== '0' && value !== '-1' ? value : null;
				} )
				.catch( () => null );
		}
		return noncePromise;
	}

	async function request( path, options ) {
		const opts = options || {};
		const token = await nonce();
		if ( ! token ) {
			return { res: { ok: false, status: 401 }, json: { ok: false, error: 'login_required', login_required: true } };
		}
		const headers = Object.assign(
			{ Accept: 'application/json', 'X-WP-Nonce': token },
			opts.body ? { 'Content-Type': 'application/json' } : {},
			opts.headers || {}
		);
		const res = await fetch( restUrl( path ), {
			method: opts.method || ( opts.body ? 'POST' : 'GET' ),
			credentials: 'same-origin',
			cache: 'no-store',
			headers,
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} );
		let json = {};
		try {
			json = await res.json();
		} catch ( _err ) {
			json = {};
		}
		return { res, json };
	}

	window.puraAuth = {
		nonce,
		request,
		me: () => request( 'account/me' ),
		isLoggedIn: () => nonce().then( ( token ) => !! token ),
	};
} )();
