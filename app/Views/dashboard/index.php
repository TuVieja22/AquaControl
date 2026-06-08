<?php
$dashboardJson = json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$isAdmin = session()->get('user_role') === 'administrador';
$cards = $dashboardData['cards'];
$alertCount = count($dashboardData['alerts'] ?? []);
$statusScores = [
    'ok'      => 98,
    'neutral' => 96,
    'warn'    => 78,
    'danger'  => 44,
];
$healthStatuses = [
    $cards['temperature']['status'] ?? 'neutral',
    $cards['ph']['status'] ?? 'neutral',
    $cards['waterLevel']['status'] ?? 'neutral',
    $cards['heater']['status'] ?? 'neutral',
    $cards['vacationMode']['status'] ?? 'neutral',
];
$healthTotal = 0;
foreach ($healthStatuses as $status) {
    $healthTotal += $statusScores[$status] ?? $statusScores['neutral'];
}
$ecosystemHealth = max(0, min(100, (int) round($healthTotal / max(count($healthStatuses), 1)) - ($alertCount * 6)));
$alertMetricMeta = $alertCount === 0
    ? 'Sin eventos criticos'
    : ($alertCount === 1 ? '1 evento pendiente' : $alertCount . ' eventos pendientes');
$latestSyncText = 'Sin lecturas';
if (! empty($dashboardData['latestTimestamp'])) {
    $elapsedSeconds = max(0, time() - strtotime($dashboardData['latestTimestamp']));
    if ($elapsedSeconds < 60) {
        $latestSyncText = 'hace ' . $elapsedSeconds . ' s';
    } elseif ($elapsedSeconds < 3600) {
        $latestSyncText = 'hace ' . floor($elapsedSeconds / 60) . ' min';
    } else {
        $latestSyncText = date('d/m/Y H:i', strtotime($dashboardData['latestTimestamp']));
    }
}
$waterTitle = ($cards['waterLevel']['status'] ?? 'neutral') === 'ok' ? 'Agua clara' : 'Revisar nivel';
$feedingTitle = ($cards['lastFeeding']['value'] ?? '--') === '--' ? 'Alimentacion pendiente' : 'Ultima alimentacion';
$feedingMeta = ($cards['lastFeeding']['value'] ?? '--') === '--'
    ? ($cards['lastFeeding']['meta'] ?? 'Sin registros')
    : ($cards['lastFeeding']['value'] . ' - ' . ($cards['lastFeeding']['meta'] ?? 'Registrada'));
