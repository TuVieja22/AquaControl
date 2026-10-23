<?php usar_css('css/home/secciones/caracteristicas.css') ?>
<?php
// "Caracteristicas principales": 5 tarjetas. El icono sale de la clase CSS icon-<icon>.
// Las cinco tarjetas: icono, titulo y texto.
$caracteristicas = [
    ['icon' => 'pulse', 'title' => 'Monitoreo en Tiempo Real', 'copy' => 'Temperatura, pH, nivel y actividad del sistema actualizados desde tu panel web.'],
    ['icon' => 'feed', 'title' => 'Alimentaci&oacute;n Autom&aacute;tica', 'copy' => 'Programa horarios y evita excesos con rutinas ajustadas al comportamiento del acuario.'],
    ['icon' => 'alert', 'title' => 'Alertas Inteligentes', 'copy' => 'Recibi avisos claros cuando una variable sale del rango saludable.'],
    ['icon' => 'away', 'title' => 'Modo Ausencia', 'copy' => 'Activa rutinas autonomas para mantener el acuario estable mientras no estas.'],
    ['icon' => 'energy', 'title' => 'Bajo Consumo Energ&eacute;tico', 'copy' => 'Arquitectura eficiente sobre ESP32, sensores y actuadores optimizados.'],
];
?>
<section class="landing-section feature-section" id="caracteristicas">
  <div class="container">
    <?php // Titulo de la seccion. ?>
    <div class="section-heading centered" data-reveal>
      <p class="eyebrow">Caracter&iacute;sticas principales</p>
      <h2>Tecnologia invisible, cuidado constante.</h2>
    </div>

    <?php // Una tarjeta por cada caracteristica de la lista. ?>
    <div class="feature-grid">
      <?php foreach ($caracteristicas as $feature): ?>
        <article class="premium-card feature-tile" data-reveal>
          <span class="icon-badge icon-<?= esc($feature['icon']) ?>" aria-hidden="true"></span>
          <h3><?= $feature['title'] ?></h3>
          <p><?= $feature['copy'] ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
