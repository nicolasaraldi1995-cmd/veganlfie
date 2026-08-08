<?php

namespace App\Models;

use App\Observers\PedidoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[ObservedBy(PedidoObserver::class)]
class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'estado', 'total', 'datos_cliente'];

    protected $casts = [
        'total' => 'decimal:2',
        'datos_cliente' => 'array',
    ];

    /**
     * Al operador no le llega el total. La ficha del pedido ya se lo esconde en
     * pantalla, pero viajaba igual dentro del estado del formulario. El total
     * se recalcula solo al guardar (EditPedido::afterSave), así que nadie lo
     * necesita de vuelta desde el navegador.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();
        $usuario = auth()->user();

        if (($usuario?->isOperador() ?? false) && ! $usuario->isAdmin()) {
            unset($data['total']);
        }

        return $data;
    }

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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PedidoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
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
     * Devuelve al stock las cantidades reservadas por este pedido. Se usa al
     * cancelar: los items no se borran, así que PedidoItemObserver no dispara.
     */
    public function restaurarStock(): void
    {
        $this->moverStock(1);
    }

    /**
     * Vuelve a reservar las cantidades de este pedido. Se usa al revivir un
     * pedido cancelado: sin esto, el stock que se había devuelto al cancelar
     * quedaba de más, y volver a cancelar lo devolvía otra vez.
     */
    public function reservarStock(): void
    {
        $this->moverStock(-1);
    }

    /**
     * @param  int  $signo  1 devuelve al stock, -1 reserva.
     */
    private function moverStock(int $signo): void
    {
        DB::transaction(function () use ($signo) {
            foreach ($this->items as $item) {
                Presentacion::whereKey($item->presentacion_id)
                    ->lockForUpdate()
                    ->increment('stock', $signo * $item->cantidad);
            }
        });
    }
}
