<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $fillable = ['concepto', 'tipo', 'monto', 'fecha', 'notas'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public const TIPOS = [
        'comisionista' => 'Comisionista',
        'proveedor' => 'Proveedor',
        'logistica' => 'Logística',
        'otro' => 'Otro',
    ];
}
