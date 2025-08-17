<?php require __DIR__."/../config/db.php";
$email = $_GET["email"] ?? "admin@veganlife"; $pass = $_GET["pass"] ?? "admin123";
$hash = password_hash($pass, PASSWORD_BCRYPT);
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_usuarios(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$st = $pdo->prepare("INSERT INTO admin_usuarios (nombre, email, password_hash) VALUES ('Admin', :e, :h)
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), nombre=VALUES(nombre)");
$st->execute([":e"=>$email, ":h"=>$hash]);
echo "OK - Admin actualizado para DB={$GLOBALS['DB_NAME']} - email=$email - pass=$pass";
