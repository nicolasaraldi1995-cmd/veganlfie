<?php
require __DIR__."/../config/db.php"; require __DIR__."/../config/functions.php";
$cart = $_SESSION["cart"] ?? []; if (empty($cart)){ echo json_encode(["ok"=>false]); exit; }
$pdo->beginTransaction();
try{
  $total = 0; foreach($cart as $pid=>$item){ $total += $item["precio"] * $item["qty"]; }
  $pdo->prepare("INSERT INTO pedidos (usuario_id, total, estado, created_at) VALUES (NULL, :t, 'nuevo', NOW())")->execute([":t"=>$total]);
  $pedido_id = (int)$pdo->lastInsertId();
  $ins = $pdo->prepare("INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario) VALUES (:p, :prod, :c, :pu)");
  foreach ($cart as $pid=>$item){ $ins->execute([":p"=>$pedido_id, ":prod"=>$pid, ":c"=>$item["qty"], ":pu"=>$item["precio"]]); }
  $pdo->commit(); $_SESSION["cart"] = []; echo json_encode(["ok"=>true, "pedido_id"=>$pedido_id]);
}catch(Exception $e){ $pdo->rollBack(); echo json_encode(["ok"=>false, "error"=>$e->getMessage()]); }
