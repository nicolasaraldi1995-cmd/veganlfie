<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Producto extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = [
        'nombre', 'slug', 'marca_id', 'categoria_id',
        'descripcion', 'imagen', 'sin_tacc', 'frio', 'congelado', 'nuevo', 'activo',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'sin_tacc' => 'boolean',
        'frio' => 'boolean',
        'congelado' => 'boolean',
        'nuevo' => 'boolean',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Producto $producto) {
            if (empty($producto->slug)) {
                $producto->slug = Str::slug($producto->nombre);
            }
        });

        // Sin esto, borrar un Producto deja sus Presentaciones "huérfanas"
        // (siguen activas pero apuntan a un producto invisible), lo que
        // hacía explotar el selector de productos al armar un pedido.
        static::deleting(function (Producto $producto) {
            if (! $producto->isForceDeleting()) {
                $producto->presentaciones()->delete();
            }
        });

        static::restoring(function (Producto $producto) {
            $producto->presentaciones()->onlyTrashed()->restore();
        });
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function presentaciones(): HasMany
    {
        return $this->hasMany(Presentacion::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeSinTacc($query)
    {
        return $query->where('sin_tacc', true);
    }

    public function scopeFrios($query)
    {
        return $query->where('frio', true);
    }

    public function scopeCongelados($query)
    {
        return $query->where('congelado', true);
    }

    public function scopeNuevos($query)
    {
        return $query->where('nuevo', true);
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
