/* ===================================================
   AquaControl — sw.js (service worker)
   Lo instala el navegador desde "Mi cuenta" al activar las notificaciones y sigue andando
   aunque la pagina este cerrada: recibe las alertas de la pecera y las muestra.
   Va en la raiz de la pagina para poder atender todas sus direcciones.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Llega un aviso del servidor (ya descifrado por el navegador): se muestra la notificacion.
self.addEventListener('push', event => {
  let datos = {};
  try {
    datos = event.data ? event.data.json() : {};
  } catch (error) {
    datos = { mensaje: event.data ? event.data.text() : '' };
  }

  event.waitUntil(self.registration.showNotification(datos.titulo || 'AquaControl', {
    body: datos.mensaje || 'Hay una novedad en tu pecera.',
    icon: 'img/iconos/icono-192.png',
    data: { url: datos.url || './dashboard' },
    // Un aviso nuevo reemplaza al anterior en vez de apilarse, pero vuelve a sonar.
    tag: 'aquacontrol-alerta',
    renotify: true,
  }));
});

// Al tocar la notificacion: si la pagina ya esta abierta la trae al frente; si no, la abre.
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = new URL(event.notification.data.url, self.registration.scope).href;

  event.waitUntil((async () => {
    const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const abierta = ventanas.find(ventana => ventana.url.startsWith(self.registration.scope));
    if (abierta) {
      await abierta.focus();
      return abierta.navigate(url);
    }
    return self.clients.openWindow(url);
  })());
});
