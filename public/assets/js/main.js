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
    initContactForm();
    highlightActiveNav();
  });
})();
