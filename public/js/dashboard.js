'use strict';

(function () {
  const dataNode = document.getElementById('dashboard-data');
  if (!dataNode) {
    return;
  }

  const state = JSON.parse(dataNode.textContent || '{}');
  const charts = {};
  const statusLabels = {
    ok: 'OK',
    warn: 'Advertencia',
    danger: 'Critico',
    neutral: 'Info'
  };
  const statusDotClasses = ['status-ok', 'status-info', 'status-warn', 'status-danger'];
  const statusScores = {
    ok: 98,
    neutral: 96,
    warn: 78,
    danger: 44
  };
  const cardStatusClasses = ['sensor-status-ok', 'sensor-status-warn', 'sensor-status-danger', 'sensor-status-neutral'];
  const chartDefinitions = {
    ecosystem: {
      canvasId: 'ecosystemChart'
    }
  };

  const nodes = {
    latestTimestamp: document.getElementById('latestTimestamp'),
    vacationStateLabel: document.getElementById('vacationStateLabel'),
    alertsList: document.getElementById('alertsList'),
    feedingsTableBody: document.getElementById('feedingsTableBody'),
    alertsMetric: document.getElementById('alertsMetric'),
    alertsMetricMeta: document.getElementById('alertsMetricMeta'),
    ecosystemHealth: document.getElementById('ecosystemHealth'),
    ecosystemHealthMeta: document.getElementById('ecosystemHealthMeta'),
    waterStatusDot: document.getElementById('waterStatusDot'),
    waterStatusTitle: document.getElementById('waterStatusTitle'),
    waterStatusMeta: document.getElementById('waterStatusMeta'),
    feedingStatusDot: document.getElementById('feedingStatusDot'),
    feedingStatusTitle: document.getElementById('feedingStatusTitle'),
    feedingStatusMeta: document.getElementById('feedingStatusMeta'),
    vacationStatusDot: document.getElementById('vacationStatusDot'),
    vacationStatusTitle: document.getElementById('vacationStatusTitle'),
    vacationStatusMeta: document.getElementById('vacationStatusMeta')
  };

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  function formatDateTime(value, fallback = '--') {
    if (!value) {
      return fallback;
    }

    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('es-AR');
  }

  function formatRelativeTime(value, fallback = 'Sin lecturas') {
    if (!value) {
      return fallback;
    }

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    const elapsedSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (elapsedSeconds < 60) {
      return `hace ${elapsedSeconds} s`;
    }

    if (elapsedSeconds < 3600) {
      return `hace ${Math.floor(elapsedSeconds / 60)} min`;
    }

    return formatDateTime(value, fallback);
  }

  function formatCardStatus(status) {
    return statusLabels[status] || status || 'Info';
  }

  function metricMeta(count) {
    if (count === 0) {
      return 'Sin eventos criticos';
    }

    return count === 1 ? '1 evento pendiente' : `${count} eventos pendientes`;
  }

  function ecosystemHealth() {
    const cards = state.cards || {};
    const statuses = [
      cards.temperature?.status,
      cards.ph?.status,
      cards.waterLevel?.status,
      cards.heater?.status,
      cards.vacationMode?.status
    ];
    const total = statuses.reduce(function (sum, status) {
      return sum + (statusScores[status] || statusScores.neutral);
    }, 0);
    const score = Math.round(total / statuses.length) - ((state.alerts?.length || 0) * 6);

    return Math.max(0, Math.min(100, score));
  }

  function setText(node, value) {
    if (node) {
      node.textContent = value;
    }
  }

  function setStatusDot(node, status) {
    if (!node) {
      return;
    }

    node.classList.remove(...statusDotClasses);
    node.classList.add({
      ok: 'status-ok',
      neutral: 'status-info',
      warn: 'status-warn',
      danger: 'status-danger'
    }[status] || 'status-info');
  }

  function feedingMeta(card) {
    if (!card || card.value === '--') {
      return card?.meta || 'Sin registros';
    }

    return `${card.value} - ${card.meta || 'Registrada'}`;
  }

  function mergePayload(payload) {
    ['config', 'cards', 'alerts', 'feedings', 'charts', 'latestTimestamp'].forEach(function (key) {
      if (payload[key] !== undefined) {
        state[key] = payload[key];
      }
    });
  }

  function chartTheme() {
    const styles = getComputedStyle(document.documentElement);

    return {
      label: styles.getPropertyValue('--chart-label').trim() || 'rgba(159,225,203,0.75)',
      grid: styles.getPropertyValue('--chart-grid').trim() || 'rgba(93,202,165,0.08)'
    };
  }

  function chartOptions() {
    const theme = chartTheme();

    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      elements: {
        point: { radius: 0, hitRadius: 10 },
        line: { borderWidth: 4, borderCapStyle: 'round', borderJoinStyle: 'round' }
      },
      plugins: {
        legend: {
          display: false,
          labels: { color: theme.label }
        },
        tooltip: {
          backgroundColor: 'rgba(8, 14, 27, 0.92)',
          borderColor: 'rgba(0, 212, 255, 0.24)',
          borderWidth: 1,
          titleColor: '#ffffff',
          bodyColor: theme.label,
          displayColors: false
        }
      },
      scales: {
        x: {
          display: false,
          ticks: { color: theme.label },
          grid: { color: theme.grid, drawBorder: false }
        },
        temperature: {
          display: false,
          position: 'left',
          ticks: { color: theme.label },
          grid: { color: theme.grid, drawBorder: false }
        },
        ph: {
          display: false,
          position: 'right',
          ticks: { color: theme.label },
          grid: { drawOnChartArea: false, drawBorder: false }
        }
      }
    };
  }

  function datasetsFor(definition, labels) {
    return [
      {
        label: 'Temperatura',
        data: state.charts?.temperature || [],
        yAxisID: 'temperature',
        borderColor: '#00d4ff',
        backgroundColor: 'rgba(0, 212, 255, 0.18)',
        tension: 0.42,
        fill: true
      },
      {
        label: 'pH',
        data: state.charts?.ph || [],
        yAxisID: 'ph',
        borderColor: '#40f2bf',
        backgroundColor: 'rgba(64, 242, 191, 0.04)',
        tension: 0.42,
        fill: false
      }
    ];
  }

  function renderChart(key) {
    if (typeof Chart === 'undefined') {
      return;
    }

    const definition = chartDefinitions[key];
    const canvas = document.getElementById(definition.canvasId);
    const labels = state.charts?.labels || [];

    if (!canvas) {
      return;
    }

    if (!charts[key]) {
      charts[key] = new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: datasetsFor(definition, labels)
        },
        options: chartOptions()
      });
      return;
    }

    charts[key].data.labels = labels;
    charts[key].data.datasets = datasetsFor(definition, labels);
    charts[key].options = chartOptions();
    charts[key].update();
  }

  function renderCharts() {
    Object.keys(chartDefinitions).forEach(renderChart);
  }

  function renderCards() {
    document.querySelectorAll('[data-card]').forEach(function (cardNode) {
      const card = state.cards?.[cardNode.dataset.card];
      if (!card) {
        return;
      }

      cardNode.classList.remove(...cardStatusClasses);
      cardNode.classList.add(`sensor-status-${card.status}`);

      const valueNode = cardNode.querySelector('[data-field="value"]');
      const statusNode = cardNode.querySelector('[data-field="status"]');
      const metaNode = cardNode.querySelector('[data-field="meta"]');

      if (valueNode) {
        valueNode.textContent = card.value ?? '--';
      }

      if (statusNode) {
        statusNode.textContent = formatCardStatus(card.status);
      }

      if (metaNode) {
        let statMeta = card.meta;
        if (cardNode.matches('.dashboard-stat-grid [data-card="temperature"]')) {
          statMeta = String(card.meta || '').replace('Optimo:', 'Rango ideal');
        }
        if (cardNode.matches('.dashboard-stat-grid [data-card="ph"]') && card.status === 'ok') {
          statMeta = 'Agua estable';
        }
        metaNode.textContent = statMeta ?? '';
      }
    });

    if (nodes.vacationStateLabel && state.cards?.vacationMode?.value) {
      nodes.vacationStateLabel.textContent = state.cards.vacationMode.value;
    }
  }

  function renderLiveSummary() {
    const alertsCount = state.alerts?.length || 0;
    const alertsMeta = metricMeta(alertsCount);
    const health = ecosystemHealth();
    const water = state.cards?.waterLevel || {};
    const feeding = state.cards?.lastFeeding || {};
    const vacation = state.cards?.vacationMode || {};

    setText(nodes.alertsMetric, String(alertsCount));
    setText(nodes.alertsMetricMeta, alertsMeta);
    setText(nodes.ecosystemHealth, `${health}%`);
    setText(nodes.ecosystemHealthMeta, alertsMeta);

    setStatusDot(nodes.waterStatusDot, water.status);
    setText(nodes.waterStatusTitle, water.status === 'ok' ? 'Agua clara' : 'Revisar nivel');
    setText(nodes.waterStatusMeta, water.meta || 'Sin lecturas');

    setStatusDot(nodes.feedingStatusDot, feeding.status);
    setText(nodes.feedingStatusTitle, feeding.value === '--' ? 'Alimentacion pendiente' : 'Ultima alimentacion');
    setText(nodes.feedingStatusMeta, feedingMeta(feeding));

    setStatusDot(nodes.vacationStatusDot, vacation.status);
    setText(nodes.vacationStatusTitle, vacation.value === 'Activo' ? 'Modo Ausencia listo' : 'Modo manual activo');
    setText(nodes.vacationStatusMeta, vacation.meta || '');
  }

  function renderAlerts() {
    if (!nodes.alertsList) {
      return;
    }

    if (!state.alerts?.length) {
      nodes.alertsList.innerHTML = '<p class="empty-state">No hay alertas pendientes.</p>';
      return;
    }

    nodes.alertsList.innerHTML = state.alerts.map(function (alert) {
      return `
        <article class="alert-item">
          <span class="alert-badge level-${escapeHtml(String(alert.nivel))}">Nivel ${escapeHtml(String(alert.nivel))}</span>
          <div class="alert-copy">
            <strong>${escapeHtml(alert.mensaje)}</strong>
            <span>${escapeHtml(alert.time)}</span>
          </div>
          <button class="btn btn-outline mark-alert-btn" data-alert-id="${escapeHtml(String(alert.id))}" type="button">Marcar leida</button>
        </article>
      `;
    }).join('');
  }

  function renderFeedings() {
    if (!nodes.feedingsTableBody) {
      return;
    }

    if (!state.feedings?.length) {
      nodes.feedingsTableBody.innerHTML = '<tr><td colspan="3" class="table-empty">Sin registros.</td></tr>';
      return;
    }

    nodes.feedingsTableBody.innerHTML = state.feedings.map(function (feeding) {
      const grams = feeding.grams !== null ? `${Number(feeding.grams).toFixed(2)} g` : '--';
      const type = feeding.type ? feeding.type.charAt(0).toUpperCase() + feeding.type.slice(1) : '--';

      return `
        <tr>
          <td>${escapeHtml(formatDateTime(feeding.created_at))}</td>
          <td>${escapeHtml(grams)}</td>
          <td>${escapeHtml(type)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderConfig() {
    const config = state.config || {};
    const configMap = {
      configTempMin: `${Number(config.temp_min).toFixed(1)} °C`,
      configTempMax: `${Number(config.temp_max).toFixed(1)} °C`,
      configPhMin: Number(config.ph_min).toFixed(2),
      configPhMax: Number(config.ph_max).toFixed(2),
      configTargetTemp: `${Number(config.temp_objetivo).toFixed(1)} °C`
    };

    Object.entries(configMap).forEach(function ([id, value]) {
      const node = document.getElementById(id);
      if (node) {
        node.textContent = value;
      }
    });

    const targetInput = document.getElementById('temp_objetivo');
    if (targetInput && Number.isFinite(Number(config.temp_objetivo))) {
      targetInput.value = Number(config.temp_objetivo).toFixed(1);
    }
  }

  function renderLatestTimestamp() {
    if (!nodes.latestTimestamp) {
      return;
    }

    nodes.latestTimestamp.textContent = formatRelativeTime(state.latestTimestamp, 'Sin lecturas');
  }

  function renderAll() {
    renderCards();
    renderLiveSummary();
    renderAlerts();
    renderFeedings();
    renderConfig();
    renderLatestTimestamp();
    renderCharts();
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    if (!response.ok) {
      return null;
    }

    return response.json().catch(function () {
      return null;
    });
  }

  async function postForm(url, data = {}) {
    return requestJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams(data)
    });
  }

  async function applyRequest(promise) {
    const payload = await promise;
    if (!payload) {
      return;
    }

    mergePayload(payload);
    renderAll();
  }

  document.addEventListener('click', function (event) {
    const alertButton = event.target.closest('.mark-alert-btn');
    if (alertButton) {
      applyRequest(postForm(state.endpoints.markAlertTemplate.replace('__id__', alertButton.dataset.alertId)));
      return;
    }

    const vacationButton = event.target.closest('#vacationToggleBtn, #vacationQuickToggle');
    if (vacationButton) {
      applyRequest(postForm(state.endpoints.vacationToggle));
    }
  });

  document.getElementById('feedNowForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    applyRequest(postForm(state.endpoints.feed, {
      cantidad_gramos: document.getElementById('cantidad_gramos')?.value || '5'
    }));
  });

  document.getElementById('targetTemperatureForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    applyRequest(postForm(state.endpoints.targetTemperature, {
      temp_objetivo: document.getElementById('temp_objetivo')?.value || ''
    }));
  });

  window.addEventListener('themechange', renderCharts);

  renderAll();

  window.setInterval(function () {
    applyRequest(requestJson(state.endpoints.latest, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }));
  }, 30000);
}());
