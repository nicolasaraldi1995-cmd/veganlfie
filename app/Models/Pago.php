<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = ['pedido_id', 'user_id', 'monto', 'metodo', 'fecha', 'notas'];

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

    protected static function booted(): void
    {
        // Un pago general (sin pedido_id) ya trae su user_id. Uno ligado a un
        // pedido no lo trae desde los formularios existentes: lo copiamos del
        // pedido para que "total pagado por cliente" sea siempre un WHERE
        // user_id simple, sin joins.
        static::creating(function (Pago $pago) {
            if (! $pago->user_id && $pago->pedido_id) {
                $pago->user_id = Pedido::whereKey($pago->pedido_id)->value('user_id');
            }
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
