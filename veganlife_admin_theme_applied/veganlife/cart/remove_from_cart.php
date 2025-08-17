<?php require __DIR__."/../config/db.php";
$id = (int)($_POST["id"] ?? 0); if ($id > 0 && isset($_SESSION["cart"][$id])){ unset($_SESSION["cart"][$id]); } echo "OK";
