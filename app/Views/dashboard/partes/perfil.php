<?php usar_css('css/dashboard/partes/perfil.css') ?>
<?php
// "Mi cuenta": formulario para cambiar nombre, apellido y email (lo guarda el controlador Perfil).
// Si el formulario volvio con errores, se muestran los datos que habia escrito el usuario.
// Errores, datos a mostrar e inicial del nombre (para el circulo; mb_ para que ande con tildes).
$errores = $profileErrors;
$nombre = $profileForm['nombre'] ?? $profile['nombre'];
$apellido = $profileForm['apellido'] ?? $profile['apellido'];
$email = $profileForm['email'] ?? $profile['email'];
$inicial = mb_strtoupper(mb_substr(trim($profile['nombre']), 0, 1)) ?: 'U';
?>
<section class="dashboard-grid profile-grid" id="perfil">
  <?php // Formulario de la cuenta. ?>
  <article class="glass-card profile-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Mi cuenta</p>
        <h3>Datos de acceso</h3>
      </div>
    </div>

    <?php // Manda los cambios a dashboard/profile. ?>
    <form class="profile-form" action="<?= base_url('dashboard/profile') ?>" method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="profile-form-grid">
        <?php // Nombre. ?>
        <div class="form-group">
          <label class="form-label" for="profile_nombre">Nombre</label>
          <input class="form-control<?= clase_error($errores, 'nombre') ?>" id="profile_nombre" name="nombre" type="text" value="<?= esc($nombre) ?>" autocomplete="given-name" required>
          <p class="field-help">Aparece en el panel, alertas y registros de mantenimiento.</p>
          <?= error_campo($errores, 'nombre') ?>
        </div>

        <?php // Apellido. ?>
        <div class="form-group">
          <label class="form-label" for="profile_apellido">Apellido</label>
          <input class="form-control<?= clase_error($errores, 'apellido') ?>" id="profile_apellido" name="apellido" type="text" value="<?= esc($apellido) ?>" autocomplete="family-name" required>
          <?= error_campo($errores, 'apellido') ?>
        </div>

        <?php // Correo electronico. ?>
        <div class="form-group">
          <label class="form-label" for="profile_email">Correo electrónico</label>
          <input class="form-control<?= clase_error($errores, 'email') ?>" id="profile_email" name="email" type="email" value="<?= esc($email) ?>" autocomplete="email" required>
          <p class="field-help">Se usa para iniciar sesión y recuperar la cuenta.</p>
          <?= error_campo($errores, 'email') ?>
        </div>

        <?php // Contrasena actual (solo hace falta para cambiar el correo). ?>
        <div class="form-group">
          <label class="form-label" for="profile_current_password">Contraseña actual</label>
          <input class="form-control<?= clase_error($errores, 'current_password') ?>" id="profile_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Solo si cambias el email">
          <p class="field-help">Confirma tu identidad antes de modificar el correo de acceso.</p>
          <?= error_campo($errores, 'current_password') ?>
        </div>
      </div>

      <?php // Boton de guardar. ?>
      <div class="profile-form-actions">
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
      </div>
    </form>
  </article>

  <?php // Resumen de la cuenta. ?>
  <article class="glass-card profile-card profile-summary-card">
    <?php // Circulo con la inicial, nombre y correo. ?>
    <div class="profile-identity">
      <span class="profile-avatar"><?= esc($inicial) ?></span>
      <div>
        <p class="section-tag">Sesión activa</p>
        <h3><?= esc($profile['nombreCompleto']) ?></h3>
        <span><?= esc($profile['email']) ?></span>
      </div>
    </div>

    <?php // Fecha de alta y ultimo cambio. ?>
    <div class="profile-summary">
      <div class="profile-summary-item">
        <span>Cuenta creada</span>
        <strong><?= $profile['created_at'] ? date('d/m/Y', strtotime($profile['created_at'])) : 'Sin datos' ?></strong>
      </div>
      <div class="profile-summary-item">
        <span>Última actualización</span>
        <strong><?= $profile['updated_at'] ? date('d/m/Y H:i', strtotime($profile['updated_at'])) : 'Sin cambios' ?></strong>
      </div>
    </div>

    <?php // Notificaciones de alertas en este celular o PC (lo maneja funciones/notificaciones.js). ?>
    <div class="profile-push" id="notificaciones" data-push
      data-clave="<?= esc(config(\Config\WebPush::class)->clavePublica) ?>"
      data-sw-url="<?= base_url('sw.js') ?>"
      data-guardar-url="<?= base_url('dashboard/notificaciones') ?>"
      data-borrar-url="<?= base_url('dashboard/notificaciones/borrar') ?>"
      data-probar-url="<?= base_url('dashboard/notificaciones/probar') ?>">
      <p class="section-tag">Notificaciones</p>
      <p class="profile-push-text" role="status" aria-live="polite" data-push-texto>
        Recibe en este dispositivo las alertas de tu pecera (temperatura, desconexiones, comidas que fallaron).
      </p>
      <div class="profile-push-actions">
        <button class="btn btn-primary" type="button" data-push-activar hidden>Activar notificaciones</button>
        <button class="btn btn-outline" type="button" data-push-probar hidden>Mandar una de prueba</button>
        <button class="btn btn-ghost" type="button" data-push-desactivar hidden>Desactivar</button>
      </div>
    </div>
  </article>
</section>
