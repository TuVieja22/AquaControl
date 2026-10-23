/* ===================================================
   AquaControl — animaciones/portada.js
   Animaciones de la portada: los bloques que aparecen al hacer scroll, los numeros
   de muestra del "dashboard preview" y el carrusel de testimonios.
   Es todo visual: no toca datos ni habla con el servidor. La compra de la portada
   esta en js/funciones/purchase.js.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  /* ── APARICION AL HACER SCROLL ─────────────────────────
     Los elementos con data-reveal aparecen cuando entran en pantalla. */
  // Todos los elementos marcados con data-reveal.
  const revealNodes = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    // Vigilante: avisa cuando un elemento entra en pantalla.
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        // Lo muestra (la animacion esta en el CSS) y deja de vigilarlo.
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    // Se activa cuando se ve el 14% del elemento, un poco antes del borde de abajo.
    }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });

    // Cada uno recibe un retraso corto, para que aparezcan de a uno, y se lo empieza a vigilar.
    revealNodes.forEach((node, index) => {
      node.style.transitionDelay = `${Math.min(index % 5, 4) * 70}ms`;
      revealObserver.observe(node);
    });
  } else {
    // Navegadores viejos: se muestra todo de una.
    revealNodes.forEach(node => node.classList.add('is-visible'));
  }

  /* ── DEMO EN VIVO DE LA PORTADA ────────────────────────
     Numeros de ejemplo que cambian cada 2,8 s (no son lecturas reales). Se pausa
     mientras la pestana esta oculta. */
  const landingDemo = document.querySelector('[data-dashboard-demo]');
  if (landingDemo) {
    // Los numeros de la demo, cada uno con su marca.
    const nodes = {
      temperature: landingDemo.querySelector('[data-live-temperature]'),
      ph: landingDemo.querySelector('[data-live-ph]'),
      alerts: landingDemo.querySelector('[data-live-alerts]'),
      health: landingDemo.querySelector('[data-live-health]'),
      sync: landingDemo.querySelector('[data-live-sync]')
    };
    // Cuenta cuantas veces se actualizo.
    let tick = 0;

    // Cambia un numero y le da un brillo corto a su tarjeta.
    function updateLiveMetric(node, value) {
      if (!node) return;
      node.textContent = value;

      const wrapper = node.closest('.live-stat, .ecosystem-state');
      if (wrapper) {
        wrapper.classList.add('is-updated');
        window.setTimeout(() => wrapper.classList.remove('is-updated'), 420);
      }
    }

    // Calcula numeros nuevos, que suben y bajan alrededor de un valor.
    function renderLandingDemo() {
      if (document.hidden) return;

      tick += 1;
      updateLiveMetric(nodes.temperature, `${(25.4 + Math.sin(tick / 2) * 0.4).toFixed(1)}°C`);
      updateLiveMetric(nodes.ph, (6.82 + Math.cos(tick / 3) * 0.05).toFixed(2));
      updateLiveMetric(nodes.alerts, tick % 9 === 0 ? '1' : '0');
      updateLiveMetric(nodes.health, `${97 + Math.round(Math.sin(tick / 4))}%`);
      if (nodes.sync) nodes.sync.textContent = `hace ${2 + (tick % 5)} s`;
    }

    // Arranca ahora y se repite cada 2,8 segundos.
    renderLandingDemo();
    window.setInterval(renderLandingDemo, 2800);
  }

  /* ── CARRUSEL DE TESTIMONIOS ───────────────────────── */
  document.querySelectorAll('[data-testimonial-carousel]').forEach(carousel => {
    // La cinta que contiene las tarjetas.
    const track = carousel.querySelector('.testimonial-track');
    if (!track) return;

    // Mueve la cinta el ancho de una tarjeta (1 = adelante, -1 = atras).
    function scrollByCard(direction) {
      const card = track.querySelector('.testimonial-card');
      const amount = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.9;
      track.scrollBy({ left: amount * direction, behavior: 'smooth' });
    }

    // Las dos flechas.
    carousel.querySelector('[data-testimonial-next]')?.addEventListener('click', () => scrollByCard(1));
    carousel.querySelector('[data-testimonial-prev]')?.addEventListener('click', () => scrollByCard(-1));
  });
}());
