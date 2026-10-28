<?php usar_css('css/components/aviso_prueba.css') ?>
<?php
// Aviso de cuenta de prueba: se muestra mientras la cuenta no tiene un AquaControl. El
// vencimiento lo deja en la sesion el filtro AuthFilter en cada pagina del panel.
$hasta = session()->get('prueba_hasta');
?>
<?php if (! empty($hasta)): ?>
  <?php // Dias que quedan (redondeando para arriba: "1 dia" hasta el ultimo momento). ?>
  <?php $dias = max(1, (int) ceil((strtotime($hasta) - time()) / 86400)); ?>
  <div class="trial-notice" role="note">
    <p>
      <strong>Cuenta de prueba:</strong> se borra el <?= date('d/m', strtotime($hasta)) ?>
      (<?= $dias === 1 ? 'queda 1 día' : "quedan {$dias} días" ?>) si no conectas un AquaControl.
      Las cuentas quedan solo para quienes tienen uno.
    </p>
    <div class="trial-notice-actions">
      <a class="btn btn-primary" href="<?= base_url('/') ?>#checkout">Comprar un AquaControl</a>
      <a class="btn btn-outline" href="<?= base_url('dispositivos') ?>#conectar">Ya tengo uno</a>
    </div>
  </div>
<?php endif; ?>
