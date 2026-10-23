<?php usar_css('css/home/index.css') ?>
<?php
/*
 * Portada. Cada bloque de la pagina esta en su propio archivo dentro de home/secciones/:
 * para mover una seccion de lugar cambia el orden de estas lineas, y para sacarla
 * borra su linea.
 */
?>
<?php // Usa el molde general (barra de arriba y pie). ?>
<?= $this->extend('layouts/main') ?>

<?php // En la cabecera: le avisa al navegador que vaya bajando la foto de la portada. ?>
<?= $this->section('cabecera') ?>
  <link rel="preload" as="image" href="<?= base_url('img/aquacontrol-product.webp') ?>" type="image/webp">
<?= $this->endSection() ?>

<?php // Contenido: una linea por cada seccion de la portada, en el orden en que se ven. ?>
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

<?php // Datos del producto para el formulario de compra (los lee funciones/purchase.js). ?>
<script id="purchase-data" type="application/json"><?= json_encode($compra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?= $this->endSection() ?>

<?php // JavaScript de la portada: animaciones y compra. ?>
<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/animaciones/portada.js') ?>"></script>
  <script src="<?= base_url('js/funciones/purchase.js') ?>"></script>
<?= $this->endSection() ?>
