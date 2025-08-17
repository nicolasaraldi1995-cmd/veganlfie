<?php $active='orders'; $page_title='Detalle de pedido'; require __DIR__."/_layout_top.php"; 
$id = (int)($_GET["id"] ?? 0);
$st = $pdo->prepare("SELECT * FROM pedidos WHERE id=:id"); $st->execute([":id"=>$id]); $o = $st->fetch();
if (!$o){ echo '<div class="table-container"><div class="table-title">Pedido no encontrado</div></div>'; require __DIR__."/_layout_bottom.php"; exit; }
$items = $pdo->prepare("SELECT d.*, p.nombre, p.unidad, m.nombre AS marca
                        FROM pedido_detalles d
                        JOIN productos p ON p.id = d.producto_id
                        JOIN marcas m ON m.id = p.marca_id
                        WHERE d.pedido_id=:id");
$items->execute([":id"=>$id]); $items = $items->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST"){
  if (isset($_POST["del_item"])){
    $did = (int)$_POST["del_item"];
    $pdo->prepare("DELETE FROM pedido_detalles WHERE id=:id")->execute([":id"=>$did]);
  }
  if (isset($_POST["upd_item_id"])){
    $did = (int)$_POST["upd_item_id"];
    $qty = max(1, (int)$_POST["upd_qty"]);
    $pdo->prepare("UPDATE pedido_detalles SET cantidad=:c WHERE id=:id")->execute([":c"=>$qty, ":id"=>$did]);
  }
  header("Location: order.php?id=".$id); exit;
}
?>
  <div class="table-container">
    <div class="table-title">Pedido #<?= (int)$o["id"] ?> · $ <?= money_ar($o["total"]) ?></div>
    <table>
      <thead><tr><th>Producto</th><th>Marca</th><th>Unidad</th><th>Cantidad</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it["nombre"]) ?></td>
          <td class="muted"><?= htmlspecialchars($it["marca"]) ?></td>
          <td class="muted"><?= htmlspecialchars($it["unidad"]) ?></td>
          <td>
            <form method="post" style="display:flex;gap:.5rem;align-items:center;">
              <input type="hidden" name="upd_item_id" value="<?= (int)$it['id'] ?>">
              <input class="form-control" name="upd_qty" type="number" min="1" value="<?= (int)$it['cantidad'] ?>" style="width:100px">
              <button class="action-btn btn-edit">Actualizar</button>
            </form>
          </td>
          <td>
            <form method="post">
              <input type="hidden" name="del_item" value="<?= (int)$it['id'] ?>">
              <button class="action-btn btn-view">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; if(empty($items)): ?>
          <tr><td colspan="5" class="muted">El pedido no tiene ítems.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
