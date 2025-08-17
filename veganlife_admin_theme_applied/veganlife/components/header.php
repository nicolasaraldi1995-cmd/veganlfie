<?php /* Topbar pública */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Veganlife</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <script defer src="/assets/js/app.js"></script>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner container">
    <div class="logo"><span class="dot"></span><span>Veganlife</span></div>
    <nav class="nav">
      <a class="btn outline" href="/#hero">Inicio</a>
      <a class="btn" href="/catalogo.php">Catálogo</a>
    </nav>
    <button class="btn cart-button" onclick="cartDrawer.open()">Carrito</button>
  </div>
</header>
