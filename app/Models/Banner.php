<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use App\Observers\BannerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(BannerObserver::class)]
class Banner extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['imagen', 'ancho', 'alto', 'posicion', 'ajuste', 'destino_tipo', 'destino_valor', 'orden', 'activo'];

    /**
     * Cómo entra la imagen en la franja del banner.
     */
    public const AJUSTES = [
        'cover' => 'Recortar para llenar el banner',
        'contain' => 'Mostrar la imagen completa (sin recortar)',
    ];

    /**
     * Qué parte de la imagen se conserva cuando se recorta para llenar la
     * franja del banner (valores de object-position de CSS).
     */
    public const POSICIONES = [
        'center' => 'Centro',
        'top' => 'Arriba',
        'bottom' => 'Abajo',
        'left' => 'Izquierda',
        'right' => 'Derecha',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    /**
     * Con qué puede empezar el destino de un banner.
     *
     * El campo "URL externa" es texto libre y va derecho al href de la portada
     * (ver BannerSlider.vue). Con "javascript:..." adentro, cualquiera que
     * tocara el banner ejecutaba ese código en su propio navegador, con su
     * sesión abierta. Hace falta una cuenta del equipo para cargarlo, pero eso
     * no lo vuelve aceptable: el operador no tiene por qué poder poner código
     * en la página pública.
     */
    private const ESQUEMAS_PERMITIDOS = ['http', 'https'];

    public function getUrlAttribute(): ?string
    {
        return match ($this->destino_tipo) {
            'marca' => $this->destino_valor ? route('productos.index', ['vista' => 'marcas', 'marca' => $this->destino_valor]) : null,
            'categoria' => $this->destino_valor ? route('productos.index', ['vista' => 'categorias', 'categoria' => $this->destino_valor]) : null,
            'seccion' => $this->destino_valor ? route('productos.index', ['vista' => $this->destino_valor]) : null,
            'url' => $this->destinoExterno(),
            default => null,
        };
    }

    /**
     * El destino externo, o null si no se puede confiar.
     *
     * Se filtra acá, en la última puerta antes del navegador, y no sólo en el
     * formulario: así quedan tapados también los que ya estén guardados y
     * cualquier otro camino que escriba en la tabla (una importación, la
     * consola, la base a mano). Un banner sin destino válido se muestra igual,
     * como imagen, sin enlace.
     *
     * Es una lista de lo permitido y no de lo prohibido a propósito: los
     * navegadores ignoran espacios y saltos de línea dentro de una dirección,
     * así que "java\tscript:" se ejecuta igual que "javascript:". Prohibiendo
     * de a uno siempre queda una variante afuera.
     */
    private function destinoExterno(): ?string
    {
        $valor = trim((string) $this->destino_valor);

        if ($valor === '') {
            return null;
        }

        // Una dirección del propio sitio. Se descarta "//" porque el navegador
        // lo lee como "el mismo esquema, otro dominio".
        if (str_starts_with($valor, '/') && ! str_starts_with($valor, '//')) {
            return $valor;
        }

        $esquema = strtolower((string) parse_url($valor, PHP_URL_SCHEME));

        return in_array($esquema, self::ESQUEMAS_PERMITIDOS, true) ? $valor : null;
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
