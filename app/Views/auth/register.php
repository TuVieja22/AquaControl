<?php usar_css('css/auth/register.css') ?>
<?php // Usa el molde de las pantallas de cuenta (tarjeta centrada). ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('tarjeta') ?>
  <?php // Logo, titulo y aclaracion. ?>
  <div class="auth-logo">
    <div class="logo-mark">AC</div>
    <h2>Crear cuenta</h2>
    <p>Únete a AquaControl y automatiza tu pecera</p>
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

    <?php // Nombre y apellido, uno al lado del otro (cada uno es un campo de la tabla usuarios). ?>
    <div class="name-row">
      <div class="form-group">
        <label class="form-label" for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="form-control<?= clase_error($errors ?? [], 'nombre') ?>"
          placeholder="Ej: Juan" value="<?= set_value('nombre') ?>" autocomplete="given-name" required>
        <?= error_campo($errors ?? [], 'nombre') ?>
      </div>
      <div class="form-group">
        <label class="form-label" for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" class="form-control<?= clase_error($errors ?? [], 'apellido') ?>"
          placeholder="Ej: Pérez" value="<?= set_value('apellido') ?>" autocomplete="family-name" required>
        <?= error_campo($errors ?? [], 'apellido') ?>
      </div>
    </div>

    <?php // Correo electronico. ?>
    <div class="form-group">
      <label class="form-label" for="email">Correo electrónico</label>
      <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
        placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
      <?= error_campo($errors ?? [], 'email') ?>
    </div>

    <?php // Contrasena, con la barra que mide su seguridad. ?>
    <div class="form-group">
      <label class="form-label" for="password">Contraseña</label>
      <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Mínimo 8, Aa, 123 y símbolo', 'autocomplete' => 'new-password', 'medidor' => true]) ?>
    </div>

    <?php // Repetir la contrasena. ?>
    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar contraseña</label>
      <?= view('components/campo_password', ['nombre' => 'password_confirm', 'placeholder' => 'Repite tu contraseña', 'autocomplete' => 'new-password', 'medidor' => false]) ?>
    </div>

    <?php // Casilla para aceptar los terminos. ?>
    <div class="form-group">
      <label class="form-check" for="terms">
        <input type="checkbox" id="terms" name="terms" value="1" <?= set_checkbox('terms', '1') ?>>
        <span>Acepto los <a href="#" class="auth-link">términos y condiciones</a> y la política de privacidad</span>
      </label>
      <?= error_campo($errors ?? [], 'terms', 'terms-error') ?>
    </div>

    <?php // Boton de enviar. ?>
    <button type="submit" class="btn btn-primary">Crear mi cuenta &rarr;</button>
  </form>

  <?php // Link para ir al login. ?>
  <div class="auth-footer">
    ¿Ya tienes cuenta? <a href="<?= base_url('auth/login') ?>" class="auth-link">Iniciar sesión</a>
  </div>
<?= $this->endSection() ?>
