<?php usar_css('css/dashboard/partes/control.css') ?>
<?php
// Control manual (modo vacaciones y temperatura objetivo) y rangos optimos vigentes.
$config = $dashboardData['config'];
?>
<section class="dashboard-grid control-grid" id="configuracion">
  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Control manual</p>
        <h3>Acciones sobre actuadores</h3>
      </div>
    </div>

    <div class="control-actions">
      <div class="toggle-row">
        <div>
          <span class="form-label">Modo vacaciones</span>
          <p class="toggle-copy">Estado actual: <strong id="vacationStateLabel"><?= esc($dashboardData['cards']['vacationMode']['value']) ?></strong></p>
        </div>
        <button class="btn btn-outline" id="vacationToggleBtn" type="button">Activar / desactivar</button>
      </div>

      <form id="targetTemperatureForm" class="control-form">
        <label class="form-label" for="temp_objetivo">Temperatura objetivo</label>
        <div class="control-inline">
          <input class="form-control" id="temp_objetivo" name="temp_objetivo" type="number" step="0.1" min="10" max="40" value="<?= number_format((float) $config['temp_objetivo'], 1, '.', '') ?>">
          <button class="btn btn-outline" type="submit">Guardar objetivo</button>
        </div>
      </form>
    </div>
  </article>

  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Configuracion</p>
        <h3>Rangos optimos vigentes</h3>
      </div>
    </div>

    <div class="config-metrics">
      <div class="config-metric">
        <span>Temperatura minima</span>
        <strong id="configTempMin"><?= number_format((float) $config['temp_min'], 1) ?> &deg;C</strong>
      </div>
      <div class="config-metric">
        <span>Temperatura maxima</span>
        <strong id="configTempMax"><?= number_format((float) $config['temp_max'], 1) ?> &deg;C</strong>
      </div>
      <div class="config-metric">
        <span>pH minimo</span>
        <strong id="configPhMin"><?= number_format((float) $config['ph_min'], 2) ?></strong>
      </div>
      <div class="config-metric">
        <span>pH maximo</span>
        <strong id="configPhMax"><?= number_format((float) $config['ph_max'], 2) ?></strong>
      </div>
      <div class="config-metric">
        <span>Temperatura objetivo</span>
        <strong id="configTargetTemp"><?= number_format((float) $config['temp_objetivo'], 1) ?> &deg;C</strong>
      </div>
    </div>
  </article>
</section>
