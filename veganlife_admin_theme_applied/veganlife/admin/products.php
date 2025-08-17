<?php $active='products'; $page_title='Productos'; require __DIR__."/_layout_top.php"; 
$cat = (int)($_GET["cat"] ?? 0);
$brand = (int)($_GET["brand"] ?? 0);
if (!$cat || !$brand){ echo '<div class="table-container"><div class="table-title">Faltan parámetros</div></div>'; require __DIR__."/_layout_bottom.php"; exit; }
$st = $pdo->prepare("SELECT p.*, m.nombre AS marca, c.nombre AS categoria
                     FROM productos p
                     JOIN marcas m ON m.id = p.marca_id
                     JOIN categorias c ON c.id = p.categoria_id
                     WHERE p.categoria_id=:c AND p.marca_id=:b");
$st->execute([":c"=>$cat, ":b"=>$brand]);
$items = $st->fetchAll();
ordenar_por_presentacion($items);
?>
  <div class="table-container">
    <div class="table-title">Productos</div>
    <table>
      <thead><tr><th>#</th><th>Producto</th><th>Marca</th><th>Categoría</th><th>Unidad</th><th>Precio</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($items as $p): ?>
        <tr>
          <td><?= (int)$p["id"] ?></td>
          <td><?= htmlspecialchars($p["nombre"]) ?></td>
          <td class="muted"><?= htmlspecialchars($p["marca"]) ?></td>
          <td class="muted"><?= htmlspecialchars($p["categoria"]) ?></td>
          <td><?= htmlspecialchars($p["unidad"]) ?></td>
          <td>$ <?= money_ar($p["precio"]) ?></td>
          <td><a class="action-btn btn-edit" href="product_edit.php?id=<?= (int)$p['id'] ?>">Editar</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
