/* ===================================================
   AquaControl — funciones/notificaciones.js
   "Mi cuenta" -> Notificaciones: activa las alertas de la pecera en este celular o PC.
     1. Instala sw.js (el que recibe los avisos aunque la pagina este cerrada).
     2. Pide permiso para mostrar notificaciones.
     3. Se suscribe al servicio de push del navegador con la clave publica de la pagina
        y le pasa esa suscripcion al servidor (POST dashboard/notificaciones).
   Necesita https (o localhost). En iPhone, solo desde el acceso directo de la pantalla de inicio.
   Aca no hay nada visual: solo textos y botones que se muestran u ocultan.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  const caja = document.querySelector('[data-push]');
  if (!caja) return;

  // Atajos a las partes del bloque.
  const $ = selector => caja.querySelector(selector);
  const texto = $('[data-push-texto]');
  const botones = { activar: $('[data-push-activar]'), probar: $('[data-push-probar]'), desactivar: $('[data-push-desactivar]') };

  // Muestra un texto y solo los botones indicados.
  function mostrar(mensaje, visibles = []) {
    texto.textContent = mensaje;
    Object.entries(botones).forEach(([nombre, boton]) => {
      boton.hidden = !visibles.includes(nombre);
      boton.disabled = false;
    });
  }

  // La clave publica viene en base64url; el navegador la quiere como bytes.
  function claveEnBytes(base64url) {
    const base64 = (base64url + '='.repeat((4 - base64url.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), letra => letra.charCodeAt(0));
  }

  // Manda JSON al servidor con el sello de seguridad (CSRF) y devuelve la respuesta.
  async function enviar(url, datos) {
    const response = await fetch(url, {
      method: 'POST',
      headers: window.AquaCsrf.headers({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }),
      body: JSON.stringify(datos),
    });
    window.AquaCsrf.refresh(response);
    return response.json();
  }

  // Sin lo necesario, se explica por que y no se ofrece nada.
  if (!window.isSecureContext) {
    mostrar('Para recibir notificaciones, abre la página con una dirección https://.');
    return;
  }
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    mostrar('Este navegador no puede recibir notificaciones. En iPhone, primero agrega la página a la pantalla de inicio y ábrela desde ahí.');
    return;
  }
  if (!caja.dataset.clave) {
    mostrar('Las notificaciones todavía no están configuradas en el servidor (faltan las claves webpush en .env).');
    return;
  }

  let registro = null;

  // Estado actual: activadas, bloqueadas o sin activar.
  async function revisar() {
    registro = await navigator.serviceWorker.register(caja.dataset.swUrl);
    const suscripcion = await registro.pushManager.getSubscription();

    if (Notification.permission === 'denied') {
      mostrar('Bloqueaste las notificaciones de esta página. Para activarlas, toca el candado de la barra de direcciones y permite las notificaciones.');
    } else if (suscripcion) {
      // Se vuelve a guardar por si cambio de cuenta en este navegador.
      await enviar(caja.dataset.guardarUrl, suscripcion.toJSON());
      mostrar('Las notificaciones están activadas en este dispositivo.', ['probar', 'desactivar']);
    } else {
      mostrar('Recibe en este dispositivo las alertas de tu pecera (temperatura, desconexiones, comidas que fallaron).', ['activar']);
    }
  }

  // Activar: permiso, suscripcion y aviso al servidor.
  async function activar() {
    botones.activar.disabled = true;
    try {
      if (await Notification.requestPermission() !== 'granted') {
        await revisar();
        return;
      }
      const suscripcion = await registro.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: claveEnBytes(caja.dataset.clave),
      });
      const respuesta = await enviar(caja.dataset.guardarUrl, suscripcion.toJSON());
      if (!respuesta.success) {
        mostrar(respuesta.message || 'No se pudieron activar las notificaciones.', ['activar']);
        return;
      }
      mostrar('¡Listo! Las notificaciones están activadas en este dispositivo.', ['probar', 'desactivar']);
    } catch (error) {
      mostrar('No se pudieron activar las notificaciones. Revisa tu conexión y vuelve a intentarlo.', ['activar']);
    }
  }

  // Desactivar: se borra la suscripcion en el navegador y en el servidor.
  async function desactivar() {
    botones.desactivar.disabled = true;
    const suscripcion = await registro.pushManager.getSubscription();
    if (suscripcion) {
      await enviar(caja.dataset.borrarUrl, { endpoint: suscripcion.endpoint });
      await suscripcion.unsubscribe();
    }
    mostrar('Las notificaciones están desactivadas en este dispositivo.', ['activar']);
  }

  // Prueba: el servidor manda un aviso a todos los dispositivos activados.
  async function probar() {
    botones.probar.disabled = true;
    try {
      const respuesta = await enviar(caja.dataset.probarUrl, {});
      mostrar(respuesta.message, ['probar', 'desactivar']);
    } catch (error) {
      mostrar('No se pudo hablar con la página. Revisa tu conexión.', ['probar', 'desactivar']);
    }
  }

  // Botones.
  botones.activar.addEventListener('click', activar);
  botones.desactivar.addEventListener('click', desactivar);
  botones.probar.addEventListener('click', probar);

  revisar().catch(() => mostrar('No se pudieron preparar las notificaciones en este navegador.'));
}());
