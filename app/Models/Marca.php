<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use App\Observers\MarcaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[ObservedBy(MarcaObserver::class)]
class Marca extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['nombre', 'slug', 'logo', 'activo', 'descuento_porcentaje', 'margen_porcentaje', 'iva'];

    protected $appends = ['logo_url'];

    protected $casts = [
        'activo' => 'boolean',
        'iva' => 'boolean',
        'descuento_porcentaje' => 'decimal:2',
        'margen_porcentaje' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Marca $marca) {
            if (empty($marca->slug)) {
                $marca->slug = static::generarSlugUnico(Str::slug($marca->nombre));
            }
        });
    }

    /**
     * Un slug que no choque con el índice único. Dos nombres distintos pueden
     * dar el mismo slug ("Café" y "Cafe" → "cafe"): sin esto, crear la segunda
     * marca reventaba, y adentro de la sincronización eso revertía todo y
     * abortaba la importación de precios antes de tocar nada.
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
     * Descuento y margen por marca son los valores internos que usa el panel
     * para calcular precios: no tienen por qué salir al sitio público, donde
     * el listado de marcas se manda entero al navegador.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();

        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        unset($data['descuento_porcentaje'], $data['margen_porcentaje']);

        return $data;
    }

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->logo);
    }
}
