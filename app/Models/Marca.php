<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Marca extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['nombre', 'slug', 'logo', 'activo', 'descuento_porcentaje', 'margen_porcentaje'];

    protected $appends = ['logo_url'];

    protected $casts = [
        'activo' => 'boolean',
        'descuento_porcentaje' => 'decimal:2',
        'margen_porcentaje' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Marca $marca) {
            if (empty($marca->slug)) {
                $marca->slug = Str::slug($marca->nombre);
            }
        });
    }

    /**
     * Descuento y margen por marca son los valores internos que usa el panel
     * para calcular precios: no tienen por qué salir al sitio público, donde
     * el listado de marcas se manda entero al navegador.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        unset($data['descuento_porcentaje'], $data['margen_porcentaje']);

        return $data;
    }

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
