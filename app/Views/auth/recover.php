<?php usar_css('css/auth/recover.css') ?>
<?php // Usa el molde de las pantallas de cuenta (tarjeta centrada). ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('tarjeta') ?>
  <?php // Logo, titulo y aclaracion. ?>
  <div class="auth-logo">
    <div class="logo-mark">AC</div>
    <h2>Recuperar contraseña</h2>
    <p>Te enviaremos un enlace para restablecerla</p>
  </div>

  <?php // Mensajes de exito o error que deja el servidor. ?>
  <?= view('components/flash_messages') ?>

  <?php // Formulario: manda el correo a auth/recover. ?>
  <form id="recoverForm" action="<?= base_url('auth/recover') ?>" method="POST" novalidate data-auth-form="recover">
    <?php // Sello de seguridad (obligatorio en todo formulario). ?>
    <?= csrf_field() ?>

    <?php // Campo del correo, con su cartel de error. ?>
    <div class="form-group">
      <label class="form-label" for="email">Correo electrónico</label>
      <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
        placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
      <?= error_campo($errors ?? [], 'email') ?>
    </div>

    <?php // Boton de enviar. ?>
    <button type="submit" class="btn btn-primary">Enviar enlace de recuperación &rarr;</button>
  </form>

  <?php // Link para volver al login. ?>
  <div class="auth-footer">
    <a href="<?= base_url('auth/login') ?>" class="auth-link">&larr; Volver a iniciar sesión</a>
  </div>
<?= $this->endSection() ?>
