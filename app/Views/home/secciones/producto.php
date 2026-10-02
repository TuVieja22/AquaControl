<?php
// "Producto en accion": galeria de 4 tarjetas (2 con foto y 2 dibujadas con CSS).
$imagen = base_url('img/aquacontrol-product.webp');
$tarjetas = [
    ['class' => 'gallery-product', 'label' => 'Producto completo', 'title' => 'Kit AquaControl IoT', 'copy' => 'Modulo central, sensores y alimentador en una pieza visualmente integrada.', 'type' => 'image'],
    ['class' => 'gallery-sensors', 'label' => 'Sensores', 'title' => 'Lecturas precisas', 'copy' => 'pH, temperatura y nivel conectados al ecosistema en tiempo real.', 'type' => 'image'],
    ['class' => 'gallery-dashboard', 'label' => 'Dashboard web', 'title' => 'Control remoto', 'copy' => 'Metricas claras para actuar antes de que aparezca un problema.', 'type' => 'dashboard'],
    ['class' => 'gallery-aquarium', 'label' => 'Acuario saludable', 'title' => 'Ecosistema estable', 'copy' => 'Condiciones constantes para viajar, trabajar o descansar sin incertidumbre.', 'type' => 'aquarium'],
];
?>
<section class="landing-section product-action" id="producto">
  <div class="container">
    <div class="section-heading" data-reveal>
      <p class="eyebrow">Producto en acci&oacute;n</p>
      <h2>Hardware, sensores y software trabajando como un solo sistema.</h2>
    </div>

    <div class="product-gallery">
      <?php foreach ($tarjetas as $item): ?>
        <article class="gallery-card <?= esc($item['class']) ?>" data-reveal>
          <?php if ($item['type'] === 'image'): ?>
            <img src="<?= $imagen ?>" alt="<?= esc($item['title']) ?>" width="1448" height="1086" loading="lazy" decoding="async">
          <?php elseif ($item['type'] === 'dashboard'): ?>
            <div class="mini-dashboard" aria-hidden="true">
              <div class="mini-dashboard-top"></div>
              <div class="mini-chart">
                <span style="height: 42%"></span>
                <span style="height: 65%"></span>
                <span style="height: 54%"></span>
                <span style="height: 78%"></span>
                <span style="height: 62%"></span>
              </div>
              <div class="mini-metrics">
                <span>25.6&deg;C</span>
                <span>pH 6.84</span>
              </div>
            </div>
          <?php else: ?>
            <div class="aquarium-visual" aria-hidden="true">
              <span class="aquarium-light"></span>
              <span class="aquarium-fish fish-one"></span>
              <span class="aquarium-fish fish-two"></span>
              <span class="aquarium-plant plant-one"></span>
              <span class="aquarium-plant plant-two"></span>
              <span class="aquarium-bubble bubble-one"></span>
              <span class="aquarium-bubble bubble-two"></span>
              <span class="aquarium-bubble bubble-three"></span>
            </div>
          <?php endif; ?>
          <div class="gallery-copy">
            <span><?= $item['label'] ?></span>
            <h3><?= $item['title'] ?></h3>
            <p><?= $item['copy'] ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
