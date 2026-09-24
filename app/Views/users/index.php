<?= view('layouts/header') ?>

<main class="management-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">Administracion</p>
      <h1>Usuarios</h1>
      <p class="dashboard-intro">Gestiona datos basicos y permisos de acceso del sistema.</p>

      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Panel principal</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link">Dispositivos</a>
        <a href="<?= base_url('usuarios') ?>" class="dashboard-link is-active">Usuarios</a>
        <a href="<?= base_url('pedidos') ?>" class="dashboard-link">Pedidos</a>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

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
              <?php else: ?>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td><?= esc($user['nombre']) ?></td>
                    <td><?= esc($user['email']) ?></td>
                    <td><span class="role-badge"><?= esc($roleNames[$user['rol'] ?? 'usuario'] ?? 'Usuario comun') ?></span></td>
                    <td><span class="status-pill <?= (int) ($user['activo'] ?? 0) === 1 ? 'is-active' : 'is-muted' ?>"><?= (int) ($user['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                    <td><a class="btn btn-outline" href="<?= base_url('usuarios/editar/' . $user['id']) ?>">Editar</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</main>

<?= view('layouts/footer') ?>
