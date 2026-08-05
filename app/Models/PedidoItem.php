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

    /**
     * Al operador no le llegan los importes. La ficha del pedido ya se los
     * esconde en pantalla, pero el valor viajaba igual dentro del estado del
     * formulario y se leía con ver-código-fuente. Al guardar no hace falta:
     * los importes se rearman en el servidor (PedidoResource::precioDeLaBase).
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();

        if ($this->esOperador()) {
            unset($data['precio_unitario'], $data['subtotal']);
        }

        return $data;
    }

    private function esOperador(): bool
    {
        $usuario = auth()->user();

        return ($usuario?->isOperador() ?? false) && ! $usuario->isAdmin();
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }
}
