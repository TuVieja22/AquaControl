/* ===================================================
   AquaControl — aqua.js
   public/js/aqua.js
   =================================================== */

'use strict';

/* Theme toggle */
const THEME_KEY = 'aquacontrol-theme';
const pageLoader = document.querySelector('[data-page-loader]');
let loaderRequests = 0;
const LOADER_SAFETY_TIMEOUT_MS = 9000;
let loaderSafetyTimer = null;

function armLoaderSafetyTimer() {
  if (!pageLoader) return;

  if (loaderSafetyTimer !== null) {
    window.clearTimeout(loaderSafetyTimer);
  }

  loaderSafetyTimer = window.setTimeout(() => {
    hideGlobalLoader(true);
  }, LOADER_SAFETY_TIMEOUT_MS);
}

function showGlobalLoader() {
  if (!pageLoader) return;
  pageLoader.classList.add('is-visible');
  document.body.classList.add('is-loading');
  armLoaderSafetyTimer();
}

function hideGlobalLoader(force = false) {
  if (!pageLoader) return;

  if (force) {
    loaderRequests = 0;
  }

  if (loaderRequests > 0) {
    return;
  }

  pageLoader.classList.remove('is-visible');
  document.body.classList.remove('is-loading');

  if (loaderSafetyTimer !== null) {
    window.clearTimeout(loaderSafetyTimer);
    loaderSafetyTimer = null;
  }
}

function setLoaderBusy(isBusy) {
  if (isBusy) {
    loaderRequests += 1;
    showGlobalLoader();
    return;
  }

  loaderRequests = Math.max(loaderRequests - 1, 0);
  hideGlobalLoader();
}

function readStoredTheme() {
  try {
    return localStorage.getItem(THEME_KEY);
  } catch (error) {
    return null;
  }
}

function writeStoredTheme(theme) {
  try {
    localStorage.setItem(THEME_KEY, theme);
  } catch (error) {
    return;
  }
}

function updateThemeToggleUi(theme) {
  const isLight = theme === 'light';

  document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
    toggle.checked = isLight;
    toggle.setAttribute('aria-checked', isLight ? 'true' : 'false');
    toggle.setAttribute('title', isLight ? 'Cambiar a tema oscuro' : 'Cambiar a tema claro');
  });
}

function applyTheme(theme, persist = true) {
  const nextTheme = theme === 'light' ? 'light' : 'dark';
  document.documentElement.dataset.theme = nextTheme;
  updateThemeToggleUi(nextTheme);

  if (persist) {
    writeStoredTheme(nextTheme);
  }

  window.dispatchEvent(new CustomEvent('themechange', {
    detail: { theme: nextTheme }
  }));
}

applyTheme(readStoredTheme() === 'light' ? 'light' : 'dark', false);

document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
  toggle.addEventListener('change', event => {
    applyTheme(event.target.checked ? 'light' : 'dark');
  });
});

showGlobalLoader();

document.addEventListener('DOMContentLoaded', () => {
  hideGlobalLoader(true);
}, { once: true });

window.addEventListener('load', () => {
  hideGlobalLoader(true);
});

window.addEventListener('pageshow', () => {
  hideGlobalLoader(true);
});

document.addEventListener('click', event => {
  const link = event.target.closest('a[href]');
  if (!link) return;
  if (link.hasAttribute('download') || link.target === '_blank') return;

  const rawHref = link.getAttribute('href') || '';
  if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) return;

  const targetUrl = new URL(link.href, window.location.href);
  if (targetUrl.origin !== window.location.origin) return;
  if (
    targetUrl.pathname === window.location.pathname &&
    targetUrl.search === window.location.search &&
    targetUrl.hash
  ) {
    return;
  }

  showGlobalLoader();
});

if (typeof window.fetch === 'function') {
  const nativeFetch = window.fetch.bind(window);

  window.fetch = async function (...args) {
    try {
      const options = args[1] || {};
      const headers = new Headers(options.headers || {});
      if (headers.get('X-Skip-Loader') === 'true') {
        return await nativeFetch(...args);
      }
    } catch (error) {
      // Continue with loader when headers are not readable.
    }

    setLoaderBusy(true);
    try {
      return await nativeFetch(...args);
    } finally {
      setLoaderBusy(false);
    }
  };
}

