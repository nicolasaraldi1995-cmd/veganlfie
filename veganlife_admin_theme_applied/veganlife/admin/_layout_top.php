<?php require __DIR__."/../config/db.php"; require __DIR__."/../config/functions.php"; require_admin(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · Veganlife</title>
  <link rel="stylesheet" href="../assets/css/admin_theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">VL</div>
      <div class="sidebar-title">VEGANLIFE<br>Admin</div>
    </div>
    <div class="sidebar-menu">
      <a class="menu-item <?php if(($active??'')==='dashboard') echo 'active'; ?>" href="index.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
      <a class="menu-item <?php if(($active??'')==='orders') echo 'active'; ?>" href="orders.php"><i class="fas fa-shopping-cart"></i><span>Pedidos</span></a>
      <a class="menu-item <?php if(($active??'')==='products') echo 'active'; ?>" href="all_products.php"><i class="fas fa-box-open"></i><span>Productos</span></a>
      <a class="menu-item <?php if(($active??'')==='categories') echo 'active'; ?>" href="categories.php"><i class="fas fa-list"></i><span>Categorías</span></a>
      <a class="menu-item <?php if(($active??'')==='brands') echo 'active'; ?>" href="brands.php"><i class="fas fa-tags"></i><span>Marcas</span></a>
      <a class="menu-item <?php if(($active??'')==='import') echo 'active'; ?>" href="import_csv.php"><i class="fas fa-file-import"></i><span>Importar CSV</span></a>
      <a class="menu-item <?php if(($active??'')==='scan') echo 'active'; ?>" href="scan.php"><i class="fas fa-barcode"></i><span>Escaneo</span></a>
      <a class="menu-item <?php if(($active??'')==='settings') echo 'active'; ?>" href="settings.php"><i class="fas fa-cog"></i><span>Configuración</span></a>
    </div>
  </div>

  <!-- Main content -->
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
      <div class="user-profile">
        <div class="user-avatar">AD</div>
        <div>Admin</div>
        <a class="btn-secondary" href="logout.php" style="text-decoration:none; padding:8px 10px; border-radius:12px; border:1px solid var(--gris-medio);">Salir</a>
      </div>
    </div>
