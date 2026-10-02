/* ===================================================
   AquaControl — aqua.js
   Se carga en TODAS las paginas: token CSRF, cartel de "cargando", animaciones
   de la portada, carrusel, mensajes que se ocultan solos y boton "Copiar".
   =================================================== */

'use strict';

/* ── TOKEN CSRF ───────────────────────────────────────
   El token vive en <meta name="csrf-token">. Como el servidor lo cambia en cada POST,
   se actualiza con el header de cada respuesta (y tambien en los formularios de la
   pagina) para que el siguiente envio siga siendo valido. */
window.AquaCsrf = (function () {
  const tokenMeta = document.querySelector('meta[name="csrf-token"]');
  const headerName = document.querySelector('meta[name="csrf-header"]')?.content || 'X-CSRF-TOKEN';
  const fieldName = tokenMeta?.dataset.fieldName || '';

  function token() {
    return tokenMeta?.content || '';
  }

  function headers(extra = {}) {
    const value = token();
    return value ? { ...extra, [headerName]: value } : { ...extra };
  }

  function refresh(response) {
    const next = response?.headers?.get(headerName);
    if (!next || !tokenMeta || next === tokenMeta.content) return;

    tokenMeta.content = next;
    if (fieldName) {
      document.querySelectorAll(`input[name="${CSS.escape(fieldName)}"]`).forEach(input => {
        input.value = next;
      });
    }
  }

  return { headerName, token, headers, refresh };
}());

/* ── CARTEL DE "CARGANDO" ─────────────────────────────
   Aparece solo si la espera pasa de 300 ms: en las cargas rapidas no se ve. */
window.AquaLoader = (function () {
  const overlay = document.querySelector('[data-page-loader]');
  let timer = null;

  function mostrar() {
    if (!overlay || timer !== null) return;
    timer = window.setTimeout(() => overlay.classList.add('is-visible'), 300);
  }

  function ocultar() {
    window.clearTimeout(timer);
    timer = null;
    overlay?.classList.remove('is-visible');
  }

  return { mostrar, ocultar };
}());

// Al volver con "atras" el navegador puede mostrar la pagina guardada: sin cartel.
window.addEventListener('pageshow', () => window.AquaLoader.ocultar());

// Al ir a otra pagina por un link interno.
document.addEventListener('click', event => {
  const link = event.target.closest('a[href]');
  if (!link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey) return;

  const href = link.getAttribute('href') || '';
  if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;

  const destino = new URL(link.href, window.location.href);
  if (destino.origin !== window.location.origin) return;
  // Mismo documento con otro #ancla: solo se desplaza, no carga nada.
  if (destino.pathname === window.location.pathname && destino.search === window.location.search && destino.hash) return;

  window.AquaLoader.mostrar();
});

/* ── FORMULARIOS ───────────────────────────────────────
   data-confirm="¿Seguro?" pide confirmacion antes de enviar. Se escucha en la fase de
   captura para que corra antes que cualquier otra validacion. */
document.addEventListener('submit', event => {
  const form = event.target;
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
}, true);

// Si el formulario se envia de verdad (nadie lo cancelo), se muestra el cartel.
document.addEventListener('submit', event => {
  const form = event.target;
  if (event.defaultPrevented || form.dataset.skipLoader === 'true') return;
  window.AquaLoader.mostrar();
});

/* ── MENSAJES (flash) ──────────────────────────────────
   Se desvanecen solos a los 5 segundos. */
document.querySelectorAll('.flash').forEach(flash => {
  window.setTimeout(() => {
    flash.style.transition = 'opacity 0.5s ease';
    flash.style.opacity = '0';
    window.setTimeout(() => flash.remove(), 500);
  }, 5000);
});

/* ── APARICION AL HACER SCROLL ─────────────────────────
   Los elementos con data-reveal aparecen cuando entran en pantalla. */
const revealNodes = document.querySelectorAll('[data-reveal]');
if ('IntersectionObserver' in window) {
  const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    });
  }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });

  revealNodes.forEach((node, index) => {
    node.style.transitionDelay = `${Math.min(index % 5, 4) * 70}ms`;
    revealObserver.observe(node);
  });
} else {
  revealNodes.forEach(node => node.classList.add('is-visible'));
}

/* ── DEMO EN VIVO DE LA PORTADA ────────────────────────
   Numeros de ejemplo que cambian cada 2,8 s (no son lecturas reales). Se pausa
   mientras la pestana esta oculta. */
const landingDemo = document.querySelector('[data-dashboard-demo]');
if (landingDemo) {
  const nodes = {
    temperature: landingDemo.querySelector('[data-live-temperature]'),
    ph: landingDemo.querySelector('[data-live-ph]'),
    alerts: landingDemo.querySelector('[data-live-alerts]'),
    health: landingDemo.querySelector('[data-live-health]'),
    sync: landingDemo.querySelector('[data-live-sync]')
  };
  let tick = 0;

  function updateLiveMetric(node, value) {
    if (!node) return;
    node.textContent = value;

    const wrapper = node.closest('.live-stat, .ecosystem-state');
    if (wrapper) {
      wrapper.classList.add('is-updated');
      window.setTimeout(() => wrapper.classList.remove('is-updated'), 420);
    }
  }

  function renderLandingDemo() {
    if (document.hidden) return;

    tick += 1;
    updateLiveMetric(nodes.temperature, `${(25.4 + Math.sin(tick / 2) * 0.4).toFixed(1)}°C`);
    updateLiveMetric(nodes.ph, (6.82 + Math.cos(tick / 3) * 0.05).toFixed(2));
    updateLiveMetric(nodes.alerts, tick % 9 === 0 ? '1' : '0');
    updateLiveMetric(nodes.health, `${97 + Math.round(Math.sin(tick / 4))}%`);
    if (nodes.sync) nodes.sync.textContent = `hace ${2 + (tick % 5)} s`;
  }

  renderLandingDemo();
  window.setInterval(renderLandingDemo, 2800);
}

/* ── CARRUSEL DE TESTIMONIOS ───────────────────────── */
document.querySelectorAll('[data-testimonial-carousel]').forEach(carousel => {
  const track = carousel.querySelector('.testimonial-track');
  if (!track) return;

  function scrollByCard(direction) {
    const card = track.querySelector('.testimonial-card');
    const amount = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.9;
    track.scrollBy({ left: amount * direction, behavior: 'smooth' });
  }

  carousel.querySelector('[data-testimonial-next]')?.addEventListener('click', () => scrollByCard(1));
  carousel.querySelector('[data-testimonial-prev]')?.addEventListener('click', () => scrollByCard(-1));
});

/* ── BOTON "COPIAR" ────────────────────────────────────
   data-copy-target="id" copia el texto de ese elemento (p. ej. la API key nueva). */
document.addEventListener('click', async event => {
  const button = event.target.closest('[data-copy-target]');
  const source = button && document.getElementById(button.dataset.copyTarget);
  if (!source) return;

  const originalLabel = button.textContent;
  try {
    await navigator.clipboard.writeText(source.textContent.trim());
    button.textContent = 'Copiada';
  } catch (error) {
    window.getSelection()?.selectAllChildren(source);
    button.textContent = 'Selecciona y copia';
  }
  window.setTimeout(() => { button.textContent = originalLabel; }, 2000);
});