/* ── PASSWORD TOGGLE ─────────────────────────────── */
document.querySelectorAll('.input-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.input-group').querySelector('input');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
      ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
      : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
  });
});

/* ── PASSWORD STRENGTH ───────────────────────────── */
const pwInput = document.getElementById('password');
if (pwInput) {
  const segs = document.querySelectorAll('.strength-seg');
  const txt  = document.querySelector('.strength-text');

  const colors  = ['#E24B4A','#EF9F27','#EF9F27','#1D9E75','#5DCAA5'];
  const labels  = ['','Muy débil','Débil','Aceptable','Fuerte','Muy fuerte'];

  function calcStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return Math.min(score, 5);
  }

  pwInput.addEventListener('input', () => {
    const score = calcStrength(pwInput.value);
    segs.forEach((s, i) => {
      s.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,0.08)';
    });
    txt.textContent = pwInput.value.length ? labels[score] : '';
    txt.style.color = score >= 4 ? '#5DCAA5' : score >= 3 ? '#EF9F27' : '#F09595';
  });
}

/* ── CLIENT-SIDE FORM VALIDATION ─────────────────── */
function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showError(inputId, msg) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.classList.add('is-invalid');
  const fb = el.closest('.form-group')?.querySelector('.invalid-feedback');
  if (fb) { fb.innerHTML = `<span>⚠</span> ${msg}`; fb.style.display = 'flex'; }
}

function clearError(inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.classList.remove('is-invalid');
  const fb = el.closest('.form-group')?.querySelector('.invalid-feedback');
  if (fb) fb.style.display = 'none';
}

function clearAllErrors() {
  document.querySelectorAll('.form-control.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  document.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');
}

/* ── REGISTER FORM ───────────────────────────────── */
const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;

    const nombre   = document.getElementById('nombre')?.value.trim();
    const email    = document.getElementById('email')?.value.trim();
    const password = document.getElementById('password')?.value;
    const confirm  = document.getElementById('password_confirm')?.value;
    const terms    = document.getElementById('terms')?.checked;

    if (!nombre || nombre.length < 2) {
      showError('nombre', 'Ingresa tu nombre completo (mínimo 2 caracteres).'); valid = false;
    }
    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.'); valid = false;
    }
    if (!password || password.length < 8) {
      showError('password', 'La contraseña debe tener al menos 8 caracteres.'); valid = false;
    } else if (!/[A-Z]/.test(password)) {
      showError('password', 'Debe contener al menos una letra mayúscula.'); valid = false;
    } else if (!/[0-9]/.test(password)) {
      showError('password', 'Debe contener al menos un número.'); valid = false;
    }
    if (password !== confirm) {
      showError('password_confirm', 'Las contraseñas no coinciden.'); valid = false;
    }
    if (!terms) {
      showError('terms', 'Debes aceptar los términos y condiciones.'); valid = false;
    }

    if (!valid) e.preventDefault();
  });

  /* Live feedback */
  document.getElementById('email')?.addEventListener('blur', () => {
    const val = document.getElementById('email').value.trim();
    if (val && !validateEmail(val)) showError('email', 'Formato de correo inválido.');
    else clearError('email');
  });
  document.getElementById('password_confirm')?.addEventListener('input', () => {
    const pw = document.getElementById('password')?.value;
    const cf = document.getElementById('password_confirm')?.value;
    if (cf && pw !== cf) showError('password_confirm', 'Las contraseñas no coinciden.');
    else clearError('password_confirm');
  });
}

/* ── LOGIN FORM ──────────────────────────────────── */
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;
    const email    = document.getElementById('email')?.value.trim();
    const password = document.getElementById('password')?.value;

    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.'); valid = false;
    }
    if (!password) {
      showError('password', 'Ingresa tu contraseña.'); valid = false;
    }
    if (!valid) e.preventDefault();
  });
}

/* ── RECOVER FORM ────────────────────────────────── */
const recoverForm = document.getElementById('recoverForm');
if (recoverForm) {
  recoverForm.addEventListener('submit', e => {
    clearAllErrors();
    const email = document.getElementById('email')?.value.trim();
    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.');
      e.preventDefault();
    }
  });
}

