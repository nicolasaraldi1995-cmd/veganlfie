<?php
require __DIR__."/../config/db.php";
$id = (int)($_POST["id"] ?? 0); $qty = max(1, (int)($_POST["qty"] ?? 1));
if ($id <= 0){ http_response_code(400); exit("ID inválido"); }
$st = $pdo->prepare("SELECT p.id, p.nombre, p.unidad, p.precio, p.imagen, m.nombre AS marca
                     FROM productos p JOIN marcas m ON m.id = p.marca_id WHERE p.id = :id");
$st->execute([":id"=>$id]); $p = $st->fetch(); if (!$p){ http_response_code(404); exit("No encontrado"); }
if (!isset($_SESSION["cart"])) $_SESSION["cart"] = [];
if (!isset($_SESSION["cart"][$id])){ $_SESSION["cart"][$id] = ["nombre"=>$p["nombre"], "unidad"=>$p["unidad"], "precio"=>$p["precio"], "imagen"=>$p["imagen"], "marca"=>$p["marca"], "qty"=>0]; }
$_SESSION["cart"][$id]["qty"] += $qty; echo "OK";
