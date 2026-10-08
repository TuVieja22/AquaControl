<?php usar_css('css/errors/404.css') ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="not-found">
  <div class="not-found-box">
    <div class="not-found-fish">🐟</div>
    <h1>404</h1>
    <h2>El pez se escapó</h2>
    <p>
      Esta página no existe o fue movida a otra pecera.<br>
      Puede que el enlace esté incorrecto.
    </p>
    <a href="<?= base_url('/') ?>" class="btn btn-primary">Volver al inicio →</a>
  </div>
</div>
<?= $this->endSection() ?>
