<?php
$active = 'dashboard'; $page_title = 'Dashboard';
require __DIR__."/_layout_top.php";

// KPIs
$tot_productos  = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$tot_marcas     = (int)$pdo->query("SELECT COUNT(*) FROM marcas")->fetchColumn();
$tot_categorias = (int)$pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
$tot_pedidos    = (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$tot_nuevos     = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='nuevo'")->fetchColumn();
$ventas_mes     = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();

$ult_pedidos = $pdo->query("SELECT id, total, estado, created_at FROM pedidos ORDER BY id DESC LIMIT 6")->fetchAll();
$low_stock = $pdo->query("SELECT p.id, p.nombre, p.stock, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.stock<=5 ORDER BY p.stock ASC, p.id DESC LIMIT 8")->fetchAll();
?>
  <div class="dashboard-cards">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Pedidos</div>
        <div class="card-icon"><i class="fas fa-shopping-cart"></i></div>
      </div>
      <div class="card-value"><?= $tot_pedidos ?> <span class="muted">/ <?= $tot_nuevos ?> nuevos</span></div>
      <div class="card-footer"><a class="btn-secondary" href="orders.php">Ver pedidos</a></div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Ingresos del mes</div>
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
      </div>
      <div class="card-value">$ <?= money_ar($ventas_mes) ?></div>
      <div class="card-footer">Mes actual</div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Productos</div>
        <div class="card-icon"><i class="fas fa-box-open"></i></div>
      </div>
      <div class="card-value"><?= $tot_productos ?></div>
      <div class="card-footer"><?= $tot_marcas ?> marcas</div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Categorías</div>
        <div class="card-icon"><i class="fas fa-list"></i></div>
      </div>
      <div class="card-value"><?= $tot_categorias ?></div>
      <div class="card-footer">&nbsp;</div>
    </div>
  </div>

  <div class="table-container">
    <div class="table-title">Últimos Pedidos</div>
    <table>
      <thead><tr><th>ID</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($ult_pedidos as $o): ?>
        <tr>
          <td><?= (int)$o["id"] ?></td>
          <td><?= htmlspecialchars($o["created_at"]) ?></td>
          <td>$ <?= money_ar($o["total"]) ?></td>
          <td><span class="status <?= $o["estado"]==='nuevo'?'status-pending':($o['estado']==='completado'?'status-completed':'status-processing') ?>"><?= htmlspecialchars($o["estado"]) ?></span></td>
          <td>
            <a class="action-btn btn-view" href="order.php?id=<?= (int)$o['id'] ?>">Ver</a>
          </td>
        </tr>
        <?php endforeach; if(empty($ult_pedidos)): ?>
        <tr><td colspan="5" class="muted">Sin pedidos</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="table-container">
    <div class="table-title">Productos con bajo stock</div>
    <table>
      <thead><tr><th>#</th><th>Producto</th><th>Categoría</th><th>Stock</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($low_stock as $p): ?>
        <tr>
          <td><?= (int)$p["id"] ?></td>
          <td><?= htmlspecialchars($p["nombre"]) ?></td>
          <td><?= htmlspecialchars($p["categoria"]) ?></td>
          <td><?= (int)$p["stock"] ?></td>
          <td><a class="action-btn btn-edit" href="product_edit.php?id=<?= (int)$p['id'] ?>">Editar</a></td>
        </tr>
        <?php endforeach; if(empty($low_stock)): ?>
        <tr><td colspan="5" class="muted">Sin alertas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
