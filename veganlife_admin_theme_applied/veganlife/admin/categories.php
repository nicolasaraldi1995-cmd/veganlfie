<?php $active='categories'; $page_title='Categories'; require __DIR__."/_layout_top.php"; 
$items = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
?>
  <div class="table-container">
    <div class="table-title">Categorías</div>
    <table>
      <thead><tr><th>#</th><th>Nombre</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?= (int)$it["id"] ?></td>
          <td><?= htmlspecialchars($it["nombre"]) ?></td>
          <td>
            <?php if('categories'==='categories'): ?>
              <a class="action-btn btn-view" href="category.php?id=<?= (int)$it['id'] ?>">Ver marcas</a>
            <?php else: ?>
              <a class="action-btn btn-view" href="brand.php?id=<?= (int)$it['id'] ?>">Ver categorías</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
