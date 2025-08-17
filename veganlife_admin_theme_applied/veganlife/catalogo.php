<?php require __DIR__."/config/db.php"; require __DIR__."/config/functions.php";
$cats = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
$brands = $pdo->query("SELECT id, nombre FROM marcas ORDER BY nombre")->fetchAll();
?>
<?php include __DIR__."/components/header.php"; ?>
<div class="container">
  <div class="layout">
    <aside class="sidebar">
      <h3>Filtros</h3>
      <div class="section">
        <label for="searchInput" style="display:block;margin-bottom:.35rem;color:var(--muted);">Buscador (en tiempo real)</label>
        <input id="searchInput" type="text" placeholder="Ej: alf rinc" style="width:100%;padding:.6rem .7rem;border-radius:12px;border:1px solid rgba(255,255,255,.18);background:#0b0d10;color:var(--text);">
      </div>
      <div class="section">
        <div style="font-weight:700;margin-bottom:.35rem;">Categorías</div>
        <div class="chips">
          <?php foreach($cats as $c): ?>
          <div class="chip" data-chip-cat="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c["nombre"]) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="section">
        <div style="font-weight:700;margin-bottom:.35rem;">Marcas</div>
        <div class="chips">
          <?php foreach($brands as $b): ?>
          <div class="chip" data-chip-brand="<?= htmlspecialchars($b['id']) ?>"><?= htmlspecialchars($b["nombre"]) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="section">
        <div style="font-weight:700;margin-bottom:.35rem;">Atributos</div>
        <label style="display:flex;align-items:center;gap:.5rem;margin:.35rem 0;"><input type="checkbox" id="f_st"> SIN T.A.C.C.</label>
        <label style="display:flex;align-items:center;gap:.5rem;margin:.35rem 0;"><input type="checkbox" id="f_cong"> Congelados</label>
        <label style="display:flex;align-items:center;gap:.5rem;margin:.35rem 0;"><input type="checkbox" id="f_veg"> Veganlife</label>
      </div>
    </aside>
    <main><div id="gridProducts" class="grid"></div></main>
  </div>
</div>
<?php include __DIR__."/components/footer.php"; ?>
