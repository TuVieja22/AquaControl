<?php usar_css('css/home/secciones/como_funciona.css') ?>
<?php // "Como funciona": los 3 pasos. El dibujo de cada paso sale de su clase CSS (sensor-visual, etc.). ?>
<section class="landing-section process-section" id="funciona">
  <div class="container">
    <?php // Titulo de la seccion. ?>
    <div class="section-heading" data-reveal>
      <p class="eyebrow">C&oacute;mo funciona</p>
      <h2>Instalás, conectás y controlás todo desde tu celular.</h2>
    </div>

    <?php // Los pasos, uno al lado del otro. ?>
    <div class="process-track">
      <?php // Paso 1. ?>
      <article class="process-step" data-reveal>
        <div class="step-visual sensor-visual" aria-hidden="true"></div>
        <span>01</span>
        <h3>Instal&aacute;s los sensores</h3>
        <p>Colocá las sondas de pH, temperatura y nivel en puntos clave del acuario.</p>
      </article>
      <?php // Paso 2. ?>
      <article class="process-step" data-reveal>
        <div class="step-visual device-visual" aria-hidden="true"></div>
        <span>02</span>
        <h3>Conect&aacute;s el dispositivo</h3>
        <p>El módulo central recibe datos, procesa eventos y activa rutinas automáticas.</p>
      </article>
      <?php // Paso 3. ?>
      <article class="process-step" data-reveal>
        <div class="step-visual phone-visual" aria-hidden="true"></div>
        <span>03</span>
        <h3>Control&aacute;s todo desde tu celular</h3>
        <p>Revisá métricas, alertas y estados desde una experiencia web moderna.</p>
      </article>
    </div>
  </div>
</section>
