<?php
function money_ar($num){
  if ($num === null) return "";
  return number_format((float)$num, 0, ",", ".");
}

function is_admin(){ return !empty($_SESSION["admin_id"]); }
function require_admin(){
  if (!is_admin()){
    header("Location: login.php");
    exit;
  }
}

function tokens_from($s){
  $s = trim($s ?? "");
  if ($s === "") return [];
  $parts = preg_split("/\s+/", $s);
  return array_values(array_filter($parts, fn($p) => mb_strlen($p) >= 2));
}

function where_busqueda($tokens, &$params){
  if (empty($tokens)) return "1";
  $campos = ["p.nombre", "m.nombre", "c.nombre"];
  $ands = [];
  $i = 0;
  foreach ($tokens as $t){
    $ors = [];
    foreach ($campos as $campo){
      $key = ":t$i";
      $ors[] = "$campo LIKE $key";
      $params[$key] = "%" . $t . "%";
      $i++;
    }
    $ands[] = "(" . implode(" OR ", $ors) . ")";
  }
  return implode(" AND ", $ands);
}

function ordenar_por_presentacion(&$productos){
  usort($productos, function($a, $b){
    $ka = unidad_sort_key($a["unidad"] ?? "");
    $kb = unidad_sort_key($b["unidad"] ?? "");
    if ($ka[1] !== $kb[1]) return $ka[1] <=> $kb[1];
    if ($ka[0] !== $kb[0]) return $ka[0] <=> $kb[0];
    return strcmp($a["nombre"] ?? "", $b["nombre"] ?? "");
  });
}

function unidad_sort_key($unidad){
  $u = strtolower(trim($unidad ?? ""));
  if ($u === "") return [PHP_INT_MAX, 99];
  $u = str_replace(",", ".", $u);
  if (preg_match("/^(\d+(?:\.\d+)?)\s*u$/", $u, $m)) return [floatval($m[1]), 0];
  if (preg_match("/^(\d+(?:\.\d+)?)\s*ml$/", $u, $m)) return [floatval($m[1]), 1];
  if (preg_match("/^(\d+(?:\.\d+)?)\s*(l|lt)$/", $u, $m)) return [floatval($m[1])*1000, 2];
  if (preg_match("/^(\d+(?:\.\d+)?)\s*g$/", $u, $m)) return [floatval($m[1]), 1];
  if (preg_match("/^(\d+(?:\.\d+)?)\s*kg$/", $u, $m)) return [floatval($m[1])*1000, 2];
  if (preg_match("/^(\d+(?:\.\d+)?)/", $u, $m)) return [floatval($m[1]), 50];
  return [PHP_INT_MAX, 99];
}
?>
