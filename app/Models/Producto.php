<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre', 'slug', 'marca_id', 'categoria_id',
        'descripcion', 'imagen', 'sin_tacc', 'frio', 'congelado', 'nuevo', 'activo',
    ];

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
}
