<?php usar_css('css/errors/404.css') ?>
<?php // Usa el molde general (barra de arriba y pie). ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<?php // Cartel centrado de "pagina no encontrada". ?>
<div class="not-found">
  <div class="not-found-box">
    <div class="not-found-fish">🐟</div>
    <h1>404</h1>
    <h2>El pez se escapó</h2>
    <p>
      Esta página no existe o fue movida a otra pecera.<br>
      Puede que el enlace esté incorrecto.
    </p>
    <?php // Boton para volver a la portada. ?>
    <a href="<?= base_url('/') ?>" class="btn btn-primary">Volver al inicio →</a>
  </div>
</div>
<?= $this->endSection() ?>
