<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['envio_gratis_desde', 'controlar_stock'];

    protected $casts = [
        'envio_gratis_desde' => 'decimal:2',
        'controlar_stock' => 'boolean',
    ];

    /**
     * Memoizado en el contenedor (una vez por request/app lifetime): se llama
     * una vez por ítem de pedido al descontar stock, y no cambia dentro de un
     * mismo request. Usar el contenedor en vez de una propiedad estática
     * evita que el valor quede pegado entre tests, que corren en un solo
     * proceso PHP largo.
     */
    public static function actual(): self
    {
        if (! app()->bound(static::class.'@actual')) {
            app()->instance(
                static::class.'@actual',
                static::firstOrCreate(['id' => 1], ['envio_gratis_desde' => 600000, 'controlar_stock' => true])
            );
        }

        return app(static::class.'@actual');
    }
}
