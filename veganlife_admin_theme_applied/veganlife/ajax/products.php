<?php
require __DIR__."/../config/db.php"; require __DIR__."/../config/functions.php";
$q = trim($_GET["q"] ?? ""); $cat = trim($_GET["cat"] ?? ""); $brand = trim($_GET["brand"] ?? "");
$sin_tacc = isset($_GET["sin_tacc"]) ? 1 : 0; $congelado = isset($_GET["congelado"]) ? 1 : 0; $veganlife = isset($_GET["veganlife"]) ? 1 : 0;
$params = []; $where = []; $tokens = tokens_from($q); $wb = where_busqueda($tokens, $params); $where[] = $wb;
if ($cat !== "") { $where[] = "p.categoria_id = :cat"; $params[":cat"] = $cat; }
if ($brand !== "") { $where[] = "p.marca_id = :brand"; $params[":brand"] = $brand; }
if ($sin_tacc) { $where[] = "p.sin_tacc = 1"; } if ($congelado) { $where[] = "p.congelado = 1"; } if ($veganlife) { $where[] = "p.veganlife = 1"; }
$whereSql = implode(" AND ", array_filter($where)); if ($whereSql === "") $whereSql = "1";
$sql = "SELECT p.id, p.nombre, p.unidad, p.precio, p.imagen, m.nombre AS marca, c.nombre AS categoria
        FROM productos p JOIN marcas m ON m.id = p.marca_id JOIN categorias c ON c.id = p.categoria_id
        WHERE $whereSql LIMIT 500";
$st = $pdo->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k, $v); } $st->execute(); $items = $st->fetchAll();
ordenar_por_presentacion($items);
if (empty($items)){ echo '<div class="card"><div class="pad">Sin resultados</div></div>'; exit; }
foreach($items as $p): $img = $p["imagen"] ?: "https://picsum.photos/seed/p".$p["id"]."/400/300"; $precio = money_ar($p["precio"]); ?>
<div class="card">
  <img src="<?= htmlspecialchars($img) ?>" alt="">
  <div class="pad">
    <div class="title"><?= htmlspecialchars($p["nombre"]) ?></div>
    <div class="muted"><?= htmlspecialchars($p["marca"]) ?> · <?= htmlspecialchars($p["unidad"] ?? "") ?></div>
    <div class="row">
      <div class="price">$ <?= $precio ?></div>
      <a href="#" class="btn" data-add="<?= (int)$p['id'] ?>">Agregar</a>
    </div>
  </div>
</div>
<?php endforeach; ?>
