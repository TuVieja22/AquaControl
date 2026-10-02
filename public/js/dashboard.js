/* ===================================================
   AquaControl — dashboard.js
   Panel de la pecera: dibuja los datos que manda el servidor y los vuelve a pedir
   cada 5 s (cada 2 s mientras el alimentador tiene una orden en curso). Los textos
   ya vienen armados desde PanelPecera.php: aca solo se ponen en su lugar.
   =================================================== */

'use strict';

(function () {
  const dataNode = document.getElementById('dashboard-data');
  if (!dataNode) return;

  const state = JSON.parse(dataNode.textContent || '{}');
  const CHART_JS = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
  const REFRESH_MS = 5000;
  const REFRESH_FEEDING_MS = 2000;
  const STATUS_DOTS = { ok: 'status-ok', neutral: 'status-info', warn: 'status-warn', danger: 'status-danger' };

  let chart = null;
  let chartKey = '';
  let refreshTimer = null;

  const $ = id => document.getElementById(id);

  function setText(id, value) {
    const node = $(id);
    if (node) node.textContent = value ?? '';
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  function setStatusDot(id, status) {
    const node = $(id);
    if (node) node.className = STATUS_DOTS[status] || 'status-info';
  }

  /* "hace 12 s", "hace 3 min" o dd/mm/aaaa hh:mm (igual que PanelPecera::haceCuanto) */
  function haceCuanto(value) {
    if (!value) return 'Sin lecturas';

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;

    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return `hace ${seconds} s`;
    if (seconds < 3600) return `hace ${Math.floor(seconds / 60)} min`;

    const pad = n => String(n).padStart(2, '0');
    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  /* ── Grafico (Chart.js se descarga aparte: si no carga, el resto del panel igual anda) ── */
  function renderChart() {
    const canvas = $('ecosystemChart');
    if (!canvas || typeof window.Chart === 'undefined') return;

    const charts = state.charts || {};
    // Si los datos no cambiaron desde el ultimo dibujo, no se toca el grafico.
    const key = `${charts.count}|${(charts.labels || []).at(-1)}|${(charts.temperature || []).at(-1)}|${(charts.ph || []).at(-1)}`;
    if (chart && key === chartKey) return;
    chartKey = key;

    if (chart) {
      chart.data.labels = charts.labels || [];
      chart.data.datasets[0].data = charts.temperature || [];
      chart.data.datasets[1].data = charts.ph || [];
      chart.update('none');
      return;
    }

    chart = new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: charts.labels || [],
        datasets: [
          { label: 'Temperatura', data: charts.temperature || [], yAxisID: 'temperature', borderColor: '#00d4ff', backgroundColor: 'rgba(0, 212, 255, 0.18)', tension: 0.42, fill: true },
          { label: 'pH', data: charts.ph || [], yAxisID: 'ph', borderColor: '#40f2bf', backgroundColor: 'rgba(64, 242, 191, 0.04)', tension: 0.42, fill: false }
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

  function loadChartJs() {
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

  /* ── Tarjetas, resumen y listas ── */
  function renderCards() {
    document.querySelectorAll('[data-card]').forEach(node => {
      const card = state.cards?.[node.dataset.card];
      if (!card) return;

      node.classList.remove('sensor-status-ok', 'sensor-status-warn', 'sensor-status-danger', 'sensor-status-neutral');
      node.classList.add(`sensor-status-${card.status}`);
      node.querySelector('[data-field="value"]').textContent = card.value ?? '--';
      node.querySelector('[data-field="meta"]').textContent = card.meta ?? '';
    });

    setText('vacationStateLabel', state.cards?.vacationMode?.value);
  }

  function renderSummary() {
    const summary = state.summary || {};
    const cards = state.cards || {};

    setText('latestTimestamp', haceCuanto(state.latestTimestamp));
    setText('alertsMetric', String(summary.alertsCount ?? 0));
    setText('alertsMetricMeta', summary.alertsMeta);
    setText('ecosystemHealth', `${summary.health ?? 0}%`);
    setText('ecosystemHealthMeta', summary.alertsMeta);

    setStatusDot('waterStatusDot', cards.waterLevel?.status);
    setText('waterStatusTitle', summary.waterTitle);
    setText('waterStatusMeta', cards.waterLevel?.meta);

    setStatusDot('feedingStatusDot', cards.lastFeeding?.status);
    setText('feedingStatusTitle', summary.feedingTitle);
    setText('feedingStatusMeta', summary.feedingMeta);

    setStatusDot('vacationStatusDot', cards.vacationMode?.status);
    setText('vacationStatusTitle', summary.vacationTitle);
    setText('vacationStatusMeta', cards.vacationMode?.meta);
  }

  function renderAlerts() {
    const list = $('alertsList');
    if (!list) return;

    list.innerHTML = state.alerts?.length
      ? state.alerts.map(alert => `
        <article class="alert-item">
          <span class="alert-badge level-${Number(alert.nivel)}">Nivel ${Number(alert.nivel)}</span>
          <div class="alert-copy">
            <strong>${escapeHtml(alert.mensaje)}</strong>
            <span>${escapeHtml(alert.time)}</span>
          </div>
          <button class="btn btn-outline mark-alert-btn" data-alert-id="${Number(alert.id)}" type="button">Marcar leida</button>
        </article>`).join('')
      : '<p class="empty-state">No hay alertas pendientes.</p>';
  }

  function renderFeedings() {
    const body = $('feedingsTableBody');
    if (!body) return;

    body.innerHTML = state.feedings?.length
      ? state.feedings.map(feeding => `
        <tr>
          <td>${escapeHtml(feeding.fecha)}</td>
          <td>${Number(feeding.grams).toFixed(2)} g</td>
          <td>${escapeHtml(feeding.tipoTexto)}</td>
        </tr>`).join('')
      : '<tr><td colspan="3" class="table-empty">Sin registros.</td></tr>';
  }

  function renderConfig() {
    const config = state.config || {};
    setText('configTempMin', `${Number(config.temp_min).toFixed(1)} °C`);
    setText('configTempMax', `${Number(config.temp_max).toFixed(1)} °C`);
    setText('configPhMin', Number(config.ph_min).toFixed(2));
    setText('configPhMax', Number(config.ph_max).toFixed(2));
    setText('configTargetTemp', `${Number(config.temp_objetivo).toFixed(1)} °C`);

    const targetInput = $('temp_objetivo');
    // No se pisa lo que el usuario esta escribiendo.
    if (targetInput && document.activeElement !== targetInput && Number.isFinite(Number(config.temp_objetivo))) {
      targetInput.value = Number(config.temp_objetivo).toFixed(1);
    }
  }

  function renderFeeder() {
    const feeder = state.feeder;
    const statusNode = $('feederStatus');
    if (!feeder || !statusNode) return;

    statusNode.className = `feeder-status feeder-status-${feeder.statusLevel}`;
    setText('feederStatusText', feeder.statusText);
    setText('feederLastText', feeder.lastText);
    setText('feederScheduleText', feeder.scheduleText);

    const feedButton = $('feedNowBtn');
    if (feedButton) feedButton.disabled = !feeder.hasDevice || !feeder.online || feeder.pending;
  }

  function renderAll() {
    renderFeeder();
    renderCards();
    renderSummary();
    renderAlerts();
    renderFeedings();
    renderConfig();
    renderChart();
  }

  function showFeedback(id, payload) {
    const node = $(id);
    if (!node) return;

    node.hidden = !payload?.message;
    node.textContent = payload?.message || '';
    node.classList.toggle('is-error', payload?.success === false);
  }

  /* ── Pedidos al servidor ── */
  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    window.AquaCsrf?.refresh(response);
    const payload = await response.json().catch(() => null);

    // Los errores (422/409) tambien traen los datos del panel y un mensaje.
    return response.ok || payload?.message ? payload : null;
  }

  function postForm(url, data = {}) {
    return requestJson(url, {
      method: 'POST',
      headers: window.AquaCsrf.headers({
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      }),
      body: new URLSearchParams(data)
    });
  }

  async function applyRequest(promise) {
    const payload = await promise.catch(() => null);
    if (payload) {
      ['config', 'cards', 'summary', 'alerts', 'feedings', 'charts', 'feeder', 'latestTimestamp'].forEach(key => {
        if (payload[key] !== undefined) state[key] = payload[key];
      });
      renderAll();
    }
    scheduleRefresh();
    return payload;
  }

  function refreshLatest() {
    // Con la pestana oculta no se pregunta nada: se retoma al volver (ver visibilitychange).
    if (document.hidden) return;
    applyRequest(requestJson(state.endpoints.latest, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }));
  }

  function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(refreshLatest, state.feeder?.pending ? REFRESH_FEEDING_MS : REFRESH_MS);
  }

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refreshLatest();
  });

  /* ── Botones y formularios ── */
  document.addEventListener('click', event => {
    const alertButton = event.target.closest('.mark-alert-btn');
    if (alertButton) {
      applyRequest(postForm(state.endpoints.markAlertTemplate.replace('__id__', alertButton.dataset.alertId)));
      return;
    }

    if (event.target.closest('#vacationToggleBtn')) {
      applyRequest(postForm(state.endpoints.vacationToggle));
    }
  });

  $('feedNowForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    $('feedNowBtn').disabled = true;

    const payload = await applyRequest(postForm(state.endpoints.feed, {
      cantidad_gramos: $('cantidad_gramos')?.value || ''
    }));
    showFeedback('feedNowFeedback', payload || { success: false, message: 'No se pudo enviar la orden.' });
    renderFeeder();
  });

  $('feedingScheduleForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const payload = await applyRequest(postForm(state.endpoints.feedingSchedule, {
      hora_alim_1: $('hora_alim_1')?.value || '',
      hora_alim_2: $('hora_alim_2')?.value || '',
      cantidad_alim_gramos: $('cantidad_alim_gramos')?.value || ''
    }));
    showFeedback('feedingScheduleFeedback', payload || { success: false, message: 'No se pudieron guardar los horarios.' });
  });

  $('targetTemperatureForm')?.addEventListener('submit', event => {
    event.preventDefault();
    applyRequest(postForm(state.endpoints.targetTemperature, { temp_objetivo: $('temp_objetivo')?.value || '' }));
  });

  renderAll();
  scheduleRefresh();
  loadChartJs().then(renderChart).catch(() => {});
}());
