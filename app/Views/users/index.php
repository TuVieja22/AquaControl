<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">Administracion</p>
  <h1>Usuarios</h1>
  <p class="dashboard-intro">Gestiona datos basicos y permisos de acceso del sistema.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <section class="management-hero glass-card">
    <div>
      <p class="section-tag">Roles y permisos</p>
      <h2 class="dashboard-title">Usuarios del sistema</h2>
      <p class="dashboard-subtitle">Solo los administradores pueden modificar roles y credenciales.</p>
    </div>
  </section>

  <section class="management-card glass-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Listado</p>
        <h3>Cuentas registradas</h3>
      </div>
      <span class="management-count"><?= count($users) ?></span>
    </div>

    <div class="table-wrap">
      <table class="management-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users === []): ?>
            <tr><td colspan="5" class="table-empty">No hay usuarios registrados.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $user): ?>
            <?php $activo = (int) $user['activo'] === 1; ?>
            <tr>
              <td><?= esc($user['nombre']) ?></td>
              <td><?= esc($user['email']) ?></td>
              <td><span class="role-badge"><?= esc($roleNames[$user['rol']] ?? $roleNames['usuario']) ?></span></td>
              <td><span class="status-pill <?= $activo ? 'is-active' : 'is-muted' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
              <td><a class="btn btn-outline" href="<?= base_url('usuarios/editar/' . $user['id']) ?>">Editar</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?= $this->endSection() ?>
