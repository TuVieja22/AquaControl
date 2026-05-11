<?php
$flashMap = [
    'error' => ['class' => 'flash-error', 'icon' => '&#9888;'],
    'success' => ['class' => 'flash-success', 'icon' => '&#10003;'],
    'info' => ['class' => 'flash-info', 'icon' => '&#8505;'],
];

$flashTypes = $types ?? array_keys($flashMap);
?>
<?php foreach ($flashTypes as $type): ?>
  <?php $message = session()->getFlashdata($type); ?>
  <?php if (! $message || ! isset($flashMap[$type])): ?>
    <?php continue; ?>
  <?php endif; ?>
  <div class="flash <?= esc($flashMap[$type]['class']) ?>">
    <?= $flashMap[$type]['icon'] ?> <?= esc($message) ?>
  </div>
<?php endforeach; ?>
