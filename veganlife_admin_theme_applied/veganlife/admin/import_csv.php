<?php $active='import'; $page_title='Importar CSV'; require __DIR__."/_layout_top.php"; ?>
  <div class="form-container">
    <div class="table-title">Importar CSV (placeholder)</div>
    <p class="muted">Subí tu CSV con columnas: nombre, marca, categoria, unidad, precio, [sin_tacc, congelado, veganlife, imagen, stock]</p>
    <form method="post" enctype="multipart/form-data">
      <label class="form-label">Archivo CSV</label>
      <input class="form-control" type="file" name="csv" accept=".csv">
      <div style="margin-top:12px;"><button class="btn btn-primary" disabled>Importar (demo)</button></div>
    </form>
  </div>
<?php require __DIR__."/_layout_bottom.php"; ?>
