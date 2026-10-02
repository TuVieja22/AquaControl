<?= $this->extend('layouts/main') ?>

<?= $this->section('estilos') ?>
  <link rel="stylesheet" href="<?= base_url('css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">AC</div>
      <h2>Nueva contrasena</h2>
      <p>Elige una contrasena segura para tu cuenta</p>
    </div>

    <?= view('components/flash_messages') ?>

    <form id="resetForm" action="<?= base_url('auth/reset') ?>" method="POST" novalidate data-auth-form="reset">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

      <div class="form-group">
        <label class="form-label" for="password">Nueva contrasena</label>
        <?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Minimo 8, Aa, 123 y simbolo', 'autocomplete' => 'new-password', 'medidor' => true]) ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmar nueva contrasena</label>
        <?= view('components/campo_password', ['nombre' => 'password_confirm', 'placeholder' => 'Repite la contrasena', 'autocomplete' => 'new-password', 'medidor' => false]) ?>
      </div>

      <button type="submit" class="btn btn-primary">Guardar nueva contrasena &rarr;</button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/auth.js') ?>"></script>
<?= $this->endSection() ?>
