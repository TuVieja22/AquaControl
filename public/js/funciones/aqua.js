/* ===================================================
   AquaControl — funciones/aqua.js
   Se carga en TODAS las paginas. Aca solo hay funciones (nada visual): el token CSRF,
   la confirmacion antes de enviar un formulario y el boton "Copiar".
   Lo visual de todas las paginas (cartel de "cargando" y mensajes que se desvanecen)
   esta en js/animaciones/generales.js.
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

/* ── FORMULARIOS ───────────────────────────────────────
   data-confirm="¿Seguro?" pide confirmacion antes de enviar. Se escucha en la fase de
   captura para que corra antes que cualquier otra validacion. */
document.addEventListener('submit', event => {
  const form = event.target;
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
}, true);

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
