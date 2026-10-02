<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">AquaControl IoT</p>
  <h1>Dispositivos</h1>
  <p class="dashboard-intro">Registra y administra los equipos asociados a tu ecosistema acuatico.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
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
          <input class="form-control<?= clase_error($errors, 'nombre') ?>" id="nombre" name="nombre" type="text" value="<?= esc($form['nombre']) ?>" placeholder="Ej: Sensor pH principal" required>
          <?= error_campo($errors, 'nombre') ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="tipo">Tipo de dispositivo</label>
          <select class="form-control<?= clase_error($errors, 'tipo') ?>" id="tipo" name="tipo" required>
            <option value="">Seleccionar tipo</option>
            <?php foreach ($typeOptions as $value => $label): ?>
              <option value="<?= esc($value) ?>" <?= $form['tipo'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= error_campo($errors, 'tipo') ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="ubicacion">Ubicacion o corresponde a</label>
          <input class="form-control<?= clase_error($errors, 'ubicacion') ?>" id="ubicacion" name="ubicacion" type="text" value="<?= esc($form['ubicacion']) ?>" placeholder="Ej: Pecera living" required>
          <?= error_campo($errors, 'ubicacion') ?>
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
            <?php endif; ?>
            <?php foreach ($devices as $device): ?>
              <?php $tieneKey = ! empty($device['api_key_hash']); ?>
              <tr>
                <td><?= esc($device['nombre']) ?></td>
                <td><span class="type-badge"><?= esc($typeOptions[$device['tipo']] ?? ucfirst((string) $device['tipo'])) ?></span></td>
                <td><?= esc($device['ubicacion']) ?></td>
                <td class="api-key-cell">
                  <?php if ($tieneKey): ?>
                    <code><?= esc($device['api_key_prefijo']) ?>&hellip;</code>
                    <small><?= ! empty($device['ultima_conexion']) ? 'Ultima conexion ' . date('d/m/Y H:i', strtotime($device['ultima_conexion'])) : 'Sin conexiones aun' ?></small>
                  <?php else: ?>
                    <small>Sin API key</small>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="management-actions">
                    <a class="btn btn-outline" href="<?= base_url('dispositivos/editar/' . $device['id']) ?>">Editar</a>
                    <form action="<?= base_url('dispositivos/api-key/' . $device['id']) ?>" method="POST"
                      <?= $tieneKey ? 'data-confirm="Regenerar la API key? La actual dejara de funcionar."' : '' ?>>
                      <?= csrf_field() ?>
                      <button class="btn btn-outline" type="submit"><?= $tieneKey ? 'Regenerar key' : 'Generar key' ?></button>
                    </form>
                    <?php if ($tieneKey): ?>
                      <form action="<?= base_url('dispositivos/api-key/' . $device['id'] . '/revocar') ?>" method="POST" data-confirm="Revocar la API key? El dispositivo no podra enviar lecturas.">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost danger-action" type="submit">Revocar</button>
                      </form>
                    <?php endif; ?>
                    <form action="<?= base_url('dispositivos/eliminar/' . $device['id']) ?>" method="POST" data-confirm="Eliminar este dispositivo?">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost danger-action" type="submit">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>
  </section>
<?= $this->endSection() ?>
