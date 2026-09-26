/**
 * Store. Port of the static site's initStore() against the plugin REST API, plus a
 * localStorage cart (ids and quantities only; prices are always re-read from the catalog).
 */

const CART_KEY = 'pura_cart_v1';
const CART_TTL_MS = 7 * 24 * 60 * 60 * 1000;

function restUrl( path ) {
	const base =
		( window.puraConfig && window.puraConfig.restUrl ) ||
		'/wp-json/pura/v1/';
	return base.replace( /\/?$/, '/' ) + path;
}

async function getJson( path ) {
	const res = await fetch( restUrl( path ), {
		headers: { Accept: 'application/json' },
		cache: 'no-store',
	} );
	const json = await res.json().catch( () => ( {} ) );
	return { res, json };
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
	const json = await res.json().catch( () => ( {} ) );
	return { res, json };
}

function escapeHtml( value ) {
	return String( value ?? '' ).replace(
		/[&<>"']/g,
		( c ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ c ]
	);
}

function readStoredCart() {
	try {
		const raw = window.localStorage.getItem( CART_KEY );
		if ( ! raw ) {
			return [];
		}
		const parsed = JSON.parse( raw );
		if (
			! parsed ||
			! Array.isArray( parsed.items ) ||
			Date.now() - ( parsed.savedAt || 0 ) > CART_TTL_MS
		) {
			return [];
		}
		return parsed.items;
	} catch ( _err ) {
		return [];
	}
}

function writeStoredCart( cart ) {
	try {
		const items = cart.map( ( item ) => ( {
			productId: item.productId,
			variantId: item.variant.id,
			quantity: item.quantity,
		} ) );
		window.localStorage.setItem(
			CART_KEY,
			JSON.stringify( { items, savedAt: Date.now() } )
		);
	} catch ( _err ) {
		// storage unavailable
	}
}

function clearStoredCart() {
	try {
		window.localStorage.removeItem( CART_KEY );
	} catch ( _err ) {
		// storage unavailable
	}
}

function initStore( root ) {
	const grid = root.querySelector( '[data-store-grid]' );
	const feedback = root.querySelector( '[data-store-feedback]' );
	const cartItemsEl = root.querySelector( '[data-store-cart-items]' );
	const totalEl = root.querySelector( '[data-store-total]' );
	const orderForm = root.querySelector( '[data-store-order-form]' );

	if ( ! grid || ! feedback || ! cartItemsEl || ! totalEl || ! orderForm ) {
		return;
	}

	const refreshBtn = root.querySelector( '[data-store-refresh]' );
	const subtotalEl = root.querySelector( '[data-store-subtotal]' );
	const shippingCostEl = root.querySelector( '[data-store-shipping-cost]' );
	const shippingBox = root.querySelector( '[data-store-shipping]' );
	const shippingOptionsEl = root.querySelector(
		'[data-store-shipping-options]'
	);
	const payBtn = root.querySelector( '[data-store-pay]' );
	const resultEl = root.querySelector( '[data-store-result]' );
	const placeholder = root.dataset.placeholder || '';

	const state = {
		products: [],
		details: new Map(),
		cart: [],
		currency: ( window.puraConfig && window.puraConfig.currency ) || 'MXN',
		priceMultiplier: 1,
		rates: [],
		shipping: null,
		pendingCart: readStoredCart(),
	};

	const asNumber = ( value ) => {
		const num = Number.parseFloat( value );
		return Number.isFinite( num ) ? num : 0;
	};

	const money = ( value ) => {
		try {
			return new Intl.NumberFormat( 'es-MX', {
				style: 'currency',
				currency: state.currency || 'MXN',
			} ).format( asNumber( value ) );
		} catch ( _err ) {
			return `$${ asNumber( value ).toFixed( 2 ) }`;
		}
	};

	const setFeedback = ( msg, isError = false ) => {
		feedback.textContent = msg;
		feedback.classList.toggle( 'is-error', Boolean( isError ) );
	};

	const getProductTitle = ( product ) =>
		product?.sync_product?.name || product?.name || 'Producto';
	const getProductThumb = ( product ) =>
		product?.sync_product?.thumbnail_url ||
		product?.thumbnail_url ||
		placeholder;
	const getProductVariants = ( productId ) => {
		const detail = state.details.get( productId );
		return Array.isArray( detail?.sync_variants )
			? detail.sync_variants
			: [];
	};
	const getVariantPrice = ( variant ) =>
		asNumber( variant?.retail_price || variant?.price || 0 );
	const displayPrice = ( variant ) =>
		getVariantPrice( variant ) * ( state.priceMultiplier || 1 );

	const subtotal = () =>
		state.cart.reduce(
			( sum, item ) => sum + displayPrice( item.variant ) * item.quantity,
			0
		);
	const shippingCost = () =>
		state.shipping ? asNumber( state.shipping.rate ) : 0;
	const grandTotal = () => subtotal() + shippingCost();

	const syncTotals = () => {
		if ( subtotalEl ) {
			subtotalEl.textContent = money( subtotal() );
		}
		if ( shippingCostEl ) {
			shippingCostEl.textContent = state.shipping
				? money( shippingCost() )
				: '—';
		}
		totalEl.textContent = money( grandTotal() );
	};

	const updatePayState = () => {
		if ( payBtn ) {
			payBtn.disabled = ! ( state.cart.length && state.shipping );
		}
	};

	const invalidateShipping = () => {
		state.rates = [];
		state.shipping = null;
		if ( shippingOptionsEl ) {
			shippingOptionsEl.innerHTML = '';
		}
		if ( shippingBox ) {
			shippingBox.hidden = true;
		}
		syncTotals();
		updatePayState();
	};

	const renderShipping = () => {
		if ( ! shippingOptionsEl || ! shippingBox ) {
			return;
		}
		if ( ! state.rates.length ) {
			shippingBox.hidden = true;
			return;
		}
		shippingOptionsEl.innerHTML = state.rates
			.map(
				( r, i ) => `
			<label class="store-shipping__option">
				<input type="radio" name="shipping-rate" value="${ i }" ${
					i === 0 ? 'checked' : ''
				} />
				<span>${ escapeHtml( r.name || 'Envío' ) }</span>
				<strong>${ money( r.rate ) }</strong>
			</label>`
			)
			.join( '' );
		shippingBox.hidden = false;
		state.shipping = state.rates[ 0 ] || null;
		syncTotals();
		updatePayState();
	};

	const renderCart = () => {
		cartItemsEl.innerHTML = '';
		if ( ! state.cart.length ) {
			cartItemsEl.innerHTML =
				'<p class="store-empty">Tu carrito está vacío.</p>';
			syncTotals();
			writeStoredCart( state.cart );
			return;
		}

		state.cart.forEach( ( item, idx ) => {
			const row = document.createElement( 'div' );
			row.className = 'store-cart-item';
			row.innerHTML = `
			<div>
				<strong>${ escapeHtml( item.title ) }</strong>
				<span>${ escapeHtml( item.variantName ) }</span>
			</div>
			<div class="store-cart-item__actions">
				<button type="button" data-cart-minus="${ idx }" aria-label="Restar">-</button>
				<span>${ item.quantity }</span>
				<button type="button" data-cart-plus="${ idx }" aria-label="Sumar">+</button>
				<button type="button" data-cart-remove="${ idx }" aria-label="Eliminar">x</button>
			</div>`;
			cartItemsEl.appendChild( row );
		} );

		syncTotals();
		writeStoredCart( state.cart );
	};

	const addToCart = ( product, variant, quantity = 1, quiet = false ) => {
		if ( ! variant || ! variant.id ) {
			if ( ! quiet ) {
				setFeedback(
					'Selecciona una variante disponible para comprar.',
					true
				);
			}
			return;
		}
		const existing = state.cart.find(
			( entry ) => entry.variant.id === variant.id
		);
		if ( existing ) {
			existing.quantity += quantity;
		} else {
			state.cart.push( {
				productId: product.id,
				title: getProductTitle( product ),
				variantName: variant.name || 'Variante',
				variant,
				quantity,
			} );
		}
		renderCart();
		invalidateShipping();
		if ( ! quiet ) {
			setFeedback( 'Producto agregado al carrito.' );
		}
	};

	// Rebuild a stored cart once catalog details are available.
	const restoreCart = () => {
		if ( ! state.pendingCart.length ) {
			return;
		}
		const pending = state.pendingCart;
		state.pendingCart = [];
		pending.forEach( ( saved ) => {
			const product = state.products.find(
				( p ) => p.id === saved.productId
			);
			const variant = getProductVariants( saved.productId ).find(
				( v ) => v.id === saved.variantId
			);
			if ( product && variant ) {
				addToCart(
					product,
					variant,
					Math.max( 1, Number( saved.quantity ) || 1 ),
					true
				);
			}
		} );
		if ( state.cart.length ) {
			setFeedback( 'Recuperamos tu carrito.' );
		}
	};

	const renderProducts = () => {
		grid.innerHTML = '';
		if ( ! state.products.length ) {
			grid.innerHTML =
				'<p class="store-empty">No hay productos publicados por ahora.</p>';
			return;
		}

		state.products.forEach( ( product ) => {
			const variants = getProductVariants( product.id );
			const hasDetail = state.details.has( product.id );
			const firstVariant = variants[ 0 ] || null;

			const card = document.createElement( 'article' );
			card.className = 'store-card';
			card.setAttribute( 'data-product-id', String( product.id ) );

			const optionsHtml = variants
				.map(
					( v ) =>
						`<option value="${ v.id }">${ escapeHtml(
							v.name || 'Variante'
						) }</option>`
				)
				.join( '' );
			let variantControl =
				'<p class="store-card__loading">Cargando opciones...</p>';
			if ( hasDetail ) {
				variantControl = variants.length
					? `<select class="store-card__variant" data-variant-select aria-label="Variante">${ optionsHtml }</select>`
					: '<p class="store-card__loading">Sin variantes disponibles.</p>';
			}
			const priceText = hasDetail
				? money( displayPrice( firstVariant ) )
				: '...';

			card.innerHTML = `
			<div class="store-card__image" style="background-image:url('${ escapeHtml(
				getProductThumb( product )
			) }')"></div>
			<div class="store-card__body">
				<h3>${ escapeHtml( getProductTitle( product ) ) }</h3>
				${ variantControl }
				<div class="store-card__bottom">
					<strong data-price>${ priceText }</strong>
					<button class="btn btn--primary" type="button" data-add-product="${
						product.id
					}" ${
						hasDetail && variants.length ? '' : 'disabled'
					}>Agregar</button>
				</div>
			</div>`;
			grid.appendChild( card );
		} );
	};

	const loadDetail = async ( productId ) => {
		if ( state.details.has( productId ) ) {
			return;
		}
		try {
			const { res, json } = await getJson(
				`store/products/${ productId }`
			);
			if ( ! res.ok || ! json.ok || ! json.product ) {
				throw new Error( json.error || 'detalle no disponible' );
			}
			state.details.set( productId, json.product );
		} catch ( _err ) {
			state.details.set( productId, { sync_variants: [] } );
		}
	};

	const loadAllDetails = async () => {
		await Promise.all( state.products.map( ( p ) => loadDetail( p.id ) ) );
		renderProducts();
		restoreCart();
	};

	const fetchProducts = async ( refresh = false ) => {
		setFeedback( 'Consultando catálogo...' );
		try {
			const { res, json } = await getJson(
				refresh ? 'store/products?refresh=1' : 'store/products'
			);
			if ( ! res.ok || ! json.ok ) {
				throw new Error( json.error || 'Error al cargar productos' );
			}
			state.products = Array.isArray( json.products )
				? json.products
				: [];
			state.details.clear();
			renderProducts();
			setFeedback(
				`Catálogo actualizado: ${ state.products.length } productos.`
			);
			await loadAllDetails();
		} catch ( _err ) {
			state.products = [];
			renderProducts();
			setFeedback(
				'No fue posible cargar la tienda. Intenta de nuevo más tarde.',
				true
			);
		}
	};

	grid.addEventListener( 'change', ( e ) => {
		const select = e.target.closest( '[data-variant-select]' );
		if ( ! select ) {
			return;
		}
		const card = select.closest( '[data-product-id]' );
		if ( ! card ) {
			return;
		}
		const productId = Number.parseInt(
			card.getAttribute( 'data-product-id' ) || '',
			10
		);
		const variantId = Number.parseInt( select.value || '', 10 );
		const variant = getProductVariants( productId ).find(
			( v ) => v.id === variantId
		);
		const priceEl = card.querySelector( '[data-price]' );
		if ( variant && priceEl ) {
			priceEl.textContent = money( displayPrice( variant ) );
		}
	} );

	grid.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-add-product]' );
		if ( ! btn ) {
			return;
		}
		const productId = Number.parseInt(
			btn.getAttribute( 'data-add-product' ) || '',
			10
		);
		const product = state.products.find( ( p ) => p.id === productId );
		if ( ! product ) {
			return;
		}
		const card = btn.closest( '[data-product-id]' );
		const select = card
			? card.querySelector( '[data-variant-select]' )
			: null;
		const variants = getProductVariants( productId );
		const variantId = select
			? Number.parseInt( select.value || '', 10 )
			: NaN;
		const variant =
			variants.find( ( v ) => v.id === variantId ) ||
			variants[ 0 ] ||
			null;
		addToCart( product, variant );
	} );

	cartItemsEl.addEventListener( 'click', ( e ) => {
		const minus = e.target.closest( '[data-cart-minus]' );
		const plus = e.target.closest( '[data-cart-plus]' );
		const remove = e.target.closest( '[data-cart-remove]' );

		if ( minus ) {
			const item =
				state.cart[
					Number.parseInt(
						minus.getAttribute( 'data-cart-minus' ) || '',
						10
					)
				];
			if ( item ) {
				item.quantity = Math.max( 1, item.quantity - 1 );
				renderCart();
				invalidateShipping();
			}
		}
		if ( plus ) {
			const item =
				state.cart[
					Number.parseInt(
						plus.getAttribute( 'data-cart-plus' ) || '',
						10
					)
				];
			if ( item ) {
				item.quantity += 1;
				renderCart();
				invalidateShipping();
			}
		}
		if ( remove ) {
			const idx = Number.parseInt(
				remove.getAttribute( 'data-cart-remove' ) || '',
				10
			);
			if ( Number.isFinite( idx ) ) {
				state.cart.splice( idx, 1 );
				renderCart();
				invalidateShipping();
			}
		}
	} );

	if ( shippingOptionsEl ) {
		shippingOptionsEl.addEventListener( 'change', ( e ) => {
			const radio = e.target.closest( 'input[name="shipping-rate"]' );
			if ( ! radio ) {
				return;
			}
			state.shipping =
				state.rates[ Number.parseInt( radio.value || '', 10 ) ] || null;
			syncTotals();
			updatePayState();
		} );
	}

	const getRecipient = () => {
		const formData = new FormData( orderForm );
		return {
			name: String( formData.get( 'name' ) || '' ).trim(),
			email: String( formData.get( 'email' ) || '' ).trim(),
			address1: String( formData.get( 'address1' ) || '' ).trim(),
			city: String( formData.get( 'city' ) || '' ).trim(),
			state_code: String( formData.get( 'state_code' ) || '' ).trim(),
			country_code: String( formData.get( 'country_code' ) || '' )
				.trim()
				.toUpperCase(),
			zip: String( formData.get( 'zip' ) || '' ).trim(),
		};
	};
	const recipientReady = ( r ) =>
		r.name && r.email && r.address1 && r.city && r.country_code && r.zip;

	orderForm.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		if ( ! state.cart.length ) {
			setFeedback(
				'Agrega al menos un producto antes de calcular el envío.',
				true
			);
			return;
		}
		const recipient = getRecipient();
		if ( ! recipientReady( recipient ) ) {
			setFeedback( 'Completa todos los datos de envío.', true );
			return;
		}
		const items = state.cart.map( ( item ) => ( {
			variant_id: item.variant.variant_id,
			quantity: item.quantity,
		} ) );
		setFeedback( 'Calculando envío...' );
		try {
			const { res, json } = await postJson( 'store/shipping', {
				recipient,
				items,
			} );
			if ( ! res.ok || ! json.ok ) {
				throw new Error( json.error || 'No se pudo calcular el envío' );
			}
			state.currency = json.currency || state.currency;
			state.rates = Array.isArray( json.rates ) ? json.rates : [];
			if ( ! state.rates.length ) {
				invalidateShipping();
				setFeedback(
					'No hay métodos de envío para esa dirección.',
					true
				);
				return;
			}
			renderShipping();
			setFeedback( 'Selecciona un método de envío y continúa al pago.' );
		} catch ( err ) {
			invalidateShipping();
			setFeedback(
				err.message ||
					'No se pudo calcular el envío. Verifica la dirección.',
				true
			);
		}
	} );

	if ( payBtn ) {
		payBtn.addEventListener( 'click', async () => {
			if ( ! state.cart.length || ! state.shipping ) {
				setFeedback(
					'Calcula el envío y selecciona un método antes de pagar.',
					true
				);
				return;
			}
			const recipient = getRecipient();
			if ( ! recipientReady( recipient ) ) {
				setFeedback( 'Completa todos los datos de envío.', true );
				return;
			}
			const payload = {
				recipient,
				items: state.cart.map( ( item ) => ( {
					sync_variant_id: item.variant.id,
					quantity: item.quantity,
					name: `${ item.title } — ${ item.variantName }`,
				} ) ),
				shipping_id: state.shipping.id,
				shipping_name: state.shipping.name || 'Envío',
				shipping_rate: asNumber( state.shipping.rate ),
			};
			payBtn.disabled = true;
			setFeedback( 'Redirigiendo a pago seguro con Stripe...' );
			try {
				const { res, json } = await postJson(
					'store/checkout',
					payload
				);
				if ( ! res.ok || ! json.ok || ! json.url ) {
					throw new Error(
						json.error || 'No se pudo iniciar el pago'
					);
				}
				window.location.href = json.url;
			} catch ( err ) {
				payBtn.disabled = false;
				setFeedback(
					err.message ||
						'No se pudo iniciar el pago. Intenta de nuevo.',
					true
				);
			}
		} );
	}

	const showResult = ( msg, ok ) => {
		if ( ! resultEl ) {
			return;
		}
		resultEl.hidden = false;
		resultEl.textContent = msg;
		resultEl.classList.toggle( 'is-success', ok );
		resultEl.classList.toggle( 'is-error', ! ok );
	};

	const handleCheckoutReturn = () => {
		const params = new URLSearchParams( window.location.search );
		const status = params.get( 'checkout' );
		if ( ! status ) {
			return;
		}
		if ( status === 'success' ) {
			showResult(
				'¡Pago recibido! Tu pedido se envió a producción. Recibirás un correo de confirmación.',
				true
			);
			state.cart = [];
			state.pendingCart = [];
			clearStoredCart();
			renderCart();
			invalidateShipping();
			orderForm.reset();
		} else if ( status === 'cancel' ) {
			showResult( 'Pago cancelado. Tu carrito sigue disponible.', false );
		}
		params.delete( 'checkout' );
		params.delete( 'session_id' );
		const query = params.toString();
		window.history.replaceState(
			{},
			'',
			window.location.pathname + ( query ? `?${ query }` : '' )
		);
	};

	if ( refreshBtn ) {
		refreshBtn.addEventListener( 'click', () => fetchProducts( true ) );
	}

	const fetchConfig = async () => {
		try {
			const { res, json } = await getJson( 'store/config' );
			if ( res.ok && json.ok ) {
				state.currency = json.currency || state.currency;
				const mult = Number.parseFloat( json.price_multiplier );
				if ( Number.isFinite( mult ) && mult > 0 ) {
					state.priceMultiplier = mult;
				}
			}
		} catch ( _err ) {
			// defaults stand
		}
	};

	renderCart();
	updatePayState();
	handleCheckoutReturn();
	fetchConfig().then( () => fetchProducts() );
}

document.querySelectorAll( '[data-store-root]' ).forEach( initStore );
