<?php $active='brands'; $page_title='Marca'; require __DIR__."/_layout_top.php"; 
$id = (int)($_GET["id"] ?? 0);
$brand = $pdo->prepare("SELECT * FROM marcas WHERE id=:id"); $brand->execute([":id"=>$id]); $brand = $brand->fetch();
if (!$brand){ echo '<div class="table-container"><div class="table-title">Marca no encontrada</div></div>'; require __DIR__."/_layout_bottom.php"; exit; }
$cats = $pdo->prepare("SELECT DISTINCT c.id, c.nombre FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.marca_id = :id ORDER BY c.nombre");
$cats->execute([":id"=>$id]); $cats = $cats->fetchAll();
?>
  <div class="table-container">
    <div class="table-title">Marca: <?= htmlspecialchars($brand["nombre"]) ?></div>
    <table>
      <thead><tr><th>#</th><th>Categoría</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($cats as $c): ?>
        <tr>
          <td><?= (int)$c["id"] ?></td>
          <td><?= htmlspecialchars($c["nombre"]) ?></td>
          <td><a class="action-btn btn-view" href="products.php?brand=<?= (int)$id ?>&cat=<?= (int)$c['id'] ?>">Ver productos</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
