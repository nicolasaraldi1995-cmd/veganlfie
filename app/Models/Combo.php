<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Combo extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['nombre', 'slug', 'descripcion', 'imagen', 'precio_manual', 'descuento_porcentaje', 'activo'];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'precio_manual' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Combo $combo) {
            if (empty($combo->slug)) {
                $combo->slug = Str::slug($combo->nombre);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function presentaciones(): BelongsToMany
    {
        return $this->belongsToMany(Presentacion::class, 'combo_items')
            ->withPivot('cantidad');
    }

    public function getPrecioCalculadoAttribute(): float
    {
        return $this->items->sum(function (ComboItem $item) {
            // presentacion puede ser null si se borró después de armar el combo.
            return $item->presentacion ? $item->presentacion->precio_final * $item->cantidad : 0;
        });
    }

    public function getPrecioAttribute(): float
    {
        if ($this->precio_manual) {
            return (float) $this->precio_manual;
        }
        $calculado = $this->precio_calculado;
        if ($this->descuento_porcentaje) {
            return round($calculado * (1 - $this->descuento_porcentaje / 100), 2);
        }

        return $calculado;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
