<?php $title = 'Iniciar sesión'; ?>
<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-mark">🐟</div>
      <h2>Bienvenido de nuevo</h2>
      <p>Inicia sesión para acceder a tu dashboard</p>
    </div>

    <!-- Flash messages -->
    <?php if (session()->getFlashdata('error')): ?>
      <div class="flash flash-error">⚠ <?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
      <div class="flash flash-success">✓ <?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('info')): ?>
      <div class="flash flash-info">ℹ <?= session()->getFlashdata('info') ?></div>
    <?php endif; ?>

    <form id="loginForm" action="<?= base_url('auth/login') ?>" method="POST" novalidate>
      <?= csrf_field() ?>

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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <label class="form-label" for="password" style="margin:0;">Contraseña</label>
          <a href="<?= base_url('auth/recover') ?>" class="auth-link" style="font-size:0.8rem;">¿Olvidaste tu contraseña?</a>
        </div>
        <div class="input-group">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control <?= (isset($errors['password'])) ? 'is-invalid' : '' ?>"
            placeholder="Tu contraseña"
            autocomplete="current-password"
            required
          >
          <button type="button" class="input-toggle" aria-label="Mostrar contraseña">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="invalid-feedback" <?= isset($errors['password']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['password']) ? '<span>⚠</span> ' . esc($errors['password']) : '' ?>
        </div>
      </div>

      <!-- Remember me -->
      <div class="form-group">
        <label class="form-check" for="remember">
          <input type="checkbox" id="remember" name="remember" value="1">
          <span>Recordarme en este dispositivo</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary">Iniciar sesión →</button>
    </form>

    <div class="auth-footer">
      ¿No tienes cuenta? <a href="<?= base_url('auth/register') ?>" class="auth-link">Regístrate gratis</a>
    </div>

  </div>
</div>

<?= view('layouts/footer') ?>
