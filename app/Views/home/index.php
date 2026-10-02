<?php
/*
 * Portada. Cada bloque de la pagina esta en su propio archivo dentro de home/secciones/:
 * para mover una seccion de lugar cambia el orden de estas lineas, y para sacarla
 * borra su linea.
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('estilos') ?>
  <link rel="preload" as="image" href="<?= base_url('img/aquacontrol-product.webp') ?>" type="image/webp">
  <link rel="stylesheet" href="<?= base_url('css/home.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<main class="landing-page">
  <?= $this->include('home/secciones/hero') ?>
  <?= $this->include('home/secciones/producto') ?>
  <?= $this->include('home/secciones/caracteristicas') ?>
  <?= $this->include('home/secciones/como_funciona') ?>
  <?= $this->include('home/secciones/beneficios') ?>
  <?= $this->include('home/secciones/demo') ?>
  <?= $this->include('home/secciones/comparacion') ?>
  <?= $this->include('home/secciones/testimonios') ?>
  <?= $this->include('home/secciones/planes') ?>
  <?= $this->include('home/secciones/compra') ?>
</main>

<script id="purchase-data" type="application/json"><?= json_encode($compra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/purchase.js') ?>"></script>
<?= $this->endSection() ?>
