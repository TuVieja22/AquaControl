<?php
$isAdmin = session()->get('user_role') === 'administrador';
$form = $form ?? [];
?>
<?= view('layouts/header') ?>

<main class="management-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">AquaControl IoT</p>
      <h1>Dispositivos</h1>
      <p class="dashboard-intro">Registra y administra los equipos asociados a tu ecosistema acuatico.</p>

      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Panel principal</a>
        <a href="<?= base_url('dashboard/history') ?>#filtros-historial" class="dashboard-link">Historial</a>
        <a href="<?= base_url('dashboard/settings') ?>#configuracion" class="dashboard-link">Configuracion</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link is-active">Dispositivos</a>
        <?php if ($isAdmin): ?>
          <a href="<?= base_url('usuarios') ?>" class="dashboard-link">Usuarios</a>
          <a href="<?= base_url('pedidos') ?>" class="dashboard-link">Pedidos</a>
        <?php endif; ?>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

      <section class="management-hero glass-card">
        <div>
          <p class="section-tag">Alta de dispositivo</p>
          <h2 class="dashboard-title">Equipos registrados</h2>
          <p class="dashboard-subtitle">Guarda nombre, tipo y ubicacion para tener trazabilidad de cada componente.</p>
        </div>
      </section>

      <?php if (! empty($newApiKey)): ?>
        <section class="management-card glass-card api-key-panel" id="api-key-nueva">
          <div class="panel-head">
            <div>
              <p class="section-tag">API key de dispositivo</p>
              <h3><?= esc($newApiKey['nombre']) ?></h3>
            </div>
          </div>
          <p class="dashboard-subtitle">Copiala y cargala en el firmware del ESP32. Por seguridad solo se guarda un hash: si la perdes, genera una nueva.</p>
          <div class="api-key-value">
            <code id="newApiKeyValue"><?= esc($newApiKey['key']) ?></code>
            <button class="btn btn-outline" type="button" data-copy-target="newApiKeyValue">Copiar</button>
          </div>
          <p class="api-key-help">Envia las lecturas con el header <code>X-Device-Key</code> (o <code>Authorization: Bearer</code>):</p>
          <pre class="api-key-example"><code>POST <?= esc($apiEndpoint) ?>

X-Device-Key: <?= esc($newApiKey['key']) ?>

Content-Type: application/json

{"temperatura": 25.4, "ph": 7.1, "nivel_agua": 1, "calefactor": 0}</code></pre>
        </section>
      <?php endif; ?>

      <section class="management-grid">
        <article class="management-card glass-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Nuevo</p>
              <h3>Registrar dispositivo</h3>
            </div>
          </div>

          <form class="management-form" action="<?= base_url('dispositivos/nuevo') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
              <label class="form-label" for="nombre">Nombre del dispositivo</label>
              <input class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>" id="nombre" name="nombre" type="text" value="<?= esc($form['nombre'] ?? '') ?>" placeholder="Ej: Sensor pH principal" required>
              <?php if (isset($errors['nombre'])): ?>
                <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['nombre']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="tipo">Tipo de dispositivo</label>
              <select class="form-control <?= isset($errors['tipo']) ? 'is-invalid' : '' ?>" id="tipo" name="tipo" required>
                <option value="">Seleccionar tipo</option>
                <?php foreach ($typeOptions as $value => $label): ?>
                  <option value="<?= esc($value) ?>" <?= ($form['tipo'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['tipo'])): ?>
                <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['tipo']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="ubicacion">Ubicacion o corresponde a</label>
              <input class="form-control <?= isset($errors['ubicacion']) ? 'is-invalid' : '' ?>" id="ubicacion" name="ubicacion" type="text" value="<?= esc($form['ubicacion'] ?? '') ?>" placeholder="Ej: Pecera living" required>
              <?php if (isset($errors['ubicacion'])): ?>
                <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['ubicacion']) ?></div>
              <?php endif; ?>
            </div>

            <div class="management-form-actions">
              <button class="btn btn-primary" type="submit">Guardar dispositivo</button>
            </div>
          </form>
        </article>

        <article class="management-card glass-card">
          <div class="panel-head">
            <div>
              <p class="section-tag">Listado</p>
              <h3>Dispositivos registrados</h3>
            </div>
            <span class="management-count"><?= count($devices) ?></span>
          </div>

          <div class="table-wrap">
            <table class="management-table">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Ubicacion</th>
                  <th>API key</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($devices === []): ?>
                  <tr><td colspan="5" class="table-empty">Todavia no hay dispositivos registrados.</td></tr>
                <?php else: ?>
                  <?php foreach ($devices as $device): ?>
                    <tr>
                      <td><?= esc($device['nombre']) ?></td>
                      <td><span class="type-badge"><?= esc($typeOptions[$device['tipo']] ?? ucfirst((string) $device['tipo'])) ?></span></td>
                      <td><?= esc($device['ubicacion']) ?></td>
                      <td class="api-key-cell">
                        <?php if (! empty($device['api_key_prefijo'])): ?>
                          <code><?= esc($device['api_key_prefijo']) ?>&hellip;</code>
                          <small>
                            <?= ! empty($device['ultima_conexion'])
                                ? 'Ultima conexion ' . esc(date('d/m/Y H:i', strtotime($device['ultima_conexion'])))
                                : 'Sin conexiones aun' ?>
                          </small>
                        <?php else: ?>
                          <small>Sin API key</small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="management-actions">
                          <a class="btn btn-outline" href="<?= base_url('dispositivos/editar/' . $device['id']) ?>">Editar</a>
                          <form action="<?= base_url('dispositivos/api-key/' . $device['id']) ?>" method="POST"
                            <?= ! empty($device['api_key_hash']) ? 'onsubmit="return confirm(\'Regenerar la API key? La actual dejara de funcionar.\');"' : '' ?>>
                            <?= csrf_field() ?>
                            <button class="btn btn-outline" type="submit"><?= ! empty($device['api_key_hash']) ? 'Regenerar key' : 'Generar key' ?></button>
                          </form>
                          <?php if (! empty($device['api_key_hash'])): ?>
                            <form action="<?= base_url('dispositivos/api-key/' . $device['id'] . '/revocar') ?>" method="POST" onsubmit="return confirm('Revocar la API key? El dispositivo no podra enviar lecturas.');">
                              <?= csrf_field() ?>
                              <button class="btn btn-ghost danger-action" type="submit">Revocar</button>
                            </form>
                          <?php endif; ?>
                          <form action="<?= base_url('dispositivos/eliminar/' . $device['id']) ?>" method="POST" onsubmit="return confirm('Eliminar este dispositivo?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost danger-action" type="submit">Eliminar</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
    </div>
  </section>
</main>

<?= view('layouts/footer') ?>
