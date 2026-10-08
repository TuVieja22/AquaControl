<?php usar_css('css/layouts/main.css') ?>
<?php
/*
 * Molde de TODAS las paginas: <head>, barra de navegacion y pie.
 * Cada pagina empieza con  $this->extend('layouts/main')  y completa estas secciones:
 *   - 'contenido': lo que va entre la barra y el pie (obligatoria)
 *   - 'cabecera':  etiquetas extra para el <head> de esa pagina (opcional)
 *   - 'scripts':   <script> propios de esa pagina (opcional)
 * Las hojas de estilo no van en una seccion: cada vista anota la suya en su primera
 * linea con usar_css() y aca se escriben todas juntas con enlaces_css().
 */
$logueado = (bool) session()->get('user_id');
$esAdmin = session()->get('user_role') === 'administrador';

if ($logueado) {
    // El boton "Salir" se dibuja mas abajo, cuando el <head> ya esta escrito: su hoja se anota aca.
    usar_css('css/components/logout_button.css');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AquaControl - Sistema inteligente de monitoreo y control de ecosistemas acuaticos basado en IoT con ESP32.">
  <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
  <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>" data-field-name="<?= esc(csrf_token()) ?>">
  <title><?= isset($title) ? esc($title) . ' | AquaControl' : 'AquaControl - Ecosistemas Acuaticos IoT' ?></title>
  <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico') ?>">
  <script>document.documentElement.classList.add('js');</script>
  <!-- Tipografias: se cargan sin frenar el dibujo de la pagina (media="print" -> "all" al terminar). -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@500;600;700;800&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@500;600;700;800&display=swap"></noscript>
  <?= enlaces_css() ?>
  <?= $this->renderSection('cabecera') ?>
</head>
<body>

<div class="page-loader-overlay" data-page-loader role="status" aria-live="polite" aria-label="Cargando contenido">
  <div class="loader"></div>
</div>

<div class="aqua-bg"></div>

<div class="page-wrapper">
  <nav class="navbar">
    <div class="container">
      <a href="<?= base_url('/') ?>" class="navbar-brand">
        <div class="brand-icon">AC</div>
        Aqua<span>Control</span>
      </a>

      <div class="navbar-links">
        <div class="nav-section-links" aria-label="Secciones principales">
          <a href="<?= base_url('/') ?>#producto">Producto</a>
          <a href="<?= base_url('/') ?>#caracteristicas">Caracteristicas</a>
          <a href="<?= base_url('/') ?>#demo">Demo</a>
          <a href="<?= base_url('/') ?>#planes">Planes</a>
        </div>

        <?php if ($logueado): ?>
          <span class="nav-user"><?= esc((string) session()->get('user_nombre')) ?></span>
          <a href="<?= base_url('dashboard') ?>" class="btn-nav">Panel principal</a>
          <a href="<?= base_url('dispositivos') ?>" class="btn-nav">Dispositivos</a>
          <?php if ($esAdmin): ?>
            <a href="<?= base_url('usuarios') ?>" class="btn-nav">Usuarios</a>
            <a href="<?= base_url('pedidos') ?>" class="btn-nav">Pedidos</a>
          <?php endif; ?>
          <div class="logout-button-wrap navbar-logout">
            <?= view('components/logout_button') ?>
          </div>
        <?php else: ?>
          <a href="<?= base_url('auth/login') ?>" class="btn-nav">Iniciar sesion</a>
          <a href="<?= base_url('auth/register') ?>" class="btn-nav primary">Registrarse</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?= $this->renderSection('contenido') ?>

  <footer class="footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <a href="<?= base_url('/') ?>" class="navbar-brand footer-logo">
          <div class="brand-icon">AC</div>
          Aqua<span>Control</span>
        </a>
        <p>Sistema inteligente IoT para monitoreo, alertas y automatizacion de acuarios.</p>
      </div>

      <div class="footer-trust">
        <span>Desarrollado en Argentina</span>
        <span>Facil de instalar</span>
        <span>Soporte tecnico local</span>
      </div>

      <div class="footer-social">
        <a href="https://wa.me/" target="_blank" rel="noopener">WhatsApp</a>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
        <a href="mailto:contacto@aquacontrol.local">Contacto</a>
      </div>

      <p class="footer-copy">&copy; <?= date('Y') ?> <strong>AquaControl</strong> &middot; ESP32 + CodeIgniter 4</p>
    </div>
  </footer>
</div>

<!-- JavaScript de todas las paginas: las funciones por un lado y lo visual por otro. -->
<script src="<?= base_url('js/funciones/aqua.js') ?>"></script>
<script src="<?= base_url('js/animaciones/generales.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
