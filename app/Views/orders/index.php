<?php usar_css('css/orders/index.css') ?>
<?php
$aprobados = $summary['aprobado'] ?? ['cantidad' => 0, 'total' => 0];
$pendientes = (int) ($summary['pendiente']['cantidad'] ?? 0) + (int) ($summary['en_proceso']['cantidad'] ?? 0);
$plata = static fn ($monto, string $moneda = 'ARS'): string => $moneda . ' ' . number_format((float) $monto, 2, ',', '.');
?>
<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <p class="dashboard-kicker">Administracion</p>
  <h1>Pedidos</h1>
  <p class="dashboard-intro">Compras realizadas desde la tienda con Mercado Pago.</p>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <section class="management-hero glass-card">
    <div>
      <p class="section-tag">Ventas</p>
      <h2 class="dashboard-title">Pedidos de la tienda</h2>
      <p class="dashboard-subtitle">
        <?= (int) $aprobados['cantidad'] ?> aprobados por <?= esc($plata($aprobados['total'])) ?> ·
        <?= $pendientes ?> pendientes de acreditacion.
        El estado se verifica contra Mercado Pago.
      </p>
    </div>
  </section>

  <section class="management-card glass-card">
    <div class="panel-head">
      <div>
        <p class="section-tag">Listado</p>
        <h3><?= $activeStatus ? esc($statusNames[$activeStatus]) : 'Todos los pedidos' ?></h3>
      </div>
      <span class="management-count"><?= count($orders) ?></span>
    </div>

    <nav class="order-filters" aria-label="Filtrar por estado">
      <a class="btn <?= $activeStatus === null ? 'btn-primary' : 'btn-outline' ?>" href="<?= base_url('pedidos') ?>">Todos</a>
      <?php foreach ($statusNames as $estado => $nombre): ?>
        <a class="btn <?= $activeStatus === $estado ? 'btn-primary' : 'btn-outline' ?>" href="<?= base_url('pedidos') . '?estado=' . esc($estado, 'url') ?>">
          <?= esc($nombre) ?> (<?= (int) ($summary[$estado]['cantidad'] ?? 0) ?>)
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="table-wrap">
      <table class="management-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Pedido</th>
            <th>Cliente</th>
            <th>Producto</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders === []): ?>
            <tr><td colspan="6" class="table-empty">Todavia no hay pedidos<?= $activeStatus ? ' con este estado' : '' ?>.</td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
              <td>
                <code><?= esc($order['referencia']) ?></code>
                <?php if (! empty($order['pago_id'])): ?>
                  <small class="order-meta">Pago MP #<?= esc($order['pago_id']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= esc($order['email']) ?></td>
              <td><?= esc($order['cantidad'] . ' x ' . $order['producto_nombre']) ?></td>
              <td><?= esc($plata($order['total'], $order['moneda'])) ?></td>
              <td>
                <span class="status-pill order-status-<?= esc($order['estado']) ?>"><?= esc($statusNames[$order['estado']] ?? $order['estado']) ?></span>
                <?php if ($order['estado_detalle'] === 'monto_no_coincide'): ?>
                  <small class="order-meta order-warning">El monto pagado no coincide: revisar.</small>
                <?php elseif (! empty($order['pagado_at'])): ?>
                  <small class="order-meta">Pagado <?= date('d/m H:i', strtotime($order['pagado_at'])) ?></small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?= $this->endSection() ?>
