<?php // Alertas sin leer y ultimas 10 alimentaciones (dashboard.js redibuja ambas listas). ?>
<section class="dashboard-grid content-grid">
  <article class="glass-card alerts-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Alertas</p>
        <h3>Recientes sin leer</h3>
      </div>
    </div>
    <div id="alertsList" class="alerts-list">
      <?php if ($dashboardData['alerts'] === []): ?>
        <p class="empty-state">No hay alertas pendientes.</p>
      <?php endif; ?>
      <?php foreach ($dashboardData['alerts'] as $alert): ?>
        <article class="alert-item">
          <span class="alert-badge level-<?= $alert['nivel'] ?>">Nivel <?= $alert['nivel'] ?></span>
          <div class="alert-copy">
            <strong><?= esc($alert['mensaje']) ?></strong>
            <span><?= esc($alert['time']) ?></span>
          </div>
          <button class="btn btn-outline mark-alert-btn" data-alert-id="<?= $alert['id'] ?>" type="button">Marcar leida</button>
        </article>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="glass-card feeding-card" id="historial">
    <div class="panel-head">
      <div>
        <p class="section-tag">Historial</p>
        <h3>Ultimas 10 alimentaciones</h3>
      </div>
    </div>
    <div class="table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Cantidad</th>
            <th>Tipo</th>
          </tr>
        </thead>
        <tbody id="feedingsTableBody">
          <?php if ($dashboardData['feedings'] === []): ?>
            <tr><td colspan="3" class="table-empty">Sin registros.</td></tr>
          <?php endif; ?>
          <?php foreach ($dashboardData['feedings'] as $feeding): ?>
            <tr>
              <td><?= esc($feeding['fecha']) ?></td>
              <td><?= number_format($feeding['grams'], 2) ?> g</td>
              <td><?= esc($feeding['tipoTexto']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>
