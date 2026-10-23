/* ===================================================
   AquaControl — animaciones/grafico.js
   Grafico de temperatura y pH del panel. Aca esta todo lo que tiene que ver con como
   se dibuja: colores, grosor de las lineas y el cartelito al pasar el mouse.
   Los numeros no salen de aca: se los pasa js/funciones/dashboard.js con
   AquaGrafico.dibujar(datos).
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

window.AquaGrafico = (function () {
  // Direccion de la libreria Chart.js y lugar de la pagina donde se dibuja.
  const CHART_JS = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
  const canvas = document.getElementById('ecosystemChart');

  // El grafico ya creado, una marca de lo ultimo dibujado y los ultimos datos recibidos.
  let chart = null;
  let chartKey = '';
  let datos = null;

  /* datos = { labels, temperature, ph, count } (lo arma PanelPecera.php). */
  function dibujar(nuevos) {
    // Guarda los datos; si la libreria todavia no cargo, se dibuja cuando llegue.
    datos = nuevos || {};
    if (!canvas || typeof window.Chart === 'undefined') return;

    // Si los datos no cambiaron desde el ultimo dibujo, no se toca el grafico.
    const key = `${datos.count}|${(datos.labels || []).at(-1)}|${(datos.temperature || []).at(-1)}|${(datos.ph || []).at(-1)}`;
    if (chart && key === chartKey) return;
    chartKey = key;

    // Si el grafico ya existe, solo se le cambian los datos (sin animacion).
    if (chart) {
      chart.data.labels = datos.labels || [];
      chart.data.datasets[0].data = datos.temperature || [];
      chart.data.datasets[1].data = datos.ph || [];
      chart.update('none');
      return;
    }

    // La primera vez se crea el grafico de lineas.
    chart = new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: datos.labels || [],
        // Las dos lineas: temperatura (celeste, con relleno) y pH (verde).
        datasets: [
          { label: 'Temperatura', data: datos.temperature || [], yAxisID: 'temperature', borderColor: '#00d4ff', backgroundColor: 'rgba(0, 212, 255, 0.18)', tension: 0.42, fill: true },
          { label: 'pH', data: datos.ph || [], yAxisID: 'ph', borderColor: '#40f2bf', backgroundColor: 'rgba(64, 242, 191, 0.04)', tension: 0.42, fill: false }
        ]
      },
      // Aspecto: sin puntos ni ejes a la vista, lineas gruesas y redondeadas.
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        elements: {
          point: { radius: 0, hitRadius: 10 },
          line: { borderWidth: 4, borderCapStyle: 'round', borderJoinStyle: 'round' }
        },
        plugins: {
          legend: { display: false },
          // Cartelito que aparece al pasar el mouse.
          tooltip: {
            backgroundColor: 'rgba(8, 14, 27, 0.92)',
            borderColor: 'rgba(0, 212, 255, 0.24)',
            borderWidth: 1,
            titleColor: '#ffffff',
            bodyColor: 'rgba(159, 225, 203, 0.75)',
            displayColors: false
          }
        },
        // Cada linea tiene su propia escala (no se dibujan).
        scales: {
          x: { display: false },
          temperature: { display: false, position: 'left' },
          ph: { display: false, position: 'right', grid: { drawOnChartArea: false } }
        }
      }
    });
  }

  // Descarga la libreria Chart.js agregando una etiqueta script a la pagina.
  function cargarChartJs() {
    if (window.Chart) return Promise.resolve();

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = CHART_JS;
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  // Chart.js se descarga aparte: si no carga, el resto del panel igual anda.
  if (canvas) {
    cargarChartJs().then(() => { if (datos) dibujar(datos); }).catch(() => {});
  }

  // Lo que pueden usar los otros archivos.
  return { dibujar };
}());
