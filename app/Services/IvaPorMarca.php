<?php

namespace App\Services;

use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Prende o apaga el IVA en todos los productos de una marca.
 *
 * Al prenderlo el precio sube el 21%, al apagarlo vuelve atrás. Cuando la
 * presentación tiene costo y margen cargados se rehace el cálculo completo
 * (costo, descuento del proveedor, margen y recién ahí el IVA), que es más
 * exacto. Cuando no los tiene -- que hoy es la mayoría-- se aplica el 21%
 * sobre el precio de venta, para que la marca no quede a medias.
 */
class IvaPorMarca
{
    public const FACTOR = 1.21;

    /**
     * @return array{presentaciones: int, recalculadas: int, ajustadas: int}
     */
    public function aplicar(Marca $marca, bool $conIva): array
    {
        $stats = ['presentaciones' => 0, 'recalculadas' => 0, 'ajustadas' => 0];

        DB::transaction(function () use ($marca, $conIva, &$stats) {
            // Se resuelven los productos aparte (y con los borrados incluidos)
            // para que un producto que se restaure más adelante no quede con el
            // precio de antes del cambio.
            $productoIds = Producto::withTrashed()->where('marca_id', $marca->id)->pluck('id');

            Presentacion::withTrashed()
                ->whereIn('producto_id', $productoIds)
                // Las que ya están como corresponde no se tocan: así prender dos
                // veces la misma marca no suma el 21% dos veces.
                ->where(fn ($q) => $q->where('iva', '!=', $conIva)->orWhereNull('iva'))
                ->chunkById(200, function ($presentaciones) use ($conIva, &$stats) {
                    foreach ($presentaciones as $presentacion) {
                        $stats['presentaciones']++;

                        $precio = $this->precioRecalculado($presentacion, $conIva);

                        if ($precio !== null) {
                            $stats['recalculadas']++;
                        } else {
                            $precio = $conIva
                                ? (float) $presentacion->precio * self::FACTOR
                                : (float) $presentacion->precio / self::FACTOR;
                            $stats['ajustadas']++;
                        }

                        $presentacion->precio = round($precio, 2);
                        $presentacion->iva = $conIva;

                        // La oferta por porcentaje se recalcula sobre el precio
                        // nuevo; si el precio de oferta se puso a mano, se
                        // respeta tal cual quedó.
                        if ($presentacion->oferta_porcentaje) {
                            $presentacion->oferta_precio = round(
                                $presentacion->precio * (1 - (float) $presentacion->oferta_porcentaje / 100),
                                2
                            );
                        }

                        $presentacion->saveQuietly();
                    }
                });
        });

        return $stats;
    }

    /**
     * El precio rehecho desde el costo, o null si faltan los datos para hacerlo.
     */
    private function precioRecalculado(Presentacion $presentacion, bool $conIva): ?float
    {
        $costo = (float) $presentacion->precio_costo;
        $margen = $presentacion->margen_porcentaje;

        if ($costo <= 0 || $margen === null) {
            return null;
        }

        $descuento = (float) ($presentacion->descuento_porcentaje ?? 0);
        $precio = $costo * (1 - $descuento / 100) * (1 + (float) $margen / 100);

        return $conIva ? $precio * self::FACTOR : $precio;
    }
}
