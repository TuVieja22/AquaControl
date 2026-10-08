<?php usar_css('css/dashboard/index.css') ?>
<?php
/*
 * Panel de la pecera. Lo usan las cuatro direcciones del panel (principal, historial,
 * configuracion y mi cuenta). Cada bloque esta en dashboard/partes/.
 * funciones/dashboard.js lee los datos de <script id="dashboard-data"> y los refresca cada
 * 5 s; animaciones/grafico.js dibuja el grafico con esos datos.
 */
?>
<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">AquaControl IoT</p>
  <h1>Mi pecera</h1>
  <p class="dashboard-intro">Monitoreo y control continuo de la pecera de <?= esc($dashboardData['userName']) ?>.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <?php if ($activeSection === 'history'): ?>
    <?= $this->include('dashboard/partes/filtros') ?>
  <?php endif; ?>
  <?= $this->include('dashboard/partes/en_vivo') ?>
  <?= $this->include('dashboard/partes/alertas_y_alimentaciones') ?>
  <?= $this->include('dashboard/partes/control') ?>
  <?= $this->include('dashboard/partes/alimentador') ?>
  <?= $this->include('dashboard/partes/perfil') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script id="dashboard-data" type="application/json"><?= json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <script src="<?= base_url('js/animaciones/grafico.js') ?>"></script>
  <script src="<?= base_url('js/funciones/dashboard.js') ?>"></script>
<?= $this->endSection() ?>
