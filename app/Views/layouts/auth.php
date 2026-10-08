<?php usar_css('css/layouts/auth.css') ?>
<?php
/*
 * Molde de las pantallas de cuenta (login, registro, recuperar y nueva contrasena):
 * la tarjeta centrada y funciones/auth.js. Cada pantalla completa:
 *   - 'tarjeta': lo que va adentro de la tarjeta (logo, mensajes, formulario y pie)
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="auth-page">
  <div class="auth-card">
    <?= $this->renderSection('tarjeta') ?>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/funciones/auth.js') ?>"></script>
<?= $this->endSection() ?>
