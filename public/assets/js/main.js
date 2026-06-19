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
    const orderForm = root.querySelector('[data-store-order-form]');

    if (!grid || !feedback || !cartItemsEl || !totalEl || !orderForm) return;

    const state = {
      products: [],
      cart: [],
    };

    const asNumber = (value) => {
      const num = Number.parseFloat(value);
      return Number.isFinite(num) ? num : 0;
    };

    const money = (value) => `$${asNumber(value).toFixed(2)}`;

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

    const getDefaultVariant = (product) => {
      const variants = Array.isArray(product?.sync_variants) ? product.sync_variants : [];
      return variants[0] || null;
    };

    const getVariantPrice = (variant) => {
      return asNumber(variant?.retail_price || variant?.price || 0);
    };

    const cartTotal = () =>
      state.cart.reduce((sum, item) => sum + getVariantPrice(item.variant) * item.quantity, 0);

    const syncCartTotal = () => {
      totalEl.textContent = money(cartTotal());
    };

    const renderCart = () => {
      cartItemsEl.innerHTML = '';
      if (!state.cart.length) {
        cartItemsEl.innerHTML = '<p class="store-empty">Tu carrito está vacío.</p>';
        syncCartTotal();
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

      syncCartTotal();
    };

    const addToCart = (product) => {
      const variant = getDefaultVariant(product);
      if (!variant || !variant.sync_variant_id) {
        setFeedback('Este producto no tiene variantes disponibles para compra.', true);
        return;
      }

      const existing = state.cart.find(
        (entry) => entry.variant.sync_variant_id === variant.sync_variant_id
      );
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
      setFeedback('Producto agregado al carrito.');
    };

    const renderProducts = () => {
      grid.innerHTML = '';

      if (!state.products.length) {
        grid.innerHTML = '<p class="store-empty">No hay productos publicados por ahora.</p>';
        return;
      }

      state.products.forEach((product, idx) => {
        const variant = getDefaultVariant(product);
        const card = document.createElement('article');
        card.className = 'store-card reveal';
        card.setAttribute('data-testid', `store-product-${idx}`);
        card.innerHTML = `
          <div class="store-card__image" style="background-image:url('${getProductThumb(product)}')"></div>
          <div class="store-card__body">
            <h3>${getProductTitle(product)}</h3>
            <p>${variant?.name || 'Sin variante visible'}</p>
            <div class="store-card__bottom">
              <strong>${money(getVariantPrice(variant))}</strong>
              <button class="btn btn--primary" type="button" data-add-product="${product.id}">Agregar</button>
            </div>
          </div>
        `;
        grid.appendChild(card);
      });

      initReveal();
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
        renderProducts();
        setFeedback(`Catálogo actualizado: ${state.products.length} productos.`);
      } catch (err) {
        console.warn('Store load failed:', err);
        state.products = [];
        renderProducts();
        setFeedback('No fue posible cargar la tienda. Revisa la configuración del API de Printful.', true);
      }
    };

    grid.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-add-product]');
      if (!btn) return;
      const productId = Number.parseInt(btn.getAttribute('data-add-product') || '', 10);
      if (!Number.isFinite(productId)) return;

      const product = state.products.find((p) => p.id === productId);
      if (!product) return;
      addToCart(product);
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
      }

      if (plus) {
        const idx = Number.parseInt(plus.getAttribute('data-cart-plus') || '', 10);
        const item = state.cart[idx];
        if (!item) return;
        item.quantity += 1;
        renderCart();
      }

      if (remove) {
        const idx = Number.parseInt(remove.getAttribute('data-cart-remove') || '', 10);
        if (!Number.isFinite(idx)) return;
        state.cart.splice(idx, 1);
        renderCart();
      }
    });

    orderForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!state.cart.length) {
        setFeedback('Agrega al menos un producto antes de crear el pedido.', true);
        return;
      }

      const formData = new FormData(orderForm);
      const recipient = {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        address1: String(formData.get('address1') || '').trim(),
        city: String(formData.get('city') || '').trim(),
        state_code: String(formData.get('state_code') || '').trim(),
        country_code: String(formData.get('country_code') || '').trim().toUpperCase(),
        zip: String(formData.get('zip') || '').trim(),
      };

      const payload = {
        external_id: `capoeira-${Date.now()}`,
        recipient,
        currency: 'USD',
        items: state.cart.map((item) => ({
          sync_variant_id: item.variant.sync_variant_id,
          quantity: item.quantity,
          retail_price: getVariantPrice(item.variant).toFixed(2),
        })),
      };

      setFeedback('Creando pedido borrador...');

      try {
        const res = await fetch('api/printful.php?action=order', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.error || 'No se pudo crear el pedido');
        }

        const orderId = json?.order?.id ? ` #${json.order.id}` : '';
        setFeedback(`Pedido borrador creado${orderId}. Revisa tu panel de Printful para confirmar costos.`);
        state.cart = [];
        renderCart();
        orderForm.reset();
      } catch (err) {
        console.warn('Store order failed:', err);
        setFeedback('No se pudo crear el pedido. Verifica datos de envío y configuración del API.', true);
      }
    });

    if (refreshBtn) {
      refreshBtn.addEventListener('click', fetchProducts);
    }

    renderCart();
    fetchProducts();
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
    highlightActiveNav();
  });
})();
