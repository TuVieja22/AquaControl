<?php
$productConfig = $purchase['product'] ?? [];
$paymentConfig = $purchase['payment'] ?? [];
$unitPrice = $productConfig['unitPrice'] ?? null;
$priceAvailable = is_numeric($unitPrice) && (float) $unitPrice > 0;
$purchasePayload = json_encode([
    'product' => [
        'sku'          => $productConfig['sku'] ?? 'aquacontrol',
        'name'         => $productConfig['name'] ?? 'AquaControl',
        'unitPrice'    => $priceAvailable ? (float) $unitPrice : null,
        'currency'     => $productConfig['currency'] ?? 'ARS',
        'maxQuantity'  => $productConfig['maxQuantity'] ?? 6,
    ],
    'payment' => [
        'locale'                   => $paymentConfig['locale'] ?? 'es-AR',
        'checkoutOrderUrl'         => $paymentConfig['checkoutOrderUrl'] ?? '',
        'mercadoPagoPublicKey'     => $paymentConfig['mercadoPagoPublicKey'] ?? '',
        'mercadoPagoPreferenceUrl' => $paymentConfig['mercadoPagoPreferenceUrl'] ?? '',
        'paypalClientId'           => $paymentConfig['paypalClientId'] ?? '',
        'paypalCurrency'           => $paymentConfig['paypalCurrency'] ?? ($productConfig['currency'] ?? 'ARS'),
        'paypalCreateOrderUrl'     => $paymentConfig['paypalCreateOrderUrl'] ?? '',
        'paypalCaptureOrderUrl'    => $paymentConfig['paypalCaptureOrderUrl'] ?? '',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<?= view('layouts/header') ?>

<main>
  <section class="hero">
    <div class="container">
      <div class="hero-badge">
        <span class="dot"></span>
        ESP32 · IoT · Tiempo real
      </div>

      <h1>
        Control total de<br>
        tu <span class="accent">ecosistema acuatico</span>
      </h1>

      <p>
        Monitorea temperatura, pH y nivel de agua desde cualquier lugar.
        Tu pecera siempre en condiciones optimas sin intervencion humana constante.
      </p>

      <div class="hero-cta">
        <?php if (session()->get('user_id')): ?>
          <a href="<?= base_url('dashboard') ?>" class="btn btn-primary btn-lg">Ir a mi pecera →</a>
        <?php else: ?>
          <a href="<?= base_url('auth/register') ?>" class="btn btn-primary btn-lg">Comenzar gratis →</a>
          <a href="<?= base_url('auth/login') ?>" class="btn btn-outline btn-lg">Iniciar sesion</a>
        <?php endif; ?>
      </div>

      <div class="stats-row">
        <div class="stat-item">
          <span class="stat-num">7</span>
          <span class="stat-label">Sensores</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">24/7</span>
          <span class="stat-label">Monitoreo</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">3</span>
          <span class="stat-label">Niveles de alerta</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">IoT</span>
          <span class="stat-label">Tiempo real</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">MVC</span>
          <span class="stat-label">Arquitectura</span>
        </div>
      </div>
    </div>
  </section>

  <section class="features">
    <div class="container">
      <p class="section-tag">¿Que hace AquaControl?</p>
      <h2 class="section-title">Todo lo que necesita<br>tu pecera, automatizado</h2>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">🌡️</div>
          <h3>Control de temperatura</h3>
          <p>Sensor DS18B20 sumergible conectado al ESP32. El calefactor se activa automaticamente cuando la temperatura baja del rango optimo.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🧪</div>
          <h3>Calidad del agua</h3>
          <p>Monitoreo continuo de pH. El sistema detecta contaminacion y recomienda cambio de agua antes de que afecte a los peces.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🍽️</div>
          <h3>Alimentacion inteligente</h3>
          <p>Servo SG90 controlado con horarios personalizables. Ajusta la cantidad de alimento segun historial para evitar sobrealimentacion.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">💧</div>
          <h3>Sensor de nivel</h3>
          <p>Detector de nivel tipo flotador que previene daños al calefactor cuando el agua esta baja. Alerta inmediata al usuario.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>Estadisticas e historial</h3>
          <p>Graficas de temperatura, registros de alimentacion y estado del agua almacenados en base de datos para analisis y seguimiento.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🏖️</div>
          <h3>Modo vacaciones</h3>
          <p>Activa el modo autonomo y el sistema mantiene todas las condiciones sin que necesites estar presente. Ideal para viajes.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">📡</div>
          <h3>Conectividad WiFi</h3>
          <p>El ESP32 envia datos en tiempo real via HTTP o MQTT. Arquitectura cliente servidor con actualizaciones cada pocos segundos.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3>Seguridad integrada</h3>
          <p>Limite maximo de temperatura programado, modo automatico si falla la conexion y proteccion ante sensores defectuosos.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="how">
    <div class="container">
      <p class="section-tag">Funcionamiento</p>
      <h2 class="section-title" style="margin-bottom: 48px;">Del sensor a tu pantalla<br>en segundos</h2>

      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <h4>Sensores miden</h4>
          <p>Temperatura, pH, turbidez y nivel son capturados continuamente.</p>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <h4>ESP32 procesa</h4>
          <p>El microcontrolador analiza los datos y decide que actuadores activar.</p>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <h4>Envio WiFi</h4>
          <p>Los datos se transmiten al servidor en tiempo real.</p>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <h4>Backend guarda</h4>
          <p>CodeIgniter 4 recibe y almacena todo para analisis y seguimiento.</p>
        </div>
        <div class="step">
          <div class="step-num">5</div>
          <h4>Tu controlas</h4>
          <p>Desde el dashboard web monitoreas, ajustas y recibes alertas.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="alert-section">
    <div class="container">
      <p class="section-tag">Sistema de alertas</p>
      <h2 class="section-title" style="margin-bottom: 32px;">Tres niveles de respuesta<br>inteligente</h2>

      <div class="alert-levels">
        <div class="alert-card l1">
          <p class="alert-label">Nivel 1 - Aviso</p>
          <h4>Notificacion web</h4>
          <p>Un parametro salio del rango optimo. Se muestra en el dashboard para revision.</p>
        </div>
        <div class="alert-card l2">
          <p class="alert-label">Nivel 2 - Alerta</p>
          <h4>Notificacion push</h4>
          <p>Condicion que requiere atencion. Se envia notificacion al dispositivo del usuario.</p>
        </div>
        <div class="alert-card l3">
          <p class="alert-label">Nivel 3 - Critico</p>
          <h4>Alerta critica</h4>
          <p>Situacion de riesgo para los peces. Actuadores se activan automaticamente y se dispara alerta urgente.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Seccion de compra preparada para integraciones futuras de checkout -->
  <section class="purchase-section" id="comprar">
    <div class="container">
      <div class="purchase-layout">
        <div class="purchase-copy">
          <p class="section-tag">Compra</p>
          <h2 class="section-title purchase-title">Lleva <?= esc($productConfig['name'] ?? 'AquaControl') ?><br>desde el sitio oficial</h2>
          <p class="purchase-lead"><?= esc($productConfig['headline'] ?? '') ?></p>
          <p class="purchase-description"><?= esc($productConfig['description'] ?? '') ?></p>

          <div class="purchase-highlights">
            <article class="purchase-highlight glass-card">
              <span class="purchase-highlight-kicker">Checkout</span>
              <strong>Mercado Pago o PayPal</strong>
              <p>El usuario elige su medio y continua con un flujo claro y listo para produccion.</p>
            </article>
            <article class="purchase-highlight glass-card">
              <span class="purchase-highlight-kicker">Resumen</span>
              <strong>Pedido claro antes de pagar</strong>
              <p>Cantidad, importe total y datos de contacto visibles antes de disparar el checkout.</p>
            </article>
            <article class="purchase-highlight glass-card">
              <span class="purchase-highlight-kicker">Integracion</span>
              <strong>Backend friendly</strong>
              <p>La estructura queda preparada para conectar APIs, ordenes y captura de pago sin rehacer la UI.</p>
            </article>
          </div>
        </div>

        <div class="purchase-panel glass-card">
          <div class="purchase-panel-head">
            <div>
              <p class="purchase-panel-tag">Pedido</p>
              <h3>Configura tu compra</h3>
            </div>
            <span class="purchase-status">Compra segura</span>
          </div>

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
                  <small>Abre el checkout oficial desde el SDK con preferencia generada en backend.</small>
                </span>
              </label>

              <label class="purchase-method-card">
                <input type="radio" name="payment_method" value="paypal">
                <span class="purchase-method-copy">
                  <strong>PayPal Checkout</strong>
                  <small>Renderiza botones oficiales y permite crear o capturar la orden desde tu API.</small>
                </span>
              </label>
            </fieldset>

            <div class="purchase-summary" aria-live="polite">
              <div class="purchase-summary-head">
                <h4>Resumen del pedido</h4>
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
                    <?= $priceAvailable ? esc(($productConfig['currency'] ?? 'ARS') . ' ' . number_format((float) $unitPrice, 2, ',', '.')) : 'Disponible al habilitar el checkout' ?>
                  </dd>
                </div>
                <div>
                  <dt>Cantidad</dt>
                  <dd id="summaryQuantity">1</dd>
                </div>
                <div class="purchase-summary-total-row">
                  <dt>Total</dt>
                  <dd id="summaryTotal">
                    <?= $priceAvailable ? esc(($productConfig['currency'] ?? 'ARS') . ' ' . number_format((float) $unitPrice, 2, ',', '.')) : 'Se calcula al confirmar el pago' ?>
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
                <span class="purchase-submit-label" data-purchase-submit-label>Pagar ahora</span>
              </button>
            </div>

            <!-- Los contenedores quedan listos para que los SDK oficiales monten el checkout -->
            <div class="purchase-sdk-area">
              <div id="mercadoPagoContainer" class="purchase-sdk-panel" hidden></div>
              <div id="paypalContainer" class="purchase-sdk-panel" hidden></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  
        <center>
        <p style="color:var(--text-muted); margin-bottom:32px; font-size:1rem;">
          Crea tu cuenta gratis y conecta tu ESP32 en minutos.
        </p>
        </center>
       

</main>

<script id="purchase-data" type="application/json"><?= $purchasePayload ?></script>

<?= view('layouts/footer') ?>
