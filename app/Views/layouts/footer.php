  <footer class="footer">
    <div class="container">
      <p>&copy; <?= date('Y') ?> <strong>AquaControl</strong> &middot; Sistema Inteligente IoT para Ecosistemas Acuaticos &middot; Tesina universitaria &middot; ESP32 + CodeIgniter 4</p>
    </div>
  </footer>

</div>

<script src="<?= base_url('js/aqua.js') ?>"></script>
<?php foreach (($extraJs ?? []) as $jsFile): ?>
  <script src="<?= base_url($jsFile) ?>"></script>
<?php endforeach; ?>
</body>
</html>
