const menuToggle = document.querySelector('.menu-toggle');
const siteNav = document.querySelector('.site-nav');

if (menuToggle && siteNav) {
  menuToggle.addEventListener('click', () => {
    const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!isExpanded));
    siteNav.classList.toggle('is-open');
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && siteNav.classList.contains('is-open')) {
      siteNav.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
      menuToggle.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (!siteNav.classList.contains('is-open')) {
      return;
    }

    const clickInsideMenu = siteNav.contains(event.target) || menuToggle.contains(event.target);
    if (!clickInsideMenu) {
      siteNav.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
    }
  });
}

const galleryGrid = document.querySelector('#gallery-grid');
const galleryStatus = document.querySelector('#gallery-status');
const galleryFallback = document.querySelector('#gallery-fallback');
const filterButtons = document.querySelectorAll('[data-filter]');

if (galleryGrid && galleryStatus && filterButtons.length > 0) {
  let galleryItems = [];
  let currentFilter = 'Todos';

  const formatDate = (isoDate) => {
    try {
      return new Date(`${isoDate}T00:00:00`).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    } catch (error) {
      return isoDate;
    }
  };

  const createCard = (item) => {
    const article = document.createElement('article');
    article.className = 'card gallery-card';

    const img = document.createElement('img');
    img.src = item.thumbnail;
    img.alt = `Miniatura de ${item.title}`;
    img.loading = 'lazy';

    const title = document.createElement('h3');
    title.textContent = item.title;

    const meta = document.createElement('div');
    meta.className = 'gallery-meta';
    meta.innerHTML = `<span>${formatDate(item.date)}</span><span class="gallery-category">${item.category}</span>`;

    const desc = document.createElement('p');
    desc.textContent = item.description;

    const link = document.createElement('a');
    link.className = 'btn btn-secondary';
    link.href = item.url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = 'Ver video';

    article.append(img, title, meta, desc, link);
    return article;
  };

  const renderGallery = () => {
    const filtered = currentFilter === 'Todos'
      ? galleryItems
      : galleryItems.filter((item) => item.category === currentFilter);

    galleryGrid.innerHTML = '';

    if (filtered.length === 0) {
      galleryStatus.textContent = `No hay videos disponibles para la categoría ${currentFilter}.`;
      return;
    }

    galleryStatus.textContent = `Mostrando ${filtered.length} video(s) en ${currentFilter}.`;
    filtered.forEach((item) => galleryGrid.appendChild(createCard(item)));
  };

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      currentFilter = button.dataset.filter || 'Todos';
      filterButtons.forEach((item) => item.classList.remove('is-active'));
      button.classList.add('is-active');
      renderGallery();
    });
  });

  const showFallback = () => {
    galleryStatus.textContent = 'No fue posible cargar la galería local en este momento.';
    if (galleryFallback) {
      galleryFallback.hidden = false;
    }
  };

  fetch('/data/gallery.json')
    .then((response) => {
      if (!response.ok) {
        throw new Error('Error al cargar JSON');
      }
      return response.json();
    })
    .then((data) => {
      if (!Array.isArray(data)) {
        throw new Error('Formato inválido');
      }
      galleryItems = data;
      renderGallery();
    })
    .catch(() => {
      showFallback();
    });
}