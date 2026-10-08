<?php usar_css('css/components/flash_messages.css') ?>
<?php
// Mensajes de un solo uso que deja el controlador con ->with('success'|'error'|'info', '...').
$iconos = ['error' => '&#9888;', 'success' => '&#10003;', 'info' => '&#8505;'];
?>
<?php foreach ($iconos as $tipo => $icono): ?>
  <?php if ($mensaje = session()->getFlashdata($tipo)): ?>
    <div class="flash flash-<?= $tipo ?>"><?= $icono ?> <?= esc($mensaje) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
