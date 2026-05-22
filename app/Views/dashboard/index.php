<?php $dashboardJson = json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
<?= view('layouts/header') ?>

<main class="dashboard-page" id="dashboardApp">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">AquaControl IoT</p>
      <h1>Mi pecera</h1>
      <p class="dashboard-intro">Monitoreo y control continuo de la pecera de <?= esc($dashboardData['userName']) ?>.</p>

      
      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link <?= $activeSection === 'overview' ? 'is-active' : '' ?>">Panel principal</a>
        <a href="<?= base_url('dashboard/history') ?>#historial" class="dashboard-link <?= $activeSection === 'history' ? 'is-active' : '' ?>">Historial</a>
        <a href="<?= base_url('dashboard/settings') ?>#configuracion" class="dashboard-link <?= $activeSection === 'settings' ? 'is-active' : '' ?>">Configuracion</a>
      </nav>

      <div class="logout-button-wrap dashboard-logout">
        <a href="<?= base_url('auth/logout') ?>" class="Btn" aria-label="Cerrar sesion">
          <span class="sign" aria-hidden="true">
            <svg viewBox="0 0 512 512">
              <path d="M377.9 105.9 500.7 228.7c15 15 15 39.3 0 54.3L377.9 406.1c-15.1 15.1-41 4.4-41-17V320H192c-22.1 0-40-17.9-40-40v-48c0-22.1 17.9-40 40-40h144.9v-69.1c0-21.4 25.9-32.1 41-17ZM192 352H96c-17.7 0-32-14.3-32-32V192c0-17.7 14.3-32 32-32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96C42.98 96 0 138.1 0 192v128c0 53 42.98 96 96 96h96c17.7 0 32-14.3 32-32s-14.3-32-32-32Z"/>
            </svg>
          </span>
          <span class="text">Salir</span>
        </a>
      </div>

      <div class="dashboard-user glass-card">
        <span class="dashboard-user-label">Usuario logueado</span>
        <strong><?= esc($dashboardData['userName']) ?></strong>
      </div>
    </aside>


    <div class="dashboard-main">
      <section class="dashboard-hero glass-card" id="panel-principal">
        <div>
          <h2 class="dashboard-title">Estado en tiempo real de la pecera</h2>
          <p class="dashboard-subtitle">Actualizacion automatica cada 30 segundos desde `dashboard/api/latest`.</p>
        </div>
        <div class="dashboard-hero-meta">
          <span class="live-dot"></span>
          <span id="latestTimestamp"><?= esc($dashboardData['latestTimestamp'] ? date('d/m/Y H:i', strtotime($dashboardData['latestTimestamp'])) : 'Sin lecturas') ?></span>
        </div>
      </section>

      <section class="sensor-grid">
        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['temperature']['status']) ?>" data-card="temperature">
          <div class="sensor-card-head"><span>🌡️</span><span>Temperatura</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['temperature']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['temperature']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['temperature']['meta']) ?></p>
        </article>

        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['ph']['status']) ?>" data-card="ph">
          <div class="sensor-card-head"><span>🧪</span><span>pH</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['ph']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['ph']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['ph']['meta']) ?></p>
        </article>

        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['waterLevel']['status']) ?>" data-card="waterLevel">
          <div class="sensor-card-head"><span>💧</span><span>Nivel de agua</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['waterLevel']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['waterLevel']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['waterLevel']['meta']) ?></p>
        </article>

        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['heater']['status']) ?>" data-card="heater">
          <div class="sensor-card-head"><span>🔌</span><span>Calefactor</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['heater']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['heater']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['heater']['meta']) ?></p>
        </article>

        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['lastFeeding']['status']) ?>" data-card="lastFeeding">
          <div class="sensor-card-head"><span>🍽️</span><span>Ultima alimentacion</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['lastFeeding']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['lastFeeding']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['lastFeeding']['meta']) ?></p>
        </article>

        <article class="sensor-card glass-card sensor-status-<?= esc($dashboardData['cards']['vacationMode']['status']) ?>" data-card="vacationMode">
          <div class="sensor-card-head"><span>🏖️</span><span>Modo vacaciones</span></div>
          <strong class="sensor-value" data-field="value"><?= esc($dashboardData['cards']['vacationMode']['value']) ?></strong>
          <span class="sensor-pill" data-field="status"><?= esc($dashboardData['cards']['vacationMode']['status']) ?></span>
          <p class="sensor-meta" data-field="meta"><?= esc($dashboardData['cards']['vacationMode']['meta']) ?></p>
          <button class="btn btn-outline sensor-inline-btn" id="vacationQuickToggle" type="button">Toggle</button>
        </article>
      </section>

      <section class="dashboard-grid charts-grid">
        <article class="glass-card chart-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Ultimas 24 horas</p>
              <h3>Temperatura</h3>
            </div>
            <span class="panel-range"><?= esc(number_format((float) $dashboardData['config']['temp_min'], 1)) ?> - <?= esc(number_format((float) $dashboardData['config']['temp_max'], 1)) ?> &deg;C</span>
          </div>
          <canvas id="temperatureChart"></canvas>
        </article>

        <article class="glass-card chart-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Ultimas 24 horas</p>
              <h3>pH</h3>
            </div>
            <span class="panel-range"><?= esc(number_format((float) $dashboardData['config']['ph_min'], 2)) ?> - <?= esc(number_format((float) $dashboardData['config']['ph_max'], 2)) ?></span>
          </div>
          <canvas id="phChart"></canvas>
        </article>
      </section>

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
            <?php else: ?>
              <?php foreach ($dashboardData['alerts'] as $alert): ?>
                <article class="alert-item">
                  <span class="alert-badge level-<?= esc((string) $alert['nivel']) ?>">Nivel <?= esc((string) $alert['nivel']) ?></span>
                  <div class="alert-copy">
                    <strong><?= esc($alert['mensaje']) ?></strong>
                    <span><?= esc($alert['time']) ?></span>
                  </div>
                  <button class="btn btn-outline mark-alert-btn" data-alert-id="<?= esc((string) $alert['id']) ?>" type="button">Marcar leida</button>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
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
                <?php else: ?>
                  <?php foreach ($dashboardData['feedings'] as $feeding): ?>
                    <tr>
                      <td><?= esc($feeding['created_at'] ? date('d/m/Y H:i', strtotime($feeding['created_at'])) : '--') ?></td>
                      <td><?= esc($feeding['grams'] !== null ? number_format((float) $feeding['grams'], 2) . ' g' : '--') ?></td>
                      <td><?= esc($feeding['type'] !== null ? ucfirst($feeding['type']) : '--') ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section class="dashboard-grid control-grid" id="configuracion">
        <article class="glass-card control-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Control manual</p>
              <h3>Acciones sobre actuadores</h3>
            </div>
          </div>

          <div class="control-actions">
            <form id="feedNowForm" class="control-form">
              <label class="form-label" for="cantidad_gramos">Cantidad a dispensar</label>
              <div class="control-inline">
                <input class="form-control" id="cantidad_gramos" name="cantidad_gramos" type="number" step="0.1" min="0.1" value="5">
                <button class="btn btn-primary" type="submit">Alimentar ahora</button>
              </div>
            </form>

            <div class="toggle-row">
              <div>
                <span class="form-label">Modo vacaciones</span>
                <p class="toggle-copy">Estado actual: <strong id="vacationStateLabel"><?= esc($dashboardData['cards']['vacationMode']['value']) ?></strong></p>
              </div>
              <button class="btn btn-outline" id="vacationToggleBtn" type="button">Activar / desactivar</button>
            </div>

            <form id="targetTemperatureForm" class="control-form">
              <label class="form-label" for="temp_objetivo">Temperatura objetivo</label>
              <div class="control-inline">
                <input class="form-control" id="temp_objetivo" name="temp_objetivo" type="number" step="0.1" min="10" max="40" value="<?= esc(number_format((float) $dashboardData['config']['temp_objetivo'], 1, '.', '')) ?>">
                <button class="btn btn-outline" type="submit">Guardar objetivo</button>
              </div>
            </form>
          </div>
        </article>

        <article class="glass-card control-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Configuracion</p>
              <h3>Rangos optimos vigentes</h3>
            </div>
          </div>

          <div class="config-metrics">
            <div class="config-metric">
              <span>Temperatura minima</span>
              <strong id="configTempMin"><?= esc(number_format((float) $dashboardData['config']['temp_min'], 1)) ?> &deg;C</strong>
            </div>
            <div class="config-metric">
              <span>Temperatura maxima</span>
              <strong id="configTempMax"><?= esc(number_format((float) $dashboardData['config']['temp_max'], 1)) ?> &deg;C</strong>
            </div>
            <div class="config-metric">
              <span>pH minimo</span>
              <strong id="configPhMin"><?= esc(number_format((float) $dashboardData['config']['ph_min'], 2)) ?></strong>
            </div>
            <div class="config-metric">
              <span>pH maximo</span>
              <strong id="configPhMax"><?= esc(number_format((float) $dashboardData['config']['ph_max'], 2)) ?></strong>
            </div>
            <div class="config-metric">
              <span>Temperatura objetivo</span>
              <strong id="configTargetTemp"><?= esc(number_format((float) $dashboardData['config']['temp_objetivo'], 1)) ?> &deg;C</strong>
            </div>
          </div>
        </article>
      </section>
    </div>
  </section>
</main>

<script id="dashboard-data" type="application/json"><?= $dashboardJson ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?= view('layouts/footer') ?>
