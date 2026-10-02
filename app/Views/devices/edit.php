<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">AquaControl IoT</p>
  <h1>Editar</h1>
  <p class="dashboard-intro">Actualiza los datos del dispositivo seleccionado.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <section class="management-card glass-card management-narrow">
    <div class="panel-head">
      <div>
        <p class="section-tag">Dispositivo</p>
        <h3>Editar datos</h3>
      </div>
      <a class="btn btn-outline" href="<?= base_url('dispositivos') ?>">Volver</a>
    </div>

    <form class="management-form" action="<?= base_url('dispositivos/editar/' . $device['id']) ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="nombre">Nombre del dispositivo</label>
        <input class="form-control<?= clase_error($errors, 'nombre') ?>" id="nombre" name="nombre" type="text" value="<?= esc($device['nombre']) ?>" required>
        <?= error_campo($errors, 'nombre') ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="tipo">Tipo de dispositivo</label>
        <select class="form-control<?= clase_error($errors, 'tipo') ?>" id="tipo" name="tipo" required>
          <?php foreach ($typeOptions as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $device['tipo'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= error_campo($errors, 'tipo') ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="ubicacion">Ubicacion o corresponde a</label>
        <input class="form-control<?= clase_error($errors, 'ubicacion') ?>" id="ubicacion" name="ubicacion" type="text" value="<?= esc($device['ubicacion']) ?>" required>
        <?= error_campo($errors, 'ubicacion') ?>
      </div>

      <div class="management-form-actions">
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
        <a class="btn btn-outline" href="<?= base_url('dispositivos') ?>">Cancelar</a>
      </div>
    </form>
  </section>
<?= $this->endSection() ?>
