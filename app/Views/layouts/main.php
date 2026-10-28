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
// Si hay sesion iniciada (cambia que links se ven).
$logueado = (bool) session()->get('user_id');

if ($logueado) {
    // El boton "Salir" se dibuja mas abajo, cuando el <head> ya esta escrito: su hoja se anota aca.
    usar_css('css/components/logout_button.css');
}
?>
<?php // Aca empieza la pagina que recibe el navegador. ?>
<!DOCTYPE html>
<html lang="es">
<?php // Cabecera: datos de la pagina que no se ven en pantalla. ?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AquaControl - Sistema inteligente de monitoreo y control de ecosistemas acuáticos basado en IoT con ESP32.">
  <?php // Sello de seguridad (token CSRF): lo lee funciones/aqua.js. ?>
  <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
  <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>" data-field-name="<?= esc(csrf_token()) ?>">
  <?php // Titulo de la pestana. ?>
  <title><?= isset($title) ? esc($title) . ' | AquaControl' : 'AquaControl - Ecosistemas Acuáticos IoT' ?></title>
  <?php // Icono de la pestana (el logo de AquaControl; el .png es para pantallas de mucha resolucion). ?>
  <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="48x48">
  <link rel="icon" type="image/png" href="<?= base_url('img/iconos/icono-192.png') ?>" sizes="192x192">
  <?php // Acceso directo en la pantalla del celular: icono, nombre y color de la barra. ?>
  <link rel="apple-touch-icon" href="<?= base_url('img/iconos/icono-180.png') ?>">
  <link rel="manifest" href="<?= base_url('manifest.json') ?>">
  <meta name="theme-color" content="#070b14">
  <?php // Marca que hay JavaScript (lo usan las animaciones de aparicion y el cartel de cargando). ?>
  <script>document.documentElement.classList.add('js');</script>
  <!-- Tipografias: se cargan sin frenar el dibujo de la pagina (media="print" -> "all" al terminar). -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@500;600;700;800&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@500;600;700;800&display=swap"></noscript>
  <?php // Hojas de estilo que pidieron las vistas de esta pagina. ?>
  <?= enlaces_css() ?>
  <?php // Etiquetas extra de cada pagina (opcional). ?>
  <?= $this->renderSection('cabecera') ?>
</head>
<body>

<?php // Cartel de "cargando" (escondido hasta que hace falta). ?>
<div class="page-loader-overlay" data-page-loader role="status" aria-live="polite" aria-label="Cargando contenido">
  <div class="loader"></div>
</div>

<?php // Fondo animado. ?>
<div class="aqua-bg"></div>

<div class="page-wrapper">
  <?php // Barra de navegacion de arriba. ?>
  <nav class="navbar">
    <div class="container">
      <?php // Logo: lleva a la portada. ?>
      <a href="<?= base_url('/') ?>" class="navbar-brand">
        <div class="brand-icon">AC</div>
        Aqua<span>Control</span>
      </a>

      <div class="navbar-links">
        <?php // Links a las secciones de la portada. ?>
        <div class="nav-section-links" aria-label="Secciones principales">
          <a href="<?= base_url('/') ?>#producto">Producto</a>
          <a href="<?= base_url('/') ?>#caracteristicas">Características</a>
          <a href="<?= base_url('/') ?>#demo">Demo</a>
          <a href="<?= base_url('/') ?>#planes">Planes</a>
        </div>

        <?php // Con sesion: nombre, panel, dispositivos y salir. ?>
        <?php if ($logueado): ?>
          <span class="nav-user"><?= esc((string) session()->get('user_nombre')) ?></span>
          <a href="<?= base_url('dashboard') ?>" class="btn-nav">Panel principal</a>
          <a href="<?= base_url('dispositivos') ?>" class="btn-nav">Dispositivos</a>
          <div class="logout-button-wrap navbar-logout">
            <?= view('components/logout_button') ?>
          </div>
        <?php else: ?>
          <?php // Sin sesion: iniciar sesion y registrarse. ?>
          <a href="<?= base_url('auth/login') ?>" class="btn-nav">Iniciar sesión</a>
          <a href="<?= base_url('auth/register') ?>" class="btn-nav primary">Registrarse</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?php // Aca va el contenido de cada pagina. ?>
  <?= $this->renderSection('contenido') ?>

  <?php // Pie de pagina. ?>
  <footer class="footer">
    <div class="container footer-grid">
      <?php // Logo y frase. ?>
      <div class="footer-brand">
        <a href="<?= base_url('/') ?>" class="navbar-brand footer-logo">
          <div class="brand-icon">AC</div>
          Aqua<span>Control</span>
        </a>
        <p>Sistema inteligente IoT para monitoreo, alertas y automatización de acuarios.</p>
      </div>

      <?php // Tres datos destacados. ?>
      <div class="footer-trust">
        <span>Desarrollado en Argentina</span>
        <span>Fácil de instalar</span>
        <span>Soporte técnico local</span>
      </div>

      <?php // Redes y contacto. ?>
      <div class="footer-social">
        <a href="https://wa.me/" target="_blank" rel="noopener">WhatsApp</a>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
        <a href="mailto:contacto@aquacontrol.local">Contacto</a>
      </div>

      <?php // Texto de derechos (la fecha se actualiza sola). ?>
      <p class="footer-copy">&copy; <?= date('Y') ?> <strong>AquaControl</strong> &middot; ESP32 + CodeIgniter 4</p>
    </div>
  </footer>
</div>

<!-- JavaScript de todas las paginas: las funciones por un lado y lo visual por otro. -->
<script src="<?= base_url('js/funciones/aqua.js') ?>"></script>
<script src="<?= base_url('js/animaciones/generales.js') ?>"></script>
<?php // JavaScript propio de cada pagina (opcional). ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
