/* ===================================================
   AquaControl — funciones/purchase.js
   Formulario de compra de la portada: calcula el total segun la cantidad y, al
   confirmar, pide al servidor la "preferencia" de Mercado Pago y muestra su boton.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  // Datos del producto (script purchase-data) y formulario; si faltan, no hay nada que hacer.
  const configNode = document.getElementById('purchase-data');
  const form = document.getElementById('purchaseForm');
  if (!configNode || !form) return;

  // Producto, formato de numeros, moneda, cantidad maxima y precio.
  const config = JSON.parse(configNode.textContent || '{}');
  const product = config.product || {};
  const payment = config.payment || {};
  const locale = payment.locale || 'es-AR';
  const currency = product.currency || 'ARS';
  const maxQuantity = Number(product.maxQuantity || 6);
  const unitPrice = Number(product.unitPrice) > 0 ? Number(product.unitPrice) : null;

  // Elementos del formulario que se usan mas abajo.
  const quantityInput = document.getElementById('purchaseQuantity');
  const emailInput = document.getElementById('purchaseEmail');
  const termsInput = document.getElementById('purchaseTerms');
  const feedback = document.getElementById('purchaseFeedback');
  const submitButton = document.getElementById('purchaseSubmit');
  const submitLabel = form.querySelector('[data-purchase-submit-label]');
  const defaultSubmitLabel = submitLabel?.textContent.trim() || 'Pagar ahora';
  const mercadoPagoContainer = document.getElementById('mercadoPagoContainer');
  // Boton de Mercado Pago ya creado (si lo hay).
  let mercadoPagoBrick = null;

  // Escribe un precio con el formato de la moneda.
  function formatPrice(amount) {
    try {
      return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
    } catch (error) {
      return `${currency} ${amount.toFixed(2)}`;
    }
  }

  // Cantidad elegida, siempre entre 1 y el maximo.
  function cantidad() {
    const value = Number.parseInt(quantityInput.value, 10);
    return Number.isFinite(value) ? Math.min(Math.max(value, 1), maxQuantity) : 1;
  }

  // Actualiza el resumen: cantidad, precio unitario y total.
  function updateSummary() {
    const quantity = cantidad();
    quantityInput.value = String(quantity);
    document.getElementById('summaryQuantity').textContent = String(quantity);
    document.getElementById('summaryUnitPrice').textContent = unitPrice === null ? 'Disponible al habilitar el checkout' : formatPrice(unitPrice);
    document.getElementById('summaryTotal').textContent = unitPrice === null ? 'Se calcula al confirmar el pago' : formatPrice(unitPrice * quantity);
  }

  // Muestra un aviso debajo del formulario (error, info o exito).
  function setFeedback(message, type) {
    feedback.className = `purchase-feedback is-visible is-${type}`;
    feedback.textContent = message;
  }

  // Borra el aviso.
  function clearFeedback() {
    feedback.className = 'purchase-feedback';
    feedback.textContent = '';
  }

  // Revisa el correo y la casilla de aceptacion.
  function validateForm() {
    const email = emailInput.value.trim();

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setFeedback('Ingresa un email valido para recibir la confirmacion del pedido.', 'error');
      return false;
    }

    if (!termsInput.checked) {
      setFeedback('Debes aceptar la continuacion al checkout seguro.', 'error');
      return false;
    }

    return true;
  }

  // Mientras se prepara el pago, apaga el boton y cambia su texto.
  function setSubmitting(isSubmitting) {
    submitButton.disabled = isSubmitting;
    if (submitLabel) submitLabel.textContent = isSubmitting ? 'Cargando' : defaultSubmitLabel;
  }

  // Descarga la libreria de Mercado Pago (una sola vez).
  function loadScript(src) {
    if (window.MercadoPago) return Promise.resolve();

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.body.appendChild(script);
    });
  }

  // Le pide al servidor que cree el cobro y devuelve su identificador.
  async function createPreference() {
    const response = await fetch(payment.mercadoPagoPreferenceUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: window.AquaCsrf.headers({ 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }),
      body: JSON.stringify({
        product: { sku: product.sku, name: product.name, quantity: cantidad(), unitPrice, currency },
        customer: { email: emailInput.value.trim() },
        paymentMethod: 'mercadopago'
      })
    });
    window.AquaCsrf.refresh(response);

    // Si el servidor respondio con un error, se muestra su mensaje.
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'No se pudo completar la solicitud.');
    if (!data.preferenceId) throw new Error('El servidor no devolvio la preferencia de Mercado Pago.');

    return data.preferenceId;
  }

  // Prepara y muestra el boton oficial de Mercado Pago.
  async function mountMercadoPago() {
    // Sin las claves de Mercado Pago no se puede seguir.
    if (!payment.mercadoPagoPublicKey || !payment.mercadoPagoPreferenceUrl) {
      setFeedback('Configura la clave publica y el endpoint de preferencias de Mercado Pago para habilitar este checkout.', 'info');
      return;
    }

    // Quita el boton anterior (si habia) y deja el espacio listo.
    await mercadoPagoBrick?.unmount?.();
    mercadoPagoContainer.hidden = false;
    mercadoPagoContainer.classList.add('is-loading');
    mercadoPagoContainer.innerHTML = '';

    // Al mismo tiempo: crea el cobro y descarga la libreria.
    const [preferenceId] = await Promise.all([createPreference(), loadScript('https://sdk.mercadopago.com/js/v2')]);

    // Crea el boton de pago en su lugar.
    mercadoPagoContainer.innerHTML = '<div id="mercadoPagoBrickRoot"></div>';
    const mercadoPago = new window.MercadoPago(payment.mercadoPagoPublicKey, { locale });

    mercadoPagoBrick = await mercadoPago.bricks().create('wallet', 'mercadoPagoBrickRoot', {
      initialization: { preferenceId, redirectMode: 'self' },
      customization: { texts: { valueProp: 'practicality' } },
      callbacks: {
        onReady: () => mercadoPagoContainer.classList.remove('is-loading'),
        onError: () => setFeedback('Mercado Pago no pudo cargar el checkout. Revisa la configuracion del SDK y la preferencia.', 'error')
      }
    });

    setFeedback('Mercado Pago listo. Puedes continuar desde el boton oficial del checkout.', 'success');
  }

  // Al tocar el boton de comprar: revisa y, si esta todo bien, prepara el pago.
  form.addEventListener('submit', async event => {
    event.preventDefault();
    clearFeedback();
    updateSummary();
    if (!validateForm()) return;

    setSubmitting(true);
    window.AquaLoader?.mostrar();
    try {
      await mountMercadoPago();
    } catch (error) {
      mercadoPagoContainer.hidden = true;
      setFeedback(error.message || 'No fue posible preparar el checkout.', 'error');
    } finally {
      window.AquaLoader?.ocultar();
      setSubmitting(false);
    }
  });

  // Botones de sumar y restar una unidad.
  form.querySelectorAll('[data-quantity-action]').forEach(button => {
    button.addEventListener('click', () => {
      quantityInput.value = String(cantidad() + (button.dataset.quantityAction === 'increment' ? 1 : -1));
      updateSummary();
      clearFeedback();
    });
  });

  // Si se escribe la cantidad a mano, tambien se actualiza el resumen.
  quantityInput.addEventListener('input', () => {
    updateSummary();
    clearFeedback();
  });

  // Marca el medio de pago elegido y calcula el resumen inicial.
  form.querySelector('input[name="payment_method"]')?.closest('.purchase-method-card')?.classList.add('is-selected');
  updateSummary();
}());
