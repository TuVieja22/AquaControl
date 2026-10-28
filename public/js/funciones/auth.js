/* ===================================================
   AquaControl — funciones/auth.js
   Paginas de cuenta (login, registro, recuperar y nueva contrasena): revisa los
   formularios antes de enviarlos, calcula la seguridad de la contrasena y el boton
   para mostrarla. El servidor vuelve a validar todo: esto solo avisa antes.
   Aca no hay colores ni iconos: el aspecto de los errores esta en css/layouts/main.css
   y el del campo de contrasena en css/components/campo_password.css.
   =================================================== */

// Modo estricto: el navegador avisa de mas errores.
'use strict';

// Todo va dentro de una funcion para no mezclar sus nombres con los de otros archivos.
(function () {
  // Forma que tiene que tener un correo: algo, arroba, algo, punto, algo.
  const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  // Mensaje de cada requisito de la contrasena.
  const PASSWORD_MESSAGES = {
    short: 'La contraseña debe tener al menos 8 caracteres.',
    uppercase: 'Debe contener al menos una letra mayúscula.',
    lowercase: 'Debe contener al menos una letra minúscula.',
    number: 'Debe contener al menos un número.',
    special: 'Debe contener al menos un carácter especial.'
  };

  // Texto de la barra de seguridad segun el puntaje (0 a 5).
  const strengthLabels = ['', 'Muy débil', 'Débil', 'Aceptable', 'Fuerte', 'Muy fuerte'];

  // Busca un campo dentro de un formulario.
  function getField(form, selector) {
    return form.querySelector(selector);
  }

  // Busca la cajita de error que le corresponde a un campo.
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

  // Marca un campo como invalido y escribe su mensaje de error.
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

  // Le quita el error a un campo.
  function clearFieldError(field, feedbackNode = null) {
    if (!field) {
      return;
    }

    field.classList.remove('is-invalid');
    clearFeedback(feedbackNode || getFeedbackNode(field));
  }

  // Quita todos los errores de un formulario.
  function clearFormErrors(form) {
    form.querySelectorAll('.form-control.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
    });

    form.querySelectorAll('.invalid-feedback').forEach(clearFeedback);
  }

  // Dice si un correo esta bien escrito.
  function isValidEmail(value) {
    return EMAIL_PATTERN.test(value);
  }

  // Devuelve el primer requisito que no cumple la contrasena (vacio si cumple todos).
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

  // Puntaje de la contrasena: un punto por cada requisito cumplido.
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

  // Conecta el campo de contrasena con la barra de seguridad.
  function bindPasswordStrength(form) {
    const passwordField = getField(form, '#password');
    const bar = form.querySelector('.strength-bar');
    const label = form.querySelector('.strength-text');

    if (!passwordField || !bar || !label) {
      return;
    }

    // Cada vez que se escribe, se calcula el puntaje de nuevo.
    passwordField.addEventListener('input', function () {
      const score = passwordStrengthScore(passwordField.value);

      // Solo se anota el puntaje (0 a 5): los colores de la barra los pone el CSS.
      bar.dataset.score = String(score);
      label.textContent = passwordField.value.length ? strengthLabels[score] : '';
    });
  }

  // Antes de enviar un formulario lo revisa; si hay errores, no se envia.
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

  // Revisa el formulario de registro.
  function validateRegister(form) {
    let valid = true;
    const nameField = getField(form, '#nombre');
    const lastNameField = getField(form, '#apellido');
    const emailField = getField(form, '#email');
    const passwordField = getField(form, '#password');
    const confirmField = getField(form, '#password_confirm');
    const termsField = getField(form, '#terms');
    const termsFeedback = form.querySelector('#terms-error');

    // Nombre, apellido, correo, contrasena, repeticion y terminos: cada uno con su error.
    if (nameField.value.trim().length < 2) {
      showFieldError(nameField, 'Ingresa tu nombre (mínimo 2 caracteres).');
      valid = false;
    }

    if (lastNameField.value.trim().length < 2) {
      showFieldError(lastNameField, 'Ingresa tu apellido (mínimo 2 caracteres).');
      valid = false;
    }

    if (!isValidEmail(emailField.value.trim())) {
      showFieldError(emailField, 'Ingresa un correo electrónico válido.');
      valid = false;
    }

    const passwordError = getPasswordError(passwordField.value);
    if (passwordError) {
      showFieldError(passwordField, passwordError);
      valid = false;
    }

    if (passwordField.value !== confirmField.value) {
      showFieldError(confirmField, 'Las contraseñas no coinciden.');
      valid = false;
    }

    if (!termsField.checked) {
      showFieldError(termsField, 'Debes aceptar los términos y condiciones.', termsFeedback);
      valid = false;
    }

    return valid;
  }

  // Revisa el formulario de login.
  function validateLogin(form) {
    let valid = true;
    const emailField = getField(form, '#email');
    const passwordField = getField(form, '#password');

    if (!isValidEmail(emailField.value.trim())) {
      showFieldError(emailField, 'Ingresa un correo electrónico válido.');
      valid = false;
    }

    if (!passwordField.value) {
      showFieldError(passwordField, 'Ingresa tu contraseña.');
      valid = false;
    }

    return valid;
  }

  // Revisa el formulario de recuperar contrasena.
  function validateRecover(form) {
    const emailField = getField(form, '#email');

    if (isValidEmail(emailField.value.trim())) {
      return true;
    }

    showFieldError(emailField, 'Ingresa un correo electrónico válido.');
    return false;
  }

  // Revisa el formulario de nueva contrasena.
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
      showFieldError(confirmField, 'Las contraseñas no coinciden.');
      valid = false;
    }

    return valid;
  }

  // Que revision le toca a cada formulario (segun su data-auth-form).
  const validators = {
    login: validateLogin,
    register: validateRegister,
    recover: validateRecover,
    reset: validateReset
  };

  // Conecta cada formulario de la pagina con su revision y con la barra.
  document.querySelectorAll('[data-auth-form]').forEach(function (form) {
    bindForm(form, validators[form.dataset.authForm]);
    bindPasswordStrength(form);
  });

  // Boton del ojito: muestra u oculta la contrasena. Los dos dibujos del ojo estan en
  // components/campo_password.php; la clase is-showing decide cual se ve.
  document.querySelectorAll('.input-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      // Cambia el tipo del campo y le avisa al CSS con la clase is-showing.
      const input = button.closest('.input-group').querySelector('input');
      const mostrar = input.type === 'password';
      input.type = mostrar ? 'text' : 'password';
      button.classList.toggle('is-showing', mostrar);
      button.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
  });

  // En el registro: avisa si el correo esta mal al salir del campo.
  const registerEmail = document.querySelector('#registerForm #email');
  if (registerEmail) {
    registerEmail.addEventListener('blur', function () {
      if (registerEmail.value.trim() && !isValidEmail(registerEmail.value.trim())) {
        showFieldError(registerEmail, 'Formato de correo inválido.');
        return;
      }

      clearFieldError(registerEmail);
    });
  }

  // En el registro: avisa mientras se escribe si las contrasenas no coinciden.
  const registerConfirm = document.querySelector('#registerForm #password_confirm');
  const registerPassword = document.querySelector('#registerForm #password');
  if (registerConfirm && registerPassword) {
    registerConfirm.addEventListener('input', function () {
      if (registerConfirm.value && registerConfirm.value !== registerPassword.value) {
        showFieldError(registerConfirm, 'Las contraseñas no coinciden.');
        return;
      }

      clearFieldError(registerConfirm);
    });
  }
}());
