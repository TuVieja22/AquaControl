<?= $this->extend('layouts/main') ?>

<?= $this->section('estilos') ?>
  <link rel="stylesheet" href="<?= base_url('css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">AC</div>
      <h2>Recuperar contrasena</h2>
      <p>Te enviaremos un enlace para restablecerla</p>
    </div>

    <?= view('components/flash_messages') ?>

    <form id="recoverForm" action="<?= base_url('auth/recover') ?>" method="POST" novalidate data-auth-form="recover">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Correo electronico</label>
        <input type="email" id="email" name="email" class="form-control<?= clase_error($errors ?? [], 'email') ?>"
          placeholder="correo@ejemplo.com" value="<?= set_value('email') ?>" autocomplete="email" required>
        <?= error_campo($errors ?? [], 'email') ?>
      </div>

      <button type="submit" class="btn btn-primary">Enviar enlace de recuperacion &rarr;</button>
    </form>

    <div class="auth-footer">
      <a href="<?= base_url('auth/login') ?>" class="auth-link">&larr; Volver a iniciar sesion</a>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/auth.js') ?>"></script>
<?= $this->endSection() ?>
