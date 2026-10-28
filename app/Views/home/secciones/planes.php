<?php usar_css('css/home/secciones/planes.css') ?>
<?php // "Precio y planes": plan gratis y el kit. El precio sale de commerce.unitPrice (.env). ?>
<section class="landing-section pricing-section" id="planes">
  <div class="container">
    <?php // Titulo de la seccion. ?>
    <div class="section-heading centered" data-reveal>
      <p class="eyebrow">Precio y planes</p>
      <h2>Eleg&iacute; el nivel de protecci&oacute;n para tu acuario.</h2>
    </div>

    <?php // Los dos planes, uno al lado del otro. ?>
    <div class="pricing-grid">
      <?php // Plan gratis: lleva al registro (o al panel, si ya hay sesion). ?>
      <article class="price-card" data-reveal>
        <span>B&aacute;sico</span>
        <h3>Control inicial</h3>
        <p class="price-value">Gratis</p>
        <p>Acceso a cuenta, vista general y configuración inicial para explorar AquaControl.</p>
        <ul>
          <li>Panel web responsive</li>
          <li>Registro de usuarios</li>
          <li>Base para conectar tu dispositivo</li>
        </ul>
        <a class="landing-button landing-button-secondary" href="<?= base_url(session()->get('user_id') ? 'dashboard' : 'auth/register') ?>">Quiero Proteger Mi Acuario</a>
      </article>

      <?php // Plan pago: muestra el precio y lleva al formulario de compra. ?>
      <article class="price-card highlighted" data-reveal>
        <span>Pro</span>
        <h3>Kit AquaControl IoT</h3>
        <p class="price-value"><?= esc($producto['precioTarjeta']) ?></p>
        <p>Sensores, alimentación automática, alertas y monitoreo inteligente listo para producción.</p>
        <ul>
          <li>Monitoreo de temperatura, pH y nivel</li>
          <li>Alertas inteligentes</li>
          <li>Soporte técnico local</li>
        </ul>
        <a class="landing-button landing-button-primary" href="#checkout">Obtener mi AquaControl</a>
      </article>
    </div>
  </div>
</section>
