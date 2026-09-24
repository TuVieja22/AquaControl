<?= view('layouts/header') ?>

<main class="management-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">Administracion</p>
      <h1>Editar usuario</h1>
      <p class="dashboard-intro">Actualiza nombre, contrasena y rol sin exponer la contrasena actual.</p>

      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Panel principal</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link">Dispositivos</a>
        <a href="<?= base_url('usuarios') ?>" class="dashboard-link is-active">Usuarios</a>
        <a href="<?= base_url('pedidos') ?>" class="dashboard-link">Pedidos</a>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

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
            <input class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>" id="nombre" name="nombre" type="text" value="<?= esc($form['nombre'] ?? $user['nombre']) ?>" required>
            <?php if (isset($errors['nombre'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['nombre']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Correo electronico</label>
            <input class="form-control" id="email" type="email" value="<?= esc($user['email']) ?>" readonly>
            <p class="field-help">El email queda bloqueado para evitar cambios accidentales de identidad.</p>
          </div>

          <div class="form-group">
            <label class="form-label" for="rol">Rol de usuario</label>
            <select class="form-control <?= isset($errors['rol']) ? 'is-invalid' : '' ?>" id="rol" name="rol" required>
              <?php foreach ($roleNames as $value => $label): ?>
                <option value="<?= esc($value) ?>" <?= ($form['rol'] ?? $user['rol']) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['rol'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['rol']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">Nueva contrasena</label>
            <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" placeholder="Dejar vacio para no cambiar">
            <p class="field-help">Minimo 8 caracteres con mayusculas, minusculas, numeros y caracteres especiales.</p>
            <?php if (isset($errors['password'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['password']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="password_confirm">Confirmar contrasena</label>
            <input class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" placeholder="Repite la nueva contrasena">
            <?php if (isset($errors['password_confirm'])): ?>
              <div class="invalid-feedback"><span>&#9888;</span> <?= esc($errors['password_confirm']) ?></div>
            <?php endif; ?>
          </div>

          <div class="management-form-actions">
            <button class="btn btn-primary" type="submit">Guardar usuario</button>
            <a class="btn btn-outline" href="<?= base_url('usuarios') ?>">Cancelar</a>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?= view('layouts/footer') ?>
