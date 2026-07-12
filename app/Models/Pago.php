<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = ['pedido_id', 'monto', 'metodo', 'fecha', 'notas'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public const METODOS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'mercadopago' => 'MercadoPago',
        'otro' => 'Otro',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