/* ── RESET PASSWORD FORM ─────────────────────────── */
const resetForm = document.getElementById('resetForm');
if (resetForm) {
  resetForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;
    const password = document.getElementById('password')?.value;
    const confirm  = document.getElementById('password_confirm')?.value;

    if (!password || password.length < 8) {
      showError('password', 'La contraseña debe tener al menos 8 caracteres.'); valid = false;
    } else if (!/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
      showError('password', 'Debe tener mayúsculas y números.'); valid = false;
    }
    if (password !== confirm) {
      showError('password_confirm', 'Las contraseñas no coinciden.'); valid = false;
    }
    if (!valid) e.preventDefault();
  });
}

/* ── AUTO-HIDE FLASH MESSAGES ────────────────────── */
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity 0.5s ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 5000);
});

/* ── SCROLL ANIMATIONS ───────────────────────────── */
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.feature-card, .step, .alert-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(28px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });
}

/* Premium landing interactions */
const revealNodes = document.querySelectorAll('[data-reveal]');
if (revealNodes.length > 0) {
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });

    revealNodes.forEach((node, index) => {
      node.style.transitionDelay = `${Math.min(index % 5, 4) * 70}ms`;
      revealObserver.observe(node);
    });
  } else {
    revealNodes.forEach(node => node.classList.add('is-visible'));
  }
}

const landingDemo = document.querySelector('[data-dashboard-demo]');
if (landingDemo) {
  const temperatureNode = landingDemo.querySelector('[data-live-temperature]');
  const phNode = landingDemo.querySelector('[data-live-ph]');
  const alertNode = landingDemo.querySelector('[data-live-alerts]');
  const healthNode = landingDemo.querySelector('[data-live-health]');
  const syncNode = landingDemo.querySelector('[data-live-sync]');
  let tick = 0;

  function updateLiveMetric(node, value) {
    if (!node) return;

    const wrapper = node.closest('.live-stat, .ecosystem-state');
    node.textContent = value;

    if (wrapper) {
      wrapper.classList.add('is-updated');
      window.setTimeout(() => wrapper.classList.remove('is-updated'), 420);
    }
  }

  function renderLandingDemo() {
    tick += 1;
    const temperature = 25.4 + Math.sin(tick / 2) * 0.4;
    const ph = 6.82 + Math.cos(tick / 3) * 0.05;
    const health = 97 + Math.round(Math.sin(tick / 4) * 1);

    updateLiveMetric(temperatureNode, `${temperature.toFixed(1)}\u00b0C`);
    updateLiveMetric(phNode, ph.toFixed(2));
    updateLiveMetric(alertNode, tick % 9 === 0 ? '1' : '0');
    updateLiveMetric(healthNode, `${health}%`);

    if (syncNode) {
      syncNode.textContent = `hace ${2 + (tick % 5)} s`;
    }
  }

  renderLandingDemo();
  window.setInterval(renderLandingDemo, 2800);
}

document.querySelectorAll('[data-testimonial-carousel]').forEach(carousel => {
  const track = carousel.querySelector('.testimonial-track');
  const next = carousel.querySelector('[data-testimonial-next]');
  const prev = carousel.querySelector('[data-testimonial-prev]');

  if (!track) return;

  function scrollByCard(direction) {
    const card = track.querySelector('.testimonial-card');
    const amount = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.9;
    track.scrollBy({ left: amount * direction, behavior: 'smooth' });
  }

  next?.addEventListener('click', () => scrollByCard(1));
  prev?.addEventListener('click', () => scrollByCard(-1));
});

