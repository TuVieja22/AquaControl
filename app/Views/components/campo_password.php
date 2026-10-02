<?php
/*
 * Campo de contrasena con el boton del ojito (mostrar / ocultar).
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
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
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
