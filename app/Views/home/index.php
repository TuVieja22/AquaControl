<?php
$productConfig = $purchase['product'] ?? [];
$paymentConfig = $purchase['payment'] ?? [];
$unitPrice = $productConfig['unitPrice'] ?? null;
$priceAvailable = is_numeric($unitPrice) && (float) $unitPrice > 0;
$currency = $productConfig['currency'] ?? 'ARS';
$formattedPrice = $priceAvailable
    ? $currency . ' ' . number_format((float) $unitPrice, 0, ',', '.')
    : 'Consultar';
$purchasePayload = json_encode([
    'product' => [
        'sku'          => $productConfig['sku'] ?? 'aquacontrol',
        'name'         => $productConfig['name'] ?? 'AquaControl',
        'unitPrice'    => $priceAvailable ? (float) $unitPrice : null,
        'currency'     => $currency,
        'maxQuantity'  => $productConfig['maxQuantity'] ?? 6,
    ],
    'payment' => [
        'locale'                   => $paymentConfig['locale'] ?? 'es-AR',
        'checkoutOrderUrl'         => $paymentConfig['checkoutOrderUrl'] ?? '',
        'mercadoPagoPublicKey'     => $paymentConfig['mercadoPagoPublicKey'] ?? '',
        'mercadoPagoPreferenceUrl' => $paymentConfig['mercadoPagoPreferenceUrl'] ?? '',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$productImage = base_url('img/aquacontrol-product.png');
$isAuthenticated = (bool) session()->get('user_id');

$galleryItems = [
    [
        'class' => 'gallery-product',
        'label' => 'Producto completo',
        'title' => 'Kit AquaControl IoT',
        'copy' => 'Modulo central, sensores y alimentador en una pieza visualmente integrada.',
        'type' => 'image',
        'image' => $productImage,
    ],
    [
        'class' => 'gallery-sensors',
        'label' => 'Sensores',
        'title' => 'Lecturas precisas',
        'copy' => 'pH, temperatura y nivel conectados al ecosistema en tiempo real.',
        'type' => 'image',
        'image' => $productImage,
    ],
    [
        'class' => 'gallery-dashboard',
        'label' => 'Dashboard web',
        'title' => 'Control remoto',
        'copy' => 'Metricas claras para actuar antes de que aparezca un problema.',
        'type' => 'dashboard',
    ],
    [
        'class' => 'gallery-aquarium',
        'label' => 'Acuario saludable',
        'title' => 'Ecosistema estable',
        'copy' => 'Condiciones constantes para viajar, trabajar o descansar sin incertidumbre.',
        'type' => 'aquarium',
    ],
];

$features = [
    ['icon' => 'pulse', 'title' => 'Monitoreo en Tiempo Real', 'copy' => 'Temperatura, pH, nivel y actividad del sistema actualizados desde tu panel web.'],
    ['icon' => 'feed', 'title' => 'Alimentaci&oacute;n Autom&aacute;tica', 'copy' => 'Programa horarios y evita excesos con rutinas ajustadas al comportamiento del acuario.'],
    ['icon' => 'alert', 'title' => 'Alertas Inteligentes', 'copy' => 'Recibi avisos claros cuando una variable sale del rango saludable.'],
    ['icon' => 'away', 'title' => 'Modo Ausencia', 'copy' => 'Activa rutinas autonomas para mantener el acuario estable mientras no estas.'],
    ['icon' => 'energy', 'title' => 'Bajo Consumo Energ&eacute;tico', 'copy' => 'Arquitectura eficiente sobre ESP32, sensores y actuadores optimizados.'],
];

$benefits = [
    ['title' => 'Evit&aacute; la muerte de tus peces', 'copy' => 'Detecta cambios criticos antes de que el agua afecte la salud del ecosistema.'],
    ['title' => 'Viaj&aacute; tranquilo', 'copy' => 'AquaControl mantiene rutinas y te avisa si necesita tu atencion.'],
    ['title' => 'Ahorra tiempo y dinero', 'copy' => 'Menos controles manuales, menos emergencias y decisiones con datos.'],
    ['title' => 'Control total desde cualquier lugar', 'copy' => 'Consulta el estado del acuario desde una interfaz web clara y responsive.'],
];

$comparisonRows = [
    ['label' => 'Monitoreo inteligente', 'aquacontrol' => true, 'others' => false],
    ['label' => 'Alertas autom&aacute;ticas', 'aquacontrol' => true, 'others' => false],
    ['label' => 'F&aacute;cil instalaci&oacute;n', 'aquacontrol' => true, 'others' => true],
    ['label' => 'Soporte local', 'aquacontrol' => true, 'others' => false],
    ['label' => 'Plataforma web moderna', 'aquacontrol' => true, 'others' => false],
];

$testimonials = [
    ['name' => 'Martina Silva', 'role' => 'Acuario plantado, CABA', 'quote' => 'Ahora puedo viajar sin preocuparme por mi acuario.', 'avatar' => 'MS'],
    ['name' => 'Nicolas Rivas', 'role' => 'Acuarista principiante', 'quote' => 'El sistema me alerto antes de que el agua se contaminara.', 'avatar' => 'NR'],
    ['name' => 'Laura Medina', 'role' => 'Criadora de bettas', 'quote' => 'La vista del panel es clara, rapida y me evita revisar sensores a mano.', 'avatar' => 'LM'],
];
?>
<?= view('layouts/header') ?>

<main class="landing-page">
  <section class="landing-hero" style="--hero-image: url('<?= esc($productImage) ?>');">
    <div class="hero-content container">
      <p class="eyebrow hero-eyebrow" data-reveal> Sistema Inteligente AquaControl IoT</p>
      <h1 data-reveal>Mant&eacute;n tu acuario bajo control, aunque no est&eacute;s en casa</h1>
      <p class="hero-subtitle" data-reveal>
        Monitoreo inteligente, alimentacion automatica y alertas en tiempo real para cuidar el ecosistema de tu pecera desde cualquier lugar.
      </p>
      <div class="hero-actions" data-reveal>
        <a class="landing-button landing-button-primary" href="#demo">Ver Demo</a>
        <a class="landing-button landing-button-secondary" href="#planes">Comprar Ahora</a>
      </div>
      <div class="hero-signal" aria-label="Estado del sistema" data-reveal>
        <span class="signal-dot"></span>
        Ecosistema estable - datos en vivo
      </div>
    </div>
  </section>

  <section class="landing-section product-action" id="producto">
    <div class="container">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Producto en acci&oacute;n</p>
        <h2>Hardware, sensores y software trabajando como un solo sistema.</h2>
      </div>

      <div class="product-gallery">
        <?php foreach ($galleryItems as $item): ?>
          <article class="gallery-card <?= esc($item['class']) ?>" data-reveal>
            <?php if ($item['type'] === 'image'): ?>
              <img src="<?= esc($item['image']) ?>" alt="<?= esc($item['title']) ?>" loading="lazy" decoding="async">
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

  <section class="landing-section feature-section" id="caracteristicas">
    <div class="container">
      <div class="section-heading centered" data-reveal>
        <p class="eyebrow">Caracter&iacute;sticas principales</p>
        <h2>Tecnologia invisible, cuidado constante.</h2>
      </div>

      <div class="feature-grid">
        <?php foreach ($features as $feature): ?>
          <article class="premium-card feature-tile" data-reveal>
            <span class="icon-badge icon-<?= esc($feature['icon']) ?>" aria-hidden="true"></span>
            <h3><?= $feature['title'] ?></h3>
            <p><?= $feature['copy'] ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="landing-section process-section" id="funciona">
    <div class="container">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">C&oacute;mo funciona</p>
        <h2>Instalas, conectas y controlas todo desde tu celular.</h2>
      </div>

      <div class="process-track">
        <article class="process-step" data-reveal>
          <div class="step-visual sensor-visual" aria-hidden="true"></div>
          <span>01</span>
          <h3>Instal&aacute;s los sensores</h3>
          <p>Coloca las sondas de pH, temperatura y nivel en puntos clave del acuario.</p>
        </article>
        <article class="process-step" data-reveal>
          <div class="step-visual device-visual" aria-hidden="true"></div>
          <span>02</span>
          <h3>Conect&aacute;s el dispositivo</h3>
          <p>El modulo central recibe datos, procesa eventos y activa rutinas automaticas.</p>
        </article>
        <article class="process-step" data-reveal>
          <div class="step-visual phone-visual" aria-hidden="true"></div>
          <span>03</span>
          <h3>Control&aacute;s todo desde tu celular</h3>
          <p>Revisa metricas, alertas y estados desde una experiencia web moderna.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="landing-section benefits-section">
    <div class="container">
      <div class="section-heading centered" data-reveal>
        <p class="eyebrow">Beneficios reales</p>
        <h2>Mas tranquilidad para vos. Mas estabilidad para tu acuario.</h2>
      </div>

      <div class="benefit-grid">
        <?php foreach ($benefits as $benefit): ?>
          <article class="premium-card benefit-card" data-reveal>
            <span class="benefit-mark" aria-hidden="true"></span>
            <h3><?= $benefit['title'] ?></h3>
            <p><?= $benefit['copy'] ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="landing-section dashboard-preview-section" id="demo">
    <div class="container">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Dashboard preview</p>
        <h2>Una cabina de control futurista para tu ecosistema.</h2>
      </div>

      <div class="dashboard-preview" data-dashboard-demo data-reveal>
        <div class="dashboard-preview-header">
          <div>
            <span class="dashboard-chip"><span class="signal-dot"></span> En vivo</span>
            <h3>Acuario principal</h3>
          </div>
          <p>Ultima sincronizacion <strong data-live-sync>hace 4 s</strong></p>
        </div>

        <div class="dashboard-preview-grid">
          <div class="live-stat">
            <span>Temperatura</span>
            <strong data-live-temperature>25.6&deg;C</strong>
            <small>Rango ideal 24-27&deg;C</small>
          </div>
          <div class="live-stat">
            <span>pH</span>
            <strong data-live-ph>6.84</strong>
            <small>Agua estable</small>
          </div>
          <div class="live-stat">
            <span>Alertas</span>
            <strong data-live-alerts>0</strong>
            <small>Sin eventos criticos</small>
          </div>
          <div class="ecosystem-state">
            <span>Estado del ecosistema</span>
            <strong data-live-health>98%</strong>
            <div class="health-ring" aria-hidden="true"><span></span></div>
          </div>
        </div>

        <div class="dashboard-chart-area">
          <div class="chart-panel">
            <div class="chart-head">
              <span>Temperatura / pH</span>
              <strong>24 h</strong>
            </div>
            <svg class="preview-chart" viewBox="0 0 640 240" aria-hidden="true">
              <defs>
                <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#00d4ff" stop-opacity="0.32"/>
                  <stop offset="100%" stop-color="#00d4ff" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <path class="chart-area-fill" d="M20 172 C95 126 126 146 178 108 C245 58 292 120 350 86 C424 42 485 72 620 36 L620 220 L20 220 Z"/>
              <path class="chart-line-main" d="M20 172 C95 126 126 146 178 108 C245 58 292 120 350 86 C424 42 485 72 620 36"/>
              <path class="chart-line-secondary" d="M20 126 C92 146 128 98 194 134 C272 176 314 88 386 118 C462 148 524 104 620 132"/>
            </svg>
          </div>
          <div class="alert-stack">
            <article>
              <span class="status-ok"></span>
              <div>
                <strong>Agua clara</strong>
                <small>Turbidez dentro del rango</small>
              </div>
            </article>
            <article>
              <span class="status-info"></span>
              <div>
                <strong>Proxima alimentacion</strong>
                <small>Hoy 20:30</small>
              </div>
            </article>
            <article>
              <span class="status-ok"></span>
              <div>
                <strong>Modo Ausencia listo</strong>
                <small>Rutinas automatizadas</small>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="landing-section comparison-section">
    <div class="container">
      <div class="section-heading centered" data-reveal>
        <p class="eyebrow">Por qu&eacute; elegir AquaControl</p>
        <h2>Una plataforma pensada para acuaristas reales.</h2>
      </div>

      <div class="comparison-table" data-reveal>
        <div class="comparison-row comparison-head">
          <span>Ventaja</span>
          <strong>AquaControl</strong>
          <span>Otros sistemas</span>
        </div>
        <?php foreach ($comparisonRows as $row): ?>
          <div class="comparison-row">
            <span><?= $row['label'] ?></span>
            <strong class="check-cell"><?= $row['aquacontrol'] ? '&#10003;' : '-' ?></strong>
            <span class="<?= $row['others'] ? 'check-muted' : 'empty-muted' ?>"><?= $row['others'] ? '&#10003;' : '-' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="landing-section testimonial-section">
    <div class="container">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Testimonios</p>
        <h2>Usuarios que dejaron de adivinar y empezaron a medir.</h2>
      </div>

      <div class="testimonial-shell" data-testimonial-carousel data-reveal>
        <div class="testimonial-track">
          <?php foreach ($testimonials as $testimonial): ?>
            <article class="testimonial-card">
              <div class="avatar-photo" aria-hidden="true"><?= esc($testimonial['avatar']) ?></div>
              <blockquote>&ldquo;<?= $testimonial['quote'] ?>&rdquo;</blockquote>
              <strong><?= esc($testimonial['name']) ?></strong>
              <span><?= esc($testimonial['role']) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="testimonial-controls" aria-label="Controles de testimonios">
          <button type="button" data-testimonial-prev aria-label="Testimonio anterior">&lsaquo;</button>
          <button type="button" data-testimonial-next aria-label="Testimonio siguiente">&rsaquo;</button>
        </div>
      </div>
    </div>
  </section>

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
          <a class="landing-button landing-button-secondary" href="<?= $isAuthenticated ? base_url('dashboard') : base_url('auth/register') ?>">Quiero Proteger Mi Acuario</a>
        </article>

        <article class="price-card highlighted" data-reveal>
          <span>Pro</span>
          <h3>Kit AquaControl IoT</h3>
          <p class="price-value"><?= esc($formattedPrice) ?></p>
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

  <section class="landing-section checkout-section" id="checkout">
    <div class="container">
      <div class="checkout-layout">
        <div class="checkout-copy" data-reveal>
          <p class="eyebrow">Compra segura</p>
          <h2>Configura tu pedido y deja listo el checkout.</h2>
          <p><?= esc($productConfig['description'] ?? 'Eleg&iacute; la cantidad, revisa tu pedido y continua con el medio de pago que prefieras.') ?></p>
          <div class="trust-row">
            <span>Desarrollado en Argentina</span>
            <span>F&aacute;cil de instalar</span>
            <span>Soporte t&eacute;cnico local</span>
          </div>
        </div>

        <div class="purchase-panel checkout-console" data-reveal>
          <div class="purchase-panel-head">
            <div>
              <p class="purchase-panel-tag">Pedido</p>
              <h3><?= esc($productConfig['name'] ?? 'AquaControl') ?></h3>
            </div>
            <span class="purchase-status">Compra segura</span>
          </div>

          <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

          <form id="purchaseForm" class="purchase-form" data-skip-loader="true" novalidate>
            <input type="hidden" name="product_sku" value="<?= esc($productConfig['sku'] ?? 'aquacontrol') ?>">

            <div class="purchase-field-grid">
              <div class="purchase-field">
                <label class="form-label" for="purchaseEmail">Email de contacto</label>
                <input
                  class="form-control"
                  id="purchaseEmail"
                  name="email"
                  type="email"
                  inputmode="email"
                  autocomplete="email"
                  placeholder="tu@correo.com"
                  required
                >
              </div>

              <div class="purchase-field">
                <label class="form-label" for="purchaseQuantity">Cantidad</label>
                <div class="purchase-quantity">
                  <button type="button" class="purchase-qty-btn" data-quantity-action="decrement" aria-label="Restar una unidad">-</button>
                  <input
                    class="form-control purchase-qty-input"
                    id="purchaseQuantity"
                    name="quantity"
                    type="number"
                    min="1"
                    max="<?= esc((string) ($productConfig['maxQuantity'] ?? 6)) ?>"
                    step="1"
                    value="1"
                    required
                  >
                  <button type="button" class="purchase-qty-btn" data-quantity-action="increment" aria-label="Sumar una unidad">+</button>
                </div>
              </div>
            </div>

            <fieldset class="purchase-methods">
              <legend class="form-label">Metodo de pago</legend>

              <label class="purchase-method-card">
                <input type="radio" name="payment_method" value="mercadopago" checked>
                <span class="purchase-method-copy">
                  <strong>Mercado Pago</strong>
                  <small>Checkout oficial con preferencia generada desde el backend.</small>
                </span>
              </label>

            </fieldset>

            <div class="purchase-summary" aria-live="polite">
              <div class="purchase-summary-head">
                <h4>Resumen</h4>
                <span class="purchase-summary-note"><?= esc($productConfig['deliveryMessage'] ?? '') ?></span>
              </div>

              <dl class="purchase-summary-list">
                <div>
                  <dt>Producto</dt>
                  <dd id="summaryProduct"><?= esc($productConfig['name'] ?? 'AquaControl') ?></dd>
                </div>
                <div>
                  <dt>Precio unitario</dt>
                  <dd id="summaryUnitPrice">
                    <?= $priceAvailable ? esc($currency . ' ' . number_format((float) $unitPrice, 2, ',', '.')) : 'Disponible al habilitar el checkout' ?>
                  </dd>
                </div>
                <div>
                  <dt>Cantidad</dt>
                  <dd id="summaryQuantity">1</dd>
                </div>
                <div class="purchase-summary-total-row">
                  <dt>Total</dt>
                  <dd id="summaryTotal">
                    <?= $priceAvailable ? esc($currency . ' ' . number_format((float) $unitPrice, 2, ',', '.')) : 'Se calcula al confirmar el pago' ?>
                  </dd>
                </div>
              </dl>
            </div>

            <label class="form-check purchase-consent">
              <input type="checkbox" id="purchaseTerms" name="terms" value="1" required>
              <span>Acepto continuar con el checkout seguro y recibir confirmaciones del pedido en el email indicado.</span>
            </label>

            <div id="purchaseFeedback" class="purchase-feedback" role="status" aria-live="polite"></div>

            <div class="purchase-actions purchase-payment-action">
              <button type="submit" class="Btn purchase-submit" id="purchaseSubmit">
                <svg class="svgIcon" viewBox="0 0 576 512" aria-hidden="true">
                  <path d="M64 64C28.7 64 0 92.7 0 128V384c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm32 80H480c17.7 0 32 14.3 32 32V208H64V176c0-17.7 14.3-32 32-32zm-32 96H512V336c0 17.7-14.3 32-32 32H96c-17.7 0-32-14.3-32-32V240z"/>
                </svg>
                <span class="purchase-submit-label" data-purchase-submit-label>Obtener mi AquaControl</span>
              </button>
            </div>

            <div class="purchase-sdk-area">
              <div id="mercadoPagoContainer" class="purchase-sdk-panel" hidden></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<script id="purchase-data" type="application/json"><?= $purchasePayload ?></script>

<?= view('layouts/footer') ?>
