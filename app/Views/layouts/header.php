<?php
$isAuthenticated = (bool) session()->get('user_id');
$authenticatedLinks = [
    ['href' => base_url('dashboard'), 'label' => 'Panel principal'],
    ['href' => base_url('dashboard/history') . '#historial', 'label' => 'Historial'],
    ['href' => base_url('dashboard/settings') . '#configuracion', 'label' => 'Configuracion'],
];
$guestLinks = [
    ['href' => base_url('auth/login'), 'label' => 'Iniciar sesion', 'class' => 'btn-nav'],
    ['href' => base_url('auth/register'), 'label' => 'Registrarse', 'class' => 'btn-nav primary'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AquaControl - Sistema inteligente de monitoreo y control de ecosistemas acuaticos basado en IoT con ESP32.">
  <title><?= isset($title) ? esc($title) . ' | AquaControl' : 'AquaControl - Ecosistemas Acuaticos IoT' ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= base_url('img/favicon.svg') ?>">
  <script>
    (function () {
      document.documentElement.classList.add('js');
      try {
        var storedTheme = localStorage.getItem('aquacontrol-theme');
        document.documentElement.dataset.theme = storedTheme === 'light' ? 'light' : 'dark';
      } catch (error) {
        document.documentElement.dataset.theme = 'dark';
      }
    }());
  </script>
  <link rel="stylesheet" href="<?= base_url('css/aqua.css') ?>">
  <link rel="stylesheet" href="<?= base_url('css/components/theme-switch.css') ?>">
  <link rel="stylesheet" href="<?= base_url('css/components/loader.css') ?>">
  <link rel="stylesheet" href="<?= base_url('css/components/logout-button.css') ?>">
  <?php foreach (($extraCss ?? []) as $cssFile): ?>
    <link rel="stylesheet" href="<?= base_url($cssFile) ?>">
  <?php endforeach; ?>
</head>
<body>

<div class="page-loader-overlay is-visible" data-page-loader role="status" aria-live="polite" aria-label="Cargando contenido">
  <div class="loader-shell">
    <div class="loader">
      <div class="cap"></div>
      <div class="shadow-top"></div>
      <div class="circle"></div>
      <div class="circle-top"></div>
      <svg class="fish" viewBox="0 0 64 64" aria-hidden="true">
        <path fill="#ffb347" d="M50 31c0 8-7 15-18 15-4 0-8-1-11-3l-11 6 3-10c-3-2-5-5-5-8 0-8 9-15 21-15s21 7 21 15Z"/>
        <path fill="#ff8f1f" d="M12 23 4 18l2 9-2 9 8-5z"/>
        <circle cx="39" cy="28" r="2.6" fill="#10231f"/>
      </svg>
    </div>
    <div class="shadow-bottom"></div>
  </div>
</div>

<div class="aqua-bg"></div>

<div class="fish-deco f1">
  <svg width="120" height="60" viewBox="0 0 120 60">
    <ellipse cx="55" cy="30" rx="45" ry="22"/>
    <polygon points="100,30 120,14 120,46"/>
    <circle cx="22" cy="24" r="6" fill="rgba(255,255,255,0.5)"/>
    <circle cx="20" cy="23" r="3" fill="#085041"/>
  </svg>
</div>
<div class="fish-deco f2">
  <svg width="90" height="46" viewBox="0 0 90 46">
    <ellipse cx="40" cy="23" rx="34" ry="17"/>
    <polygon points="74,23 90,10 90,36"/>
    <circle cx="16" cy="18" r="5" fill="rgba(255,255,255,0.5)"/>
    <circle cx="15" cy="17" r="2.5" fill="#085041"/>
  </svg>
</div>

<div class="page-wrapper">
  <nav class="navbar">
    <div class="container">
      <a href="<?= base_url('/') ?>" class="navbar-brand">
        <div class="brand-icon">AC</div>
        Aqua<span>Control</span>
      </a>

      <div class="navbar-links">
        <div class="theme-switch-shell" title="Cambiar tema">
          <span class="theme-switch-text">Tema</span>
          <div class="toggle-switch">
            <label class="switch-label" for="globalThemeToggle">
              <input class="checkbox" id="globalThemeToggle" type="checkbox" data-theme-toggle aria-label="Cambiar entre tema claro y oscuro">
              <span class="slider"></span>
            </label>
          </div>
        </div>

        <?php if ($isAuthenticated): ?>
          <span class="nav-user"><?= esc((string) session()->get('user_nombre')) ?></span>
          <?php foreach ($authenticatedLinks as $link): ?>
            <a href="<?= esc($link['href']) ?>" class="btn-nav"><?= esc($link['label']) ?></a>
          <?php endforeach; ?>
          <div class="logout-button-wrap navbar-logout">
            <?= view('components/logout_button') ?>
          </div>
        <?php else: ?>
          <?php foreach ($guestLinks as $link): ?>
            <a href="<?= esc($link['href']) ?>" class="<?= esc($link['class']) ?>"><?= esc($link['label']) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </nav>
