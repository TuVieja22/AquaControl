<?php usar_css('css/components/campo_password.css') ?>
<?php
/*
 * Campo de contrasena con el boton del ojito (mostrar / ocultar). El boton trae los dos
 * dibujos (ojo y ojo tachado): campo_password.css muestra uno u otro.
 * Variables: $nombre (id y name), $placeholder, $autocomplete, $errors
 *            y $medidor = true para agregar la barra de seguridad debajo.
 */
?>
<div class="input-group">
  <input
    type="password"
    id="<?= esc($nombre) ?>"
    name="<?= esc($nombre) ?>"
    class="form-control<?= clase_error($errors ?? [], $nombre) ?>"
    placeholder="<?= esc($placeholder) ?>"
    autocomplete="<?= esc($autocomplete) ?>"
    required
  >
  <button type="button" class="input-toggle" aria-label="Mostrar contrasena">
    <svg class="icon-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    <svg class="icon-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
  </button>
</div>
<?php if (! empty($medidor)): ?>
  <div class="strength-bar">
    <div class="strength-seg"></div>
    <div class="strength-seg"></div>
    <div class="strength-seg"></div>
    <div class="strength-seg"></div>
    <div class="strength-seg"></div>
  </div>
  <div class="strength-text"></div>
<?php endif; ?>
<?= error_campo($errors ?? [], $nombre) ?>
