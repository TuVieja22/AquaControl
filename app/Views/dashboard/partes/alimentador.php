<?php usar_css('css/dashboard/partes/alimentador.css') ?>
<?php
// Alimentador (servo del ESP32): "Alimentar ahora" y horarios programados.
// Atajos a los datos, si se puede alimentar ahora y gramos por racion.
$feeder = $dashboardData['feeder'];
$config = $dashboardData['config'];
$puedeAlimentar = $feeder['hasDevice'] && $feeder['online'] && ! $feeder['pending'];
$gramosPorRacion = number_format((float) $config['cantidad_alim_gramos'], 1, '.', '');
// Los horarios guardados ("HH:MM"); el formulario muestra dos casilleros.
$horarios = $config['horarios'] ?? [];
?>
<section class="dashboard-grid control-grid feeder-grid" id="alimentador">
  <?php // Alimentar ahora. ?>
  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Alimentador</p>
        <h3>Activar servo ahora</h3>
      </div>
    </div>

    <div class="control-actions">
      <?php // Estado del alimentador (conectado, sin contacto, orden en curso). ?>
      <div class="feeder-status feeder-status-<?= esc($feeder['statusLevel']) ?>" id="feederStatus" role="status" aria-live="polite">
        <span class="feeder-status-dot" aria-hidden="true"></span>
        <div>
          <strong id="feederStatusText"><?= esc($feeder['statusText']) ?></strong>
          <small id="feederLastText"><?= esc($feeder['lastText'] ?? '') ?></small>
        </div>
      </div>

      <?php // Cantidad y boton "Alimentar ahora". ?>
      <form id="feedNowForm" class="control-form">
        <label class="form-label" for="cantidad_gramos">Cantidad a dispensar (g)</label>
        <div class="control-inline">
          <input class="form-control" id="cantidad_gramos" name="cantidad_gramos" type="number" step="0.1" min="<?= $feeding->minGrams ?>" max="<?= $feeding->maxGrams ?>" value="<?= $gramosPorRacion ?>">
          <button class="btn btn-primary" id="feedNowBtn" type="submit" <?= $puedeAlimentar ? '' : 'disabled' ?>>Alimentar ahora</button>
        </div>
      </form>
      <?php // Aca aparece la respuesta del servidor. ?>
      <p class="feeder-feedback" id="feedNowFeedback" role="alert" hidden></p>
    </div>
  </article>

  <?php // Horarios programados. ?>
  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Programación</p>
        <h3>Horarios de alimentación</h3>
      </div>
    </div>

    <?php // Dos horarios y los gramos por racion. ?>
    <form id="feedingScheduleForm" class="control-form feeder-schedule-form">
      <div class="feeder-schedule-fields">
        <div class="form-group">
          <label class="form-label" for="hora_alim_1">Horario 1</label>
          <input class="form-control" id="hora_alim_1" name="hora_alim_1" type="time" value="<?= esc($horarios[0] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="hora_alim_2">Horario 2</label>
          <input class="form-control" id="hora_alim_2" name="hora_alim_2" type="time" value="<?= esc($horarios[1] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="cantidad_alim_gramos">Gramos por ración</label>
          <input class="form-control" id="cantidad_alim_gramos" name="cantidad_alim_gramos" type="number" step="0.1" min="<?= $feeding->minGrams ?>" max="<?= $feeding->maxGrams ?>" value="<?= $gramosPorRacion ?>" required>
        </div>
      </div>
      <?php // Resumen de lo programado y aclaraciones. ?>
      <p class="toggle-copy" id="feederScheduleText"><?= esc($feeder['scheduleText']) ?></p>
      <p class="toggle-copy">Deja un horario vacío para desactivarlo. A esa hora el alimentador se activa solo (con hasta <?= $feeding->scheduleWindowMinutes ?> min de margen si estuvo offline).</p>
      <?php // Boton de guardar y respuesta del servidor. ?>
      <div class="management-form-actions">
        <button class="btn btn-outline" type="submit">Guardar horarios</button>
      </div>
      <p class="feeder-feedback" id="feedingScheduleFeedback" role="alert" hidden></p>
    </form>
  </article>
</section>
