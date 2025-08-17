<?php
require __DIR__."/../config/db.php"; require __DIR__."/../config/functions.php";
$cart = $_SESSION["cart"] ?? [];
if (empty($cart)){ echo '<div class="muted">Tu carrito está vacío.</div>'; return; }
$total = 0;
foreach ($cart as $pid => $item){
  $total += ($item["precio"] * $item["qty"]);
  $img = $item["imagen"] ?: "https://picsum.photos/seed/p".$pid."/200/200"; ?>
  <div class="cart-item">
    <img src="<?= htmlspecialchars($img) ?>" alt="">
    <div style="flex:1;">
      <div style="font-weight:700;"><?= htmlspecialchars($item["nombre"]) ?></div>
      <div class="muted"><?= htmlspecialchars($item["marca"]) ?> · <?= htmlspecialchars($item["unidad"]) ?></div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.35rem;">
        <div class="qty"><span>Cant.</span><input type="number" min="1" data-qty-id="<?= (int)$pid ?>" value="<?= (int)$item["qty"] ?>"></div>
        <div>$ <?= money_ar($item["precio"]*$item["qty"]) ?></div>
      </div>
    </div>
    <button class="btn outline" data-del-id="<?= (int)$pid ?>">X</button>
  </div>
<?php } ?>
<div style="display:flex;align-items:center;justify-content:space-between; font-weight:800; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.08);">
  <div>Total</div><div>$ <?= money_ar($total) ?></div>
</div>
