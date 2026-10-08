<?php usar_css('css/dashboard/partes/filtros.css') ?>
<?php // Filtros del historial (solo en /dashboard/history): fechas y dispositivo. ?>
<section class="glass-card history-filters" id="filtros-historial">
  <div class="panel-head">
    <div>
      <p class="section-tag">Historial de lecturas</p>
      <h3>Filtrar por fecha y dispositivo</h3>
    </div>
  </div>
  <form class="history-filters-form" action="<?= base_url('dashboard/history') ?>#filtros-historial" method="GET">
    <div class="form-group">
      <label class="form-label" for="historyDesde">Desde</label>
      <input class="form-control" id="historyDesde" name="desde" type="date" max="<?= date('Y-m-d') ?>" value="<?= esc($historyFilters['custom'] ? $historyFilters['desde'] : '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="historyHasta">Hasta</label>
      <input class="form-control" id="historyHasta" name="hasta" type="date" max="<?= date('Y-m-d') ?>" value="<?= esc($historyFilters['custom'] ? $historyFilters['hasta'] : '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="historyDispositivo">Dispositivo</label>
      <select class="form-control" id="historyDispositivo" name="dispositivo">
        <option value="">Todos los dispositivos</option>
        <?php foreach ($devices as $device): ?>
          <option value="<?= esc((string) $device['id']) ?>" <?= (int) $device['id'] === $historyFilters['dispositivo'] ? 'selected' : '' ?>><?= esc($device['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="history-filters-actions">
      <button class="btn btn-primary" type="submit">Aplicar</button>
      <a class="btn btn-outline" href="<?= base_url('dashboard/history') ?>#filtros-historial">Limpiar</a>
    </div>
  </form>
  <?php if (! empty($historyFilters['error'])): ?>
    <p class="history-filters-error" role="alert"><?= esc($historyFilters['error']) ?></p>
  <?php endif; ?>
  <p class="history-filters-meta">
    Mostrando <strong><?= esc($rangoGrafico) ?></strong>:
    <?= esc((string) $dashboardData['charts']['count']) ?> lecturas.
    <?php if (! $historyFilters['custom']): ?>Sin rango elegido se muestran las ultimas 24 h.<?php endif; ?>
  </p>
</section>
