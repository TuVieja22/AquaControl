<?php usar_css('css/home/secciones/beneficios.css') ?>
<?php
// "Beneficios reales": 4 tarjetas con tilde.
$beneficios = [
    ['title' => 'Evit&aacute; la muerte de tus peces', 'copy' => 'Detecta cambios criticos antes de que el agua afecte la salud del ecosistema.'],
    ['title' => 'Viaj&aacute; tranquilo', 'copy' => 'AquaControl mantiene rutinas y te avisa si necesita tu atencion.'],
    ['title' => 'Ahorra tiempo y dinero', 'copy' => 'Menos controles manuales, menos emergencias y decisiones con datos.'],
    ['title' => 'Control total desde cualquier lugar', 'copy' => 'Consulta el estado del acuario desde una interfaz web clara y responsive.'],
];
?>
<section class="landing-section benefits-section">
  <div class="container">
    <div class="section-heading centered" data-reveal>
      <p class="eyebrow">Beneficios reales</p>
      <h2>Mas tranquilidad para vos. Mas estabilidad para tu acuario.</h2>
    </div>

    <div class="benefit-grid">
      <?php foreach ($beneficios as $benefit): ?>
        <article class="premium-card benefit-card" data-reveal>
          <span class="benefit-mark" aria-hidden="true"></span>
          <h3><?= $benefit['title'] ?></h3>
          <p><?= $benefit['copy'] ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
