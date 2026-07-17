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

    public static function actual(): self
    {
        return static::firstOrCreate(['id' => 1], ['envio_gratis_desde' => 600000, 'controlar_stock' => true]);
    }
}
