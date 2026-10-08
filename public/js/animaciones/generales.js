/* ===================================================
   AquaControl — animaciones/generales.js
   Se carga en TODAS las paginas. Aca solo hay cosas visuales: el cartel de "cargando"
   y los mensajes que se desvanecen solos. No toca datos ni habla con el servidor.
   Las funciones de todas las paginas estan en js/funciones/aqua.js.
   =================================================== */

'use strict';

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

// Al enviar un formulario de verdad (nadie lo cancelo). data-skip-loader="true" lo evita.
document.addEventListener('submit', event => {
  const form = event.target;
  if (event.defaultPrevented || form.dataset.skipLoader === 'true') return;
  window.AquaLoader.mostrar();
});

/* ── MENSAJES (flash) ──────────────────────────────────
   Se desvanecen solos a los 5 segundos. El desvanecido (medio segundo) esta en
   css/components/flash_messages.css, en la clase .is-leaving. */
document.querySelectorAll('.flash').forEach(flash => {
  window.setTimeout(() => {
    flash.classList.add('is-leaving');
    window.setTimeout(() => flash.remove(), 500);
  }, 5000);
});
