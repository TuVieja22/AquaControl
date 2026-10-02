<?php // "Precio y planes": plan gratis y el kit. El precio sale de commerce.unitPrice (.env). ?>
<section class="landing-section pricing-section" id="planes">
  <div class="container">
    <div class="section-heading centered" data-reveal>
      <p class="eyebrow">Precio y planes</p>
      <h2>Eleg&iacute; el nivel de protecci&oacute;n para tu acuario.</h2>
    </div>

    <div class="pricing-grid">
      <article class="price-card" data-reveal>
        <span>B&aacute;sico</span>
        <h3>Control inicial</h3>
        <p class="price-value">Gratis</p>
        <p>Acceso a cuenta, vista general y configuracion inicial para explorar AquaControl.</p>
        <ul>
          <li>Panel web responsive</li>
          <li>Registro de usuarios</li>
          <li>Base para conectar tu dispositivo</li>
        </ul>
        <a class="landing-button landing-button-secondary" href="<?= base_url(session()->get('user_id') ? 'dashboard' : 'auth/register') ?>">Quiero Proteger Mi Acuario</a>
      </article>

      <article class="price-card highlighted" data-reveal>
        <span>Pro</span>
        <h3>Kit AquaControl IoT</h3>
        <p class="price-value"><?= esc($producto['precioTarjeta']) ?></p>
        <p>Sensores, alimentacion automatica, alertas y monitoreo inteligente listo para produccion.</p>
        <ul>
          <li>Monitoreo de temperatura, pH y nivel</li>
          <li>Alertas inteligentes</li>
          <li>Soporte tecnico local</li>
        </ul>
        <a class="landing-button landing-button-primary" href="#checkout">Obtener mi AquaControl</a>
      </article>
    </div>
  </div>
</section>
