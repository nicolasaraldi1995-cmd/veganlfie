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

    protected $appends = ['imagen_url', 'hay_stock'];

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
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();

        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        unset($data['precio_costo'], $data['descuento_porcentaje'], $data['margen_porcentaje']);

        // Cuántas unidades hay es dato del sistema. Afuera alcanza con saber si
        // se puede comprar o no: el número exacto le dibujaba el inventario a
        // cualquiera que mirara el código de la página. El equipo sí lo ve, que
        // lo necesita para la lista de precios y para preparar los pedidos.
        if (! (auth()->user()?->isOperador() ?? false)) {
            unset($data['stock']);
        }

        if (auth()->guest()) {
            unset($data['precio'], $data['oferta_precio'], $data['oferta_porcentaje']);
        }

        return $data;
    }

    /**
     * El precio de venta que sale de los datos del proveedor: se le saca el
     * descuento al costo, se le suma el margen y recién ahí el IVA.
     *
     * Vive acá porque la cuenta estaba escrita tres veces (el formulario de
     * producto, IvaPorMarca y la pantalla de Precios). Con tres copias, el día
     * que cambie el criterio dos de ellas quedan viejas y nadie se entera:
     * salen precios distintos según por dónde se toque.
     *
     * Devuelve null cuando faltan costo o margen, que es lo que pasa hoy en
     * todo el catálogo: quien llama decide si en ese caso deja el precio como
     * está o hace otra cosa.
     */
    public static function calcularPrecio(float|string|null $costo, float|string|null $margen, float|string|null $descuento = null, bool $iva = false): ?float
    {
        if ($costo === null || $costo === '' || $margen === null || $margen === '') {
            return null;
        }

        $precio = (float) $costo
            * (1 - (float) ($descuento ?? 0) / 100)
            * (1 + (float) $margen / 100);

        return round($iva ? $precio * 1.21 : $precio, 2);
    }

    /**
     * El margen que le corresponde a esta presentación: el suyo si lo tiene
     * cargado, y si no, el de su marca.
     *
     * La idea es no escribir el mismo número 2161 veces. Se carga una vez en la
     * marca y sus productos lo toman prestado; el día que la marca cambie a
     * 35%, cambian todos solos. El que necesite otro margen se lo escribe
     * encima y deja de seguirla.
     *
     * Prestado y no copiado: si al abrir un producto se le grabara el número de
     * la marca, ese producto quedaría clavado en 30% para siempre y cambiar la
     * marca ya no lo movería.
     */
    public function margenEfectivo(): ?float
    {
        return $this->heredadoDeLaMarca('margen_porcentaje');
    }

    public function descuentoEfectivo(): ?float
    {
        return $this->heredadoDeLaMarca('descuento_porcentaje');
    }

    /**
     * Es un método y no un accesor a propósito: costo, margen y descuento son
     * datos internos que no salen del panel, y un accesor corre el riesgo de
     * terminar en $appends y viajar al navegador de cualquiera.
     */
    private function heredadoDeLaMarca(string $campo): ?float
    {
        $propio = $this->{$campo};

        if ($propio !== null) {
            return (float) $propio;
        }

        $deLaMarca = $this->producto?->marca?->{$campo};

        return $deLaMarca !== null ? (float) $deLaMarca : null;
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Lo que se puede vender: activa, con precio, y de un producto que siga
     * publicado.
     *
     * Sin precio no se puede vender: el operador no ve ese campo al cargar un
     * producto, así que se guardaba en 0 y quedaba publicada a $0, comprable.
     * Que no aparezca es más sano que que se venda regalada; el admin la sigue
     * viendo en el panel para ponerle el precio.
     *
     * Y sin producto publicado tampoco. Antes esto miraba la presentación y
     * nada más, así que apagar un producto no apagaba lo que colgaba de él:
     * quedaban 331 productos dados de baja con 344 presentaciones vivas que el
     * carrito aceptaba y el checkout cobraba. Preguntarle al producto en vez de
     * copiarle el estado es a propósito: cuando el producto se vuelve a prender,
     * sus presentaciones vuelven como estaban, sin perder las que estén
     * apagadas de a una.
     *
     * whereHas también descarta las de un producto borrado, porque Producto usa
     * borrado lógico.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true)
            ->where('precio', '>', 0)
            ->whereHas('producto', fn ($q) => $q->where('activo', true));
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

    /**
     * Lo único que necesita saber la web: si el botón de comprar va habilitado
     * o bloqueado. El número de unidades no sale de acá adentro.
     */
    public function getHayStockAttribute(): bool
    {
        return $this->stock > 0;
    }
}
