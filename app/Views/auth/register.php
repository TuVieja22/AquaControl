<?php usar_css('css/auth/register.css') ?>
<?php // Usa el molde de las pantallas de cuenta (tarjeta centrada). ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('tarjeta') ?>
  <?php // Logo, titulo y aclaracion. ?>
  <div class="auth-logo">
    <div class="logo-mark">AC</div>
    <h2>Crear cuenta</h2>
    <p>Unete a AquaControl y automatiza tu pecera</p>
  </div>

  <?php // Mensajes de exito o error que deja el servidor. ?>
  <?= view('components/flash_messages') ?>

  <?php // Error general (por ejemplo, si la base no pudo guardar la cuenta). ?>
  <?php if (isset($errors['general'])): ?>
    <div class="flash flash-error">&#9888; <?= esc($errors['general']) ?></div>
  <?php endif; ?>

  <?php // Formulario: manda los datos a auth/register. ?>
  <form id="registerForm" action="<?= base_url('auth/register') ?>" method="POST" novalidate data-auth-form="register">
    <?php // Sello de seguridad (obligatorio en todo formulario). ?>
    <?= csrf_field() ?>

    <?php // Nombre y apellido. ?>
    <div class="form-group">
      <label class="form-label" for="nombre">Nombre y apellido</label>
      <input type="text" id="nombre" name="nombre" class="form-control<?= clase_error($errors ?? [], 'nombre') ?>"
        placeholder="Ej: Juan Perez" value="<?= set_value('nombre') ?>" autocomplete="name" required>
      <?= error_campo($errors ?? [], 'nombre') ?>
    </div>

    <?php // Correo electronico. ?>
    <div class="form-group">
      <label class="form-label" for="email">Correo electronico</label>
      <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
        placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
      <?= error_campo($errors ?? [], 'email') ?>
    </div>

    <?php // Contrasena, con la barra que mide su seguridad. ?>
    <div class="form-group">
      <label class="form-label" for="password">Contrasena</label>
      <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Minimo 8, Aa, 123 y simbolo', 'autocomplete' => 'new-password', 'medidor' => true]) ?>
    </div>

    <?php // Repetir la contrasena. ?>
    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar contrasena</label>
      <?= view('components/campo_password', ['nombre' => 'password_confirm', 'placeholder' => 'Repite tu contrasena', 'autocomplete' => 'new-password', 'medidor' => false]) ?>
    </div>

    <?php // Casilla para aceptar los terminos. ?>
    <div class="form-group">
      <label class="form-check" for="terms">
        <input type="checkbox" id="terms" name="terms" value="1" <?= set_checkbox('terms', '1') ?>>
        <span>Acepto los <a href="#" class="auth-link">terminos y condiciones</a> y la politica de privacidad</span>
      </label>
      <?= error_campo($errors ?? [], 'terms', 'terms-error') ?>
    </div>

    <?php // Boton de enviar. ?>
    <button type="submit" class="btn btn-primary">Crear mi cuenta &rarr;</button>
  </form>

  <?php // Link para ir al login. ?>
  <div class="auth-footer">
    Ya tienes cuenta? <a href="<?= base_url('auth/login') ?>" class="auth-link">Iniciar sesion</a>
  </div>
<?= $this->endSection() ?>
