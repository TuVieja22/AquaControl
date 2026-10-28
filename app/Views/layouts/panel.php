<?php usar_css('css/layouts/panel.css') ?>
<?php
/*
 * Molde de las paginas del panel (Panel y Dispositivos): menu lateral
 * a la izquierda y el contenido a la derecha. Cada pagina completa:
 *   - 'lateral': titulo y descripcion que van arriba del menu
 *   - 'panel':   el contenido principal
 *   - 'scripts': (opcional) sus propios <script>
 * El link activo del menu se marca solo, segun la direccion de la pagina.
 */
// Links del menu lateral: direccion, texto y si es la pagina actual.
$menu = [
    ['url' => 'dashboard', 'texto' => 'Panel principal', 'activo' => url_is('dashboard')],
    ['url' => 'dashboard/history#filtros-historial', 'texto' => 'Historial', 'activo' => url_is('dashboard/history')],
    ['url' => 'dashboard/settings#configuracion', 'texto' => 'Configuración', 'activo' => url_is('dashboard/settings')],
    ['url' => 'dashboard/settings#alimentador', 'texto' => 'Alimentador', 'activo' => false],
    ['url' => 'dashboard/profile#perfil', 'texto' => 'Mi cuenta', 'activo' => url_is('dashboard/profile')],
    ['url' => 'dispositivos', 'texto' => 'Dispositivos', 'activo' => url_is('dispositivos*')],
];
?>
<?php // Usa el molde general (barra de arriba y pie). ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<main class="dashboard-page">
  <section class="dashboard-shell container">
    <?php // Columna izquierda: titulo de la pagina y menu. ?>
    <aside class="dashboard-sidebar glass-card">
      <?= $this->renderSection('lateral') ?>

      <?php // Menu: un link por cada elemento de la lista. ?>
      <nav class="dashboard-menu">
        <?php foreach ($menu as $link): ?>
          <a href="<?= base_url($link['url']) ?>" class="dashboard-link<?= $link['activo'] ? ' is-active' : '' ?>"><?= esc($link['texto']) ?></a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <?php // Columna derecha: aviso de cuenta de prueba, mensajes y contenido de la pagina. ?>
    <div class="dashboard-main">
      <?= view('components/aviso_prueba') ?>
      <?= view('components/flash_messages') ?>

      <?= $this->renderSection('panel') ?>
    </div>
  </section>
</main>
<?= $this->endSection() ?>
