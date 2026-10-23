<?php usar_css('css/home/secciones/comparacion.css') ?>
<?php
// "Por que elegir AquaControl": tabla comparativa (true = tilde, false = guion).
// Filas de la tabla: ventaja, si la tiene AquaControl y si la tienen otros.
$filas = [
    ['label' => 'Monitoreo inteligente', 'aquacontrol' => true, 'others' => false],
    ['label' => 'Alertas autom&aacute;ticas', 'aquacontrol' => true, 'others' => false],
    ['label' => 'F&aacute;cil instalaci&oacute;n', 'aquacontrol' => true, 'others' => true],
    ['label' => 'Soporte local', 'aquacontrol' => true, 'others' => false],
    ['label' => 'Plataforma web moderna', 'aquacontrol' => true, 'others' => false],
];
?>
<section class="landing-section comparison-section">
  <div class="container">
    <?php // Titulo de la seccion. ?>
    <div class="section-heading centered" data-reveal>
      <p class="eyebrow">Por qu&eacute; elegir AquaControl</p>
      <h2>Una plataforma pensada para acuaristas reales.</h2>
    </div>

    <?php // Tabla comparativa. ?>
    <div class="comparison-table" data-reveal>
      <?php // Encabezado de la tabla. ?>
      <div class="comparison-row comparison-head">
        <span>Ventaja</span>
        <strong>AquaControl</strong>
        <span>Otros sistemas</span>
      </div>
      <?php // Una fila por cada ventaja: tilde si la tiene, guion si no. ?>
      <?php foreach ($filas as $row): ?>
        <div class="comparison-row">
          <span><?= $row['label'] ?></span>
          <strong class="check-cell"><?= $row['aquacontrol'] ? '&#10003;' : '-' ?></strong>
          <span class="<?= $row['others'] ? 'check-muted' : 'empty-muted' ?>"><?= $row['others'] ? '&#10003;' : '-' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
