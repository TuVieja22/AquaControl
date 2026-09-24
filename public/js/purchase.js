/* Purchase checkout
   Keeps the product section isolated and ready for real payment APIs.
*/

'use strict';

(function () {
  const configNode = document.getElementById('purchase-data');
  const form = document.getElementById('purchaseForm');

  if (!configNode || !form) {
    return;
  }

  const config = JSON.parse(configNode.textContent || '{}');
  const quantityInput = document.getElementById('purchaseQuantity');
  const emailInput = document.getElementById('purchaseEmail');
  const termsInput = document.getElementById('purchaseTerms');
  const summaryQuantity = document.getElementById('summaryQuantity');
  const summaryUnitPrice = document.getElementById('summaryUnitPrice');
  const summaryTotal = document.getElementById('summaryTotal');
  const feedback = document.getElementById('purchaseFeedback');
  const submitButton = document.getElementById('purchaseSubmit');
  const submitButtonLabel = form.querySelector('[data-purchase-submit-label]');
  const defaultSubmitLabel = submitButtonLabel?.textContent.trim() || 'Pagar ahora';
  const mercadoPagoContainer = document.getElementById('mercadoPagoContainer');

  const state = {
    mercadoPagoController: null,
    currentMethod: 'mercadopago'
  };

  function getLocale() {
    return config.payment?.locale || 'es-AR';
  }

  function getCurrency() {
    return config.product?.currency || 'ARS';
  }

  function getMaxQuantity() {
    return Number(config.product?.maxQuantity || 6);
  }

  function getUnitPrice() {
    const price = Number(config.product?.unitPrice);
    return Number.isFinite(price) && price > 0 ? price : null;
  }

  function formatPrice(amount) {
    if (!Number.isFinite(amount)) {
      return 'Se calcula al confirmar el pago';
    }

    try {
      return new Intl.NumberFormat(getLocale(), {
        style: 'currency',
        currency: getCurrency(),
        maximumFractionDigits: 2
      }).format(amount);
    } catch (error) {
      return `${getCurrency()} ${amount.toFixed(2)}`;
    }
  }

  function sanitizeQuantity(rawValue) {
    const parsed = Number.parseInt(rawValue, 10);
    if (!Number.isFinite(parsed)) {
      return 1;
    }

    return Math.min(Math.max(parsed, 1), getMaxQuantity());
  }

  function getSelectedMethod() {
    return form.querySelector('input[name="payment_method"]:checked')?.value || 'mercadopago';
  }

  function syncSelectedMethodUi() {
    form.querySelectorAll('.purchase-method-card').forEach(card => {
      const radio = card.querySelector('input[name="payment_method"]');
      card.classList.toggle('is-selected', Boolean(radio?.checked));
    });
  }

  function buildOrderPayload() {
    const quantity = sanitizeQuantity(quantityInput.value);
    const unitPrice = getUnitPrice();

    return {
      product: {
        sku: config.product?.sku || 'aquacontrol',
        name: config.product?.name || 'AquaControl',
        quantity,
        unitPrice,
        currency: getCurrency()
      },
      customer: {
        email: emailInput.value.trim()
      },
      paymentMethod: getSelectedMethod()
    };
  }

  function updateSummary() {
    const quantity = sanitizeQuantity(quantityInput.value);
    const unitPrice = getUnitPrice();

    quantityInput.value = String(quantity);
    summaryQuantity.textContent = String(quantity);
    summaryUnitPrice.textContent = unitPrice === null ? 'Disponible al habilitar el checkout' : formatPrice(unitPrice);
    summaryTotal.textContent = unitPrice === null ? 'Se calcula al confirmar el pago' : formatPrice(unitPrice * quantity);
  }

  function setFeedback(message, type) {
    feedback.className = 'purchase-feedback is-visible';
    feedback.textContent = message;

    if (type) {
      feedback.classList.add(`is-${type}`);
    }
  }

  function clearFeedback() {
    feedback.className = 'purchase-feedback';
    feedback.textContent = '';
  }

  function validateForm() {
    const quantity = sanitizeQuantity(quantityInput.value);
    const email = emailInput.value.trim();
    const method = getSelectedMethod();
    const issues = [];

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      issues.push('Ingresa un email valido para recibir la confirmacion del pedido.');
    }

    if (quantity < 1 || quantity > getMaxQuantity()) {
      issues.push(`La cantidad debe estar entre 1 y ${getMaxQuantity()}.`);
    }

    if (!termsInput.checked) {
      issues.push('Debes aceptar la continuacion al checkout seguro.');
    }

    if (method !== 'mercadopago') {
      issues.push('Selecciona un metodo de pago disponible.');
    }

    if (issues.length > 0) {
      setFeedback(issues[0], 'error');
      return false;
    }

    return true;
  }

  function setSubmitting(isSubmitting) {
    submitButton.disabled = isSubmitting;
    if (submitButtonLabel) {
      submitButtonLabel.textContent = isSubmitting ? 'Cargando' : defaultSubmitLabel;
    }
  }

  async function ensureScript(src, globalName) {
    if (globalName && window[globalName]) {
      return;
    }

    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      await new Promise((resolve, reject) => {
        if (globalName && window[globalName]) {
          resolve();
          return;
        }

        existing.addEventListener('load', resolve, { once: true });
        existing.addEventListener('error', reject, { once: true });
      });
      return;
    }

    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.body.appendChild(script);
    });
  }

  function csrfHeaders(headers) {
    return window.AquaCsrf ? window.AquaCsrf.headers(headers) : headers;
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfHeaders({
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }),
      body: JSON.stringify(payload)
    });
    window.AquaCsrf?.refresh(response);

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || 'No se pudo completar la solicitud.');
    }

    return data;
  }

  async function resetPaymentMounts() {
    mercadoPagoContainer.hidden = true;
    mercadoPagoContainer.classList.remove('is-loading');

    if (state.mercadoPagoController && typeof state.mercadoPagoController.unmount === 'function') {
      await state.mercadoPagoController.unmount();
    }

    state.mercadoPagoController = null;
  }

  async function mountMercadoPago(orderPayload) {
    const publicKey = config.payment?.mercadoPagoPublicKey || '';
    const preferenceUrl = config.payment?.mercadoPagoPreferenceUrl || '';

    if (!publicKey || !preferenceUrl) {
      setFeedback('Configura la clave publica y el endpoint de preferencias de Mercado Pago para habilitar este checkout.', 'info');
      return;
    }

    await resetPaymentMounts();
    mercadoPagoContainer.hidden = false;
    mercadoPagoContainer.classList.add('is-loading');
    mercadoPagoContainer.innerHTML = '';

    await ensureScript('https://sdk.mercadopago.com/js/v2', 'MercadoPago');
    const preferenceResponse = await postJson(preferenceUrl, orderPayload);
    const preferenceId = preferenceResponse.preferenceId || preferenceResponse.preference_id || preferenceResponse.id;

    if (!preferenceId) {
      throw new Error('El backend debe devolver un preferenceId valido para Mercado Pago.');
    }

    mercadoPagoContainer.innerHTML = '<div id="mercadoPagoBrickRoot"></div>';

    const mercadoPago = new window.MercadoPago(publicKey, {
      locale: getLocale()
    });

    const bricksBuilder = mercadoPago.bricks();
    state.mercadoPagoController = await bricksBuilder.create('wallet', 'mercadoPagoBrickRoot', {
      initialization: {
        preferenceId,
        redirectMode: 'self'
      },
      customization: {
        texts: {
          valueProp: 'practicality'
        }
      },
      callbacks: {
        onReady: function () {
          mercadoPagoContainer.classList.remove('is-loading');
        },
        onError: function () {
          setFeedback('Mercado Pago no pudo cargar el checkout. Revisa la configuracion del SDK y la preferencia.', 'error');
        }
      }
    });

    setFeedback('Mercado Pago listo. Puedes continuar desde el boton oficial del checkout.', 'success');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    clearFeedback();
    updateSummary();

    if (!validateForm()) {
      return;
    }

    setSubmitting(true);

    try {
      await mountMercadoPago(buildOrderPayload());
    } catch (error) {
      setFeedback(error.message || 'No fue posible preparar el checkout seleccionado.', 'error');
    } finally {
      setSubmitting(false);
    }
  }

  document.querySelectorAll('[data-quantity-action]').forEach(button => {
    button.addEventListener('click', function () {
      const quantity = sanitizeQuantity(quantityInput.value);
      const nextValue = this.dataset.quantityAction === 'increment' ? quantity + 1 : quantity - 1;

      quantityInput.value = String(sanitizeQuantity(nextValue));
      updateSummary();
      clearFeedback();
    });
  });

  quantityInput.addEventListener('input', function () {
    updateSummary();
    clearFeedback();
  });

  form.querySelectorAll('input[name="payment_method"]').forEach(input => {
    input.addEventListener('change', function () {
      state.currentMethod = this.value;
      syncSelectedMethodUi();
      clearFeedback();
      resetPaymentMounts().catch(function () {
        return;
      });
    });
  });

  form.addEventListener('submit', handleSubmit);
  syncSelectedMethodUi();
  updateSummary();
}());
