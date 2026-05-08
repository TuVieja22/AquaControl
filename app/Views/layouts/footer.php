  <!-- FOOTER -->
  <footer class="footer">
    <div class="container">
      <p>© <?= date('Y') ?> <strong>AquaControl</strong> — Sistema Inteligente IoT para Ecosistemas Acuáticos &nbsp;·&nbsp; Tesina universitaria &nbsp;·&nbsp; ESP32 + CodeIgniter 4</p>
    </div>
  </footer>

</div><!-- /.page-wrapper -->

<script src="<?= base_url('js/aqua.js') ?>"></script>
<?php if (! empty($extraJs ?? [])): ?>
  <?php foreach (($extraJs ?? []) as $jsFile): ?>
    <script src="<?= base_url($jsFile) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
