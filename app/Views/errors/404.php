<?php $title = 'Página no encontrada'; ?>
<?= view('layouts/header') ?>

<div class="auth-page" style="text-align:center;">
  <div style="max-width:460px;margin:auto;">
    <div style="font-size:80px;margin-bottom:16px;opacity:0.6;">🐟</div>
    <h1 style="font-family:var(--font-display);font-size:5rem;font-weight:800;color:var(--teal-300);letter-spacing:-0.04em;margin-bottom:8px;">404</h1>
    <h2 style="font-family:var(--font-display);font-size:1.4rem;color:#fff;margin-bottom:12px;">El pez se escapó</h2>
    <p style="color:rgba(159,225,203,0.5);margin-bottom:36px;font-size:0.95rem;line-height:1.7;">
      Esta página no existe o fue movida a otra pecera.<br>
      Puede que el enlace esté incorrecto.
    </p>
    <a href="<?= base_url('/') ?>" class="btn btn-primary" style="display:inline-flex;width:auto;padding:14px 32px;">
      Volver al inicio →
    </a>
  </div>
</div>

<?= view('layouts/footer') ?>
