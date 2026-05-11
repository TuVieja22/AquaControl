<?= view('layouts/header') ?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">&#x1F41F;</div>
      <h2>Crear cuenta</h2>
      <p>Unete a AquaControl y automatiza tu pecera</p>
    </div>

    <?= view('components/flash_messages', ['types' => ['error', 'success']]) ?>

    <?php if (isset($errors['general'])): ?>
      <div class="flash flash-error">&#9888; <?= esc($errors['general']) ?></div>
    <?php endif; ?>

    <form id="registerForm" action="<?= base_url('auth/register') ?>" method="POST" novalidate data-auth-form="register">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="nombre">Nombre completo</label>
        <input
          type="text"
          id="nombre"
          name="nombre"
          class="form-control <?= isset($errors['nombre']) ? 'is-invalid' : '' ?>"
          placeholder="Ej: Juan Perez"
          value="<?= old('nombre') ?>"
          autocomplete="name"
          required
        >
        <div class="invalid-feedback" <?= isset($errors['nombre']) ? '' : 'style="display:none"' ?>>
          <?= isset($errors['nombre']) ? '<span>&#9888;</span> ' . esc($errors['nombre']) : '' ?>
        </div>
      </div>

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

      <div class="form-group">
        <label class="form-label" for="password">Contrasena</label>
        <div class="input-group">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
            placeholder="Minimo 8 caracteres"
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
        <label class="form-label" for="password_confirm">Confirmar contrasena</label>
        <div class="input-group">
          <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
            placeholder="Repite tu contrasena"
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

      <div class="form-group">
        <label class="form-check" for="terms">
          <input type="checkbox" id="terms" name="terms" value="1" <?= old('terms') ? 'checked' : '' ?>>
          <span>Acepto los <a href="#" class="auth-link">terminos y condiciones</a> y la politica de privacidad</span>
        </label>
        <div class="invalid-feedback" id="terms-error" <?= isset($errors['terms']) ? '' : 'style="display:none; margin-top:6px;"' ?>>
          <?= isset($errors['terms']) ? '<span>&#9888;</span> ' . esc($errors['terms']) : '' ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Crear mi cuenta &rarr;</button>
    </form>

    <div class="auth-footer">
      Ya tienes cuenta? <a href="<?= base_url('auth/login') ?>" class="auth-link">Iniciar sesion</a>
    </div>
  </div>
</div>

<?= view('layouts/footer') ?>
