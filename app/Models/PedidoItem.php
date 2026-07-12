<?php

namespace App\Models;

use App\Observers\PedidoItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PedidoItemObserver::class)]
class PedidoItem extends Model
{
    protected $fillable = ['pedido_id', 'presentacion_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }
}
