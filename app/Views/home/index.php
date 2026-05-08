<?php $title = 'Inicio'; ?>
<?= view('layouts/header') ?>

<main>
  <!-- ── HERO ─────────────────────────────────────── -->
  <section class="hero">
    <div class="container">

      <div class="hero-badge">
        <span class="dot"></span>
        ESP32 · IoT · Tiempo real
      </div>

      <h1>
        Control total de<br>
        tu <span class="accent">ecosistema acuático</span>
      </h1>

      <p>
        Monitorea temperatura, pH, turbidez y nivel de agua desde cualquier lugar.
        Tu pecera siempre en condiciones óptimas — sin intervención humana constante.
      </p>

      <div class="hero-cta">
        <?php if (session()->get('user_id')): ?>
          <a href="<?= base_url('dashboard') ?>" class="btn btn-primary btn-lg">Ir al Dashboard →</a>
        <?php else: ?>
          <a href="<?= base_url('auth/register') ?>" class="btn btn-primary btn-lg">Comenzar gratis →</a>
          <a href="<?= base_url('auth/login') ?>"    class="btn btn-outline btn-lg">Iniciar sesión</a>
        <?php endif; ?>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-item">
          <span class="stat-num">7</span>
          <span class="stat-label">Sensores</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">24/7</span>
          <span class="stat-label">Monitoreo</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">3</span>
          <span class="stat-label">Niveles de alerta</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">IoT</span>
          <span class="stat-label">Tiempo real</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">MVC</span>
          <span class="stat-label">Arquitectura</span>
        </div>
      </div>

    </div>
  </section>

  <!-- ── FEATURES ──────────────────────────────────── -->
  <section class="features">
    <div class="container">
      <p class="section-tag">¿Qué hace AquaControl?</p>
      <h2 class="section-title">Todo lo que necesita<br>tu pecera, automatizado</h2>

      <div class="features-grid">

        <div class="feature-card">
          <div class="feature-icon">🌡️</div>
          <h3>Control de temperatura</h3>
          <p>Sensor DS18B20 sumergible conectado al ESP32. El calefactor se activa automáticamente cuando la temperatura baja del rango óptimo.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🧪</div>
          <h3>Calidad del agua</h3>
          <p>Monitoreo continuo de pH y turbidez. El sistema detecta contaminación y recomienda cambio de agua antes de que afecte a los peces.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🍽️</div>
          <h3>Alimentación inteligente</h3>
          <p>Servo SG90 controlado con horarios personalizables. Ajusta la cantidad de alimento según historial para evitar sobrealimentación.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">💧</div>
          <h3>Sensor de nivel</h3>
          <p>Detector de nivel tipo flotador que previene daños al calefactor cuando el agua está baja. Alerta inmediata al usuario.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>Estadísticas e historial</h3>
          <p>Gráficas de temperatura, registros de alimentación y estado del agua almacenados en base de datos para análisis y seguimiento.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🏖️</div>
          <h3>Modo vacaciones</h3>
          <p>Activa el modo autónomo y el sistema mantiene todas las condiciones sin que necesites estar presente. Ideal para viajes.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">📡</div>
          <h3>Conectividad WiFi</h3>
          <p>El ESP32 envía datos en tiempo real vía HTTP/MQTT. Arquitectura cliente-servidor con actualizaciones cada pocos segundos.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3>Seguridad integrada</h3>
          <p>Límite máximo de temperatura programado, modo automático si falla la conexión y protección ante sensores defectuosos.</p>
        </div>

      </div>
    </div>
  </section>

  <!-- ── HOW IT WORKS ───────────────────────────────── -->
  <section class="how">
    <div class="container">
      <p class="section-tag">Funcionamiento</p>
      <h2 class="section-title" style="margin-bottom: 48px;">Del sensor a tu pantalla<br>en segundos</h2>

      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <h4>Sensores miden</h4>
          <p>Temperatura, pH, turbidez y nivel son capturados continuamente.</p>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <h4>ESP32 procesa</h4>
          <p>El microcontrolador analiza los datos y decide qué actuadores activar.</p>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <h4>Envío WiFi</h4>
          <p>Los datos se transmiten al servidor vía HTTP/MQTT en tiempo real.</p>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <h4>Backend guarda</h4>
          <p>CodeIgniter 4 recibe y almacena todo en MySQL/Firebase.</p>
        </div>
        <div class="step">
          <div class="step-num">5</div>
          <h4>Tú controlas</h4>
          <p>Desde el dashboard web monitoreas, ajustas y recibes alertas.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── ALERT LEVELS ───────────────────────────────── -->
  <section class="alert-section">
    <div class="container">
      <p class="section-tag">Sistema de alertas</p>
      <h2 class="section-title" style="margin-bottom: 32px;">Tres niveles de respuesta<br>inteligente</h2>

      <div class="alert-levels">
        <div class="alert-card l1">
          <p class="alert-label">Nivel 1 — Aviso</p>
          <h4>Notificación web</h4>
          <p>Un parámetro salió del rango óptimo. Se muestra en el dashboard para revisión.</p>
        </div>
        <div class="alert-card l2">
          <p class="alert-label">Nivel 2 — Alerta</p>
          <h4>Notificación push</h4>
          <p>Condición que requiere atención. Se envía notificación al dispositivo del usuario.</p>
        </div>
        <div class="alert-card l3">
          <p class="alert-label">Nivel 3 — Crítico</p>
          <h4>Alerta crítica</h4>
          <p>Situación de riesgo para los peces. Actuadores se activan automáticamente y se dispara alerta urgente.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── CTA FINAL ──────────────────────────────────── -->
  <?php if (!session()->get('user_id')): ?>
  <section style="padding: 80px 0; text-align: center;">
    <div class="container">
      <h2 style="font-family: var(--font-display); font-size: clamp(1.6rem,4vw,2.4rem); font-weight:700; color:#fff; margin-bottom:16px; letter-spacing:-0.02em;">
        ¿Listo para automatizar<br>tu pecera?
      </h2>
      <p style="color:rgba(159,225,203,0.55); margin-bottom:32px; font-size:1rem;">
        Crea tu cuenta gratis y conecta tu ESP32 en minutos.
      </p>
      <a href="<?= base_url('auth/register') ?>" class="btn btn-primary btn-lg" style="display:inline-flex; width:auto;">
        Crear cuenta gratis →
      </a>
    </div>
  </section>
  <?php endif; ?>

</main>

<?= view('layouts/footer') ?>
