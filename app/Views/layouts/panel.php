<?php
/*
 * Molde de las paginas del panel (Panel, Dispositivos, Usuarios, Pedidos): menu lateral
 * a la izquierda y el contenido a la derecha. Cada pagina completa:
 *   - 'lateral': titulo y descripcion que van arriba del menu
 *   - 'panel':   el contenido principal
 *   - 'scripts': (opcional) sus propios <script>
 * El link activo del menu se marca solo, segun la direccion de la pagina.
 */
$esAdmin = session()->get('user_role') === 'administrador';
$menu = [
    ['url' => 'dashboard', 'texto' => 'Panel principal', 'activo' => url_is('dashboard')],
    ['url' => 'dashboard/history#filtros-historial', 'texto' => 'Historial', 'activo' => url_is('dashboard/history')],
    ['url' => 'dashboard/settings#configuracion', 'texto' => 'Configuracion', 'activo' => url_is('dashboard/settings')],
    ['url' => 'dashboard/settings#alimentador', 'texto' => 'Alimentador', 'activo' => false],
    ['url' => 'dashboard/profile#perfil', 'texto' => 'Mi cuenta', 'activo' => url_is('dashboard/profile')],
    ['url' => 'dispositivos', 'texto' => 'Dispositivos', 'activo' => url_is('dispositivos*')],
];
if ($esAdmin) {
    $menu[] = ['url' => 'usuarios', 'texto' => 'Usuarios', 'activo' => url_is('usuarios*')];
    $menu[] = ['url' => 'pedidos', 'texto' => 'Pedidos', 'activo' => url_is('pedidos*')];
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('estilos') ?>
  <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<main class="dashboard-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <?= $this->renderSection('lateral') ?>

      <nav class="dashboard-menu">
        <?php foreach ($menu as $link): ?>
          <a href="<?= base_url($link['url']) ?>" class="dashboard-link<?= $link['activo'] ? ' is-active' : '' ?>"><?= esc($link['texto']) ?></a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages') ?>

      <?= $this->renderSection('panel') ?>
    </div>
  </section>
</main>
<?= $this->endSection() ?>