/* Dashboard */
const dashboardDataNode = document.getElementById('dashboard-data');
if (dashboardDataNode && !window.aquaDashboardHandledByModule) {
  const dashboardState = JSON.parse(dashboardDataNode.textContent || '{}');
  const cardNodes = document.querySelectorAll('[data-card]');
  const latestTimestamp = document.getElementById('latestTimestamp');
  const vacationStateLabel = document.getElementById('vacationStateLabel');
  const alertsList = document.getElementById('alertsList');
  const feedingsTableBody = document.getElementById('feedingsTableBody');
  let temperatureChart = null;
  let phChart = null;

  const statusLabels = {
    ok: 'OK',
    warn: 'Advertencia',
    danger: 'Critico',
    neutral: 'Info'
  };

  function buildReferenceSeries(labels, value) {
    return labels.map(() => value);
  }

  function getChartColors() {
    const styles = getComputedStyle(document.documentElement);

    return {
      label: styles.getPropertyValue('--chart-label').trim() || 'rgba(159,225,203,0.75)',
      grid: styles.getPropertyValue('--chart-grid').trim() || 'rgba(93,202,165,0.08)'
    };
  }

  function chartOptions() {
    const colors = getChartColors();

    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: colors.label } }
      },
      scales: {
        x: {
          ticks: { color: colors.label },
          grid: { color: colors.grid }
        },
        y: {
          ticks: { color: colors.label },
          grid: { color: colors.grid }
        }
      }
    };
  }

  function syncChartTheme(chart) {
    if (!chart) return;

    const colors = getChartColors();
    chart.options.plugins.legend.labels.color = colors.label;
    chart.options.scales.x.ticks.color = colors.label;
    chart.options.scales.x.grid.color = colors.grid;
    chart.options.scales.y.ticks.color = colors.label;
    chart.options.scales.y.grid.color = colors.grid;
    chart.update();
  }

  function renderCharts(payload) {
    if (typeof Chart === 'undefined') return;

    const labels = payload.charts?.labels || [];
    const config = payload.config || {};

    if (!temperatureChart) {
      temperatureChart = new Chart(document.getElementById('temperatureChart'), {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'Temperatura', data: payload.charts?.temperature || [], borderColor: '#5DCAA5', backgroundColor: 'rgba(93,202,165,0.14)', tension: 0.35, fill: true },
            { label: 'Temp min', data: buildReferenceSeries(labels, config.temp_min), borderColor: 'rgba(239,159,39,0.9)', borderDash: [6, 6], pointRadius: 0, tension: 0 },
            { label: 'Temp max', data: buildReferenceSeries(labels, config.temp_max), borderColor: 'rgba(226,75,74,0.9)', borderDash: [6, 6], pointRadius: 0, tension: 0 }
          ]
        },
        options: chartOptions()
      });
    } else {
      temperatureChart.data.labels = labels;
      temperatureChart.data.datasets[0].data = payload.charts?.temperature || [];
      temperatureChart.data.datasets[1].data = buildReferenceSeries(labels, config.temp_min);
      temperatureChart.data.datasets[2].data = buildReferenceSeries(labels, config.temp_max);
      temperatureChart.update();
    }
    syncChartTheme(temperatureChart);

    if (!phChart) {
      phChart = new Chart(document.getElementById('phChart'), {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'pH', data: payload.charts?.ph || [], borderColor: '#1D9E75', backgroundColor: 'rgba(29,158,117,0.14)', tension: 0.35, fill: true },
            { label: 'pH min', data: buildReferenceSeries(labels, config.ph_min), borderColor: 'rgba(239,159,39,0.9)', borderDash: [6, 6], pointRadius: 0, tension: 0 },
            { label: 'pH max', data: buildReferenceSeries(labels, config.ph_max), borderColor: 'rgba(226,75,74,0.9)', borderDash: [6, 6], pointRadius: 0, tension: 0 }
          ]
        },
        options: chartOptions()
      });
    } else {
      phChart.data.labels = labels;
      phChart.data.datasets[0].data = payload.charts?.ph || [];
      phChart.data.datasets[1].data = buildReferenceSeries(labels, config.ph_min);
      phChart.data.datasets[2].data = buildReferenceSeries(labels, config.ph_max);
      phChart.update();
    }
    syncChartTheme(phChart);
  }

  function renderCards(cards) {
    cardNodes.forEach(node => {
      const cardName = node.dataset.card;
      const payload = cards?.[cardName];
      if (!payload) return;

      node.classList.remove('sensor-status-ok', 'sensor-status-warn', 'sensor-status-danger', 'sensor-status-neutral');
      node.classList.add(`sensor-status-${payload.status}`);
      node.querySelector('[data-field="value"]').textContent = payload.value ?? '--';
      node.querySelector('[data-field="status"]').textContent = statusLabels[payload.status] || payload.status || 'Info';
      node.querySelector('[data-field="meta"]').textContent = payload.meta ?? '';
    });

    if (vacationStateLabel && cards?.vacationMode?.value) {
      vacationStateLabel.textContent = cards.vacationMode.value;
    }
  }

  function renderAlerts(alerts) {
    if (!alertsList) return;
    if (!alerts || !alerts.length) {
      alertsList.innerHTML = '<p class="empty-state">No hay alertas pendientes.</p>';
      return;
    }

    alertsList.innerHTML = alerts.map(alert => `
      <article class="alert-item">
        <span class="alert-badge level-${alert.nivel}">Nivel ${alert.nivel}</span>
        <div class="alert-copy">
          <strong>${alert.mensaje}</strong>
          <span>${alert.time}</span>
        </div>
        <button class="btn btn-outline mark-alert-btn" data-alert-id="${alert.id}" type="button">Marcar leida</button>
      </article>
    `).join('');
  }

  function renderFeedings(feedings) {
    if (!feedingsTableBody) return;
    if (!feedings || !feedings.length) {
      feedingsTableBody.innerHTML = '<tr><td colspan="3" class="table-empty">Sin registros.</td></tr>';
      return;
    }

    feedingsTableBody.innerHTML = feedings.map(item => `
      <tr>
        <td>${item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('es-AR') : '--'}</td>
        <td>${item.grams !== null ? Number(item.grams).toFixed(2) + ' g' : '--'}</td>
        <td>${item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : '--'}</td>
      </tr>
    `).join('');
  }

  function renderConfig(config) {
    const map = {
      configTempMin: `${Number(config.temp_min).toFixed(1)} °C`,
      configTempMax: `${Number(config.temp_max).toFixed(1)} °C`,
      configPhMin: Number(config.ph_min).toFixed(2),
      configPhMax: Number(config.ph_max).toFixed(2),
      configTargetTemp: `${Number(config.temp_objetivo).toFixed(1)} °C`
    };

    Object.entries(map).forEach(([id, value]) => {
      const node = document.getElementById(id);
      if (node) node.textContent = value;
    });

    const targetInput = document.getElementById('temp_objetivo');
    if (targetInput) targetInput.value = Number(config.temp_objetivo).toFixed(1);
  }

  function renderAll(payload, keepCharts = false) {
    renderCards(payload.cards || {});
    renderAlerts(payload.alerts || []);
    renderFeedings(payload.feedings || []);
    renderConfig(payload.config || {});
    if (latestTimestamp) {
      latestTimestamp.textContent = payload.latestTimestamp
        ? new Date(payload.latestTimestamp.replace(' ', 'T')).toLocaleString('es-AR')
        : 'Sin lecturas';
    }
    if (!keepCharts) renderCharts(payload);
  }

  async function postJson(url, body = {}) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(body)
    });
    return response.json();
  }

  async function refreshLatest() {
    const response = await fetch(dashboardState.endpoints.latest, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!response.ok) return;
    const payload = await response.json();
    renderAll({ ...dashboardState, ...payload }, true);
  }

  document.addEventListener('click', async event => {
    const alertButton = event.target.closest('.mark-alert-btn');
    if (alertButton) {
      const payload = await postJson(dashboardState.endpoints.markAlertTemplate.replace('__id__', alertButton.dataset.alertId));
      renderAlerts(payload.alerts || []);
    }

    const vacationButton = event.target.closest('#vacationToggleBtn, #vacationQuickToggle');
    if (vacationButton) {
      const payload = await postJson(dashboardState.endpoints.vacationToggle);
      renderConfig(payload.config || {});
      renderCards({ vacationMode: {
        value: payload.modoVacaciones ? 'Activo' : 'Inactivo',
        status: payload.modoVacaciones ? 'ok' : 'neutral',
        meta: payload.modoVacaciones ? 'Rutinas automaticas habilitadas' : 'Modo manual activo'
      }});
    }
  });

  document.getElementById('feedNowForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const grams = document.getElementById('cantidad_gramos')?.value || '5';
    const payload = await postJson(dashboardState.endpoints.feed, { cantidad_gramos: grams });
    renderFeedings(payload.feedings || []);
    renderCards({ lastFeeding: payload.lastFeeding || {} });
  });

  document.getElementById('targetTemperatureForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const target = document.getElementById('temp_objetivo')?.value || '';
    const payload = await postJson(dashboardState.endpoints.targetTemperature, { temp_objetivo: target });
    renderConfig(payload.config || {});
  });

  window.addEventListener('themechange', () => {
    syncChartTheme(temperatureChart);
    syncChartTheme(phChart);
  });

  renderAll(dashboardState);
  setInterval(refreshLatest, 30000);
}

/* Global loader hooks
   Registered after form-specific listeners so prevented submits do not lock the UI.
*/
document.addEventListener('submit', event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (form.dataset.skipLoader === 'true') return;
  if (event.defaultPrevented) return;

  showGlobalLoader();
});
