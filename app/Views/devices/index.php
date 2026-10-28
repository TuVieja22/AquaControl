<?php usar_css('css/devices/index.css') ?>
<?php
// Dispositivos: como conectar un AquaControl (su red WiFi, con el QR de "Conectar mi AquaControl"), los
// AquaControl nuevos que aparecieron en la red del usuario (para agregarlos con un toque)
// y la lista de equipos con su estado. funciones/dispositivos.js recarga la pagina sola
// cuando aparece uno nuevo o cuando uno recien agregado se conecta.
// Texto y color de cada estado de la lista.
$estados = [
    'conectado'    => ['online', 'Conectado'],
    'sin_conexion' => ['offline', 'Sin conexión'],
    'desvinculado' => ['off', 'Desvinculado'],
    'sin_key'      => ['off', 'Sin API key'],
];
?>
<?php // Usa el molde del panel (menu lateral). ?>
<?= $this->extend('layouts/panel') ?>

<?php // Titulo y descripcion que van arriba del menu. ?>
<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">AquaControl IoT</p>
  <h1>Dispositivos</h1>
  <p class="dashboard-intro">Conecta tu AquaControl al WiFi de tu casa y a tu cuenta, y mira si está funcionando.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <?php // AquaControl nuevos en la red del usuario: se agregan con un toque (la MAC se reconoce sola). ?>
  <?php if ($nuevos !== []): ?>
    <section class="management-card glass-card pairing-card found-card" id="encontrados">
      <div class="panel-head">
        <div>
          <p class="section-tag">Encontrado en tu red</p>
          <h3><?= count($nuevos) === 1 ? 'Hay un AquaControl nuevo en tu WiFi' : 'Hay ' . count($nuevos) . ' AquaControl nuevos en tu WiFi' ?></h3>
        </div>
      </div>
      <ul class="found-list">
        <?php foreach ($nuevos as $nuevo): ?>
          <li class="found-item">
            <div>
              <strong><?= esc($nuevo['nombre']) ?></strong>
              <small class="device-place">MAC <?= esc($nuevo['macTexto']) ?></small>
            </div>
            <form action="<?= base_url('dispositivos/agregar/' . $nuevo['id']) ?>" method="POST">
              <?= csrf_field() ?>
              <button class="btn btn-primary" type="submit">Agregar a mi cuenta</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php // Como conectar un AquaControl nuevo (y la direccion que pregunta el JavaScript). ?>
  <section class="management-card glass-card pairing-card" id="conectar" data-dispositivos
    data-estado-url="<?= base_url('dispositivos/estado') ?>"
    data-nuevos="<?= esc(implode(',', array_column($nuevos, 'id'))) ?>">
    <div class="panel-head">
      <div>
        <p class="section-tag">Conectar</p>
        <h3>Conecta tu AquaControl</h3>
      </div>
    </div>

    <ol class="pairing-steps">
      <li><strong>Enchufa tu AquaControl</strong> al cargador y espera unos segundos.</li>
      <li>
        <strong>Toca «Conectar mi AquaControl» y escanea el QR con la cámara del celular.</strong> El celular
        se conecta a la red de tu AquaControl, como al WiFi de cualquier casa.
      </li>
      <li>
        <strong>Se abre sola una página:</strong> elige el WiFi de tu casa y escribe su contraseña
        (si no se abre, entra a <code>http://192.168.4.1</code>).
      </li>
      <li>
        <strong>Vuelve a esta página</strong> con el celular en tu WiFi: tu AquaControl aparece arriba, con
        su MAC, para agregarlo con un toque.
      </li>
    </ol>

    <?php // El QR de la red del AquaControl (es el mismo para todos; tambien va en el manual). ?>
    <details class="wifi-qr">
      <summary class="btn btn-primary">Conectar mi AquaControl</summary>
      <div class="wifi-qr-content">
        <img src="<?= base_url('img/qr-red-aquacontrol.png') ?>" width="220" height="220"
          alt="QR para conectarse a la red WiFi <?= esc($red->nombre) ?>">
        <div>
          <p><strong>Escanea este QR con la cámara del celular.</strong></p>
          <p class="wifi-qr-manual">
            ¿Estás viendo esta página en el mismo celular? Conéctate a mano: en el WiFi del celular elige la
            red <strong><?= esc($red->nombre) ?></strong> y escribe la contraseña <code><?= esc($red->clave) ?></code>.
          </p>
          <?php // Por si el celular no abre solo la pagina del AquaControl al conectarse (no necesita internet). ?>
          <p class="wifi-qr-manual">
            Al conectarte, el celular abre solo la página para elegir tu WiFi. Si no se abre, vuelve a esta
            pestaña y toca:
          </p>
          <a class="btn btn-outline" href="http://192.168.4.1/">Ya me conecté: abrir la configuración</a>
        </div>
      </div>
    </details>

    <p class="pairing-status" role="status" aria-live="polite">
      <span class="pairing-dot" aria-hidden="true"></span> Buscando AquaControl nuevos en tu red…
    </p>

    <?php // Como cambiar de red mas adelante. ?>
    <p class="pairing-note">
      ¿Te mudaste o cambiaste el WiFi? Si tu AquaControl no encuentra su red, en más o menos un minuto
      prende la suya: escanea de nuevo el QR. Si todavía está conectado y lo quieres pasar a otra red, usa
      <strong>Cambiar WiFi</strong> en la lista de abajo. Tu WiFi tiene que ser de 2.4 GHz.
    </p>
  </section>

  <?php // Lista de dispositivos con su estado. ?>
  <section class="management-card glass-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Listado</p>
        <h3>Mis dispositivos</h3>
      </div>
      <span class="management-count"><?= count($devices) ?></span>
    </div>

    <div class="table-wrap">
      <table class="management-table device-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php // Si no hay ninguno, una fila que lo dice. ?>
          <?php if ($devices === []): ?>
            <tr><td colspan="3" class="table-empty">Todavía no conectaste ningún dispositivo.</td></tr>
          <?php endif; ?>
          <?php foreach ($devices as $device): ?>
            <?php // Si este dispositivo esta vinculado (tiene API key), y el color y texto de su estado. ?>
            <?php $vinculado = ! empty($device['api_key_hash']); ?>
            <?php [$color, $textoEstado] = $estados[$device['estado']]; ?>
            <tr>
              <td>
                <strong><?= esc($device['nombre']) ?></strong>
                <small class="device-place"><?= esc($device['ubicacion']) ?></small>
                <?php if ($device['macTexto'] !== ''): ?>
                  <small class="device-place">MAC <?= esc($device['macTexto']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php // Conectado, sin conexion o desvinculado, con la ultima conexion debajo. ?>
                <span class="device-state" data-state="<?= $color ?>"><?= $textoEstado ?></span>
                <small class="device-place"><?= ! empty($device['ultima_conexion']) ? 'Última conexión ' . date('d/m/Y H:i', strtotime($device['ultima_conexion'])) : 'Nunca se conectó' ?></small>
              </td>
              <td>
                <?php // Editar, cambiar WiFi (solo si esta conectado), desvincular y eliminar (estas dos piden confirmacion). ?>
                <div class="management-actions">
                  <a class="btn btn-outline" href="<?= base_url('dispositivos/editar/' . $device['id']) ?>">Editar</a>
                  <?php if ($device['estado'] === 'conectado' && $device['macTexto'] !== ''): ?>
                    <form action="<?= base_url('dispositivos/wifi/' . $device['id']) ?>" method="POST">
                      <?= csrf_field() ?>
                      <button class="btn btn-outline" type="submit">Cambiar WiFi</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($vinculado): ?>
                    <form action="<?= base_url('dispositivos/desvincular/' . $device['id']) ?>" method="POST"
                      data-confirm="¿Desvincular este dispositivo? Deja de enviar lecturas. Para usarlo de nuevo vas a tener que conectarlo otra vez desde esta página.">
                      <?= csrf_field() ?>
                      <button class="btn btn-ghost danger-action" type="submit">Desvincular</button>
                    </form>
                  <?php endif; ?>
                  <form action="<?= base_url('dispositivos/eliminar/' . $device['id']) ?>" method="POST" data-confirm="¿Eliminar este dispositivo? También se borran todas sus lecturas.">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost danger-action" type="submit">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?= $this->endSection() ?>

<?php // JavaScript de la pagina: se actualiza sola cuando aparece o se conecta un AquaControl. ?>
<?= $this->section('scripts') ?>
  <script src="<?= base_url('js/funciones/dispositivos.js') ?>"></script>
<?= $this->endSection() ?>
