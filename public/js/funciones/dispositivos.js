/* ===================================================
   AquaControl — funciones/dispositivos.js
   Pagina de Dispositivos: cada 5 s le pregunta al servidor si aparecio un AquaControl
   nuevo en la red del usuario (o si se fue) y como esta cada dispositivo. Si algo cambio,
   recarga la pagina, que ya trae los avisos armados ("Encontrado en tu red", "Conectado").
   Aca no hay nada visual: solo preguntas y recargas.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  const tarjeta = document.querySelector('[data-dispositivos]');
  if (!tarjeta) return;

  // Cada cuanto se pregunta, y los nuevos que ya muestra la pagina (ids separados por coma).
  const CADA_MS = 5000;
  const nuevosEnPagina = tarjeta.dataset.nuevos;
  // Estados de la primera respuesta: contra eso se compara.
  let estadosIniciales = null;

  // Pregunta y, si cambio algo, recarga.
  async function preguntar() {
    // Con la pestana oculta no se pregunta: se retoma al volver.
    if (document.hidden) return;
    try {
      const response = await fetch(tarjeta.dataset.estadoUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
      });
      // Si la sesion se cerro, el servidor redirige al login: se recarga para que se vea.
      if (response.redirected) {
        window.location.reload();
        return;
      }
      if (!response.ok) return;
      const datos = await response.json();

      // Aparecio (o se fue) un AquaControl nuevo en la red.
      if (datos.nuevos.join(',') !== nuevosEnPagina) {
        window.location.reload();
        return;
      }
      // Algun dispositivo cambio de estado (por ejemplo, el recien agregado ya se conecto).
      const estados = JSON.stringify(datos.estados);
      if (estadosIniciales !== null && estados !== estadosIniciales) {
        window.location.reload();
        return;
      }
      estadosIniciales = estados;
    } catch (error) {
      // Sin conexion un momento: se vuelve a preguntar en el proximo turno.
    }
  }

  // Arranca y repite; al volver a la pestana, pregunta enseguida.
  preguntar();
  window.setInterval(preguntar, CADA_MS);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) preguntar();
  });
}());
