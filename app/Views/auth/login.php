<?= $this->extend('layouts/main') ?>

<?= $this->section('estilos') ?>
  <link rel="stylesheet" href="<?= base_url('css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">AC</div>
      <h2>Bienvenido de nuevo</h2>
      <p>Inicia sesion para acceder a tu dashboard</p>
    </div>

    <?= view('components/flash_messages') ?>

    <form id="loginForm" action="<?= base_url('auth/login') ?>" method="POST" novalidate data-auth-form="login">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Correo electronico</label>
        <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
          placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
        <?= error_campo($errors ?? [], 'email') ?>
      </div>

      <div class="form-group">
        <div class="auth-label-row">
          <label class="form-label" for="password">Contrasena</label>
          <a href="<?= base_url('auth/recover') ?>" class="auth-link">Olvidaste tu contrasena?</a>
        </div>
        <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Tu contrasena', 'autocomplete' => 'current-password', 'medidor' => false]) ?>
      </div>

      <div class="form-group">
        <label class="form-check" for="remember">
          <input type="checkbox" id="remember" name="remember" value="1">
          <span>Recordarme en este dispositivo</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary">Iniciar sesion &rarr;</button>
    </form>

    <div class="auth-footer">
      No tienes cuenta? <a href="<?= base_url('auth/register') ?>" class="auth-link">Registrate gratis</a>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/auth.js') ?>"></script>
<?= $this->endSection() ?>
