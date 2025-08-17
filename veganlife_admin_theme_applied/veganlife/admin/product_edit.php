<?php $active='products'; $page_title='Editar producto'; require __DIR__."/_layout_top.php"; 
$id = (int)($_GET["id"] ?? 0);
$st = $pdo->prepare("SELECT * FROM productos WHERE id=:id"); $st->execute([":id"=>$id]); $p = $st->fetch();
if (!$p){ echo '<div class="table-container"><div class="table-title">Producto no encontrado</div></div>'; require __DIR__."/_layout_bottom.php"; exit; }
if ($_SERVER["REQUEST_METHOD"] === "POST"){
  $nombre = trim($_POST["nombre"] ?? "");
  $unidad = trim($_POST["unidad"] ?? "");
  $precio = (float)($_POST["precio"] ?? 0);
  $imagen = trim($_POST["imagen"] ?? "");
  $sin_tacc = isset($_POST["sin_tacc"]) ? 1 : 0;
  $congelado = isset($_POST["congelado"]) ? 1 : 0;
  $veganlife = isset($_POST["veganlife"]) ? 1 : 0;
  $pdo->prepare("UPDATE productos SET nombre=:n, unidad=:u, precio=:p, imagen=:img, sin_tacc=:st, congelado=:cg, veganlife=:vg WHERE id=:id")
      ->execute([":n"=>$nombre, ":u"=>$unidad, ":p"=>$precio, ":img"=>$imagen, ":st"=>$sin_tacc, ":cg"=>$congelado, ":vg"=>$veganlife, ":id"=>$id]);
  header("Location: all_products.php"); exit;
}
?>
  <div class="form-container">
    <div class="table-title">Editar producto #<?= (int)$p["id"] ?></div>
    <form method="post">
      <label class="form-label">Nombre</label>
      <input class="form-control" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
        <div>
          <label class="form-label">Unidad</label>
          <input class="form-control" name="unidad" value="<?= htmlspecialchars($p['unidad']) ?>">
        </div>
        <div>
          <label class="form-label">Precio</label>
          <input class="form-control" name="precio" type="number" step="0.01" value="<?= htmlspecialchars($p['precio']) ?>">
        </div>
      </div>
      <label class="form-label" style="margin-top:12px;">URL de imagen</label>
      <input class="form-control" name="imagen" value="<?= htmlspecialchars($p['imagen']) ?>">
      <div style="display:flex;gap:16px;margin:12px 0;">
        <label><input type="checkbox" name="sin_tacc" <?= $p["sin_tacc"]?'checked':''; ?>> SIN T.A.C.C.</label>
        <label><input type="checkbox" name="congelado" <?= $p["congelado"]?'checked':''; ?>> Congelado</label>
        <label><input type="checkbox" name="veganlife" <?= $p["veganlife"]?'checked':''; ?>> Veganlife</label>
      </div>
      <button class="btn btn-primary">Guardar cambios</button>
      <a class="btn btn-secondary" href="all_products.php" style="margin-left:8px;">Volver</a>
    </form>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
