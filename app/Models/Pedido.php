<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'estado', 'total', 'datos_cliente'];

    protected $casts = [
        'total' => 'decimal:2',
        'datos_cliente' => 'array',
    ];

    const ESTADOS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'En preparación',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'canceled' => 'Cancelado',
    ];

    public function esEditable(): bool
    {
        return $this->estado === 'pending';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function getTotalPagadoAttribute(): float
    {
        return (float) $this->pagos()->sum('monto');
    }

    public function getSaldoAttribute(): float
    {
        return (float) $this->total - $this->total_pagado;
    }

    public function recalcularTotal(): void
    {
        $this->update(['total' => $this->items()->sum('subtotal')]);
    }

    /**
     * Devuelve al stock las cantidades reservadas por este pedido.
     * Se usa al cancelar un pedido; los items no se borran, así que
     * PedidoItemObserver no dispara automáticamente en este caso.
     */
    public function restaurarStock(): void
    {
        DB::transaction(function () {
            foreach ($this->items as $item) {
                Presentacion::whereKey($item->presentacion_id)
                    ->lockForUpdate()
                    ->increment('stock', $item->cantidad);
            }
        });
    }
}
