<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Presentacion extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $table = 'presentaciones';

    protected $fillable = [
        'producto_id', 'unidad', 'sku', 'imagen', 'precio', 'stock', 'activo',
        'oferta_porcentaje', 'oferta_precio', 'oferta_inicio', 'oferta_fin',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'precio' => 'decimal:2',
        'oferta_porcentaje' => 'decimal:2',
        'oferta_precio' => 'decimal:2',
        'oferta_inicio' => 'date',
        'oferta_fin' => 'date',
        'activo' => 'boolean',
        'stock' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeEnOferta($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('oferta_porcentaje')
                ->orWhereNotNull('oferta_precio');
        })->where(function ($q) {
            $q->whereNull('oferta_inicio')->orWhere('oferta_inicio', '<=', now());
        })->where(function ($q) {
            $q->whereNull('oferta_fin')->orWhere('oferta_fin', '>=', now());
        });
    }

    public function getPrecioFinalAttribute(): float
    {
        if ($this->estaEnOferta()) {
            if ($this->oferta_precio) {
                return (float) $this->oferta_precio;
            }
            if ($this->oferta_porcentaje) {
                return round($this->precio * (1 - $this->oferta_porcentaje / 100), 2);
            }
        }

        return (float) $this->precio;
    }

    public function estaEnOferta(): bool
    {
        if (! $this->oferta_porcentaje && ! $this->oferta_precio) {
            return false;
        }
        if ($this->oferta_inicio && $this->oferta_inicio->isFuture()) {
            return false;
        }
        if ($this->oferta_fin && $this->oferta_fin->isPast()) {
            return false;
        }

        return true;
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
