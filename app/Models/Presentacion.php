<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El producto usa borrado lógico, así que la relación puede venir en null
 * aunque la columna sea obligatoria: por eso el código la lee con ?->.
 *
 * @property-read Producto|null $producto
 */
class Presentacion extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $table = 'presentaciones';

    protected $fillable = [
        'producto_id', 'unidad', 'sku', 'imagen', 'precio', 'stock', 'activo',
        'oferta_porcentaje', 'oferta_precio', 'oferta_inicio', 'oferta_fin',
        'precio_costo', 'descuento_porcentaje', 'margen_porcentaje', 'iva',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_costo' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'margen_porcentaje' => 'decimal:2',
        'iva' => 'boolean',
        'oferta_porcentaje' => 'decimal:2',
        'oferta_precio' => 'decimal:2',
        'oferta_inicio' => 'date',
        'oferta_fin' => 'date',
        'activo' => 'boolean',
        'stock' => 'integer',
    ];

    /**
     * Recorta lo que sale del modelo al serializarse (Inertia manda estos datos
     * al navegador, donde cualquiera los puede leer: esconderlos solo en el
     * diseño no alcanza).
     *
     * - costo/descuento/margen son datos internos del negocio y nunca deberían
     *   llegar al sitio público (antes viajaban a cualquier visitante).
     * - los precios solo se muestran a clientes con cuenta.
     *
     * El panel admin queda intacto: sus formularios necesitan estos campos.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        unset($data['precio_costo'], $data['descuento_porcentaje'], $data['margen_porcentaje']);

        if (auth()->guest()) {
            unset($data['precio'], $data['oferta_precio'], $data['oferta_porcentaje']);
        }

        return $data;
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
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
            // oferta_fin es una columna DATE (medianoche); comparar contra la fecha
            // sola (no now() completo) para que la oferta siga activa todo ese día.
            $q->whereNull('oferta_fin')->orWhere('oferta_fin', '>=', now()->toDateString());
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
        if ($this->oferta_fin && $this->oferta_fin->copy()->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