$vacationTitle = ($cards['vacationMode']['value'] ?? 'Inactivo') === 'Activo' ? 'Modo Ausencia listo' : 'Modo manual activo';
$statusDotClasses = [
    'ok'      => 'status-ok',
    'neutral' => 'status-info',
    'warn'    => 'status-warn',
    'danger'  => 'status-danger',
];
$profile = $profile ?? [];
$profileErrors = $profileErrors ?? [];
$profileForm = $profileForm ?? [];
$profileName = $profileForm['nombre'] ?? ($profile['nombre'] ?? '');
$profileEmail = $profileForm['email'] ?? ($profile['email'] ?? '');
$profileInitial = strtoupper(substr(trim((string) $profileName), 0, 1)) ?: 'U';
$profileCreated = ! empty($profile['created_at']) ? date('d/m/Y', strtotime($profile['created_at'])) : 'Sin datos';
$profileUpdated = ! empty($profile['updated_at']) ? date('d/m/Y H:i', strtotime($profile['updated_at'])) : 'Sin cambios';
?>
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
        <a href="<?= base_url('dashboard/profile') ?>#perfil" class="dashboard-link <?= $activeSection === 'profile' ? 'is-active' : '' ?>">Mi cuenta</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link">Dispositivos</a>
        <?php if ($isAdmin): ?>
          <a href="<?= base_url('usuarios') ?>" class="dashboard-link">Usuarios</a>
        <?php endif; ?>
      </nav>

    </aside>


    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

      <section class="dashboard-preview dashboard-live-panel" id="panel-principal">
        <div class="dashboard-preview-header">
          <div>
            <span class="dashboard-chip"><span class="signal-dot"></span> En vivo</span>
            <h2 class="dashboard-title">Acuario principal</h2>
          </div>
          <p>Ultima sincronizacion <strong id="latestTimestamp"><?= esc($latestSyncText) ?></strong></p>
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
            <small data-field="meta"><?= esc(($cards['ph']['status'] ?? 'neutral') === 'ok' ? 'Agua estable' : $cards['ph']['meta']) ?></small>
          </article>

          <article class="live-stat" data-summary="alerts">
            <span>Alertas</span>
            <strong id="alertsMetric"><?= esc((string) $alertCount) ?></strong>
            <small id="alertsMetricMeta"><?= esc($alertMetricMeta) ?></small>
          </article>

          <article class="ecosystem-state" data-summary="health">
            <span>Estado del ecosistema</span>
            <strong id="ecosystemHealth"><?= esc((string) $ecosystemHealth) ?>%</strong>
            <small id="ecosystemHealthMeta"><?= esc($alertMetricMeta) ?></small>
            <div class="health-ring" aria-hidden="true"><span></span></div>
          </article>
        </div>

        <div class="dashboard-chart-area dashboard-live-area">
          <article class="chart-panel live-chart-panel">
            <div class="chart-head">
              <span>Temperatura / pH</span>
              <strong>24 h</strong>
            </div>
            <canvas id="ecosystemChart"></canvas>
          </article>

          <div class="alert-stack live-status-stack">
            <article data-status-card="water">
              <span id="waterStatusDot" class="<?= esc($statusDotClasses[$cards['waterLevel']['status']] ?? 'status-info') ?>"></span>
              <div>
                <strong id="waterStatusTitle"><?= esc($waterTitle) ?></strong>
                <small id="waterStatusMeta"><?= esc($cards['waterLevel']['meta']) ?></small>
              </div>
            </article>
            <article data-status-card="feeding">
              <span id="feedingStatusDot" class="<?= esc($statusDotClasses[$cards['lastFeeding']['status']] ?? 'status-info') ?>"></span>
              <div>
                <strong id="feedingStatusTitle"><?= esc($feedingTitle) ?></strong>
                <small id="feedingStatusMeta"><?= esc($feedingMeta) ?></small>
              </div>
            </article>
            <article data-status-card="vacation">
              <span id="vacationStatusDot" class="<?= esc($statusDotClasses[$cards['vacationMode']['status']] ?? 'status-info') ?>"></span>
              <div>
                <strong id="vacationStatusTitle"><?= esc($vacationTitle) ?></strong>
                <small id="vacationStatusMeta"><?= esc($cards['vacationMode']['meta']) ?></small>
              </div>
            </article>
          </div>
        </div>
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

      <section class="dashboard-grid profile-grid" id="perfil">
        <article class="glass-card profile-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Mi cuenta</p>
              <h3>Datos de acceso</h3>
            </div>
          </div>

          <form class="profile-form" action="<?= base_url('dashboard/profile') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="profile-form-grid">
              <div class="form-group">
                <label class="form-label" for="profile_nombre">Nombre y apellido</label>
                <input class="form-control <?= isset($profileErrors['nombre']) ? 'is-invalid' : '' ?>" id="profile_nombre" name="nombre" type="text" value="<?= esc($profileName) ?>" autocomplete="name" required>
                <p class="field-help">Aparece en el panel, alertas y registros de mantenimiento.</p>
                <?php if (isset($profileErrors['nombre'])): ?>
                  <div class="invalid-feedback"><span>&#9888;</span> <?= esc($profileErrors['nombre']) ?></div>
                <?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="profile_email">Correo electronico</label>
                <input class="form-control <?= isset($profileErrors['email']) ? 'is-invalid' : '' ?>" id="profile_email" name="email" type="email" value="<?= esc($profileEmail) ?>" autocomplete="email" required>
                <p class="field-help">Se usa para iniciar sesion y recuperar la cuenta.</p>
                <?php if (isset($profileErrors['email'])): ?>
                  <div class="invalid-feedback"><span>&#9888;</span> <?= esc($profileErrors['email']) ?></div>
                <?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="profile_current_password">Contrasena actual</label>
                <input class="form-control <?= isset($profileErrors['current_password']) ? 'is-invalid' : '' ?>" id="profile_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Solo si cambias el email">
                <p class="field-help">Confirma tu identidad antes de modificar el correo de acceso.</p>
                <?php if (isset($profileErrors['current_password'])): ?>
                  <div class="invalid-feedback"><span>&#9888;</span> <?= esc($profileErrors['current_password']) ?></div>
                <?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="profile_role">Rol actual</label>
                <input class="form-control" id="profile_role" type="text" value="<?= esc($profile['roleLabel'] ?? 'Usuario comun') ?>" readonly>
                <p class="field-help">Define los permisos disponibles dentro de AquaControl.</p>
              </div>
            </div>

            <div class="profile-form-actions">
              <button class="btn btn-primary" type="submit">Guardar cambios</button>
            </div>
          </form>
        </article>

        <article class="glass-card profile-card profile-summary-card">
          <div class="profile-identity">
            <span class="profile-avatar"><?= esc($profileInitial) ?></span>
            <div>
              <p class="section-tag">Sesion activa</p>
              <h3><?= esc($profile['nombre'] ?? $profileName) ?></h3>
              <span><?= esc($profile['email'] ?? $profileEmail) ?></span>
            </div>
          </div>

          <div class="profile-summary">
            <div class="profile-summary-item">
              <span>Rol</span>
              <strong><?= esc($profile['roleLabel'] ?? 'Usuario comun') ?></strong>
            </div>
            <div class="profile-summary-item">
              <span>Cuenta creada</span>
              <strong><?= esc($profileCreated) ?></strong>
            </div>
            <div class="profile-summary-item">
              <span>Ultima actualizacion</span>
              <strong><?= esc($profileUpdated) ?></strong>
            </div>
          </div>
        </article>
      </section>
    </div>
  </section>
</main>

<script id="dashboard-data" type="application/json"><?= $dashboardJson ?></script>
<script>window.aquaDashboardHandledByModule = true;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?= view('layouts/footer') ?>
