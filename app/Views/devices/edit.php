<?php
$isAdmin = session()->get('user_role') === 'administrador';
?>
<?= view('layouts/header') ?>

<main class="management-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">AquaControl IoT</p>
      <h1>Editar</h1>
      <p class="dashboard-intro">Actualiza los datos del dispositivo seleccionado.</p>

      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Panel principal</a>
        <a href="<?= base_url('dashboard/history') ?>#historial" class="dashboard-link">Historial</a>
        <a href="<?= base_url('dashboard/settings') ?>#configuracion" class="dashboard-link">Configuracion</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link is-active">Dispositivos</a>
        <?php if ($isAdmin): ?>
          <a href="<?= base_url('usuarios') ?>" class="dashboard-link">Usuarios</a>
        <?php endif; ?>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

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
            <input class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>" id="nombre" name="nombre" type="text" value="<?= old('nombre', $device['nombre']) ?>" required>
            <?php if (isset($errors['nombre'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['nombre']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="tipo">Tipo de dispositivo</label>
            <select class="form-control <?= isset($errors['tipo']) ? 'is-invalid' : '' ?>" id="tipo" name="tipo" required>
              <?php foreach ($typeOptions as $value => $label): ?>
                <option value="<?= esc($value) ?>" <?= old('tipo', $device['tipo']) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['tipo'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['tipo']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="ubicacion">Ubicacion o corresponde a</label>
            <input class="form-control <?= isset($errors['ubicacion']) ? 'is-invalid' : '' ?>" id="ubicacion" name="ubicacion" type="text" value="<?= old('ubicacion', $device['ubicacion']) ?>" required>
            <?php if (isset($errors['ubicacion'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['ubicacion']) ?></div>
            <?php endif; ?>
          </div>

          <div class="management-form-actions">
            <button class="btn btn-primary" type="submit">Guardar cambios</button>
            <a class="btn btn-outline" href="<?= base_url('dispositivos') ?>">Cancelar</a>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?= view('layouts/footer') ?>
