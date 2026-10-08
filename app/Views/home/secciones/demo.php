<?php usar_css('css/home/secciones/demo.css') ?>
<?php // "Dashboard preview": panel de muestra. animaciones/portada.js le cambia los numeros cada pocos segundos. ?>
<section class="landing-section dashboard-preview-section" id="demo">
  <div class="container">
    <div class="section-heading" data-reveal>
      <p class="eyebrow">Dashboard preview</p>
      <h2>Una cabina de control futurista para tu ecosistema.</h2>
    </div>

    <div class="dashboard-preview" data-dashboard-demo data-reveal>
      <div class="dashboard-preview-header">
        <div>
          <span class="dashboard-chip"><span class="signal-dot"></span> En vivo</span>
          <h3>Acuario principal</h3>
        </div>
        <p>Ultima sincronizacion <strong data-live-sync>hace 4 s</strong></p>
      </div>

      <div class="dashboard-preview-grid">
        <div class="live-stat">
          <span>Temperatura</span>
          <strong data-live-temperature>25.6&deg;C</strong>
          <small>Rango ideal 24-27&deg;C</small>
        </div>
        <div class="live-stat">
          <span>pH</span>
          <strong data-live-ph>6.84</strong>
          <small>Agua estable</small>
        </div>
        <div class="live-stat">
          <span>Alertas</span>
          <strong data-live-alerts>0</strong>
          <small>Sin eventos criticos</small>
        </div>
        <div class="ecosystem-state">
          <span>Estado del ecosistema</span>
          <strong data-live-health>98%</strong>
          <div class="health-ring" aria-hidden="true"><span></span></div>
        </div>
      </div>

      <div class="dashboard-chart-area">
        <div class="chart-panel">
          <div class="chart-head">
            <span>Temperatura / pH</span>
            <strong>24 h</strong>
          </div>
          <svg class="preview-chart" viewBox="0 0 640 240" aria-hidden="true">
            <defs>
              <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#00d4ff" stop-opacity="0.32"/>
                <stop offset="100%" stop-color="#00d4ff" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path class="chart-area-fill" d="M20 172 C95 126 126 146 178 108 C245 58 292 120 350 86 C424 42 485 72 620 36 L620 220 L20 220 Z"/>
            <path class="chart-line-main" d="M20 172 C95 126 126 146 178 108 C245 58 292 120 350 86 C424 42 485 72 620 36"/>
            <path class="chart-line-secondary" d="M20 126 C92 146 128 98 194 134 C272 176 314 88 386 118 C462 148 524 104 620 132"/>
          </svg>
        </div>
        <div class="alert-stack">
          <article>
            <span class="status-ok"></span>
            <div>
              <strong>Agua clara</strong>
              <small>Turbidez dentro del rango</small>
            </div>
          </article>
          <article>
            <span class="status-info"></span>
            <div>
              <strong>Proxima alimentacion</strong>
              <small>Hoy 20:30</small>
            </div>
          </article>
          <article>
            <span class="status-ok"></span>
            <div>
              <strong>Modo Ausencia listo</strong>
              <small>Rutinas automatizadas</small>
            </div>
          </article>
        </div>
      </div>
    </div>
  </div>
</section>
