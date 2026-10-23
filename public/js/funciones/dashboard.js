/* ===================================================
   AquaControl — funciones/dashboard.js
   Panel de la pecera: pone en su lugar los datos que manda el servidor y los vuelve a
   pedir cada 5 s (cada 2 s mientras el alimentador tiene una orden en curso). Los
   textos ya vienen armados desde PanelPecera.php: aca solo se ponen en su lugar.
   El dibujo del grafico (colores, lineas) esta aparte, en js/animaciones/grafico.js.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  // Los datos del panel vienen escritos en la pagina (script dashboard-data).
  const dataNode = document.getElementById('dashboard-data');
  if (!dataNode) return;

  // Estado actual del panel, tiempos de actualizacion y clase CSS de cada estado.
  const state = JSON.parse(dataNode.textContent || '{}');
  const REFRESH_MS = 5000;
  const REFRESH_FEEDING_MS = 2000;
  const STATUS_DOTS = { ok: 'status-ok', neutral: 'status-info', warn: 'status-warn', danger: 'status-danger' };

  // Reloj de la proxima actualizacion.
  let refreshTimer = null;

  // Atajo para buscar un elemento por su id.
  const $ = id => document.getElementById(id);

  // Escribe un texto en un elemento (si existe).
  function setText(id, value) {
    const node = $(id);
    if (node) node.textContent = value ?? '';
  }

  // Convierte un texto en HTML seguro (para que no se interprete como etiquetas).
  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  // Cambia el color de un puntito de estado.
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

  /* ── Tarjetas, resumen y listas ── */
  function renderCards() {
    // Cada tarjeta: color segun su estado, valor y texto de abajo.
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

  // Resumen de arriba y las tres tarjetas de estado.
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

  // Lista de alertas (o un texto si no hay ninguna).
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

  // Tabla de las ultimas alimentaciones.
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

  // Rangos configurados y campo de temperatura objetivo.
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

  // Estado del alimentador; el boton se apaga si no se puede alimentar.
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

  // Vuelve a poner todos los datos en pantalla.
  function renderAll() {
    renderFeeder();
    renderCards();
    renderSummary();
    renderAlerts();
    renderFeedings();
    renderConfig();
    // El grafico lo dibuja animaciones/grafico.js: aca solo se le pasan los numeros.
    window.AquaGrafico?.dibujar(state.charts);
  }

  // Muestra el mensaje que respondio el servidor (marcado como error si lo es).
  function showFeedback(id, payload) {
    const node = $(id);
    if (!node) return;

    node.hidden = !payload?.message;
    node.textContent = payload?.message || '';
    node.classList.toggle('is-error', payload?.success === false);
  }

  /* ── Pedidos al servidor ── */
  // Hace un pedido al servidor y devuelve su respuesta ya convertida.
  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    window.AquaCsrf?.refresh(response);
    const payload = await response.json().catch(() => null);

    // Los errores (422/409) tambien traen los datos del panel y un mensaje.
    return response.ok || payload?.message ? payload : null;
  }

  // Envia datos al servidor como un formulario, con el token de seguridad.
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

  // Espera una respuesta, guarda los datos nuevos, redibuja y programa la proxima consulta.
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

  // Pide los datos actualizados.
  function refreshLatest() {
    // Con la pestana oculta no se pregunta nada: se retoma al volver (ver visibilitychange).
    if (document.hidden) return;
    applyRequest(requestJson(state.endpoints.latest, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }));
  }

  // Programa la proxima consulta: a los 2 s si hay una orden en curso, si no a los 5 s.
  function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(refreshLatest, state.feeder?.pending ? REFRESH_FEEDING_MS : REFRESH_MS);
  }

  // Al volver a la pestana, consulta enseguida.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refreshLatest();
  });

  /* ── Botones y formularios ── */
  document.addEventListener('click', event => {
    // Boton "Marcar leida" de una alerta.
    const alertButton = event.target.closest('.mark-alert-btn');
    if (alertButton) {
      applyRequest(postForm(state.endpoints.markAlertTemplate.replace('__id__', alertButton.dataset.alertId)));
      return;
    }

    // Boton del modo vacaciones.
    if (event.target.closest('#vacationToggleBtn')) {
      applyRequest(postForm(state.endpoints.vacationToggle));
    }
  });

  // Formulario "Alimentar ahora": manda la orden sin recargar la pagina.
  $('feedNowForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    $('feedNowBtn').disabled = true;

    const payload = await applyRequest(postForm(state.endpoints.feed, {
      cantidad_gramos: $('cantidad_gramos')?.value || ''
    }));
    showFeedback('feedNowFeedback', payload || { success: false, message: 'No se pudo enviar la orden.' });
    renderFeeder();
  });

  // Formulario de horarios.
  $('feedingScheduleForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const payload = await applyRequest(postForm(state.endpoints.feedingSchedule, {
      hora_alim_1: $('hora_alim_1')?.value || '',
      hora_alim_2: $('hora_alim_2')?.value || '',
      cantidad_alim_gramos: $('cantidad_alim_gramos')?.value || ''
    }));
    showFeedback('feedingScheduleFeedback', payload || { success: false, message: 'No se pudieron guardar los horarios.' });
  });

  // Formulario de temperatura objetivo.
  $('targetTemperatureForm')?.addEventListener('submit', event => {
    event.preventDefault();
    applyRequest(postForm(state.endpoints.targetTemperature, { temp_objetivo: $('temp_objetivo')?.value || '' }));
  });

  // Al cargar: dibuja todo y programa la primera consulta.
  renderAll();
  scheduleRefresh();
}());
