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
  const cardStatusClasses = ['sensor-status-ok', 'sensor-status-warn', 'sensor-status-danger', 'sensor-status-neutral'];
  const chartDefinitions = {
    temperature: {
      canvasId: 'temperatureChart',
      dataKey: 'temperature',
      label: 'Temperatura',
      fillColor: 'rgba(93,202,165,0.14)',
      lineColor: '#5DCAA5',
      minKey: 'temp_min',
      maxKey: 'temp_max',
      minLabel: 'Temp min',
      maxLabel: 'Temp max'
    },
    ph: {
      canvasId: 'phChart',
      dataKey: 'ph',
      label: 'pH',
      fillColor: 'rgba(29,158,117,0.14)',
      lineColor: '#1D9E75',
      minKey: 'ph_min',
      maxKey: 'ph_max',
      minLabel: 'pH min',
      maxLabel: 'pH max'
    }
  };

  const nodes = {
    latestTimestamp: document.getElementById('latestTimestamp'),
    vacationStateLabel: document.getElementById('vacationStateLabel'),
    alertsList: document.getElementById('alertsList'),
    feedingsTableBody: document.getElementById('feedingsTableBody')
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

  function formatCardStatus(status) {
    return statusLabels[status] || status || 'Info';
  }

  function mergePayload(payload) {
    ['config', 'cards', 'alerts', 'feedings', 'charts', 'latestTimestamp'].forEach(function (key) {
      if (payload[key] !== undefined) {
        state[key] = payload[key];
      }
    });
  }

  function buildReferenceSeries(labels, value) {
    return labels.map(function () {
      return value;
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
      plugins: {
        legend: {
          labels: { color: theme.label }
        }
      },
      scales: {
        x: {
          ticks: { color: theme.label },
          grid: { color: theme.grid }
        },
        y: {
          ticks: { color: theme.label },
          grid: { color: theme.grid }
        }
      }
    };
  }

  function datasetsFor(definition, labels) {
    return [
      {
        label: definition.label,
        data: state.charts?.[definition.dataKey] || [],
        borderColor: definition.lineColor,
        backgroundColor: definition.fillColor,
        tension: 0.35,
        fill: true
      },
      {
        label: definition.minLabel,
        data: buildReferenceSeries(labels, state.config?.[definition.minKey]),
        borderColor: 'rgba(239,159,39,0.9)',
        borderDash: [6, 6],
        pointRadius: 0,
        tension: 0
      },
      {
        label: definition.maxLabel,
        data: buildReferenceSeries(labels, state.config?.[definition.maxKey]),
        borderColor: 'rgba(226,75,74,0.9)',
        borderDash: [6, 6],
        pointRadius: 0,
        tension: 0
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
        metaNode.textContent = card.meta ?? '';
      }
    });

    if (nodes.vacationStateLabel && state.cards?.vacationMode?.value) {
      nodes.vacationStateLabel.textContent = state.cards.vacationMode.value;
    }
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

    nodes.latestTimestamp.textContent = formatDateTime(state.latestTimestamp, 'Sin lecturas');
  }

  function renderAll() {
    renderCards();
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
