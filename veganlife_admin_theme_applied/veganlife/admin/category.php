<?php $active='categories'; $page_title='Categoría'; require __DIR__."/_layout_top.php"; 
$id = (int)($_GET["id"] ?? 0);
$cat = $pdo->prepare("SELECT * FROM categorias WHERE id=:id"); $cat->execute([":id"=>$id]); $cat = $cat->fetch();
if (!$cat){ echo '<div class="table-container"><div class="table-title">Categoría no encontrada</div></div>'; require __DIR__."/_layout_bottom.php"; exit; }
$brands = $pdo->prepare("SELECT DISTINCT m.id, m.nombre FROM productos p JOIN marcas m ON m.id = p.marca_id WHERE p.categoria_id = :id ORDER BY m.nombre");
$brands->execute([":id"=>$id]); $brands = $brands->fetchAll();
?>
  <div class="table-container">
    <div class="table-title">Categoría: <?= htmlspecialchars($cat["nombre"]) ?></div>
    <table>
      <thead><tr><th>#</th><th>Marca</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($brands as $b): ?>
        <tr>
          <td><?= (int)$b["id"] ?></td>
          <td><?= htmlspecialchars($b["nombre"]) ?></td>
          <td><a class="action-btn btn-view" href="products.php?cat=<?= (int)$id ?>&brand=<?= (int)$b['id'] ?>">Ver productos</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
