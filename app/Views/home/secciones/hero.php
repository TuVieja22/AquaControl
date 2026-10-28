<?php usar_css('css/home/secciones/hero.css') ?>
<?php // Portada: titulo grande, botones y la imagen del producto de fondo (la foto se elige en hero.css). ?>
<section class="landing-hero">
  <?php // Contenido de la portada, centrado. ?>
  <div class="hero-content container">
    <?php // Texto chico de arriba del titulo. ?>
    <p class="eyebrow hero-eyebrow" data-reveal> Sistema Inteligente AquaControl IoT</p>
    <?php // Titulo principal. ?>
    <h1 data-reveal>Mantén tu acuario bajo control, aunque no estés en casa</h1>
    <?php // Subtitulo. ?>
    <p class="hero-subtitle" data-reveal>
      Monitoreo inteligente, alimentación automática y alertas en tiempo real para cuidar el ecosistema de tu pecera desde cualquier lugar.
    </p>
    <?php // Botones: llevan a otras secciones de la pagina. ?>
    <div class="hero-actions" data-reveal>
      <a class="landing-button landing-button-secondary" href="#demo">Ver Demo</a>
      <a class="landing-button landing-button-secondary" href="#checkout">Comprar Ahora</a>
      <a class="landing-button landing-button-secondary" href="#funciona">Cómo funciona</a>
    </div>
    <?php // Indicador con el puntito que parpadea. ?>
    <div class="hero-signal" aria-label="Estado del sistema" data-reveal>
      <span class="signal-dot"></span>
      Ecosistema estable - datos en vivo
    </div>
  </div>
</section>
