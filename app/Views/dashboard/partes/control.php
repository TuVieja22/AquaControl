<?php usar_css('css/dashboard/partes/control.css') ?>
<?php
// Control manual (modo vacaciones y temperatura objetivo) y rangos optimos vigentes.
// Atajo a la configuracion de la pecera.
$config = $dashboardData['config'];
?>
<section class="dashboard-grid control-grid" id="configuracion">
  <?php // Control manual. ?>
  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Control manual</p>
        <h3>Acciones sobre actuadores</h3>
      </div>
    </div>

    <div class="control-actions">
      <?php // Modo vacaciones: estado actual y boton para cambiarlo. ?>
      <div class="toggle-row">
        <div>
          <span class="form-label">Modo vacaciones</span>
          <p class="toggle-copy">Estado actual: <strong id="vacationStateLabel"><?= esc($dashboardData['cards']['vacationMode']['value']) ?></strong></p>
        </div>
        <button class="btn btn-outline" id="vacationToggleBtn" type="button">Activar / desactivar</button>
      </div>

      <?php // Temperatura objetivo. ?>
      <form id="targetTemperatureForm" class="control-form">
        <label class="form-label" for="temp_objetivo">Temperatura objetivo</label>
        <div class="control-inline">
          <input class="form-control" id="temp_objetivo" name="temp_objetivo" type="number" step="0.1" min="10" max="40" value="<?= number_format((float) $config['temp_objetivo'], 1, '.', '') ?>">
          <button class="btn btn-outline" type="submit">Guardar objetivo</button>
        </div>
      </form>
      <?php // Aca aparece la respuesta del servidor. ?>
      <p class="feeder-feedback" id="targetTemperatureFeedback" role="alert" hidden></p>
    </div>
  </article>

  <?php // Rangos ideales de la pecera: con estos valores se arman las alertas. ?>
  <article class="glass-card control-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Configuración</p>
        <h3>Parámetros de la pecera</h3>
      </div>
    </div>

    <form id="parametrosForm" class="control-form parametros-form">
      <p class="parametros-ayuda">Fuera de estos rangos, la pecera genera una alerta.</p>

      <div class="parametros-fields">
        <div class="form-group">
          <label class="form-label" for="temp_min">Temperatura mínima (&deg;C)</label>
          <input class="form-control" id="temp_min" name="temp_min" type="number" step="0.1" min="0" max="40" value="<?= number_format((float) $config['temp_min'], 1, '.', '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="temp_max">Temperatura máxima (&deg;C)</label>
          <input class="form-control" id="temp_max" name="temp_max" type="number" step="0.1" min="0" max="40" value="<?= number_format((float) $config['temp_max'], 1, '.', '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="ph_min">pH mínimo</label>
          <input class="form-control" id="ph_min" name="ph_min" type="number" step="0.01" min="0" max="14" value="<?= number_format((float) $config['ph_min'], 2, '.', '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="ph_max">pH máximo</label>
          <input class="form-control" id="ph_max" name="ph_max" type="number" step="0.01" min="0" max="14" value="<?= number_format((float) $config['ph_max'], 2, '.', '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="gramos_vacaciones">Ración de vacaciones (g)</label>
          <input class="form-control" id="gramos_vacaciones" name="gramos_vacaciones" type="number" step="0.1" min="<?= $feeding->minGrams ?>" max="<?= $feeding->maxGrams ?>" value="<?= number_format((float) $config['gramos_vacaciones'], 1, '.', '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="gramos_por_giro">Gramos por giro</label>
          <input class="form-control" id="gramos_por_giro" name="gramos_por_giro" type="number" step="0.005" min="0.005" max="5" value="<?= number_format((float) $config['gramos_por_giro'], 3, '.', '') ?>" required>
        </div>
      </div>

      <p class="parametros-ayuda">
        Con el modo vacaciones prendido, las alimentaciones programadas usan esa ración
        en lugar de la de todos los días (<strong id="configRacionHabitual"><?= number_format((float) $config['cantidad_alim_gramos'], 1) ?></strong> g).
      </p>

      <?php // Los gramos por giro son los que traducen una racion en movimientos del servo. ?>
      <p class="parametros-ayuda">
        El alimentador abre la compuerta varias veces hasta completar la ración.
        Para medir los gramos por giro: pedí <strong>Alimentar ahora</strong> unas 25 veces,
        pesá lo que cayó y dividí por 25.
        <span id="configGirosAviso"></span>
      </p>

      <button class="btn btn-outline" type="submit">Guardar parámetros</button>
    </form>
    <?php // Aca aparece la respuesta del servidor. ?>
    <p class="feeder-feedback" id="parametrosFeedback" role="alert" hidden></p>
  </article>
</section>
