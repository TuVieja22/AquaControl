<?php usar_css('css/dashboard/partes/perfil.css') ?>
<?php
// "Mi cuenta": formulario para cambiar nombre y email (lo guarda el controlador Perfil).
// Si el formulario volvio con errores, se muestran los datos que habia escrito el usuario.
$errores = $profileErrors;
$nombre = $profileForm['nombre'] ?? $profile['nombre'];
$email = $profileForm['email'] ?? $profile['email'];
$inicial = strtoupper(substr(trim($nombre), 0, 1)) ?: 'U';
?>
<section class="dashboard-grid profile-grid" id="perfil">
  <article class="glass-card profile-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Mi cuenta</p>
        <h3>Datos de acceso</h3>
      </div>
    </div>

    <form class="profile-form" action="<?= base_url('dashboard/profile') ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="profile-form-grid">
        <div class="form-group">
          <label class="form-label" for="profile_nombre">Nombre y apellido</label>
          <input class="form-control<?= clase_error($errores, 'nombre') ?>" id="profile_nombre" name="nombre" type="text" value="<?= esc($nombre) ?>" autocomplete="name" required>
          <p class="field-help">Aparece en el panel, alertas y registros de mantenimiento.</p>
          <?= error_campo($errores, 'nombre') ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile_email">Correo electronico</label>
          <input class="form-control<?= clase_error($errores, 'email') ?>" id="profile_email" name="email" type="email" value="<?= esc($email) ?>" autocomplete="email" required>
          <p class="field-help">Se usa para iniciar sesion y recuperar la cuenta.</p>
          <?= error_campo($errores, 'email') ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile_current_password">Contrasena actual</label>
          <input class="form-control<?= clase_error($errores, 'current_password') ?>" id="profile_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Solo si cambias el email">
          <p class="field-help">Confirma tu identidad antes de modificar el correo de acceso.</p>
          <?= error_campo($errores, 'current_password') ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile_role">Rol actual</label>
          <input class="form-control" id="profile_role" type="text" value="<?= esc($profile['roleLabel']) ?>" readonly>
          <p class="field-help">Define los permisos disponibles dentro de AquaControl.</p>
        </div>
      </div>

      <div class="profile-form-actions">
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
      </div>
    </form>
  </article>

  <article class="glass-card profile-card profile-summary-card">
    <div class="profile-identity">
      <span class="profile-avatar"><?= esc($inicial) ?></span>
      <div>
        <p class="section-tag">Sesion activa</p>
        <h3><?= esc($profile['nombre']) ?></h3>
        <span><?= esc($profile['email']) ?></span>
      </div>
    </div>

    <div class="profile-summary">
      <div class="profile-summary-item">
        <span>Rol</span>
        <strong><?= esc($profile['roleLabel']) ?></strong>
      </div>
      <div class="profile-summary-item">
        <span>Cuenta creada</span>
        <strong><?= $profile['created_at'] ? date('d/m/Y', strtotime($profile['created_at'])) : 'Sin datos' ?></strong>
      </div>
      <div class="profile-summary-item">
        <span>Ultima actualizacion</span>
        <strong><?= $profile['updated_at'] ? date('d/m/Y H:i', strtotime($profile['updated_at'])) : 'Sin cambios' ?></strong>
      </div>
    </div>
  </article>
</section>
