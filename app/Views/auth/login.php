<?php usar_css('css/auth/login.css') ?>
<?php // Usa el molde de las pantallas de cuenta (tarjeta centrada). ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('tarjeta') ?>
  <?php // Logo, titulo y aclaracion. ?>
  <div class="auth-logo">
    <div class="logo-mark">AC</div>
    <h2>Bienvenido de nuevo</h2>
    <p>Inicia sesion para acceder a tu dashboard</p>
  </div>

  <?php // Mensajes de exito o error que deja el servidor. ?>
  <?= view('components/flash_messages') ?>

  <?php // Formulario: manda el correo y la contrasena a auth/login. ?>
  <form id="loginForm" action="<?= base_url('auth/login') ?>" method="POST" novalidate data-auth-form="login">
    <?php // Sello de seguridad (obligatorio en todo formulario). ?>
    <?= csrf_field() ?>

    <?php // Campo del correo, con su cartel de error. ?>
    <div class="form-group">
      <label class="form-label" for="email">Correo electronico</label>
      <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
        placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
      <?= error_campo($errors ?? [], 'email') ?>
    </div>

    <?php // Campo de la contrasena, con el link para recuperarla. ?>
    <div class="form-group">
      <div class="auth-label-row">
        <label class="form-label" for="password">Contrasena</label>
        <a href="<?= base_url('auth/recover') ?>" class="auth-link">Olvidaste tu contrasena?</a>
      </div>
      <?php // El campo en si es un componente (trae el boton del ojito). ?>
      <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Tu contrasena', 'autocomplete' => 'current-password', 'medidor' => false]) ?>
    </div>

    <?php // Casilla "Recordarme". ?>
    <div class="form-group">
      <label class="form-check" for="remember">
        <input type="checkbox" id="remember" name="remember" value="1">
        <span>Recordarme en este dispositivo</span>
      </label>
    </div>

    <?php // Boton de enviar. ?>
    <button type="submit" class="btn btn-primary">Iniciar sesion &rarr;</button>
  </form>

  <?php // Link para crear una cuenta. ?>
  <div class="auth-footer">
    No tienes cuenta? <a href="<?= base_url('auth/register') ?>" class="auth-link">Registrate gratis</a>
  </div>
<?= $this->endSection() ?>
