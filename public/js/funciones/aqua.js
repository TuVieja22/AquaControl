/* ===================================================
   AquaControl — funciones/aqua.js
   Se carga en TODAS las paginas. Aca solo hay funciones (nada visual): el token CSRF,
   la confirmacion antes de enviar un formulario y el boton "Copiar".
   Lo visual de todas las paginas (cartel de "cargando" y mensajes que se desvanecen)
   esta en js/animaciones/generales.js.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

/* ── TOKEN CSRF ───────────────────────────────────────
   El token vive en <meta name="csrf-token">. Como el servidor lo cambia en cada POST,
   se actualiza con el header de cada respuesta (y tambien en los formularios de la
   pagina) para que el siguiente envio siga siendo valido. */
window.AquaCsrf = (function () {
  // Donde esta guardado el token, y como se llaman su header y su campo en los formularios.
  const tokenMeta = document.querySelector('meta[name="csrf-token"]');
  const headerName = document.querySelector('meta[name="csrf-header"]')?.content || 'X-CSRF-TOKEN';
  const fieldName = tokenMeta?.dataset.fieldName || '';

  // Devuelve el token actual.
  function token() {
    return tokenMeta?.content || '';
  }

  // Devuelve los headers para un pedido, con el token agregado.
  function headers(extra = {}) {
    const value = token();
    return value ? { ...extra, [headerName]: value } : { ...extra };
  }

  // Guarda el token nuevo que trae una respuesta del servidor.
  function refresh(response) {
    const next = response?.headers?.get(headerName);
    if (!next || !tokenMeta || next === tokenMeta.content) return;

    // Lo actualiza en la pagina y en todos los formularios.
    tokenMeta.content = next;
    if (fieldName) {
      document.querySelectorAll(`input[name="${CSS.escape(fieldName)}"]`).forEach(input => {
        input.value = next;
      });
    }
  }

  // Lo que pueden usar los otros archivos.
  return { headerName, token, headers, refresh };
}());

/* ── FORMULARIOS ───────────────────────────────────────
   data-confirm="¿Seguro?" pide confirmacion antes de enviar. Se escucha en la fase de
   captura para que corra antes que cualquier otra validacion. */
document.addEventListener('submit', event => {
  const form = event.target;
  // Si el usuario no acepta, el formulario no se envia.
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
}, true);

/* ── BOTON "COPIAR" ────────────────────────────────────
   data-copy-target="id" copia el texto de ese elemento (p. ej. la API key nueva). */
document.addEventListener('click', async event => {
  // Boton que se toco y elemento que tiene el texto a copiar.
  const button = event.target.closest('[data-copy-target]');
  const source = button && document.getElementById(button.dataset.copyTarget);
  if (!source) return;

  // Copia el texto; si el navegador no deja, lo selecciona para copiarlo a mano.
  const originalLabel = button.textContent;
  try {
    await navigator.clipboard.writeText(source.textContent.trim());
    button.textContent = 'Copiada';
  } catch (error) {
    window.getSelection()?.selectAllChildren(source);
    button.textContent = 'Selecciona y copia';
  }
  // A los 2 segundos el boton vuelve a su texto original.
  window.setTimeout(() => { button.textContent = originalLabel; }, 2000);
});
