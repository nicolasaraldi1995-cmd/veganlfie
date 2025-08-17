<?php require __DIR__."/../config/db.php"; require __DIR__."/../config/functions.php";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST"){
  $email = trim($_POST["email"] ?? ""); $pass = $_POST["password"] ?? "";
  $st = $pdo->prepare("SELECT id, email, password_hash, nombre FROM admin_usuarios WHERE email=:e");
  $st->execute([":e"=>$email]); $u = $st->fetch();
  if ($u && password_verify($pass, $u["password_hash"])){
    $_SESSION["admin_id"] = $u["id"]; $_SESSION["admin_name"] = $u["nombre"];
    header("Location: index.php"); exit;
  } else { $error = "Credenciales inválidas"; }
}
?>
<!doctype html><html lang="es"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · Veganlife</title>
  <link rel="stylesheet" href="../assets/css/admin_theme.css">
  <style>
    body{ background:#eef8f7; color:#062a2a; }
    .login-topbar{ background: linear-gradient(90deg, #12a9b3, #089e90); color:#eaffff; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .login-topbar .inner{ max-width:1200px; margin:0 auto; padding:.9rem 1rem; display:flex; align-items:center; gap:.75rem; }
    .badge{ width:36px; height:36px; border-radius:999px; display:grid; place-items:center; background:#c8eeea; color:#04504d; font-weight:900; letter-spacing:.5px; box-shadow: inset 0 0 0 2px rgba(255,255,255,.6); }
    .login-wrap{ max-width:480px; margin:8vh auto; padding:0 1rem; }
    .card-login{ background:#ffffff; border-radius:22px; overflow:hidden; border:1px solid #e6f5f3; box-shadow: 0 10px 40px rgba(8,158,144,.12), 0 2px 10px rgba(0,0,0,.05); }
    .card-head{ background: linear-gradient(135deg, rgba(18,169,179,.15), rgba(8,158,144,.10)); padding:1rem 1.2rem; border-bottom:1px solid #eaf7f6; }
    .card-head h2{ margin:.2rem 0; color:#044c49; }
    .card-body{ padding:1.2rem; }
    label{ font-weight:700; color:#0f5a59; font-size:.95rem; }
    .input{ width:100%; padding:.8rem .9rem; border-radius:14px; border:1px solid #cfecea; background:#f9fefd; color:#063333; }
    .input:focus{ outline:none; border-color:#2ac3b5; box-shadow:0 0 0 3px rgba(42,195,181,.22); }
    .btn-login{ width:100%; padding:.8rem 1rem; border-radius:999px; border:none; cursor:pointer; background:#2ac3b5; color:#003333; font-weight:900; letter-spacing:.3px; box-shadow:0 10px 30px rgba(42,195,181,.25); }
    .btn-login:hover{ transform:translateY(-1px); }
    .error{ background:#ffecec; border:1px solid #ffc3c3; color:#a01818; padding:.75rem 1rem; border-radius:12px; margin:.75rem 0; }
  </style>
</head><body>
  <header class="login-topbar"><div class="inner"><div class="badge">VL</div><div><div style="font-weight:900;letter-spacing:.5px;">Catálogo VEGANLIFE</div><div style="font-size:.9rem;opacity:.85;">Panel de administración</div></div></div></header>
  <div class="login-wrap">
    <div class="card-login">
      <div class="card-head"><h2>Ingresar</h2></div>
      <div class="card-body">
        <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
          <div style="margin-bottom:1rem;"><label>Email</label><input class="input" name="email" type="email" required></div>
          <div style="margin-bottom:1.2rem;"><label>Contraseña</label><input class="input" name="password" type="password" required></div>
          <button class="btn-login">Ingresar</button>
        </form>
      </div>
    </div>
  </div>
</body></html>
