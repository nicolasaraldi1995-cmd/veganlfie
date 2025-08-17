<?php $active='orders'; $page_title='Pedidos'; require __DIR__."/_layout_top.php"; 
$orders = $pdo->query("SELECT id, total, estado, created_at FROM pedidos ORDER BY id DESC LIMIT 200")->fetchAll();
?>
  <div class="table-container">
    <div class="table-title">Pedidos</div>
    <table>
      <thead><tr><th>#</th><th>Fecha</th><th>Estado</th><th>Total</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($orders as $o): ?>
        <tr>
          <td><?= (int)$o["id"] ?></td>
          <td><?= htmlspecialchars($o["created_at"]) ?></td>
          <td><span class="status <?= $o["estado"]==='nuevo'?'status-pending':($o['estado']==='completado'?'status-completed':'status-processing') ?>"><?= htmlspecialchars($o["estado"]) ?></span></td>
          <td>$ <?= money_ar($o["total"]) ?></td>
          <td><a class="action-btn btn-view" href="order.php?id=<?= (int)$o['id'] ?>">Ver</a></td>
        </tr>
        <?php endforeach; if(empty($orders)): ?>
        <tr><td colspan="5" class="muted">Sin pedidos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
