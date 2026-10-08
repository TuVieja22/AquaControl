/* ===================================================
   AquaControl — animaciones/portada.js
   Animaciones de la portada: los bloques que aparecen al hacer scroll, los numeros
   de muestra del "dashboard preview" y el carrusel de testimonios.
   Es todo visual: no toca datos ni habla con el servidor. La compra de la portada
   esta en js/funciones/purchase.js.
   =================================================== */

'use strict';

(function () {
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
}());
