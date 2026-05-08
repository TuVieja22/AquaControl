<?php $title = 'Crear cuenta'; ?>
<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-mark">🐟</div>
      <h2>Crear cuenta</h2>
      <p>Únete a AquaControl y automatiza tu pecera</p>
    </div>

    <!-- Flash messages -->
    <?php if (session()->getFlashdata('error')): ?>
      <div class="flash flash-error">⚠ <?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
      <div class="flash flash-success">✓ <?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>

    <form id="registerForm" action="<?= base_url('auth/register') ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <!-- Nombre -->
      <div class="form-group">
        <label class="form-label" for="nombre">Nombre completo</label>
        <input
          type="text"
          id="nombre"
          name="nombre"
          class="form-control <?= (isset($errors['nombre'])) ? 'is-invalid' : '' ?>"
          placeholder="Ej: Juan Pérez"
          value="<?= old('nombre') ?>"
          autocomplete="name"
          required
        >
        <div class="invalid-feedback" <?= isset($errors['nombre']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['nombre']) ? '<span>⚠</span> ' . esc($errors['nombre']) : '' ?>
        </div>
      </div>

      <!-- Email -->
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

      <!-- Password -->
      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
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
        <!-- Strength bar -->
        <div class="strength-bar">
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
          <div class="strength-seg"></div>
        </div>
        <div class="strength-text"></div>
        <div class="invalid-feedback" <?= isset($errors['password']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password']) ? '<span>⚠</span> ' . esc($errors['password']) : '' ?>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmar contraseña</label>
        <div class="input-group">
          <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            class="form-control <?= (isset($errors['password_confirm'])) ? 'is-invalid' : '' ?>"
            placeholder="Repite tu contraseña"
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

      <!-- Terms -->
      <div class="form-group">
        <label class="form-check" for="terms">
          <input type="checkbox" id="terms" name="terms" value="1" <?= old('terms') ? 'checked' : '' ?>>
          <span>Acepto los <a href="#" class="auth-link">términos y condiciones</a> y la política de privacidad</span>
        </label>
        <div class="invalid-feedback" id="terms-error" style="display:none; margin-top:6px;"></div>
      </div>

      <button type="submit" class="btn btn-primary">Crear mi cuenta →</button>
    </form>

    <div class="auth-footer">
      ¿Ya tienes cuenta? <a href="<?= base_url('auth/login') ?>" class="auth-link">Iniciar sesión</a>
    </div>

  </div>
</div>

<?= view('layouts/footer') ?>
