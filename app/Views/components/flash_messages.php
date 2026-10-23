<?php usar_css('css/components/flash_messages.css') ?>
<?php
// Mensajes de un solo uso que deja el controlador con ->with('success'|'error'|'info', '...').
// Icono de cada tipo de mensaje.
$iconos = ['error' => '&#9888;', 'success' => '&#10003;', 'info' => '&#8505;'];
?>
<?php // Por cada tipo: si hay un mensaje guardado se muestra (y no vuelve a aparecer). ?>
<?php foreach ($iconos as $tipo => $icono): ?>
  <?php if ($mensaje = session()->getFlashdata($tipo)): ?>
    <div class="flash flash-<?= $tipo ?>"><?= $icono ?> <?= esc($mensaje) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
