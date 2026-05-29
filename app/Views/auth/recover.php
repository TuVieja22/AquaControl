<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">AC</div>
      <h2>Recuperar contrasena</h2>
      <p>Te enviaremos un enlace para restablecerla</p>
    </div>

    <?= view('components/flash_messages', ['types' => ['success', 'error']]) ?>

    <form id="recoverForm" action="<?= base_url('auth/recover') ?>" method="POST" novalidate data-auth-form="recover">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Correo electronico</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
          placeholder="correo@ejemplo.com"
          value="<?= old('email') ?>"
          autocomplete="email"
          required
        >
        <div class="invalid-feedback" <?= isset($errors['email']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['email']) ? '<span>&#9888;</span> ' . esc($errors['email']) : '' ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Enviar enlace de recuperacion &rarr;</button>
    </form>

    <div class="auth-footer">
      <a href="<?= base_url('auth/login') ?>" class="auth-link">&larr; Volver a iniciar sesion</a>
    </div>
  </div>
</div>

<?= view('layouts/footer') ?>
