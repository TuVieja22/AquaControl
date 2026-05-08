/* ===================================================
   AquaControl — aqua.js
   public/js/aqua.js
   =================================================== */

'use strict';

/* ── PASSWORD TOGGLE ─────────────────────────────── */
document.querySelectorAll('.input-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.input-group').querySelector('input');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
      ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
      : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
  });
});

/* ── PASSWORD STRENGTH ───────────────────────────── */
const pwInput = document.getElementById('password');
if (pwInput) {
  const segs = document.querySelectorAll('.strength-seg');
  const txt  = document.querySelector('.strength-text');

  const colors  = ['#E24B4A','#EF9F27','#EF9F27','#1D9E75','#5DCAA5'];
  const labels  = ['','Muy débil','Débil','Aceptable','Fuerte','Muy fuerte'];

  function calcStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return Math.min(score, 5);
  }

  pwInput.addEventListener('input', () => {
    const score = calcStrength(pwInput.value);
    segs.forEach((s, i) => {
      s.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,0.08)';
    });
    txt.textContent = pwInput.value.length ? labels[score] : '';
    txt.style.color = score >= 4 ? '#5DCAA5' : score >= 3 ? '#EF9F27' : '#F09595';
  });
}

/* ── CLIENT-SIDE FORM VALIDATION ─────────────────── */
function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showError(inputId, msg) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.classList.add('is-invalid');
  const fb = el.closest('.form-group')?.querySelector('.invalid-feedback');
  if (fb) { fb.innerHTML = `<span>⚠</span> ${msg}`; fb.style.display = 'flex'; }
}

function clearError(inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.classList.remove('is-invalid');
  const fb = el.closest('.form-group')?.querySelector('.invalid-feedback');
  if (fb) fb.style.display = 'none';
}

function clearAllErrors() {
  document.querySelectorAll('.form-control.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  document.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');
}

/* ── REGISTER FORM ───────────────────────────────── */
const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;

    const nombre   = document.getElementById('nombre')?.value.trim();
    const email    = document.getElementById('email')?.value.trim();
    const password = document.getElementById('password')?.value;
    const confirm  = document.getElementById('password_confirm')?.value;
    const terms    = document.getElementById('terms')?.checked;

    if (!nombre || nombre.length < 2) {
      showError('nombre', 'Ingresa tu nombre completo (mínimo 2 caracteres).'); valid = false;
    }
    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.'); valid = false;
    }
    if (!password || password.length < 8) {
      showError('password', 'La contraseña debe tener al menos 8 caracteres.'); valid = false;
    } else if (!/[A-Z]/.test(password)) {
      showError('password', 'Debe contener al menos una letra mayúscula.'); valid = false;
    } else if (!/[0-9]/.test(password)) {
      showError('password', 'Debe contener al menos un número.'); valid = false;
    }
    if (password !== confirm) {
      showError('password_confirm', 'Las contraseñas no coinciden.'); valid = false;
    }
    if (!terms) {
      showError('terms', 'Debes aceptar los términos y condiciones.'); valid = false;
    }

    if (!valid) e.preventDefault();
  });

  /* Live feedback */
  document.getElementById('email')?.addEventListener('blur', () => {
    const val = document.getElementById('email').value.trim();
    if (val && !validateEmail(val)) showError('email', 'Formato de correo inválido.');
    else clearError('email');
  });
  document.getElementById('password_confirm')?.addEventListener('input', () => {
    const pw = document.getElementById('password')?.value;
    const cf = document.getElementById('password_confirm')?.value;
    if (cf && pw !== cf) showError('password_confirm', 'Las contraseñas no coinciden.');
    else clearError('password_confirm');
  });
}

/* ── LOGIN FORM ──────────────────────────────────── */
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;
    const email    = document.getElementById('email')?.value.trim();
    const password = document.getElementById('password')?.value;

    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.'); valid = false;
    }
    if (!password) {
      showError('password', 'Ingresa tu contraseña.'); valid = false;
    }
    if (!valid) e.preventDefault();
  });
}

/* ── RECOVER FORM ────────────────────────────────── */
const recoverForm = document.getElementById('recoverForm');
if (recoverForm) {
  recoverForm.addEventListener('submit', e => {
    clearAllErrors();
    const email = document.getElementById('email')?.value.trim();
    if (!email || !validateEmail(email)) {
      showError('email', 'Ingresa un correo electrónico válido.');
      e.preventDefault();
    }
  });
}

/* ── RESET PASSWORD FORM ─────────────────────────── */
const resetForm = document.getElementById('resetForm');
if (resetForm) {
  resetForm.addEventListener('submit', e => {
    clearAllErrors();
    let valid = true;
    const password = document.getElementById('password')?.value;
    const confirm  = document.getElementById('password_confirm')?.value;

    if (!password || password.length < 8) {
      showError('password', 'La contraseña debe tener al menos 8 caracteres.'); valid = false;
    } else if (!/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
      showError('password', 'Debe tener mayúsculas y números.'); valid = false;
    }
    if (password !== confirm) {
      showError('password_confirm', 'Las contraseñas no coinciden.'); valid = false;
    }
    if (!valid) e.preventDefault();
  });
}

/* ── AUTO-HIDE FLASH MESSAGES ────────────────────── */
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity 0.5s ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 5000);
});

/* ── SCROLL ANIMATIONS ───────────────────────────── */
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.feature-card, .step, .alert-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(28px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });
}
