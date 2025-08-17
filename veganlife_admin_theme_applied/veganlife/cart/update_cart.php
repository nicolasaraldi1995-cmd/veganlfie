<?php require __DIR__."/../config/db.php";
$id = (int)($_POST["id"] ?? 0); $qty = max(1, (int)($_POST["qty"] ?? 1));
if ($id <= 0 || empty($_SESSION["cart"][$id])){ http_response_code(400); exit("Inválido"); }
$_SESSION["cart"][$id]["qty"] = $qty; echo "OK";
