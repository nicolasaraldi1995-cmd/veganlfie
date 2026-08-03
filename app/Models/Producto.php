<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Marca y categoría usan borrado lógico, así que la relación puede venir en
 * null aunque la columna sea obligatoria: por eso el código las lee con ?->.
 *
 * @property-read Categoria|null $categoria
 * @property-read Marca|null $marca
 */
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
                $producto->slug = static::generarSlugUnico(Str::slug($producto->nombre));
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

    /**
     * "productos.slug" es único en base (ver migración): dos productos con el
     * mismo nombre (o incluso marcas distintas) generaban el mismo slug, y el
     * segundo quedaba inalcanzable porque {producto:slug} siempre resuelve al
     * primero que matchea. withTrashed() porque un slug de un producto borrado
     * sigue "ocupado" para el índice único.
     */
    protected static function generarSlugUnico(string $base): string
    {
        $slug = $base;
        $sufijo = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return HasMany<Presentacion, $this>
     */
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
