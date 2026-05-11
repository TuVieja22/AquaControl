<?php
$logoutHref = $href ?? base_url('auth/logout');
$logoutLabel = $label ?? 'Salir';
$logoutAriaLabel = $ariaLabel ?? 'Cerrar sesion';
$logoutClass = trim('Btn ' . ($class ?? ''));
?>
<a href="<?= esc($logoutHref) ?>" class="<?= esc($logoutClass) ?>" aria-label="<?= esc($logoutAriaLabel) ?>">
  <span class="sign" aria-hidden="true">
    <svg viewBox="0 0 512 512">
      <path d="M377.9 105.9 500.7 228.7c15 15 15 39.3 0 54.3L377.9 406.1c-15.1 15.1-41 4.4-41-17V320H192c-22.1 0-40-17.9-40-40v-48c0-22.1 17.9-40 40-40h144.9v-69.1c0-21.4 25.9-32.1 41-17ZM192 352H96c-17.7 0-32-14.3-32-32V192c0-17.7 14.3-32 32-32h96c17.7 0 32 14.3 32 32s-14.3 32-32 32H96C42.98 96 0 138.1 0 192v128c0 53 42.98 96 96 96h96c17.7 0 32-14.3 32-32s-14.3-32-32-32Z"/>
    </svg>
  </span>
  <span class="text"><?= esc($logoutLabel) ?></span>
</a>
