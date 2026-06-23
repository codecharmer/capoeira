/* =========================================================
   Pura Capoeira Cuernavaca — main.js
   Vanilla JS. No frameworks.
   ========================================================= */
(function () {
  'use strict';

  /* ----- Helpers ----- */
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ----- Mobile menu ----- */
  function initMobileMenu() {
    const toggle = $('[data-testid="menu-toggle"]');
    const close = $('[data-testid="menu-close"]');
    const menu = $('[data-testid="mobile-menu"]');
    if (!toggle || !menu) return;

    const open = () => {
      menu.classList.add('is-open');
      menu.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      const first = menu.querySelector('a, button');
      if (first) first.focus();
    };
    const shut = () => {
      menu.classList.remove('is-open');
      menu.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      toggle.focus();
    };
    toggle.addEventListener('click', open);
    close && close.addEventListener('click', shut);
    menu.addEventListener('click', (e) => {
      if (e.target.tagName === 'A') shut();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) shut();
    });
  }

  /* ----- Reveal on scroll ----- */
  function initReveal() {
    const els = $$('.reveal');
    if (!('IntersectionObserver' in window) || els.length === 0) {
      els.forEach((el) => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    els.forEach((el) => io.observe(el));
  }

  /* ----- Gallery ----- */
  const PLACEHOLDER_THUMB =
    'https://images.unsplash.com/photo-1641688587256-7b6549157cef?crop=entropy&cs=srgb&fm=jpg&w=800&q=70';

  function renderGalleryCards(items) {
    const grid = $('[data-testid="gallery-grid"]');
    if (!grid) return;
    grid.innerHTML = '';
    if (!items.length) {
      grid.innerHTML =
        '<p class="text-muted" style="grid-column:1/-1;text-align:center;padding:2rem;">No hay videos en esta categoría todavía.</p>';
      return;
    }
    items.forEach((it, idx) => {
      const card = document.createElement('article');
      card.className = 'video-card reveal';
      card.setAttribute('data-testid', `video-card-${idx}`);
      const thumb = it.thumbnail || PLACEHOLDER_THUMB;
      const dateStr = it.date
        ? new Date(it.date).toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
          })
        : '';
      card.innerHTML = `
        <a href="${it.url}" target="_blank" rel="noopener noreferrer" class="video-card__thumb-link" data-testid="video-card-link-${idx}" aria-label="Ver video: ${it.title}">
          <div class="video-card__thumb" style="background-image:url('${thumb}')">
            <span class="video-card__play" aria-hidden="true"></span>
          </div>
        </a>
        <div class="video-card__body">
          <span class="video-card__cat">${it.category || ''}</span>
          <h3 class="video-card__title">${it.title}</h3>
          <span class="video-card__date">${dateStr}</span>
          <p class="video-card__desc">${it.description || ''}</p>
          <a class="video-card__link" href="${it.url}" target="_blank" rel="noopener noreferrer" data-testid="video-card-cta-${idx}">
            Ver video <span aria-hidden="true">→</span>
          </a>
        </div>
      `;
      grid.appendChild(card);
    });
    initReveal();
  }

  function showGalleryFallback() {
    const grid = $('[data-testid="gallery-grid"]');
    const fallback = $('[data-testid="gallery-fallback"]');
    if (grid) grid.style.display = 'none';
    if (fallback) fallback.hidden = false;
  }

  function initGallery() {
    const grid = $('[data-testid="gallery-grid"]');
    if (!grid) return;
    let allItems = [];
    let activeCategory = 'Todos';

    const applyFilter = () => {
      const filtered =
        activeCategory === 'Todos'
          ? allItems
          : allItems.filter(
              (it) => (it.category || '').toLowerCase() === activeCategory.toLowerCase()
            );
      renderGalleryCards(filtered);
    };

    fetch('data/gallery.json', { cache: 'no-store' })
      .then((res) => {
        if (!res.ok) throw new Error('No se pudo cargar la galería');
        return res.json();
      })
      .then((data) => {
        if (!Array.isArray(data) || data.length === 0) {
          showGalleryFallback();
          return;
        }
        allItems = data;
        renderGalleryCards(allItems);
      })
      .catch((err) => {
        console.warn('Gallery load failed:', err);
        showGalleryFallback();
      });

    $$('[data-testid^="gallery-filter-"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('[data-testid^="gallery-filter-"]').forEach((b) =>
          b.classList.remove('is-active')
        );
        btn.classList.add('is-active');
        activeCategory = btn.dataset.category || 'Todos';
        applyFilter();
      });
    });
  }

  /* ----- Store (Printful) ----- */
  function initStore() {
    const root = document.querySelector('[data-store-root]');
    if (!root) return;

    const grid = root.querySelector('[data-store-grid]');
    const feedback = root.querySelector('[data-store-feedback]');
    const refreshBtn = root.querySelector('[data-store-refresh]');
    const cartItemsEl = root.querySelector('[data-store-cart-items]');
    const totalEl = root.querySelector('[data-store-total]');
    const subtotalEl = root.querySelector('[data-store-subtotal]');
    const shippingCostEl = root.querySelector('[data-store-shipping-cost]');
    const shippingBox = root.querySelector('[data-store-shipping]');
    const shippingOptionsEl = root.querySelector('[data-store-shipping-options]');
    const payBtn = root.querySelector('[data-store-pay]');
    const resultEl = root.querySelector('[data-store-result]');
    const orderForm = root.querySelector('[data-store-order-form]');

    if (!grid || !feedback || !cartItemsEl || !totalEl || !orderForm) return;

    const state = {
      products: [],
      details: new Map(),
      cart: [],
      currency: 'MXN',
      priceMultiplier: 1,
      rates: [],
      shipping: null,
    };

    const asNumber = (value) => {
      const num = Number.parseFloat(value);
      return Number.isFinite(num) ? num : 0;
    };

    const money = (value) => {
      try {
        return new Intl.NumberFormat('es-MX', {
          style: 'currency',
          currency: state.currency || 'MXN',
        }).format(asNumber(value));
      } catch (_err) {
        return `$${asNumber(value).toFixed(2)}`;
      }
    };

    const setFeedback = (msg, isError = false) => {
      feedback.textContent = msg;
      feedback.classList.toggle('is-error', Boolean(isError));
    };

    const getProductTitle = (product) => {
      if (product?.sync_product?.name) return product.sync_product.name;
      if (product?.name) return product.name;
      return 'Producto';
    };

    const getProductThumb = (product) => {
      return (
        product?.sync_product?.thumbnail_url ||
        product?.thumbnail_url ||
        PLACEHOLDER_THUMB
      );
    };

    const getProductVariants = (productId) => {
      const detail = state.details.get(productId);
      return Array.isArray(detail?.sync_variants) ? detail.sync_variants : [];
    };

    const getVariantPrice = (variant) => {
      return asNumber(variant?.retail_price || variant?.price || 0);
    };

    // Printful prices are stored in USD; convert to the store currency (MXN)
    // for everything shown to the customer. The raw USD value is still sent to
    // the backend, which applies the same multiplier when charging.
    const displayPrice = (variant) => getVariantPrice(variant) * (state.priceMultiplier || 1);

    const subtotal = () =>
      state.cart.reduce((sum, item) => sum + displayPrice(item.variant) * item.quantity, 0);

    const shippingCost = () => (state.shipping ? asNumber(state.shipping.rate) : 0);

    const grandTotal = () => subtotal() + shippingCost();

    const syncTotals = () => {
      if (subtotalEl) subtotalEl.textContent = money(subtotal());
      if (shippingCostEl) shippingCostEl.textContent = state.shipping ? money(shippingCost()) : '—';
      totalEl.textContent = money(grandTotal());
    };

    const updatePayState = () => {
      if (!payBtn) return;
      payBtn.disabled = !(state.cart.length && state.shipping);
    };

    // Any cart change invalidates previously fetched shipping rates.
    const invalidateShipping = () => {
      state.rates = [];
      state.shipping = null;
      if (shippingOptionsEl) shippingOptionsEl.innerHTML = '';
      if (shippingBox) shippingBox.hidden = true;
      syncTotals();
      updatePayState();
    };

    const renderShipping = () => {
      if (!shippingOptionsEl || !shippingBox) return;
      if (!state.rates.length) {
        shippingBox.hidden = true;
        return;
      }
      shippingOptionsEl.innerHTML = state.rates
        .map(
          (r, i) => `
          <label class="store-shipping__option">
            <input type="radio" name="shipping-rate" value="${i}" ${i === 0 ? 'checked' : ''} />
            <span>${r.name || 'Envío'}</span>
            <strong>${money(r.rate)}</strong>
          </label>`
        )
        .join('');
      shippingBox.hidden = false;
      state.shipping = state.rates[0] || null;
      syncTotals();
      updatePayState();
    };

    const renderCart = () => {
      cartItemsEl.innerHTML = '';
      if (!state.cart.length) {
        cartItemsEl.innerHTML = '<p class="store-empty">Tu carrito está vacío.</p>';
        syncTotals();
        return;
      }

      state.cart.forEach((item, idx) => {
        const row = document.createElement('div');
        row.className = 'store-cart-item';
        row.innerHTML = `
          <div>
            <strong>${item.title}</strong>
            <span>${item.variantName}</span>
          </div>
          <div class="store-cart-item__actions">
            <button type="button" data-cart-minus="${idx}" aria-label="Restar">-</button>
            <span>${item.quantity}</span>
            <button type="button" data-cart-plus="${idx}" aria-label="Sumar">+</button>
            <button type="button" data-cart-remove="${idx}" aria-label="Eliminar">x</button>
          </div>
        `;
        cartItemsEl.appendChild(row);
      });

      syncTotals();
    };

    const addToCart = (product, variant) => {
      if (!variant || !variant.id) {
        setFeedback('Selecciona una variante disponible para comprar.', true);
        return;
      }

      const existing = state.cart.find((entry) => entry.variant.id === variant.id);
      if (existing) {
        existing.quantity += 1;
      } else {
        state.cart.push({
          productId: product.id,
          title: getProductTitle(product),
          variantName: variant.name || 'Variante',
          variant,
          quantity: 1,
        });
      }

      renderCart();
      invalidateShipping();
      setFeedback('Producto agregado al carrito.');
    };

    const renderProducts = () => {
      grid.innerHTML = '';

      if (!state.products.length) {
        grid.innerHTML = '<p class="store-empty">No hay productos publicados por ahora.</p>';
        return;
      }

      state.products.forEach((product, idx) => {
        const variants = getProductVariants(product.id);
        const hasDetail = state.details.has(product.id);
        const firstVariant = variants[0] || null;

        const card = document.createElement('article');
        card.className = 'store-card reveal';
        card.setAttribute('data-testid', `store-product-${idx}`);
        card.setAttribute('data-product-id', String(product.id));

        const optionsHtml = variants
          .map(
            (v) => `<option value="${v.id}">${v.name || 'Variante'}</option>`
          )
          .join('');

        const variantControl = !hasDetail
          ? '<p class="store-card__loading">Cargando opciones...</p>'
          : variants.length
            ? `<select class="store-card__variant" data-variant-select aria-label="Variante">${optionsHtml}</select>`
            : '<p class="store-card__loading">Sin variantes disponibles.</p>';

        const priceText = hasDetail ? money(displayPrice(firstVariant)) : '...';

        card.innerHTML = `
          <div class="store-card__image" style="background-image:url('${getProductThumb(product)}')"></div>
          <div class="store-card__body">
            <h3>${getProductTitle(product)}</h3>
            ${variantControl}
            <div class="store-card__bottom">
              <strong data-price>${priceText}</strong>
              <button class="btn btn--primary" type="button" data-add-product="${product.id}" ${hasDetail && variants.length ? '' : 'disabled'}>Agregar</button>
            </div>
          </div>
        `;
        grid.appendChild(card);
      });

      initReveal();
    };

    const loadDetail = async (productId) => {
      if (state.details.has(productId)) return;
      try {
        const res = await fetch(`api/printful.php?action=product&id=${productId}`, {
          cache: 'no-store',
        });
        const json = await res.json();
        if (!res.ok || !json.ok || !json.product) {
          throw new Error(json.error || 'detalle no disponible');
        }
        state.details.set(productId, json.product);
      } catch (err) {
        console.warn(`Detail load failed for ${productId}:`, err);
        state.details.set(productId, { sync_variants: [] });
      }
    };

    const loadAllDetails = async () => {
      await Promise.all(state.products.map((p) => loadDetail(p.id)));
      renderProducts();
    };

    const fetchProducts = async () => {
      setFeedback('Consultando catálogo...');
      try {
        const res = await fetch('api/printful.php?action=products', { cache: 'no-store' });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.error || 'Error al cargar productos');
        }

        state.products = Array.isArray(json.products) ? json.products : [];
        state.details.clear();
        renderProducts();
        setFeedback(`Catálogo actualizado: ${state.products.length} productos.`);
        await loadAllDetails();
      } catch (err) {
        console.warn('Store load failed:', err);
        state.products = [];
        renderProducts();
        setFeedback('No fue posible cargar la tienda. Revisa la configuración del API de Printful.', true);
      }
    };

    grid.addEventListener('change', (e) => {
      const select = e.target.closest('[data-variant-select]');
      if (!select) return;
      const card = select.closest('[data-product-id]');
      if (!card) return;
      const productId = Number.parseInt(card.getAttribute('data-product-id') || '', 10);
      const variantId = Number.parseInt(select.value || '', 10);
      const variant = getProductVariants(productId).find((v) => v.id === variantId);
      const priceEl = card.querySelector('[data-price]');
      if (variant && priceEl) {
        priceEl.textContent = money(displayPrice(variant));
      }
    });

    grid.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-add-product]');
      if (!btn) return;
      const productId = Number.parseInt(btn.getAttribute('data-add-product') || '', 10);
      if (!Number.isFinite(productId)) return;

      const product = state.products.find((p) => p.id === productId);
      if (!product) return;

      const card = btn.closest('[data-product-id]');
      const select = card ? card.querySelector('[data-variant-select]') : null;
      const variants = getProductVariants(productId);
      const variantId = select ? Number.parseInt(select.value || '', 10) : NaN;
      const variant =
        variants.find((v) => v.id === variantId) || variants[0] || null;

      addToCart(product, variant);
    });

    cartItemsEl.addEventListener('click', (e) => {
      const minus = e.target.closest('[data-cart-minus]');
      const plus = e.target.closest('[data-cart-plus]');
      const remove = e.target.closest('[data-cart-remove]');

      if (minus) {
        const idx = Number.parseInt(minus.getAttribute('data-cart-minus') || '', 10);
        const item = state.cart[idx];
        if (!item) return;
        item.quantity = Math.max(1, item.quantity - 1);
        renderCart();
        invalidateShipping();
      }

      if (plus) {
        const idx = Number.parseInt(plus.getAttribute('data-cart-plus') || '', 10);
        const item = state.cart[idx];
        if (!item) return;
        item.quantity += 1;
        renderCart();
        invalidateShipping();
      }

      if (remove) {
        const idx = Number.parseInt(remove.getAttribute('data-cart-remove') || '', 10);
        if (!Number.isFinite(idx)) return;
        state.cart.splice(idx, 1);
        renderCart();
        invalidateShipping();
      }
    });

    if (shippingOptionsEl) {
      shippingOptionsEl.addEventListener('change', (e) => {
        const radio = e.target.closest('input[name="shipping-rate"]');
        if (!radio) return;
        const idx = Number.parseInt(radio.value || '', 10);
        state.shipping = state.rates[idx] || null;
        syncTotals();
        updatePayState();
      });
    }

    const getRecipient = () => {
      const formData = new FormData(orderForm);
      return {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        address1: String(formData.get('address1') || '').trim(),
        city: String(formData.get('city') || '').trim(),
        state_code: String(formData.get('state_code') || '').trim(),
        country_code: String(formData.get('country_code') || '').trim().toUpperCase(),
        zip: String(formData.get('zip') || '').trim(),
      };
    };

    const recipientReady = (r) =>
      r.name && r.email && r.address1 && r.city && r.country_code && r.zip;

    // Step 1: calculate real Printful shipping rates for the cart + address.
    orderForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!state.cart.length) {
        setFeedback('Agrega al menos un producto antes de calcular el envío.', true);
        return;
      }

      const recipient = getRecipient();
      if (!recipientReady(recipient)) {
        setFeedback('Completa todos los datos de envío.', true);
        return;
      }

      const items = state.cart.map((item) => ({
        variant_id: item.variant.variant_id, // Printful catalog variant id
        quantity: item.quantity,
      }));

      setFeedback('Calculando envío...');

      try {
        const res = await fetch('api/checkout.php?action=shipping', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ recipient, items }),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.error || 'No se pudo calcular el envío');
        }

        state.currency = json.currency || 'MXN';
        state.rates = Array.isArray(json.rates) ? json.rates : [];

        if (!state.rates.length) {
          invalidateShipping();
          setFeedback('No hay métodos de envío para esa dirección.', true);
          return;
        }

        renderShipping();
        setFeedback('Selecciona un método de envío y continúa al pago.');
      } catch (err) {
        console.warn('Shipping calc failed:', err);
        invalidateShipping();
        setFeedback('No se pudo calcular el envío. Verifica la dirección.', true);
      }
    });

    // Step 2: create the Stripe Checkout session and redirect to pay.
    if (payBtn) {
      payBtn.addEventListener('click', async () => {
        if (!state.cart.length || !state.shipping) {
          setFeedback('Calcula el envío y selecciona un método antes de pagar.', true);
          return;
        }

        const recipient = getRecipient();
        if (!recipientReady(recipient)) {
          setFeedback('Completa todos los datos de envío.', true);
          return;
        }

        const payload = {
          recipient,
          items: state.cart.map((item) => ({
            sync_variant_id: item.variant.id,
            quantity: item.quantity,
            retail_price: getVariantPrice(item.variant).toFixed(2),
            name: `${item.title} — ${item.variantName}`,
          })),
          shipping_id: state.shipping.id,
          shipping_name: state.shipping.name || 'Envío',
          shipping_rate: asNumber(state.shipping.rate),
        };

        payBtn.disabled = true;
        setFeedback('Redirigiendo a pago seguro con Stripe...');

        try {
          const res = await fetch('api/checkout.php?action=create-checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const json = await res.json();
          if (!res.ok || !json.ok || !json.url) {
            throw new Error(json.error || 'No se pudo iniciar el pago');
          }
          window.location.href = json.url;
        } catch (err) {
          console.warn('Checkout failed:', err);
          payBtn.disabled = false;
          setFeedback('No se pudo iniciar el pago. Intenta de nuevo.', true);
        }
      });
    }

    // Handle return from Stripe Checkout.
    const showResult = (msg, ok) => {
      if (!resultEl) return;
      resultEl.hidden = false;
      resultEl.textContent = msg;
      resultEl.classList.toggle('is-success', ok);
      resultEl.classList.toggle('is-error', !ok);
    };

    const handleCheckoutReturn = () => {
      const params = new URLSearchParams(window.location.search);
      const status = params.get('checkout');
      if (!status) return;

      if (status === 'success') {
        showResult('¡Pago recibido! Tu pedido se envió a producción. Recibirás un correo de confirmación.', true);
        state.cart = [];
        renderCart();
        invalidateShipping();
        orderForm.reset();
      } else if (status === 'cancel') {
        showResult('Pago cancelado. Tu carrito sigue disponible.', false);
      }

      params.delete('checkout');
      params.delete('session_id');
      const query = params.toString();
      const newUrl = window.location.pathname + (query ? `?${query}` : '');
      window.history.replaceState({}, '', newUrl);
    };

    if (refreshBtn) {
      refreshBtn.addEventListener('click', fetchProducts);
    }

    const fetchConfig = async () => {
      try {
        const res = await fetch('api/checkout.php?action=config', { cache: 'no-store' });
        const json = await res.json();
        if (res.ok && json.ok) {
          state.currency = json.currency || state.currency;
          const mult = Number.parseFloat(json.price_multiplier);
          if (Number.isFinite(mult) && mult > 0) {
            state.priceMultiplier = mult;
          }
        }
      } catch (err) {
        console.warn('Config load failed, using defaults:', err);
      }
    };

    renderCart();
    updatePayState();
    handleCheckoutReturn();
    fetchConfig().then(fetchProducts);
  }

  /* ----- Contact form (WhatsApp redirect) ----- */
  function initContactForm() {
    const form = $('[data-testid="contact-form"]');
    if (!form) return;
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = (form.elements['name'].value || '').trim();
      const phone = (form.elements['phone'].value || '').trim();
      const message = (form.elements['message'].value || '').trim();
      const lines = [
        '¡Hola! Quiero información sobre Pura Capoeira Cuernavaca.',
        name ? `Nombre: ${name}` : '',
        phone ? `Teléfono / WhatsApp: ${phone}` : '',
        message ? `Mensaje: ${message}` : '',
      ].filter(Boolean);
      const text = encodeURIComponent(lines.join('\n'));
      // UPDATE WhatsApp number here:
      const url = `https://wa.me/18056385603?text=${text}`;
      window.open(url, '_blank', 'noopener,noreferrer');
    });
  }

  /* ----- Inscriptions (membership sign-up) ----- */
  function initInscription() {
    const root = $('[data-inscription-root]');
    if (!root) return;

    const form = $('[data-inscription-form]', root);
    const addonWrap = $('[data-inscription-addon]', root);
    const addonInput = addonWrap ? $('input', addonWrap) : null;
    const promoInput = $('[data-inscription-promo]', root);
    const promoApplyBtn = $('[data-inscription-promo-apply]', root);
    const promoNote = $('[data-inscription-promo-note]', root);
    const paymodeWrap = $('[data-inscription-paymode]', root);
    const summaryLabel = $('[data-inscription-summary-label]', root);
    const summaryTotal = $('[data-inscription-summary-total]', root);
    const resultEl = $('[data-inscription-result]', root);
    const submitBtn = $('[data-inscription-submit]', root);
    if (!form) return;

    const money = (n) =>
      new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n) + ' MXN';

    // Capture the default plan prices/notes so we can restore them if a code is cleared.
    const defaults = {};
    $$('input[name="plan"]', form).forEach((input) => {
      const id = input.value;
      defaults[id] = {
        amount: input.getAttribute('data-amount'),
        price: $(`[data-plan-price="${id}"]`, form)?.textContent || '',
        note: $(`[data-plan-note="${id}"]`, form)?.textContent || '',
      };
    });

    // Promo state for the current session.
    const promoState = { type: 'none', free: false, paymentOptional: false };

    const selectedPlan = () => $('input[name="plan"]:checked', form);
    const payLaterSelected = () =>
      !!paymodeWrap &&
      !paymodeWrap.hidden &&
      $('input[name="payment_mode"]:checked', form)?.value === 'later';

    function setPlanPrices(plans, monthly) {
      (plans || []).forEach((p) => {
        const input = $(`input[name="plan"][value="${p.id}"]`, form);
        if (input) input.setAttribute('data-amount', String(p.amount));
        const priceEl = $(`[data-plan-price="${p.id}"]`, form);
        if (priceEl) priceEl.textContent = money(p.amount);
      });
      const noteEl = $('[data-plan-note="inscription_month"]', form);
      if (noteEl && typeof monthly === 'number') {
        noteEl.textContent = `Inscripción ($1,500) + primera mensualidad (${money(monthly)}).`;
      }
    }

    function restoreDefaultPrices() {
      Object.keys(defaults).forEach((id) => {
        const input = $(`input[name="plan"][value="${id}"]`, form);
        if (input) input.setAttribute('data-amount', defaults[id].amount);
        const priceEl = $(`[data-plan-price="${id}"]`, form);
        if (priceEl) priceEl.textContent = defaults[id].price;
        const noteEl = $(`[data-plan-note="${id}"]`, form);
        if (noteEl) noteEl.textContent = defaults[id].note;
      });
    }

    function updatePaymodeUi() {
      if (paymodeWrap) paymodeWrap.hidden = !promoState.paymentOptional;
      if (submitBtn) {
        submitBtn.textContent =
          promoState.free || payLaterSelected() ? 'Registrar inscripción' : 'Continuar al pago';
      }
    }

    function updateSummary() {
      const plan = selectedPlan();
      if (!plan) {
        if (summaryLabel) summaryLabel.textContent = '—';
        if (summaryTotal) summaryTotal.textContent = money(0);
        updatePaymodeUi();
        return;
      }
      const allowAddon = plan.getAttribute('data-allow-addon') === '1';
      if (addonWrap) {
        addonWrap.hidden = !allowAddon;
        if (!allowAddon && addonInput) addonInput.checked = false;
      }
      let amount = parseFloat(plan.getAttribute('data-amount')) || 0;
      let label = $('.plan-card__title', plan.closest('.plan-card'))?.textContent || 'Paquete';
      if (allowAddon && addonInput && addonInput.checked) {
        amount += 1500;
        label += ' + inscripción';
      }
      if (promoState.free) {
        amount = 0;
        label += ' (beca 100%)';
      }
      if (summaryLabel) summaryLabel.textContent = label;
      if (summaryTotal) summaryTotal.textContent = money(amount);
      updatePaymodeUi();
    }

    form.addEventListener('change', (e) => {
      if (e.target.name === 'plan' || e.target.name === 'add_inscription' || e.target.name === 'payment_mode') {
        updateSummary();
      }
    });

    async function applyPromo() {
      const code = (promoInput?.value || '').trim();
      if (!promoNote) return;
      promoNote.hidden = false;
      promoNote.classList.remove('is-success');

      if (code === '') {
        promoState.type = 'none';
        promoState.free = false;
        promoState.paymentOptional = false;
        restoreDefaultPrices();
        promoNote.textContent = 'Escribe un código para aplicarlo.';
        updateSummary();
        return;
      }

      promoNote.textContent = 'Validando código...';
      try {
        const res = await fetch('api/checkout.php?action=validate-promo', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ promocode: code }),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) throw new Error(json.error || 'No se pudo validar el código');

        if (!json.valid) {
          promoState.type = 'none';
          promoState.free = false;
          promoState.paymentOptional = false;
          restoreDefaultPrices();
          promoNote.textContent = 'Código no válido. Se aplican los precios normales.';
          updateSummary();
          return;
        }

        promoState.type = json.type;
        promoState.free = !!json.free;
        promoState.paymentOptional = !!json.payment_optional;

        if (json.type === 'beca') {
          restoreDefaultPrices();
          promoNote.textContent = '¡Beca del 100% aplicada! Tu inscripción será gratuita.';
        } else if (json.type === 'current') {
          setPlanPrices(json.plans, json.monthly);
          promoNote.textContent =
            '¡Código de alumno aplicado! Mensualidad de ' +
            money(json.monthly) +
            '. El pago es opcional: puedes pagar ahora o después.';
        }
        promoNote.classList.add('is-success');
        updateSummary();
      } catch (err) {
        console.warn('Promo validation failed:', err);
        promoNote.textContent = 'No se pudo validar el código. Intenta de nuevo.';
      }
    }

    if (promoApplyBtn) {
      promoApplyBtn.addEventListener('click', applyPromo);
    }

    const showResult = (msg, ok) => {
      if (!resultEl) return;
      resultEl.hidden = false;
      resultEl.textContent = msg;
      resultEl.classList.toggle('is-success', !!ok);
      resultEl.classList.toggle('is-error', !ok);
      resultEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.reportValidity()) return;

      const plan = selectedPlan();
      if (!plan) {
        showResult('Selecciona un paquete para continuar.', false);
        return;
      }

      const fd = new FormData(form);
      const payload = {
        plan: fd.get('plan'),
        add_inscription: addonWrap && !addonWrap.hidden && addonInput && addonInput.checked ? 1 : 0,
        promocode: (fd.get('promocode') || '').toString().trim(),
        payment_mode: payLaterSelected() ? 'later' : 'now',
        first_name: (fd.get('first_name') || '').toString().trim(),
        last_name: (fd.get('last_name') || '').toString().trim(),
        parent_name: (fd.get('parent_name') || '').toString().trim(),
        address: (fd.get('address') || '').toString().trim(),
        email: (fd.get('email') || '').toString().trim(),
        phone: (fd.get('phone') || '').toString().trim(),
        parent_phone: (fd.get('parent_phone') || '').toString().trim(),
        emergency_phone: (fd.get('emergency_phone') || '').toString().trim(),
        dob: (fd.get('dob') || '').toString().trim(),
      };

      if (submitBtn) submitBtn.disabled = true;
      showResult('Procesando tu inscripción...', true);

      try {
        const res = await fetch('api/checkout.php?action=inscription', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.error || 'No se pudo procesar la inscripción');
        }
        if (json.free) {
          showResult(json.message || '¡Inscripción registrada!', true);
          form.reset();
          promoState.type = 'none';
          promoState.free = false;
          promoState.paymentOptional = false;
          restoreDefaultPrices();
          updateSummary();
          if (submitBtn) submitBtn.disabled = false;
          return;
        }
        if (json.url) {
          window.location.href = json.url;
          return;
        }
        throw new Error('Respuesta inesperada del servidor');
      } catch (err) {
        console.warn('Inscription failed:', err);
        showResult(err.message || 'No se pudo procesar la inscripción. Intenta de nuevo.', false);
        if (submitBtn) submitBtn.disabled = false;
      }
    });

    // Handle return from Stripe Checkout.
    const params = new URLSearchParams(window.location.search);
    const status = params.get('inscription');
    if (status === 'success') {
      showResult('¡Pago recibido! Tu inscripción quedó registrada. Te contactaremos pronto.', true);
    } else if (status === 'cancel') {
      showResult('El pago fue cancelado. Puedes intentarlo de nuevo cuando quieras.', false);
    }

    updateSummary();
  }

  /* ----- Set active nav based on current page ----- */
  function highlightActiveNav() {
    const path = window.location.pathname.replace(/\/$/, '');
    const file = path.split('/').pop() || 'index.html';
    $$('.site-nav a, .mobile-menu nav a').forEach((a) => {
      const href = a.getAttribute('href') || '';
      const hrefFile = href.split('/').pop();
      if (
        hrefFile === file ||
        (file === '' && hrefFile === 'index.html') ||
        (file === 'index.html' && href === '/')
      ) {
        a.setAttribute('aria-current', 'page');
      }
    });
  }

  /* ----- Init ----- */
  document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initReveal();
    initGallery();
    initStore();
    initContactForm();
    initInscription();
    highlightActiveNav();
  });
})();
