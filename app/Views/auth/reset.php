<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">&#x1F510;</div>
      <h2>Nueva contrasena</h2>
      <p>Elige una contrasena segura para tu cuenta</p>
    </div>

    <?= view('components/flash_messages', ['types' => ['error']]) ?>

    <form id="resetForm" action="<?= base_url('auth/reset') ?>" method="POST" novalidate data-auth-form="reset">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

      <div class="form-group">
        <label class="form-label" for="password">Nueva contrasena</label>
        <div class="input-group">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
            placeholder="Minimo 8, Aa, 123 y simbolo"
            autocomplete="new-password"
            required
          >
          <button type="button" class="input-toggle" aria-label="Mostrar contrasena">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="strength-bar">
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
        </div>
        <div class="strength-text"></div>
        <div class="invalid-feedback" <?= isset($errors['password']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password']) ? '<span>&#9888;</span> ' . esc($errors['password']) : '' ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmar nueva contrasena</label>
        <div class="input-group">
          <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
            placeholder="Repite la contrasena"
            autocomplete="new-password"
            required
          >
          <button type="button" class="input-toggle" aria-label="Mostrar contrasena">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="invalid-feedback" <?= isset($errors['password_confirm']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password_confirm']) ? '<span>&#9888;</span> ' . esc($errors['password_confirm']) : '' ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Guardar nueva contrasena &rarr;</button>
    </form>
  </div>
</div>

<?= view('layouts/footer') ?>
