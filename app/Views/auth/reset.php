<?php $title = 'Nueva contraseña'; ?>
<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-mark">🔐</div>
      <h2>Nueva contraseña</h2>
      <p>Elige una contraseña segura para tu cuenta</p>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
      <div class="flash flash-error">⚠ <?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>

    <form id="resetForm" action="<?= base_url('auth/reset') ?>" method="POST" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

      <div class="form-group">
        <label class="form-label" for="password">Nueva contraseña</label>
        <div class="input-group">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control <?= (isset($errors['password'])) ? 'is-invalid' : '' ?>"
            placeholder="Mínimo 8 caracteres"
            autocomplete="new-password"
            required
          >
          <button type="button" class="input-toggle" aria-label="Mostrar contraseña">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="strength-bar">
          <div class="strength-seg"></div><div class="strength-seg"></div>
          <div class="strength-seg"></div><div class="strength-seg"></div>
          <div class="strength-seg"></div>
        </div>
        <div class="strength-text"></div>
        <div class="invalid-feedback" <?= isset($errors['password']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password']) ? '<span>⚠</span> ' . esc($errors['password']) : '' ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmar nueva contraseña</label>
        <div class="input-group">
          <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            class="form-control <?= (isset($errors['password_confirm'])) ? 'is-invalid' : '' ?>"
            placeholder="Repite la contraseña"
            autocomplete="new-password"
            required
          >
          <button type="button" class="input-toggle" aria-label="Mostrar contraseña">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="invalid-feedback" <?= isset($errors['password_confirm']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password_confirm']) ? '<span>⚠</span> ' . esc($errors['password_confirm']) : '' ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Guardar nueva contraseña →</button>
    </form>

  </div>
</div>

<?= view('layouts/footer') ?>
