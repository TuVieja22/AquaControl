<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AquaControl – Sistema inteligente de monitoreo y control de ecosistemas acuáticos basado en IoT con ESP32.">
  <title><?= isset($title) ? esc($title) . ' | AquaControl' : 'AquaControl – Ecosistemas Acuáticos IoT' ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= base_url('img/favicon.svg') ?>">
  <link rel="stylesheet" href="<?= base_url('css/aqua.css') ?>">
</head>
<body>

<!-- Fondo animado -->
<div class="aqua-bg"></div>

<!-- Peces decorativos -->
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
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="container">
      <a href="<?= base_url('/') ?>" class="navbar-brand">
        <div class="brand-icon">🐟</div>
        Aqua<span>Control</span>
      </a>
      <div class="navbar-links">
        <?php if (session()->get('user_id')): ?>
          <a href="<?= base_url('dashboard') ?>" class="btn-nav">Dashboard</a>
          <a href="<?= base_url('auth/logout') ?>" class="btn-nav">Cerrar sesión</a>
        <?php else: ?>
          <a href="<?= base_url('auth/login') ?>"    class="btn-nav">Iniciar sesión</a>
          <a href="<?= base_url('auth/register') ?>" class="btn-nav primary">Registrarse</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
