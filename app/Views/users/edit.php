<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">Administracion</p>
  <h1>Editar usuario</h1>
  <p class="dashboard-intro">Actualiza nombre, contrasena y rol sin exponer la contrasena actual.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <section class="management-card glass-card management-narrow">
    <div class="panel-head">
      <div>
        <p class="section-tag">Cuenta</p>
        <h3><?= esc($user['nombre']) ?></h3>
      </div>
      <a class="btn btn-outline" href="<?= base_url('usuarios') ?>">Volver</a>
    </div>

    <form class="management-form" action="<?= base_url('usuarios/editar/' . $user['id']) ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="nombre">Nombre y apellido</label>
        <input class="form-control<?= clase_error($errors, 'nombre') ?>" id="nombre" name="nombre" type="text" value="<?= esc($form['nombre']) ?>" required>
        <?= error_campo($errors, 'nombre') ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Correo electronico</label>
        <input class="form-control" id="email" type="email" value="<?= esc($user['email']) ?>" readonly>
        <p class="field-help">El email queda bloqueado para evitar cambios accidentales de identidad.</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="rol">Rol de usuario</label>
        <select class="form-control<?= clase_error($errors, 'rol') ?>" id="rol" name="rol" required>
          <?php foreach ($roleNames as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $form['rol'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= error_campo($errors, 'rol') ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Nueva contrasena</label>
        <input class="form-control<?= clase_error($errors, 'password') ?>" id="password" name="password" type="password" autocomplete="new-password" placeholder="Dejar vacio para no cambiar">
        <p class="field-help">Minimo 8 caracteres con mayusculas, minusculas, numeros y caracteres especiales.</p>
        <?= error_campo($errors, 'password') ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmar contrasena</label>
        <input class="form-control<?= clase_error($errors, 'password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" placeholder="Repite la nueva contrasena">
        <?= error_campo($errors, 'password_confirm') ?>
      </div>

      <div class="management-form-actions">
        <button class="btn btn-primary" type="submit">Guardar usuario</button>
        <a class="btn btn-outline" href="<?= base_url('usuarios') ?>">Cancelar</a>
      </div>
    </form>
  </section>
<?= $this->endSection() ?>
