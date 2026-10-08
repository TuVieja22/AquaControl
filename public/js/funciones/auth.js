/* ===================================================
   AquaControl — funciones/auth.js
   Paginas de cuenta (login, registro, recuperar y nueva contrasena): revisa los
   formularios antes de enviarlos, calcula la seguridad de la contrasena y el boton
   para mostrarla. El servidor vuelve a validar todo: esto solo avisa antes.
   Aca no hay colores ni iconos: el aspecto de los errores esta en css/layouts/main.css
   y el del campo de contrasena en css/components/campo_password.css.
   =================================================== */

'use strict';

(function () {
  const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const PASSWORD_MESSAGES = {
    short: 'La contrasena debe tener al menos 8 caracteres.',
    uppercase: 'Debe contener al menos una letra mayuscula.',
    lowercase: 'Debe contener al menos una letra minuscula.',
    number: 'Debe contener al menos un numero.',
    special: 'Debe contener al menos un caracter especial.'
  };

  const strengthLabels = ['', 'Muy debil', 'Debil', 'Aceptable', 'Fuerte', 'Muy fuerte'];

  function getField(form, selector) {
    return form.querySelector(selector);
  }

  function getFeedbackNode(field) {
    return field?.closest('.form-group')?.querySelector('.invalid-feedback') || null;
  }

  // La cajita del error se oculta sola cuando queda vacia (ver .invalid-feedback en el CSS).
  function clearFeedback(node) {
    if (!node) {
      return;
    }

    node.textContent = '';
  }

  function showFieldError(field, message, feedbackNode = null) {
    if (!field) {
      return;
    }

    field.classList.add('is-invalid');

    const feedback = feedbackNode || getFeedbackNode(field);
    if (!feedback) {
      return;
    }

    feedback.textContent = message;
  }

  function clearFieldError(field, feedbackNode = null) {
    if (!field) {
      return;
    }

    field.classList.remove('is-invalid');
    clearFeedback(feedbackNode || getFeedbackNode(field));
  }

  function clearFormErrors(form) {
    form.querySelectorAll('.form-control.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
    });

    form.querySelectorAll('.invalid-feedback').forEach(clearFeedback);
  }

  function isValidEmail(value) {
    return EMAIL_PATTERN.test(value);
  }

  function getPasswordError(value) {
    if (!value || value.length < 8) {
      return PASSWORD_MESSAGES.short;
    }

    if (!/[A-Z]/.test(value)) {
      return PASSWORD_MESSAGES.uppercase;
    }

    if (!/[a-z]/.test(value)) {
      return PASSWORD_MESSAGES.lowercase;
    }

    if (!/[0-9]/.test(value)) {
      return PASSWORD_MESSAGES.number;
    }

    if (!/[^A-Za-z0-9]/.test(value)) {
      return PASSWORD_MESSAGES.special;
    }

    return '';
  }

  function passwordStrengthScore(password) {
    let score = 0;

    if (password.length >= 8) {
      score += 1;
    }

    if (/[a-z]/.test(password)) {
      score += 1;
    }

    if (/[A-Z]/.test(password)) {
      score += 1;
    }

    if (/[0-9]/.test(password)) {
      score += 1;
    }

    if (/[^A-Za-z0-9]/.test(password)) {
      score += 1;
    }

    return Math.min(score, 5);
  }

  function bindPasswordStrength(form) {
    const passwordField = getField(form, '#password');
    const bar = form.querySelector('.strength-bar');
    const label = form.querySelector('.strength-text');

    if (!passwordField || !bar || !label) {
      return;
    }

    passwordField.addEventListener('input', function () {
      const score = passwordStrengthScore(passwordField.value);

      // Solo se anota el puntaje (0 a 5): los colores de la barra los pone el CSS.
      bar.dataset.score = String(score);
      label.textContent = passwordField.value.length ? strengthLabels[score] : '';
    });
  }

  function bindForm(form, validator) {
    if (!form || typeof validator !== 'function') {
      return;
    }

    form.addEventListener('submit', function (event) {
      clearFormErrors(form);

      if (!validator(form)) {
        event.preventDefault();
      }
    });
  }

  function validateRegister(form) {
    let valid = true;
    const nameField = getField(form, '#nombre');
    const emailField = getField(form, '#email');
    const passwordField = getField(form, '#password');
    const confirmField = getField(form, '#password_confirm');
    const termsField = getField(form, '#terms');
    const termsFeedback = form.querySelector('#terms-error');

    if (!nameField.value.trim() || nameField.value.trim().length < 2) {
      showFieldError(nameField, 'Ingresa tu nombre completo (minimo 2 caracteres).');
      valid = false;
    }

    if (!isValidEmail(emailField.value.trim())) {
      showFieldError(emailField, 'Ingresa un correo electronico valido.');
      valid = false;
    }

    const passwordError = getPasswordError(passwordField.value);
    if (passwordError) {
      showFieldError(passwordField, passwordError);
      valid = false;
    }

    if (passwordField.value !== confirmField.value) {
      showFieldError(confirmField, 'Las contrasenas no coinciden.');
      valid = false;
    }

    if (!termsField.checked) {
      showFieldError(termsField, 'Debes aceptar los terminos y condiciones.', termsFeedback);
      valid = false;
    }

    return valid;
  }

  function validateLogin(form) {
    let valid = true;
    const emailField = getField(form, '#email');
    const passwordField = getField(form, '#password');

    if (!isValidEmail(emailField.value.trim())) {
      showFieldError(emailField, 'Ingresa un correo electronico valido.');
      valid = false;
    }

    if (!passwordField.value) {
      showFieldError(passwordField, 'Ingresa tu contrasena.');
      valid = false;
    }

    return valid;
  }

  function validateRecover(form) {
    const emailField = getField(form, '#email');

    if (isValidEmail(emailField.value.trim())) {
      return true;
    }

    showFieldError(emailField, 'Ingresa un correo electronico valido.');
    return false;
  }

  function validateReset(form) {
    let valid = true;
    const passwordField = getField(form, '#password');
    const confirmField = getField(form, '#password_confirm');

    const passwordError = getPasswordError(passwordField.value);
    if (passwordError) {
      showFieldError(passwordField, passwordError);
      valid = false;
    }

    if (passwordField.value !== confirmField.value) {
      showFieldError(confirmField, 'Las contrasenas no coinciden.');
      valid = false;
    }

    return valid;
  }

  const validators = {
    login: validateLogin,
    register: validateRegister,
    recover: validateRecover,
    reset: validateReset
  };

  document.querySelectorAll('[data-auth-form]').forEach(function (form) {
    bindForm(form, validators[form.dataset.authForm]);
    bindPasswordStrength(form);
  });

  // Boton del ojito: muestra u oculta la contrasena. Los dos dibujos del ojo estan en
  // components/campo_password.php; la clase is-showing decide cual se ve.
  document.querySelectorAll('.input-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const input = button.closest('.input-group').querySelector('input');
      const mostrar = input.type === 'password';
      input.type = mostrar ? 'text' : 'password';
      button.classList.toggle('is-showing', mostrar);
      button.setAttribute('aria-label', mostrar ? 'Ocultar contrasena' : 'Mostrar contrasena');
    });
  });

  const registerEmail = document.querySelector('#registerForm #email');
  if (registerEmail) {
    registerEmail.addEventListener('blur', function () {
      if (registerEmail.value.trim() && !isValidEmail(registerEmail.value.trim())) {
        showFieldError(registerEmail, 'Formato de correo invalido.');
        return;
      }

      clearFieldError(registerEmail);
    });
  }

  const registerConfirm = document.querySelector('#registerForm #password_confirm');
  const registerPassword = document.querySelector('#registerForm #password');
  if (registerConfirm && registerPassword) {
    registerConfirm.addEventListener('input', function () {
      if (registerConfirm.value && registerConfirm.value !== registerPassword.value) {
        showFieldError(registerConfirm, 'Las contrasenas no coinciden.');
        return;
      }

      clearFieldError(registerConfirm);
    });
  }
}());
