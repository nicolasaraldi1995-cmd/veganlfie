<?php

namespace App\Services;

use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Rehace el precio de venta de todos los productos de una marca cuando cambia
 * su margen o su descuento del proveedor.
 *
 * Sin esto, el porcentaje de la marca se veía en cada producto pero no
 * gobernaba ningún precio: se podía poner 100% de margen y el precio quedaba
 * donde estaba. Un número que no manda sobre nada no sirve para nada.
 *
 * Es el mismo criterio que ya tenía el IVA por marca (ver IvaPorMarca): tocar
 * la marca mueve los precios de sus productos.
 *
 * Sólo toca las presentaciones que tienen costo cargado. Sin costo no hay
 * cuenta que hacer -- el precio de esas viene de la lista del proveedor, no de
 * esta fórmula, y pisarlo con cualquier cosa sería peor que no hacer nada.
 */
class PreciosPorMarca
{
    /**
     * @return array{tocadas: int, sinCosto: int, iguales: int}
     */
    public function aplicar(Marca $marca): array
    {
        $stats = ['tocadas' => 0, 'sinCosto' => 0, 'iguales' => 0];

        DB::transaction(function () use ($marca, &$stats) {
            // Con los borrados incluidos, igual que IvaPorMarca: un producto que
            // se restaure después no puede quedar con el precio viejo.
            $productoIds = Producto::withTrashed()->where('marca_id', $marca->id)->pluck('id');

            Presentacion::withTrashed()
                // margenEfectivo() pregunta por el producto y su marca: sin esto
                // serían dos consultas por cada presentación.
                ->with('producto.marca')
                ->whereIn('producto_id', $productoIds)
                ->chunkById(200, function ($presentaciones) use (&$stats) {
                    foreach ($presentaciones as $presentacion) {
                        $this->rehacer($presentacion, $stats);
                    }
                });
        });

        return $stats;
    }

    /**
     * @param  array{tocadas: int, sinCosto: int, iguales: int}  $stats
     */
    private function rehacer(Presentacion $presentacion, array &$stats): void
    {
        if ((float) $presentacion->precio_costo <= 0) {
            $stats['sinCosto']++;

            return;
        }

        $nuevo = Presentacion::calcularPrecio(
            $presentacion->precio_costo,
            $presentacion->margenEfectivo(),
            $presentacion->descuentoEfectivo(),
            (bool) $presentacion->iva,
        );

        // null = falta el margen incluso contando el de la marca.
        if ($nuevo === null) {
            $stats['sinCosto']++;

            return;
        }

        // Comparación con tolerancia: precio viene de la base como decimal y
        // compararlo con === contra un float redondeado falla por nada.
        if (abs((float) $presentacion->precio - $nuevo) < 0.005) {
            $stats['iguales']++;

            return;
        }

        $presentacion->precio = $nuevo;

        // La oferta por porcentaje se recalcula sobre el precio nuevo; si el
        // precio de oferta se puso a mano, se respeta tal cual quedó.
        if ($presentacion->oferta_porcentaje) {
            $presentacion->oferta_precio = round(
                $nuevo * (1 - (float) $presentacion->oferta_porcentaje / 100),
                2
            );
        }

        // saveQuietly: no hay que despertar observadores de stock ni de precio
        // por un recálculo en masa.
        $presentacion->saveQuietly();
        $stats['tocadas']++;
    }
}
