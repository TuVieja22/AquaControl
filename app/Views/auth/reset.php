<?php usar_css('css/auth/reset.css') ?>
<?php // Usa el molde de las pantallas de cuenta (tarjeta centrada). ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('tarjeta') ?>
  <?php // Logo, titulo y aclaracion. ?>
  <div class="auth-logo">
    <div class="logo-mark">AC</div>
    <h2>Nueva contraseña</h2>
    <p>Elige una contraseña segura para tu cuenta</p>
  </div>

  <?php // Mensajes de exito o error que deja el servidor. ?>
  <?= view('components/flash_messages') ?>

  <?php // Formulario: manda la contrasena nueva a auth/reset. ?>
  <form id="resetForm" action="<?= base_url('auth/reset') ?>" method="POST" novalidate data-auth-form="reset">
    <?php // Sello de seguridad (obligatorio en todo formulario). ?>
    <?= csrf_field() ?>
    <?php // Codigo del enlace (viaja escondido en el formulario). ?>
    <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

    <?php // Contrasena nueva, con la barra que mide su seguridad. ?>
    <div class="form-group">
      <label class="form-label" for="password">Nueva contraseña</label>
      <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Mínimo 8, Aa, 123 y símbolo', 'autocomplete' => 'new-password', 'medidor' => true]) ?>
    </div>

    <?php // Repetir la contrasena. ?>
    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar nueva contraseña</label>
      <?= view('components/campo_password', ['nombre' => 'password_confirm', 'placeholder' => 'Repite la contraseña', 'autocomplete' => 'new-password', 'medidor' => false]) ?>
    </div>

    <?php // Boton de guardar. ?>
    <button type="submit" class="btn btn-primary">Guardar nueva contraseña &rarr;</button>
  </form>
<?= $this->endSection() ?>
