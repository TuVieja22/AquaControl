<?php
$approved = $summary['aprobado'] ?? ['cantidad' => 0, 'total' => 0];
$pendingCount = (int) ($summary['pendiente']['cantidad'] ?? 0) + (int) ($summary['en_proceso']['cantidad'] ?? 0);
$money = static fn ($amount, string $currency = 'ARS'): string => $currency . ' ' . number_format((float) $amount, 2, ',', '.');
?>
<?= view('layouts/header') ?>

<main class="management-page">
  <section class="dashboard-shell container">
    <aside class="dashboard-sidebar glass-card">
      <p class="dashboard-kicker">Administracion</p>
      <h1>Pedidos</h1>
      <p class="dashboard-intro">Compras realizadas desde la tienda con Mercado Pago.</p>

      <nav class="dashboard-menu">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Panel principal</a>
        <a href="<?= base_url('dispositivos') ?>" class="dashboard-link">Dispositivos</a>
        <a href="<?= base_url('usuarios') ?>" class="dashboard-link">Usuarios</a>
        <a href="<?= base_url('pedidos') ?>" class="dashboard-link is-active">Pedidos</a>
      </nav>
    </aside>

    <div class="dashboard-main">
      <?= view('components/flash_messages', ['types' => ['success', 'error', 'info']]) ?>

      <section class="management-hero glass-card">
        <div>
          <p class="section-tag">Ventas</p>
          <h2 class="dashboard-title">Pedidos de la tienda</h2>
          <p class="dashboard-subtitle">
            <?= esc((string) (int) $approved['cantidad']) ?> aprobados por <?= esc($money($approved['total'])) ?> ·
            <?= esc((string) $pendingCount) ?> pendientes de acreditacion.
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
          <?php foreach ($statusNames as $key => $label): ?>
            <a class="btn <?= $activeStatus === $key ? 'btn-primary' : 'btn-outline' ?>" href="<?= base_url('pedidos') . '?estado=' . esc($key, 'url') ?>">
              <?= esc($label) ?> (<?= esc((string) (int) ($summary[$key]['cantidad'] ?? 0)) ?>)
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
              <?php else: ?>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td><?= esc(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                    <td>
                      <code><?= esc($order['referencia']) ?></code>
                      <?php if (! empty($order['pago_id'])): ?>
                        <small class="order-meta">Pago MP #<?= esc($order['pago_id']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= esc($order['email']) ?></td>
                    <td><?= esc($order['cantidad'] . ' x ' . $order['producto_nombre']) ?></td>
                    <td><?= esc($money($order['total'], $order['moneda'])) ?></td>
                    <td>
                      <span class="status-pill order-status-<?= esc($order['estado']) ?>"><?= esc($statusNames[$order['estado']] ?? $order['estado']) ?></span>
                      <?php if ($order['estado_detalle'] === 'monto_no_coincide'): ?>
                        <small class="order-meta order-warning">El monto pagado no coincide: revisar.</small>
                      <?php elseif (! empty($order['pagado_at'])): ?>
                        <small class="order-meta">Pagado <?= esc(date('d/m H:i', strtotime($order['pagado_at']))) ?></small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</main>

<?= view('layouts/footer') ?>
