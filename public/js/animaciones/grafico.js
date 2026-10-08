/* ===================================================
   AquaControl — animaciones/grafico.js
   Grafico de temperatura y pH del panel. Aca esta todo lo que tiene que ver con como
   se dibuja: colores, grosor de las lineas y el cartelito al pasar el mouse.
   Los numeros no salen de aca: se los pasa js/funciones/dashboard.js con
   AquaGrafico.dibujar(datos).
   =================================================== */

'use strict';

window.AquaGrafico = (function () {
  const CHART_JS = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
  const canvas = document.getElementById('ecosystemChart');

  let chart = null;
  let chartKey = '';
  let datos = null;

  /* datos = { labels, temperature, ph, count } (lo arma PanelPecera.php). */
  function dibujar(nuevos) {
    datos = nuevos || {};
    if (!canvas || typeof window.Chart === 'undefined') return;

    // Si los datos no cambiaron desde el ultimo dibujo, no se toca el grafico.
    const key = `${datos.count}|${(datos.labels || []).at(-1)}|${(datos.temperature || []).at(-1)}|${(datos.ph || []).at(-1)}`;
    if (chart && key === chartKey) return;
    chartKey = key;

    if (chart) {
      chart.data.labels = datos.labels || [];
      chart.data.datasets[0].data = datos.temperature || [];
      chart.data.datasets[1].data = datos.ph || [];
      chart.update('none');
      return;
    }

    chart = new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: datos.labels || [],
        datasets: [
          { label: 'Temperatura', data: datos.temperature || [], yAxisID: 'temperature', borderColor: '#00d4ff', backgroundColor: 'rgba(0, 212, 255, 0.18)', tension: 0.42, fill: true },
          { label: 'pH', data: datos.ph || [], yAxisID: 'ph', borderColor: '#40f2bf', backgroundColor: 'rgba(64, 242, 191, 0.04)', tension: 0.42, fill: false }
        ]
      },
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
          tooltip: {
            backgroundColor: 'rgba(8, 14, 27, 0.92)',
            borderColor: 'rgba(0, 212, 255, 0.24)',
            borderWidth: 1,
            titleColor: '#ffffff',
            bodyColor: 'rgba(159, 225, 203, 0.75)',
            displayColors: false
          }
        },
        scales: {
          x: { display: false },
          temperature: { display: false, position: 'left' },
          ph: { display: false, position: 'right', grid: { drawOnChartArea: false } }
        }
      }
    });
  }

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

  return { dibujar };
}());
