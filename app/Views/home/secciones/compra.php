<?php // "Compra segura": formulario del pedido. purchase.js calcula el total y abre Mercado Pago. ?>
<section class="landing-section checkout-section" id="checkout">
  <div class="container">
    <div class="checkout-layout">
      <div class="checkout-copy" data-reveal>
        <p class="eyebrow">Compra segura</p>
        <h2>Configura tu pedido y deja listo el checkout.</h2>
        <p><?= esc($producto['descripcion']) ?></p>
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
            <h3><?= esc($producto['nombre']) ?></h3>
          </div>
          <span class="purchase-status">Compra segura</span>
        </div>

        <?= view('components/flash_messages') ?>

        <form id="purchaseForm" class="purchase-form" data-skip-loader="true" novalidate>
          <input type="hidden" name="product_sku" value="<?= esc($producto['sku']) ?>">

          <div class="purchase-field-grid">
            <div class="purchase-field">
              <label class="form-label" for="purchaseEmail">Email de contacto</label>
              <input class="form-control" id="purchaseEmail" name="email" type="email" inputmode="email"
                autocomplete="email" placeholder="tu@correo.com" required>
            </div>

            <div class="purchase-field">
              <label class="form-label" for="purchaseQuantity">Cantidad</label>
              <div class="purchase-quantity">
                <button type="button" class="purchase-qty-btn" data-quantity-action="decrement" aria-label="Restar una unidad">-</button>
                <input class="form-control purchase-qty-input" id="purchaseQuantity" name="quantity" type="number"
                  min="1" max="<?= esc((string) $producto['cantidadMaxima']) ?>" step="1" value="1" required>
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
              <span class="purchase-summary-note"><?= esc($producto['mensajeEntrega']) ?></span>
            </div>

            <dl class="purchase-summary-list">
              <div>
                <dt>Producto</dt>
                <dd id="summaryProduct"><?= esc($producto['nombre']) ?></dd>
              </div>
              <div>
                <dt>Precio unitario</dt>
                <dd id="summaryUnitPrice"><?= esc($producto['precioResumen'] ?? 'Disponible al habilitar el checkout') ?></dd>
              </div>
              <div>
                <dt>Cantidad</dt>
                <dd id="summaryQuantity">1</dd>
              </div>
              <div class="purchase-summary-total-row">
                <dt>Total</dt>
                <dd id="summaryTotal"><?= esc($producto['precioResumen'] ?? 'Se calcula al confirmar el pago') ?></dd>
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
