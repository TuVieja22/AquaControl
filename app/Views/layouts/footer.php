  <footer class="footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <a href="<?= base_url('/') ?>" class="navbar-brand footer-logo">
          <div class="brand-icon">AC</div>
          Aqua<span>Control</span>
        </a>
        <p>Sistema inteligente IoT para monitoreo, alertas y automatizacion de acuarios.</p>
      </div>

      <div class="footer-trust">
        <span>Desarrollado en Argentina</span>
        <span>Facil de instalar</span>
        <span>Soporte tecnico local</span>
      </div>

      <div class="footer-social">
        <a href="https://wa.me/" target="_blank" rel="noopener">WhatsApp</a>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
        <a href="mailto:contacto@aquacontrol.local">Contacto</a>
      </div>

      <p class="footer-copy">&copy; <?= date('Y') ?> <strong>AquaControl</strong> &middot; ESP32 + CodeIgniter 4</p>
    </div>
  </footer>

</div>

<script src="<?= base_url('js/aqua.js') ?>"></script>
<?php foreach (($extraJs ?? []) as $jsFile): ?>
  <script src="<?= base_url($jsFile) ?>"></script>
<?php endforeach; ?>
</body>
</html>
