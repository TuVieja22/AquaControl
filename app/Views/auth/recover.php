<?php $title = 'Recuperar contraseña'; ?>
<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-mark">🔑</div>
      <h2>Recuperar contraseña</h2>
      <p>Te enviaremos un enlace para restablecerla</p>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
      <div class="flash flash-success">✓ <?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
      <div class="flash flash-error">⚠ <?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>

    <form id="recoverForm" action="<?= base_url('auth/recover') ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Correo electrónico</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control <?= (isset($errors['email'])) ? 'is-invalid' : '' ?>"
          placeholder="correo@ejemplo.com"
          value="<?= old('email') ?>"
          autocomplete="email"
          required
        >
        <div class="invalid-feedback" <?= isset($errors['email']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['email']) ? '<span>⚠</span> ' . esc($errors['email']) : '' ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Enviar enlace de recuperación →</button>
    </form>

    <div class="auth-footer">
      <a href="<?= base_url('auth/login') ?>" class="auth-link">← Volver a iniciar sesión</a>
    </div>

  </div>
</div>

<?= view('layouts/footer') ?>
