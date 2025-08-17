<?php require __DIR__."/config/db.php"; require __DIR__."/config/functions.php"; ?>
<?php include __DIR__."/components/header.php"; ?>
<section id="hero" class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content container">
    <span class="ribbon">Catálogo Veganlife</span>
    <h1>Distribuidor oficial de Pergamino y la zona</h1>
    <p>Catálogo con búsqueda en tiempo real, filtros por marcas y categorías, y carrito deslizable.</p>
    <a class="btn" href="/catalogo.php">Ver catálogo</a>
  </div>
</section>
<div class="container">
  <div class="banner-slider">
    <div class="slide-track">
      <div class="slide" style="background-image:linear-gradient(135deg, rgba(34,211,238,.22), rgba(200,238,234,.12)), url('https://picsum.photos/seed/vegan1/1200/400');"></div>
      <div class="slide" style="background-image:linear-gradient(135deg, rgba(34,211,238,.22), rgba(200,238,234,.12)), url('https://picsum.photos/seed/vegan2/1200/400');"></div>
      <div class="slide" style="background-image:linear-gradient(135deg, rgba(34,211,238,.22), rgba(200,238,234,.12)), url('https://picsum.photos/seed/vegan3/1200/400');"></div>
    </div>
  </div>
</div>
<?php include __DIR__."/components/footer.php"; ?>
