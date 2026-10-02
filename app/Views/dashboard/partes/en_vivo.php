<?php
// Panel "En vivo": temperatura, pH, alertas, estado del ecosistema, grafico y tarjetas de estado.
// Los ids (temperatura, alertsMetric, etc.) los usa dashboard.js para actualizar sin recargar.
$cards = $dashboardData['cards'];
$resumen = $dashboardData['summary'];
$puntos = ['ok' => 'status-ok', 'neutral' => 'status-info', 'warn' => 'status-warn', 'danger' => 'status-danger'];
?>
<section class="dashboard-preview dashboard-live-panel" id="panel-principal">
  <div class="dashboard-preview-header">
    <div>
      <span class="dashboard-chip"><span class="signal-dot"></span> En vivo</span>
      <h2 class="dashboard-title">Acuario principal</h2>
    </div>
    <p>Ultima sincronizacion <strong id="latestTimestamp"><?= esc($resumen['syncText']) ?></strong></p>
  </div>

  <div class="dashboard-preview-grid dashboard-stat-grid">
    <article class="live-stat sensor-status-<?= esc($cards['temperature']['status']) ?>" data-card="temperature">
      <span>Temperatura</span>
      <strong data-field="value"><?= esc($cards['temperature']['value']) ?></strong>
      <small data-field="meta"><?= esc($cards['temperature']['meta']) ?></small>
    </article>

    <article class="live-stat sensor-status-<?= esc($cards['ph']['status']) ?>" data-card="ph">
      <span>pH</span>
      <strong data-field="value"><?= esc($cards['ph']['value']) ?></strong>
      <small data-field="meta"><?= esc($cards['ph']['meta']) ?></small>
    </article>

    <article class="live-stat" data-summary="alerts">
      <span>Alertas</span>
      <strong id="alertsMetric"><?= esc((string) $resumen['alertsCount']) ?></strong>
      <small id="alertsMetricMeta"><?= esc($resumen['alertsMeta']) ?></small>
    </article>

    <article class="ecosystem-state" data-summary="health">
      <span>Estado del ecosistema</span>
      <strong id="ecosystemHealth"><?= esc((string) $resumen['health']) ?>%</strong>
      <small id="ecosystemHealthMeta"><?= esc($resumen['alertsMeta']) ?></small>
      <div class="health-ring" aria-hidden="true"><span></span></div>
    </article>
  </div>

  <div class="dashboard-chart-area dashboard-live-area">
    <article class="chart-panel live-chart-panel">
      <div class="chart-head">
        <span>Temperatura / pH</span>
        <strong><?= esc($rangoGrafico) ?></strong>
      </div>
      <canvas id="ecosystemChart"></canvas>
    </article>

    <div class="alert-stack live-status-stack">
      <article data-status-card="water">
        <span id="waterStatusDot" class="<?= $puntos[$cards['waterLevel']['status']] ?>"></span>
        <div>
          <strong id="waterStatusTitle"><?= esc($resumen['waterTitle']) ?></strong>
          <small id="waterStatusMeta"><?= esc($cards['waterLevel']['meta']) ?></small>
        </div>
      </article>
      <article data-status-card="feeding">
        <span id="feedingStatusDot" class="<?= $puntos[$cards['lastFeeding']['status']] ?>"></span>
        <div>
          <strong id="feedingStatusTitle"><?= esc($resumen['feedingTitle']) ?></strong>
          <small id="feedingStatusMeta"><?= esc($resumen['feedingMeta']) ?></small>
        </div>
      </article>
      <article data-status-card="vacation">
        <span id="vacationStatusDot" class="<?= $puntos[$cards['vacationMode']['status']] ?>"></span>
        <div>
          <strong id="vacationStatusTitle"><?= esc($resumen['vacationTitle']) ?></strong>
          <small id="vacationStatusMeta"><?= esc($cards['vacationMode']['meta']) ?></small>
        </div>
      </article>
    </div>
  </div>
</section>
